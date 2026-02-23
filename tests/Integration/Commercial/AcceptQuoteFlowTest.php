<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Infrastructure\Persistence\Repository\SqlContractRepository;
use Pet\Infrastructure\Persistence\Repository\SqlBaselineRepository;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Listener\QuoteAcceptedListener;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\PaymentScheduleItemBecameDueEvent;
use Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Identity\Entity\Customer;

class AcceptQuoteFlowTest extends TestCase
{
    private $wpdb;
    private $quoteRepo;
    private $contractRepo;
    private $baselineRepo;
    private $eventBus;
    private $handler;
    private $capturedEvents = [];

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // Mock prepare to behave like sprintf
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            // Simple vsprintf replacement for mocking
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%f', '%s', $query);
            return vsprintf($query, $args);
        });

        $this->quoteRepo = new SqlQuoteRepository($this->wpdb);
        $this->contractRepo = new SqlContractRepository($this->wpdb);
        $this->baselineRepo = new SqlBaselineRepository($this->wpdb);
        $this->eventBus = new InMemoryEventBus();

        // 1. QuoteAccepted -> Contract/Baseline
        $listener = new QuoteAcceptedListener($this->contractRepo, $this->baselineRepo, $this->eventBus);
        $this->eventBus->subscribe(QuoteAccepted::class, $listener);

        // 2. QuoteAccepted -> Project
        $projectRepo = $this->createMock(ProjectRepository::class);
        $customerRepo = $this->createMock(CustomerRepository::class);
        $customerRepo->method('findById')->willReturn(new Customer('Customer', 'email@example.com', 1));
        $schemaRepo = $this->createMock(SchemaDefinitionRepository::class);
        $schemaValidator = $this->createMock(SchemaValidator::class);
        
        $createProjectHandler = new CreateProjectHandler(
            $projectRepo,
            $customerRepo,
            $schemaRepo,
            $schemaValidator,
            $this->eventBus
        );
        
        $projectListener = new CreateProjectFromQuoteListener($createProjectHandler);
        $this->eventBus->subscribe(QuoteAccepted::class, $projectListener);

        // Capture events
        $this->capturedEvents = [];
        $captureListener = function ($event) {
            $this->capturedEvents[get_class($event)] = $event;
        };
        $this->eventBus->subscribe(QuoteAccepted::class, $captureListener);
        $this->eventBus->subscribe(ProjectCreated::class, $captureListener);
        $this->eventBus->subscribe(PaymentScheduleItemBecameDueEvent::class, $captureListener);

        $this->handler = new AcceptQuoteHandler($this->quoteRepo, $this->eventBus);
    }

    public function testAcceptQuoteCreatesContractAndBaseline()
    {
        // 1. Setup Quote Data
        $quoteId = 123;
        $item = new QuoteCatalogItem('Item 1', 2.0, 100.0, 50.0, null, null, [], 'product', 'SKU-TEST');
        $component = new CatalogComponent([$item], 'Component 1');
        $paymentSchedule = [new PaymentMilestone('Deposit', 200.0, null, false)];
        
        $quote = new Quote(
            1, // customerId
            'Test Quote',
            'Description',
            QuoteState::draft(), // Initial state
            1, // version
            200.0, // totalValue
            100.0, // totalInternalCost
            'USD',
            null,
            $quoteId, // ID
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [$component],
            [],
            [],
            $paymentSchedule
        );

        // 2. Mock QuoteRepo findById
        $quoteRow = (object) [
            'id' => (string)$quoteId,
            'customer_id' => '1',
            'title' => 'Test Quote',
            'description' => 'Description',
            'state' => 'sent',
            'version' => '1',
            'total_value' => '200.00',
            'total_internal_cost' => '100.00',
            'currency' => 'USD',
            'accepted_at' => null,
            'malleable_data' => null,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => null,
            'archived_at' => null
        ];
        
        $implementationComponentRow = (object) [
            'id' => '10',
            'quote_id' => (string)$quoteId,
            'type' => 'implementation',
            'description' => 'Implementation Component 1',
            'created_at' => '2023-01-01 00:00:00'
        ];

        $catalogComponentRow = (object) [
            'id' => '10',
            'quote_id' => (string)$quoteId,
            'type' => 'catalog',
            'description' => 'Component 1',
            'created_at' => '2023-01-01 00:00:00'
        ];
        
        $itemRow = (object) [
            'id' => '20',
            'component_id' => '10',
            'description' => 'Item 1',
            'quantity' => '2.00',
            'unit_sell_price' => '100.00',
            'unit_internal_cost' => '50.00',
            'type' => 'product',
            'sku' => 'SKU-TEST',
            'role_id' => null,
            'wbs_snapshot' => null
        ];

        $paymentRow = (object) [
            'id' => '1',
            'quote_id' => (string)$quoteId,
            'title' => 'Deposit',
            'amount' => '200.00',
            'due_date' => null,
            'paid_flag' => '0'
        ];

        $milestoneRow = (object) [
            'id' => '30',
            'component_id' => '10',
            'title' => 'Milestone 1',
            'description' => 'Implementation milestone'
        ];

        $taskRow = (object) [
            'id' => '40',
            'milestone_id' => '30',
            'title' => 'Task 1',
            'description' => 'Implementation task',
            'duration_hours' => '10.00',
            'role_id' => '1',
            'base_internal_rate' => '50.00',
            'sell_rate' => '100.00'
        ];

        $this->wpdb->expects($this->any())
            ->method('get_row')
            ->willReturnCallback(function ($query) use ($quoteRow) {
                if (strpos($query, 'pet_quotes') !== false) {
                    return $quoteRow;
                }
                return null;
            });

        $this->wpdb->expects($this->any())
            ->method('get_results')
            ->willReturnCallback(function ($query) use ($implementationComponentRow, $catalogComponentRow, $itemRow, $paymentRow, $milestoneRow, $taskRow) {
                if (strpos($query, 'pet_quote_components') !== false) {
                    return [$implementationComponentRow, $catalogComponentRow];
                }
                if (strpos($query, 'pet_quote_catalog_items') !== false) {
                    return [$itemRow];
                }
                if (strpos($query, 'pet_quote_payment_schedule') !== false) {
                    return [$paymentRow];
                }
                if (strpos($query, 'pet_quote_milestones') !== false) {
                    return [$milestoneRow];
                }
                if (strpos($query, 'pet_quote_tasks') !== false) {
                    return [$taskRow];
                }
                return [];
            });

        // 3. Expect Updates and Inserts
        // We expect:
        // - Update Quote (status -> accepted)
        // - Insert Contract
        // - Insert Baseline
        // - Insert Baseline Component

        // Using withConsecutive or similar expectation logic is tricky with multiple repos calling wpdb.
        // We'll rely on method call counts and "contains" check or just verifying the flow completes without error
        // and check specific insert calls if possible.
        
        $this->wpdb->expects($this->once()) // Update Quote
             ->method('update') // Quote update
             ->with(
                 'wp_pet_quotes',
                 $this->callback(function ($data) {
                     return $data['state'] === 'accepted';
                 })
             );
             
        // The insert calls:
        // 1. Contract
        // 2. Baseline
        // 3. Baseline Component
        
        // Note: SqlQuoteRepository::save might try to save components again?
        // It checks if ID exists, then calls saveComponents.
        // saveComponents uses $this->wpdb->delete then insert?
        // Wait, SqlQuoteRepository::saveComponents implementation...
        // I need to check SqlQuoteRepository::saveComponents.
        // If it deletes and re-inserts components on every save, that adds more calls.
        
        // Let's check SqlQuoteRepository logic.
        // It does saveComponents.
        
        // For this test, to simplify, we can relax exact call counts and focus on the Contract/Baseline inserts.
        // But we want to ensure they are called and that insert_id is propagated correctly.

        $wpdb = $this->wpdb;

        $this->wpdb->expects($this->any()) // Allow other inserts (like quote components, milestones, tasks, payment schedule)
             ->method('insert')
             ->willReturnCallback(function ($table, $data) use ($wpdb) {
                 if ($table === 'wp_pet_contracts') {
                     if ($data['status'] !== 'active') {
                         throw new \Exception('Contract status wrong');
                     }
                     if ($data['total_value'] != 200.0) {
                         throw new \Exception('Contract value wrong');
                     }
                     $wpdb->insert_id = 100;
                     return 1;
                 }

                 if ($table === 'wp_pet_baselines') {
                     if ($data['contract_id'] !== 100) {
                         throw new \Exception('Baseline contract_id wrong');
                     }
                     $wpdb->insert_id = 200;
                     return 1;
                 }

                 if ($table === 'wp_pet_baseline_components') {
                     if ($data['baseline_id'] !== 200) {
                         throw new \Exception('Component baseline_id wrong');
                     }
                     if (!isset($data['component_data'])) {
                         throw new \Exception('Component serialization missing');
                     }
                     return 1;
                 }

                 return 1;
             });

        // 4. Execute
        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);

        // 5. Assertions
        $this->assertArrayHasKey(QuoteAccepted::class, $this->capturedEvents);
        $this->assertArrayHasKey(ProjectCreated::class, $this->capturedEvents);
        $this->assertArrayHasKey(PaymentScheduleItemBecameDueEvent::class, $this->capturedEvents);

        $projectEvent = $this->capturedEvents[ProjectCreated::class];
        $this->assertInstanceOf(ProjectCreated::class, $projectEvent);
        $this->assertEquals(123, $projectEvent->project()->sourceQuoteId());
    }
}

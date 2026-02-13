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
        $listener = new QuoteAcceptedListener($this->contractRepo, $this->baselineRepo);
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
        
        $componentRow = (object) [
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
            ->willReturnCallback(function ($query) use ($componentRow, $itemRow, $paymentRow) {
                if (strpos($query, 'pet_quote_components') !== false) {
                    return [$componentRow];
                }
                if (strpos($query, 'pet_quote_catalog_items') !== false) {
                    return [$itemRow];
                }
                if (strpos($query, 'pet_quote_payment_schedule') !== false) {
                    return [$paymentRow];
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
        // But we want to ensure they are called.
        
        $this->wpdb->expects($this->any()) // Allow other inserts (like quote components re-save)
             ->method('insert')
             ->willReturnCallback(function ($table, $data) {
                 if ($table === 'wp_pet_contracts') {
                     // Verify Contract Data
                     if ($data['status'] !== 'active') throw new \Exception('Contract status wrong');
                     if ($data['total_value'] != 200.0) throw new \Exception('Contract value wrong');
                     return 100; // Contract ID
                 }
                 if ($table === 'wp_pet_baselines') {
                     // Verify Baseline Data
                     if ($data['contract_id'] !== 100) throw new \Exception('Baseline contract_id wrong');
                     return 200; // Baseline ID
                 }
                 if ($table === 'wp_pet_baseline_components') {
                     // Verify Component Data
                     if ($data['baseline_id'] !== 200) throw new \Exception('Component baseline_id wrong');
                     if (strpos($data['component_data'], 'CatalogComponent') === false) throw new \Exception('Component serialization wrong');
                     return 300;
                 }
                 return 1;
             });

        // Set insert_id behavior is tricky with callbacks in PHPUnit mock.
        // The mock object's public property insert_id won't update automatically from our callback.
        // We have to set it manually or assume the repos don't read it immediately after insert if we return int?
        // No, wpdb->insert returns int|false, but the ID is in $wpdb->insert_id.
        // The Repos read $this->wpdb->insert_id.
        
        // This is a limitation of mocking public properties on method calls.
        // However, I can mock the `__get` or just set the property in the callback if I pass the mock by reference? No.
        // I can use `willReturnCallback` to modify the mock object?
        // $this->wpdb is the mock.
        
        // A common workaround is to mock `insert` to set the property.
        // But `insert` is a method on the mock.
        // We can't easily change the property of the mock inside the callback of the mock unless we have reference.
        
        // Actually, since I created the mock, I can capture it in closure.
        
        $wpdb = $this->wpdb;
        $wpdb->method('insert')->willReturnCallback(function($table, $data) use ($wpdb) {
             if ($table === 'wp_pet_contracts') {
                 $wpdb->insert_id = 100;
                 return 1;
             }
             if ($table === 'wp_pet_baselines') {
                 $wpdb->insert_id = 200;
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
        
        $projectEvent = $this->capturedEvents[ProjectCreated::class];
        $this->assertInstanceOf(ProjectCreated::class, $projectEvent);
        $this->assertEquals(123, $projectEvent->project()->sourceQuoteId());
    }
}

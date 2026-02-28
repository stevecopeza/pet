<?php

declare(strict_types=1);

namespace Pet\Tests\Integration;

use Pet\Tests\Stubs\InMemoryWpdb;
use Pet\Application\Commercial\Command\AddComponentCommand;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Application\System\Service\TransactionManager;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Infrastructure\Persistence\Repository\SqlCatalogItemRepository;
use Pet\Infrastructure\Persistence\Repository\SqlSlaRepository;
use Pet\Infrastructure\Persistence\Repository\SqlCostAdjustmentRepository;
use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteState;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use PHPUnit\Framework\TestCase;

class AddRecurringServiceWithSlaSnapshotTest extends TestCase
{
    private InMemoryWpdb $wpdb;
    private AddComponentHandler $handler;
    private QuoteRepository $quoteRepository;
    private SlaRepository $slaRepository;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        
        // Setup schema
        $this->wpdb->query("CREATE TABLE wp_pet_quotes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            state varchar(50) NOT NULL,
            version int(11) NOT NULL,
            total_value decimal(10,2) NOT NULL,
            total_internal_cost decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            description text,
            accepted_at datetime DEFAULT NULL,
            malleable_data text,
            PRIMARY KEY (id)
        )");

        $this->wpdb->query("CREATE TABLE wp_pet_quote_components (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            section varchar(255) NOT NULL,
            description text,
            PRIMARY KEY (id)
        )");

        $this->wpdb->query("CREATE TABLE wp_pet_quote_recurring_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            component_id bigint(20) NOT NULL,
            service_name varchar(255) NOT NULL,
            sla_snapshot text,
            cadence varchar(50) NOT NULL,
            term_months int(11) NOT NULL,
            renewal_model varchar(50) NOT NULL,
            sell_price_per_period decimal(10,2) NOT NULL,
            internal_cost_per_period decimal(10,2) NOT NULL,
            PRIMARY KEY (id)
        )");
        
        $this->wpdb->query("CREATE TABLE wp_pet_slas (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            name varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            version_number int(11) NOT NULL,
            calendar_id bigint(20) NOT NULL,
            response_target_minutes int(11) NOT NULL,
            resolution_target_minutes int(11) NOT NULL,
            PRIMARY KEY (id)
        )");

        $this->wpdb->query("CREATE TABLE wp_pet_sla_escalation_rules (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sla_id bigint(20) NOT NULL,
            threshold_percent int(11) NOT NULL,
            action varchar(255) NOT NULL,
            PRIMARY KEY (id)
        )");

        $this->wpdb->query("CREATE TABLE wp_pet_calendars (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            timezone varchar(255) NOT NULL,
            PRIMARY KEY (id)
        )");
        
        // Mock repositories
        $transactionManager = new SqlTransaction($this->wpdb);
        $costAdjustmentRepo = $this->createMock(SqlCostAdjustmentRepository::class);
        $costAdjustmentRepo->method('findByQuoteId')->willReturn([]);
        
        $this->quoteRepository = new SqlQuoteRepository($this->wpdb, $costAdjustmentRepo);
        $catalogItemRepo = new SqlCatalogItemRepository($this->wpdb);
        
        $calendarRepo = $this->createMock(CalendarRepository::class);
        // Setup calendar mock to return a calendar
        $calendar = new Calendar('Test Calendar', 'UTC', [], [], true);
        $calendarRepo->method('findById')->willReturn($calendar);

        $this->slaRepository = new SqlSlaRepository($this->wpdb, $calendarRepo);

        $this->handler = new AddComponentHandler($transactionManager, $this->quoteRepository, $catalogItemRepo, $this->slaRepository);
    }

    public function testAddRecurringServiceSnapshotsSla(): void
    {
        // 1. Create Quote
        $this->wpdb->insert('wp_pet_quotes', [
            'id' => 1,
            'customer_id' => 1,
            'title' => 'Test Quote',
            'state' => 'draft',
            'version' => 1,
            'total_value' => 0,
            'total_internal_cost' => 0,
            'currency' => 'USD',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => null,
            'archived_at' => null,
            'malleable_data' => '{}'
        ]);

        // 2. Create SLA Definition
        $calendar = new Calendar('Default', 'UTC', [], [], true);
        $sla = new SlaDefinition(
            'Gold Support',
            $calendar,
            60, // Response
            240, // Resolution
            [],
            'published',
            1,
            null,
            null
        );
        $this->slaRepository->save($sla);

        // 3. Command Data with sla_definition_id
        $data = [
            'service_name' => 'Gold Support Service',
            'cadence' => 'monthly',
            'term_months' => 12,
            'renewal_model' => 'auto',
            'sell_price_per_period' => 100.0,
            'internal_cost_per_period' => 50.0,
            'sla_definition_id' => $sla->id()
        ];

        $command = new AddComponentCommand(1, 'recurring', $data);

        // 4. Handle
        $this->handler->handle($command);

        // 5. Verify Component was added
        $quote = $this->quoteRepository->findById(1);
        $this->assertCount(1, $quote->components());
        $component = $quote->components()[0];
        $this->assertInstanceOf(RecurringServiceComponent::class, $component);

        // 6. Verify SLA Snapshot
        $snapshot = $component->slaSnapshot();
        $this->assertNotEmpty($snapshot, 'SLA snapshot should not be empty');
        $this->assertEquals('Gold Support', $snapshot['sla_name_at_binding'] ?? null);
        $this->assertEquals(60, $snapshot['response_target_minutes'] ?? null);
    }

    public function testSnapshotImmutabilityAfterSlaUpdate(): void
    {
        // Create Quote
        $this->wpdb->insert('wp_pet_quotes', [
            'id' => 2,
            'customer_id' => 1,
            'title' => 'Immutability Quote',
            'state' => 'draft',
            'version' => 1,
            'total_value' => 0,
            'total_internal_cost' => 0,
            'currency' => 'USD',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => null,
            'archived_at' => null,
            'malleable_data' => '{}'
        ]);

        // Create and Save SLA v1
        $calendar = new Calendar('Default', 'UTC', [], [], true);
        $sla = new SlaDefinition(
            'Gold Support',
            $calendar,
            60,
            240,
            [],
            'published',
            1
        );
        $this->slaRepository->save($sla);

        // Bind snapshot via command
        $data = [
            'service_name' => 'Gold Support Service',
            'cadence' => 'monthly',
            'term_months' => 12,
            'renewal_model' => 'auto',
            'sell_price_per_period' => 100.0,
            'internal_cost_per_period' => 50.0,
            'sla_definition_id' => $sla->id()
        ];
        $command = new AddComponentCommand(2, 'recurring', $data);
        $this->handler->handle($command);

        // Update SLA to v2 with different values
        $slaV2 = new SlaDefinition(
            'Gold Support v2',
            $calendar,
            120,
            480,
            [],
            'published',
            2,
            $sla->uuid(),
            $sla->id()
        );
        $this->slaRepository->save($slaV2);

        // Reload Quote and assert snapshot remains as v1
        $quote = $this->quoteRepository->findById(2);
        $this->assertCount(1, $quote->components());
        $component = $quote->components()[0];
        $this->assertInstanceOf(RecurringServiceComponent::class, $component);

        $snapshot = $component->slaSnapshot();
        $this->assertNotEmpty($snapshot, 'SLA snapshot should not be empty');
        $this->assertEquals('Gold Support', $snapshot['sla_name_at_binding'] ?? null);
        $this->assertEquals(60, $snapshot['response_target_minutes'] ?? null);
        $this->assertEquals(240, $snapshot['resolution_target_minutes'] ?? null);
    }
}

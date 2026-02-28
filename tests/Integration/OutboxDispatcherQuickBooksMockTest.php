<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Application\Finance\Command\CreateBillingExportCommand;
use Pet\Application\Finance\Command\CreateBillingExportHandler;
use Pet\Application\Finance\Command\AddBillingExportItemCommand;
use Pet\Application\Finance\Command\AddBillingExportItemHandler;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksCommand;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler;
use Pet\Application\Integration\Service\OutboxDispatcherService;
use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\Tests\Stubs\InMemoryWpdb;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;

final class OutboxDispatcherQuickBooksMockTest extends TestCase
{
    private \DI\Container $c;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new InMemoryWpdb();
        
        // Initialize tables to avoid warnings if selects happen before inserts
        $wpdb->table_data[$wpdb->prefix . 'pet_outbox'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_billing_exports'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_billing_export_items'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_qb_invoices'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_external_mappings'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_domain_event_stream'] = [];

        ContainerFactory::reset();
        $this->c = ContainerFactory::create();
    }

    public function testDispatchClaimsRowsAndPreventsDoubleProcessing(): void
    {
        $create = $this->c->get(CreateBillingExportHandler::class);
        $addItem = $this->c->get(AddBillingExportItemHandler::class);
        $queue = $this->c->get(QueueBillingExportForQuickBooksHandler::class);
        $outbox = $this->c->get(SqlOutboxRepository::class);

        // 1. Setup: Queue an export
        $exportId = $create->handle(new CreateBillingExportCommand(
            1, new DateTimeImmutable('2026-02-01'), new DateTimeImmutable('2026-02-28'), 10
        ));
        $addItem->handle(new AddBillingExportItemCommand($exportId, 'time_entry', 88, 2, 100, 'Work', null));
        $queue->handle(new QueueBillingExportForQuickBooksCommand($exportId));

        // 2. Simulate Worker A finding and claiming
        $due = $outbox->findDue('quickbooks', 10);
        $this->assertCount(1, $due, 'Should find 1 due item');
        $ids = array_column($due, 'id');
        
        // Claim for 5 minutes
        $outbox->claim($ids, new DateTimeImmutable('+5 minutes'));

        // 3. Simulate Worker B trying to find due items immediately
        $dueB = $outbox->findDue('quickbooks', 10);
        
        $this->assertEmpty($dueB, 'Should verify no items are due after claim');
    }

    public function testDispatchRetriesOnFailure(): void
    {
        $create = $this->c->get(CreateBillingExportHandler::class);
        $queue = $this->c->get(QueueBillingExportForQuickBooksHandler::class);
        $outbox = $this->c->get(SqlOutboxRepository::class);
        $dispatcher = $this->c->get(OutboxDispatcherService::class);

        // 1. Setup: Queue an export with NO items (triggers RuntimeException in simulateQuickBooksSend)
        $exportId = $create->handle(new CreateBillingExportCommand(
            1, new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-03-31'), 10
        ));
        // Intentionally NOT adding items
        $queue->handle(new QueueBillingExportForQuickBooksCommand($exportId));

        // 2. Run Dispatcher (Attempt 1)
        $dispatcher->dispatchQuickBooks();

        // 3. Verify: Should be pending, attempt 1, backed off
        // We query by destination because event_id != exportId
        global $wpdb;
        $table = $wpdb->prefix . 'pet_outbox';
        $rows = $wpdb->get_results("SELECT * FROM $table WHERE destination = 'quickbooks'", ARRAY_A);
        
        $this->assertCount(1, $rows, 'Should have 1 outbox row');
        $row = $rows[0];
        
        $this->assertEquals('pending', $row['status']);
        $this->assertEquals(1, $row['attempt_count']);
        $this->assertNotNull($row['last_error']);
        $this->assertStringContainsString('No line items', $row['last_error']);
        
        // 4. Verify Backoff (next_attempt_at > now)
        $nextAttempt = new DateTimeImmutable($row['next_attempt_at']);
        $this->assertGreaterThan(new DateTimeImmutable(), $nextAttempt);
        
        // 5. Run Dispatcher again (should NOT pick it up)
        $dispatcher->dispatchQuickBooks();
        $rowAfter = $wpdb->get_row("SELECT * FROM $table WHERE id = {$row['id']}", ARRAY_A);
        $this->assertEquals(1, $rowAfter['attempt_count'], 'Should not retry before backoff expires');
    }

    public function testDispatchMarksSentAndRecordsMapping(): void
    {
        $create = $this->c->get(CreateBillingExportHandler::class);
        $addItem = $this->c->get(AddBillingExportItemHandler::class);
        $queue = $this->c->get(QueueBillingExportForQuickBooksHandler::class);
        $outbox = $this->c->get(SqlOutboxRepository::class);
        $dispatcher = $this->c->get(OutboxDispatcherService::class);

        $exportId = $create->handle(new CreateBillingExportCommand(
            1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10
        ));
        $addItem->handle(new AddBillingExportItemCommand($exportId, 'time_entry', 77, 2, 100, 'Work', null));
        $queue->handle(new QueueBillingExportForQuickBooksCommand($exportId));
        $dispatcher->dispatchQuickBooks();

        $anySent = false;
        // Check manually as findDue won't return sent items
        global $wpdb;
        $table = $wpdb->prefix . 'pet_outbox';
        $anySent = (bool)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'");
        $this->assertTrue($anySent);

        $mapTable = $wpdb->prefix . 'pet_external_mappings';
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mapTable WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d", ['quickbooks', 'invoice', $exportId]));
        $this->assertGreaterThan(0, $count);
    }
}

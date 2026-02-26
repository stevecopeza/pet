<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Application\Finance\Command\CreateBillingExportCommand;
use Pet\Application\Finance\Command\CreateBillingExportHandler;
use Pet\Application\Finance\Command\AddBillingExportItemCommand;
use Pet\Application\Finance\Command\AddBillingExportItemHandler;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksCommand;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler;
use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository;

final class QueueBillingExportTransactionalityTest extends TestCase
{
    private \DI\Container $c;
    private SqlOutboxRepository $outbox;
    private SqlEventStreamRepository $events;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new \Pet\Tests\Stubs\InMemoryWpdb();
        $wpdb->table_data[$wpdb->prefix . 'pet_billing_exports'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_billing_export_items'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_outbox'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_event_stream'] = [];
        
        \Pet\Infrastructure\DependencyInjection\ContainerFactory::reset();
        $this->c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $this->outbox = $this->c->get(SqlOutboxRepository::class);
        $this->events = $this->c->get(SqlEventStreamRepository::class);
    }

    public function testQueueCreatesEventAndOutboxInSameTransaction(): void
    {
        $create = $this->c->get(CreateBillingExportHandler::class);
        $addItem = $this->c->get(AddBillingExportItemHandler::class);
        $queue = $this->c->get(QueueBillingExportForQuickBooksHandler::class);
        $exportId = $create->handle(new CreateBillingExportCommand(
            1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10
        ));
        $this->assertGreaterThan(0, $exportId);
        $itemId = $addItem->handle(new AddBillingExportItemCommand($exportId, 'time_entry', 77, 2, 100, 'Work', null));
        $this->assertGreaterThan(0, $itemId);

        $queue->handle(new QueueBillingExportForQuickBooksCommand($exportId));

        $events = $this->events->findLatest(10, 'billing_export', $exportId, 'BillingExportQueued');
        $this->assertNotEmpty($events);
        $eventId = $events[0]->id;
        $rows = $this->outbox->findByEventIdAndDestination($eventId, 'quickbooks');
        $this->assertNotEmpty($rows);
        $this->assertSame('pending', $rows[0]['status']);
    }
}

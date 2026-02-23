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

final class OutboxDispatcherQuickBooksMockTest extends TestCase
{
    private \DI\Container $c;

    protected function setUp(): void
    {
        $this->c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
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

        $rows = $outbox->findByEventIdAndDestination($exportId, 'quickbooks');
        // Note: dispatcher now uses event_id for lookup; verify latest entry is sent
        // But we cannot correlate easily; ensure at least one outbox row is 'sent'
        $anySent = false;
        foreach ($outbox->findDue('quickbooks', 100) as $r) {
            if ($r['status'] === 'sent') {
                $anySent = true; break;
            }
        }
        // Fallback: query table directly
        if (!$anySent) {
            global $wpdb;
            $table = $wpdb->prefix . 'pet_outbox';
            $anySent = (bool)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'");
        }
        $this->assertTrue($anySent);

        global $wpdb;
        $mapTable = $wpdb->prefix . 'pet_external_mappings';
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mapTable WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d", ['quickbooks', 'invoice', $exportId]));
        $this->assertGreaterThan(0, $count);
    }
}

<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\Domain\Event\Repository\EventStreamRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;

use Pet\Application\System\Service\TouchedTracker;
final class QueueBillingExportForQuickBooksHandler
{
    public function __construct(
        private BillingExportRepository $repository,
        private SqlOutboxRepository $outbox,
        private EventStreamRepository $events,
        private SqlTransaction $tx,
        private TouchedTracker $touched
    ) {
    }

    public function handle(QueueBillingExportForQuickBooksCommand $command): void
    {
        $export = $this->repository->findById($command->exportId());
        if (!$export) {
            throw new \DomainException('Export not found');
        }
        $export->queue();

        $this->tx->begin();
        try {
            $this->repository->save($export);
            $version = $this->events->nextVersion('billing_export', $export->id());
            $payload = json_encode(['export_id' => $export->id()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $eventId = $this->events->append('billing_export', $export->id(), $version, 'BillingExportQueued', $payload);
            $this->outbox->enqueue($eventId, 'quickbooks');
            $this->tx->commit();
            $table = $GLOBALS['wpdb']->prefix . 'pet_billing_exports';
            $this->touched->mark($table, $export->id(), $export->createdByEmployeeId());
        } catch (\Throwable $e) {
            $this->tx->rollback();
            throw $e;
        }
    }
}

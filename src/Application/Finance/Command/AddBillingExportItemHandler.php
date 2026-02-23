<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Domain\Finance\Entity\BillingExportItem;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Domain\Event\Repository\EventStreamRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;

final class AddBillingExportItemHandler
{
    public function __construct(
        private BillingExportRepository $repository,
        private EventStreamRepository $events,
        private SqlTransaction $tx
    )
    {
    }

    public function handle(AddBillingExportItemCommand $command): int
    {
        $export = $this->repository->findById($command->exportId());
        if (!$export) {
            throw new \DomainException('Export not found');
        }
        if ($export->status() !== 'draft') {
            throw new \DomainException('Export not modifiable');
        }

        $item = BillingExportItem::pending(
            $command->exportId(),
            $command->sourceType(),
            $command->sourceId(),
            $command->quantity(),
            $command->unitPrice(),
            $command->description(),
            $command->qbItemRef()
        );

        $this->tx->begin();
        try {
            $this->repository->addItem($item);
            $version = $this->events->nextVersion('billing_export', $export->id());
            $payload = json_encode([
                'export_id' => $export->id(),
                'item_id' => $item->id(),
                'source_type' => $item->sourceType(),
                'source_id' => $item->sourceId(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'amount' => $item->amount(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->events->append('billing_export', $export->id(), $version, 'BillingExportItemAdded', $payload);
            $this->tx->commit();
            return $item->id();
        } catch (\Throwable $e) {
            $this->tx->rollback();
            throw $e;
        }
    }
}

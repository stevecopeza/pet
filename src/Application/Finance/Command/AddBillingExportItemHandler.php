<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Domain\Finance\Entity\BillingExportItem;
use Pet\Domain\Finance\Repository\BillingExportRepository;

final class AddBillingExportItemHandler
{
    public function __construct(private BillingExportRepository $repository)
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
        $this->repository->addItem($item);
        return $item->id();
    }
}

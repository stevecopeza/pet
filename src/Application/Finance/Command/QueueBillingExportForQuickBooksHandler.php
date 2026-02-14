<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Domain\Finance\Repository\BillingExportRepository;

final class QueueBillingExportForQuickBooksHandler
{
    public function __construct(private BillingExportRepository $repository)
    {
    }

    public function handle(QueueBillingExportForQuickBooksCommand $command): void
    {
        $export = $this->repository->findById($command->exportId());
        if (!$export) {
            throw new \DomainException('Export not found');
        }
        if ($export->status() !== 'draft') {
            throw new \DomainException('Only draft exports can be queued');
        }
        $this->repository->setStatus($export->id(), 'queued');
    }
}

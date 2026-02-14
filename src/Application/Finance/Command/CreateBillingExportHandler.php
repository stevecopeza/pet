<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Domain\Finance\Entity\BillingExport;
use Pet\Domain\Finance\Repository\BillingExportRepository;

final class CreateBillingExportHandler
{
    public function __construct(private BillingExportRepository $repository)
    {
    }

    public function handle(CreateBillingExportCommand $command): int
    {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $export = BillingExport::draft(
            $uuid,
            $command->customerId(),
            $command->periodStart(),
            $command->periodEnd(),
            $command->createdByEmployeeId()
        );
        $this->repository->save($export);
        return $export->id();
    }
}

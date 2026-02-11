<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\LeadRepository;

class DeleteLeadHandler
{
    private LeadRepository $leadRepository;

    public function __construct(LeadRepository $leadRepository)
    {
        $this->leadRepository = $leadRepository;
    }

    public function handle(DeleteLeadCommand $command): void
    {
        $this->leadRepository->delete($command->id());
    }
}

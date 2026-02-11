<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\AssignmentRepository;

class EndAssignmentHandler
{
    private AssignmentRepository $assignmentRepository;

    public function __construct(AssignmentRepository $assignmentRepository)
    {
        $this->assignmentRepository = $assignmentRepository;
    }

    public function handle(EndAssignmentCommand $command): void
    {
        $assignment = $this->assignmentRepository->findById($command->assignmentId());

        if (!$assignment) {
            throw new \InvalidArgumentException('Assignment not found.');
        }

        if ($assignment->status() !== 'active') {
            throw new \DomainException('Assignment is not active.');
        }

        $assignment->end($command->endDate());

        $this->assignmentRepository->save($assignment);
    }
}

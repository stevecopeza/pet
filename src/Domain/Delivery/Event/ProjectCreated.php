<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Delivery\Entity\Project;

class ProjectCreated implements DomainEvent
{
    private Project $project;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;

class ProjectTaskCreated implements DomainEvent
{
    private Project $project;
    private Task $task;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Project $project, Task $task)
    {
        $this->project = $project;
        $this->task = $task;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

<?php

namespace Pet\Application\Work\Projection;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Entity\DepartmentQueue;

/**
 * WorkItem Projector.
 * 
 * Handles events from source domains (Commercial, Delivery, Support)
 * and projects them into the WorkItem and DepartmentQueue read models.
 */
class WorkItemProjector
{
    public function __construct(
        // Repositories will be injected here
    ) {
    }

    public function onTicketCreated($event): void
    {
        // TODO: Implement projection logic
        // 1. Create WorkItem from Ticket
        // 2. Create DepartmentQueue entry
        // 3. Persist
    }

    public function onProjectTaskCreated($event): void
    {
        // TODO: Implement projection logic
    }
}

<?php

namespace Pet\Application\Work\Projection;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Entity\DepartmentQueue;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketAssigned;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;

/**
 * WorkItem Projector.
 * 
 * Handles events from source domains (Commercial, Delivery, Support)
 * and projects them into the WorkItem and DepartmentQueue read models.
 */
class WorkItemProjector
{
    public function __construct(
        private WorkItemRepository $workItemRepository,
        private DepartmentQueueRepository $departmentQueueRepository,
        private DepartmentResolver $departmentResolver,
        private SlaClockCalculator $slaClockCalculator,
        private CustomerRepository $customerRepository
    ) {
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket();
        
        // 1. Determine Department
        $departmentId = $this->departmentResolver->resolveForTicket($ticket);

        // 2. Create WorkItem
        $workItemId = wp_generate_uuid4();
        
        $workItem = WorkItem::create(
            $workItemId,
            'ticket',
            (string)$ticket->id(),
            $departmentId,
            0.0, // Initial priority score
            'active',
            $event->occurredAt()
        );

        // If the ticket has an explicit owner, assign it immediately
        $ownerUserId = $ticket->ownerUserId();
        if ($ownerUserId !== null && $ownerUserId !== '') {
            $workItem->assignUser($ownerUserId);
        }

        // Populate details if available
        $due = $ticket->resolutionDueAt();
        if ($due) {
            $workItem->updateScheduling(null, $due);
            // SLA state will be calculated by SlaClockCalculator
            if ($ticket->slaSnapshotId()) {
                $workItem->updateSlaState((string)$ticket->slaSnapshotId(), null);
            }
        }

        // Commercial Info
        $customer = $this->customerRepository->findById($ticket->customerId());
        if ($customer) {
            $data = $customer->malleableData();
            $tier = isset($data['tier']) ? (int)$data['tier'] : 1;
            $workItem->updateCommercialInfo(0.0, $tier);
        }

        // Save first to ensure existence
        $this->workItemRepository->save($workItem);

        // Calculate Initial Priority Score & SLA State
        // This will update the entity and create any immediate signals
        $this->slaClockCalculator->updateItemSlaState($workItem, new \DateTimeImmutable());
        
        // Save again with updated score and SLA state
        $this->workItemRepository->save($workItem);

        // 3. Create DepartmentQueue entry
        $queueItem = DepartmentQueue::enter(
            wp_generate_uuid4(),
            $departmentId,
            $workItemId
        );

        $this->departmentQueueRepository->save($queueItem);
    }

    public function onTicketAssigned(TicketAssigned $event): void
    {
        $ticket = $event->ticket();
        $workItem = $this->workItemRepository->findBySource('ticket', (string)$ticket->id());
        
        if ($workItem) {
            $workItem->assignUser($event->assignedAgentId());
            $this->workItemRepository->save($workItem);
        }
    }

    public function onProjectTaskCreated(ProjectTaskCreated $event): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Pet\Application\Projection\Listener;

use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Delivery\Event\MilestoneCompletedEvent;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;

class FeedProjectionListener
{
    private ActivityLogRepository $activityRepo;

    public function __construct(ActivityLogRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    public function onQuoteAccepted(QuoteAccepted $event): void
    {
        $this->log('quote_accepted', "Quote {$event->quote()->id()} accepted", null, 'quote', $event->quote()->id());
    }

    public function onProjectCreated(ProjectCreated $event): void
    {
        $this->log('project_created', "Project {$event->project()->id()} created", null, 'project', $event->project()->id());
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        $this->log('ticket_created', "Ticket {$event->ticket()->id()} created", null, 'ticket', $event->ticket()->id());
    }

    public function onTicketWarning(TicketWarningEvent $event): void
    {
        $this->log('ticket_warning', "Ticket {$event->getTicketId()} warning", null, 'ticket', $event->getTicketId());
    }

    public function onTicketBreached(TicketBreachedEvent $event): void
    {
        $this->log('ticket_breached', "Ticket {$event->getTicketId()} breached", null, 'ticket', $event->getTicketId());
    }

    public function onEscalationTriggered(EscalationTriggeredEvent $event): void
    {
        $this->log('escalation_triggered', "Ticket {$event->ticketId()} escalated to stage {$event->stage()}", null, 'ticket', $event->ticketId());
    }

    public function onMilestoneCompleted(MilestoneCompletedEvent $event): void
    {
        $this->log('milestone_completed', "Milestone '{$event->milestoneTitle()}' completed for Project {$event->projectId()}", null, 'project', $event->projectId());
    }

    public function onChangeOrderApproved(ChangeOrderApprovedEvent $event): void
    {
        $this->log('change_order_approved', "Change Order approved for Quote {$event->costAdjustment()->quoteId()}", null, 'quote', $event->costAdjustment()->quoteId());
    }

    private function log(string $type, string $description, ?int $userId, string $entityType, int $entityId): void
    {
        $log = new ActivityLog(
            $type,
            $description,
            $userId ?? get_current_user_id(), // Might be 0 if system
            $entityType,
            $entityId
        );
        $this->activityRepo->save($log);
    }
}

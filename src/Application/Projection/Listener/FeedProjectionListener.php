<?php

declare(strict_types=1);

namespace Pet\Application\Projection\Listener;

use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
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
    private FeedEventRepository $feedRepo;

    public function __construct(ActivityLogRepository $activityRepo, FeedEventRepository $feedRepo)
    {
        $this->activityRepo = $activityRepo;
        $this->feedRepo = $feedRepo;
    }

    public function onQuoteAccepted(QuoteAccepted $event): void
    {
        $quoteId = (string)$event->quote()->id();
        $this->log('quote_accepted', "Quote {$quoteId} accepted", null, 'quote', (int)$quoteId);
        
        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'commercial.quote_accepted',
            'commercial',
            $quoteId,
            'strategic',
            'New Business Won',
            "Quote #{$quoteId} has been accepted.",
            ['amount' => $event->quote()->totalValue()],
            'global',
            null
        ));
    }

    public function onProjectCreated(ProjectCreated $event): void
    {
        $projectId = (string)$event->project()->id();
        $this->log('project_created', "Project {$projectId} created", null, 'project', (int)$projectId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'delivery.project_created',
            'delivery',
            $projectId,
            'operational',
            'Project Started',
            "Project #{$projectId} has been initiated.",
            [],
            'department', // Delivery department
            'delivery_dept_id' // Placeholder
        ));
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        $ticketId = (string)$event->ticket()->id();
        $this->log('ticket_created', "Ticket {$ticketId} created", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_created',
            'support',
            $ticketId,
            'operational',
            'New Ticket',
            "Ticket #{$ticketId} created: {$event->ticket()->subject()}",
            ['priority' => $event->ticket()->priority()],
            'department', // Support department
            'support_dept_id' // Placeholder
        ));
    }

    public function onTicketWarning(TicketWarningEvent $event): void
    {
        $ticketId = (string)$event->getTicketId();
        $this->log('ticket_warning', "Ticket {$ticketId} warning", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_warning',
            'support',
            $ticketId,
            'operational',
            'SLA Warning',
            "Ticket #{$ticketId} is approaching SLA breach.",
            [],
            'role', // Support Manager
            'support_manager_role_id' // Placeholder
        ));
    }

    public function onTicketBreached(TicketBreachedEvent $event): void
    {
        $ticketId = (string)$event->getTicketId();
        $this->log('ticket_breached', "Ticket {$ticketId} breached", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_breached',
            'support',
            $ticketId,
            'critical',
            'SLA Breached',
            "Ticket #{$ticketId} has breached SLA.",
            [],
            'role', // Support Manager
            'support_manager_role_id' // Placeholder
        ));
    }

    public function onEscalationTriggered(EscalationTriggeredEvent $event): void
    {
        $ticketId = (string)$event->ticketId();
        $this->log('escalation_triggered', "Ticket {$ticketId} escalated to stage {$event->stage()}", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.escalation_triggered',
            'support',
            $ticketId,
            'critical',
            'Ticket Escalated',
            "Ticket #{$ticketId} escalated to stage {$event->stage()}.",
            ['stage' => $event->stage()],
            'role', // Support Manager
            'support_manager_role_id' // Placeholder
        ));
    }

    public function onMilestoneCompleted(MilestoneCompletedEvent $event): void
    {
        $projectId = (string)$event->projectId();
        $this->log('milestone_completed', "Milestone '{$event->milestoneTitle()}' completed for Project {$projectId}", null, 'project', (int)$projectId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'delivery.milestone_completed',
            'delivery',
            $projectId,
            'operational',
            'Milestone Achieved',
            "Milestone '{$event->milestoneTitle()}' completed.",
            [],
            'department', // Delivery department
            'delivery_dept_id'
        ));
    }

    public function onChangeOrderApproved(ChangeOrderApprovedEvent $event): void
    {
        $quoteId = (string)$event->costAdjustment()->quoteId();
        $this->log('change_order_approved', "Change Order approved for Quote {$quoteId}", null, 'quote', (int)$quoteId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'commercial.change_order_approved',
            'commercial',
            $quoteId,
            'operational',
            'Change Order Approved',
            "Change Order for Quote #{$quoteId} approved.",
            [],
            'department', // Delivery department
            'delivery_dept_id'
        ));
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

    private function generateUuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

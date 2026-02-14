<?php

namespace Pet\Application\Projection\Listener;

if (!function_exists('Pet\Application\Projection\Listener\get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

namespace Tests\Unit\Application\Projection\Listener;

use PHPUnit\Framework\TestCase;
use Pet\Application\Projection\Listener\FeedProjectionListener;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;

class FeedProjectionListenerTest extends TestCase
{
    private $activityRepo;
    private $feedRepo;
    private $listener;

    protected function setUp(): void
    {
        $this->activityRepo = $this->createMock(ActivityLogRepository::class);
        $this->feedRepo = $this->createMock(FeedEventRepository::class);
        $this->listener = new FeedProjectionListener($this->activityRepo, $this->feedRepo);
    }

    public function testOnQuoteAccepted()
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn(1001);
        $quote->method('totalValue')->willReturn(5000.00);

        $event = new QuoteAccepted($quote);

        $this->feedRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (FeedEvent $feedEvent) {
                return $feedEvent->getEventType() === 'commercial.quote_accepted'
                    && $feedEvent->getSourceEntityId() === '1001'
                    && $feedEvent->getClassification() === 'strategic'
                    && $feedEvent->getAudienceScope() === 'global';
            }));

        $this->listener->onQuoteAccepted($event);
    }

    public function testOnProjectCreated()
    {
        $project = $this->createMock(Project::class);
        $project->method('id')->willReturn(2001);

        $event = new ProjectCreated($project);

        $this->feedRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (FeedEvent $feedEvent) {
                return $feedEvent->getEventType() === 'delivery.project_created'
                    && $feedEvent->getSourceEntityId() === '2001'
                    && $feedEvent->getClassification() === 'operational'
                    && $feedEvent->getAudienceScope() === 'department';
            }));

        $this->listener->onProjectCreated($event);
    }

    public function testOnTicketCreated()
    {
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn(3001);
        $ticket->method('subject')->willReturn('Help me');
        $ticket->method('priority')->willReturn('High');

        $event = new TicketCreated($ticket);

        $this->feedRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (FeedEvent $feedEvent) {
                return $feedEvent->getEventType() === 'support.ticket_created'
                    && $feedEvent->getSourceEntityId() === '3001'
                    && $feedEvent->getClassification() === 'operational';
            }));

        $this->listener->onTicketCreated($event);
    }

    public function testOnTicketWarning()
    {
        $event = new TicketWarningEvent(3001);

        $this->feedRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (FeedEvent $feedEvent) {
                return $feedEvent->getEventType() === 'support.ticket_warning'
                    && $feedEvent->getSourceEntityId() === '3001'
                    && $feedEvent->getClassification() === 'operational';
            }));

        $this->listener->onTicketWarning($event);
    }
}

<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Service;

use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Service\SlaAutomationService;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Repository\TicketRepository;
use PHPUnit\Framework\TestCase;

class SlaConcurrencyVerificationTest extends TestCase
{
    private $ticketRepo;
    private $clockStateRepo;
    private $eventDispatcher;
    private $service;

    protected function setUp(): void
    {
        $this->ticketRepo = $this->createMock(TicketRepository::class);
        $this->clockStateRepo = $this->createMock(SlaClockStateRepository::class);
        $this->eventDispatcher = $this->createMock(EventBus::class);

        // Create partial mock to control time
        $this->service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([$this->ticketRepo, $this->clockStateRepo, $this->eventDispatcher])
            ->onlyMethods(['getNow'])
            ->getMock();
    }

    public function testEscalationIsIdempotent(): void
    {
        $ticketId = 101;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('open');
        // Past due
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-2 hours'));
        $ticket->method('respondedAt')->willReturn(new \DateTimeImmutable('-3 hours'));

        // Mock current time
        $this->service->method('getNow')->willReturn(new \DateTimeImmutable());

        // 1. First Pass: Transition to BREACHED, Escalation Stage 0 -> 1
        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $clockState->setEscalationStage(0);
        
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect events
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(TicketBreachedEvent::class)],
                [$this->isInstanceOf(EscalationTriggeredEvent::class)]
            );

        $this->service->evaluate($ticket);

        // Verify state update
        $this->assertSame(1, $clockState->getEscalationStage());
        $this->assertSame(SlaState::BREACHED, $clockState->getLastEventDispatched());
    }

    public function testEscalationDoesNotRetriggerIfAlreadyEscalated(): void
    {
        $ticketId = 102;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('open');
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-2 hours'));
        
        $this->service->method('getNow')->willReturn(new \DateTimeImmutable());

        // State is already BREACHED, but maybe we are re-evaluating (e.g. after restart)
        // Or if logic checks breach again.
        // Actually, if state is already BREACHED, handleTransition is ONLY called if newState != oldState.
        // So if it remains BREACHED, handleTransition is skipped.
        // But what if we want to test that if it DOES enter handleTransition (e.g. from WARNING to BREACHED)
        // but stage is already 1 (manual intervention?), it doesn't re-escalate.
        
        // Let's test the scenario where we are in WARNING, transition to BREACHED, but stage is already 1.
        $clockState = new SlaClockState($ticketId, SlaState::WARNING);
        $clockState->setEscalationStage(1);

        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect ONLY Breached event, NO Escalation event
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TicketBreachedEvent::class));

        $this->service->evaluate($ticket);
    }

    public function testPauseStatePreventsBreach(): void
    {
        $ticketId = 103;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('pending'); // Paused status
        // Past due
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-2 hours'));
        
        $this->service->method('getNow')->willReturn(new \DateTimeImmutable());

        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Should transition to PAUSED, not BREACHED
        $this->clockStateRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($state) {
                return $state->getLastEventDispatched() === SlaState::PAUSED;
            }));

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->service->evaluate($ticket);
    }

    public function testTicketClosureHandling(): void
    {
        // If ticket is resolved/closed, we assume it shouldn't breach even if past due.
        // The service should ideally return something safe.
        // Currently code checks 'pending', 'on_hold'. 
        // We should verify if 'resolved' or 'closed' needs to be added or if they are ignored by findActive().
        // But if they ARE passed to evaluate, they should not breach.
        
        $ticketId = 104;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('resolved');
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-2 hours'));
        
        $this->service->method('getNow')->willReturn(new \DateTimeImmutable());

        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Current implementation only pauses on 'pending', 'on_hold'.
        // If 'resolved' is passed, it might BREACH if we don't fix it.
        // Let's assert that it DOES NOT breach (implying we might need to fix code if it fails).
        // Or we can update the test to expect PAUSED if we add 'resolved' to pause list.
        // Ideally, resolved tickets should be 'FULFILLED' or similar, but for now PAUSED is safe.
        
        // I will update the code to include 'resolved' and 'closed' in the pause list to be safe,
        // and expect PAUSED here.
        
        $this->clockStateRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($state) {
                return $state->getLastEventDispatched() === SlaState::PAUSED;
            }));

        $this->service->evaluate($ticket);
    }
}

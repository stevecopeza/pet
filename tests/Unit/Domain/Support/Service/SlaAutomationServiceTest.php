<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Service;

use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Service\SlaAutomationService;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Repository\TicketRepository;
use PHPUnit\Framework\TestCase;

class SlaAutomationServiceTest extends TestCase
{
    public function testEvaluateDispatchesWarningWhenStateTransitionsToWarning(): void
    {
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);

        $ticketRepo = $this->createMock(TicketRepository::class);
        $clockStateRepo = $this->createMock(SlaClockStateRepository::class);
        $eventDispatcher = $this->createMock(EventBus::class);

        // Mock repo returning ACTIVE state (previous state)
        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect warning event
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TicketWarningEvent::class));
            
        // Expect save to be called with new state
        $clockStateRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (SlaClockState $state) {
                return $state->getLastEventDispatched() === SlaState::WARNING;
            }));

        // Create a partial mock of the service to override determineState
        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([$ticketRepo, $clockStateRepo, $eventDispatcher])
            ->onlyMethods(['determineState'])
            ->getMock();

        // Simulate calculation determining state is now WARNING
        $service->method('determineState')->willReturn(SlaState::WARNING);

        $service->evaluate($ticket);
    }

    public function testEvaluateIsIdempotentAndDoesNotDispatchEventIfStateUnchanged(): void
    {
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);

        $ticketRepo = $this->createMock(TicketRepository::class);
        $clockStateRepo = $this->createMock(SlaClockStateRepository::class);
        $eventDispatcher = $this->createMock(EventBus::class);

        // Mock repo returning ACTIVE state
        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect NO dispatch
        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([$ticketRepo, $clockStateRepo, $eventDispatcher])
            ->onlyMethods(['determineState'])
            ->getMock();

        $service->method('determineState')->willReturn(SlaState::ACTIVE);

        $service->evaluate($ticket);
    }

    public function testDetermineStateLogic(): void
    {
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('open');
        $ticket->method('respondedAt')->willReturn(null);

        // Resolution due in past
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-1 hour'));

        $ticketRepo = $this->createMock(TicketRepository::class);
        $clockStateRepo = $this->createMock(SlaClockStateRepository::class);
        $eventDispatcher = $this->createMock(EventBus::class);

        // Create service with mocked getNow
        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([$ticketRepo, $clockStateRepo, $eventDispatcher])
            ->onlyMethods(['getNow'])
            ->getMock();
        
        // Mock getNow to return "now"
        $service->method('getNow')->willReturn(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        // Use reflection to call protected determineState
        $reflection = new \ReflectionClass(SlaAutomationService::class);
        $method = $reflection->getMethod('determineState');
        $method->setAccessible(true);

        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        
        $result = $method->invoke($service, $ticket, $clockState);
        
        $this->assertEquals(SlaState::BREACHED, $result);
    }
}

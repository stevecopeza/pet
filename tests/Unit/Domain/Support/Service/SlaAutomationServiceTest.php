<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Service;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Service\SlaAutomationService;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use PHPUnit\Framework\TestCase;

class SlaAutomationServiceTest extends TestCase
{
    private $ticketRepo;
    private $clockStateRepo;
    private $eventDispatcher;
    private $featureFlags;
    private $transaction;
    private $service;

    protected function setUp(): void
    {
        $this->ticketRepo = $this->createMock(TicketRepository::class);
        $this->clockStateRepo = $this->createMock(SlaClockStateRepository::class);
        $this->eventDispatcher = $this->createMock(EventBus::class);
        $this->featureFlags = $this->createMock(FeatureFlagService::class);
        $this->transaction = $this->createMock(SqlTransaction::class);

        // Default mock behaviors
        // Transaction methods are void, so no return value needed
        $this->transaction->method('begin');
        $this->transaction->method('commit');
    }

    public function testEvaluateDispatchesWarningWhenStateTransitionsToWarning(): void
    {
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);

        // Mock repo returning ACTIVE state (previous state)
        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect warning event
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TicketWarningEvent::class));
            
        // Expect save to be called with new state
        $this->clockStateRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (SlaClockState $state) {
                return $state->getLastEventDispatched() === SlaState::WARNING;
            }));

        // Create a partial mock of the service to override determineState
        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([
                $this->ticketRepo, 
                $this->clockStateRepo, 
                $this->eventDispatcher, 
                $this->featureFlags, 
                $this->transaction
            ])
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

        // Mock repo returning ACTIVE state
        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Expect NO dispatch
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([
                $this->ticketRepo, 
                $this->clockStateRepo, 
                $this->eventDispatcher, 
                $this->featureFlags, 
                $this->transaction
            ])
            ->onlyMethods(['determineState'])
            ->getMock();

        $service->method('determineState')->willReturn(SlaState::ACTIVE);

        $service->evaluate($ticket);
    }

    public function testDetermineStateLogic(): void
    {
        // This test bypasses constructor dependency on mocks for logic testing via reflection,
        // but we need to instantiate the service correctly.
        
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);
        $ticket->method('status')->willReturn('open');
        $ticket->method('respondedAt')->willReturn(null);
        $ticket->method('resolutionDueAt')->willReturn(new \DateTimeImmutable('-1 hour'));

        $service = new SlaAutomationService(
            $this->ticketRepo, 
            $this->clockStateRepo, 
            $this->eventDispatcher, 
            $this->featureFlags, 
            $this->transaction
        );
        
        // Use reflection to call protected determineState
        $reflection = new \ReflectionClass(SlaAutomationService::class);
        $method = $reflection->getMethod('determineState');
        $method->setAccessible(true);

        $clockState = new SlaClockState($ticketId, SlaState::ACTIVE);
        
        $result = $method->invoke($service, $ticket, $clockState);
        
        $this->assertEquals(SlaState::BREACHED, $result);
    }

    public function testEscalationTriggeredOnBreachWhenFlagEnabled(): void
    {
        $ticketId = 123;
        $ticket = $this->createMock(Ticket::class);
        $ticket->method('id')->willReturn($ticketId);

        $clockState = new SlaClockState($ticketId, SlaState::WARNING);
        $this->clockStateRepo->method('findByTicketIdForUpdate')->willReturn($clockState);

        // Enable Escalation Feature Flag
        $this->featureFlags->method('isEscalationEngineEnabled')->willReturn(true);

        // Expect TicketBreachedEvent AND EscalationTriggeredEvent
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(TicketBreachedEvent::class)],
                [$this->isInstanceOf(EscalationTriggeredEvent::class)]
            );
            
        // Expect escalation stage to be set to 1
        $this->clockStateRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (SlaClockState $state) {
                return $state->getLastEventDispatched() === SlaState::BREACHED 
                    && $state->getEscalationStage() === 1;
            }));

        $service = $this->getMockBuilder(SlaAutomationService::class)
            ->setConstructorArgs([
                $this->ticketRepo, 
                $this->clockStateRepo, 
                $this->eventDispatcher, 
                $this->featureFlags, 
                $this->transaction
            ])
            ->onlyMethods(['determineState'])
            ->getMock();

        $service->method('determineState')->willReturn(SlaState::BREACHED);

        $service->evaluate($ticket);
    }
}

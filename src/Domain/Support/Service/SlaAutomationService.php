<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\ValueObject\SlaState;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Support\Event\EscalationTriggeredEvent;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Entity\SlaClockState;

class SlaAutomationService
{
    private TicketRepository $ticketRepo;
    private SlaClockStateRepository $clockStateRepo;
    private EventBus $eventDispatcher;

    public function __construct(
        TicketRepository $ticketRepo,
        SlaClockStateRepository $clockStateRepo,
        EventBus $eventDispatcher
    ) {
        $this->ticketRepo = $ticketRepo;
        $this->clockStateRepo = $clockStateRepo;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Entry point for the SLA automation loop.
     * Finds all active tickets and evaluates their SLA state.
     */
    public function runSlaCheck(): void
    {
        $activeTickets = $this->ticketRepo->findActive();
        foreach ($activeTickets as $ticket) {
            $this->evaluate($ticket);
        }
    }

    /**
     * Evaluates the SLA state for a ticket and dispatches events if transitions occur.
     * This method is idempotent and stateful.
     */
    public function evaluate(Ticket $ticket): void
    {
        // 1. Calculate current state based on business time
        // Note: In a real implementation, we would use a BusinessTimeCalculator here.
        // For this skeleton, we assume the ticket entity has the necessary timestamps.
        
        // 2. Load persisted state with row locking
        $clockState = $this->clockStateRepo->findByTicketIdForUpdate($ticket->id());
        
        if (!$clockState) {
            // First evaluation, initialize state
            $clockState = $this->clockStateRepo->initialize($ticket);
        }

        // 3. Determine new state
        $newState = $this->determineState($ticket, $clockState);
        
        // 4. Compare and Transition
        if ($newState !== $clockState->getLastEventDispatched()) {
            $this->handleTransition($ticket, $newState, $clockState);
            
            // 5. Persist new state
            $clockState->setLastEventDispatched($newState);
            $clockState->setLastEvaluatedAt(new \DateTimeImmutable());
            $this->clockStateRepo->save($clockState);
        }
    }

    /**
     * Protected for testing purposes to simulate different time states
     */
    protected function determineState(Ticket $ticket, $clockState): string
    {
        $now = $this->getNow();
        
        // 1. Check for Paused Status
        // If ticket status implies pause (e.g., pending input), return PAUSED
        // Assuming 'pending' or 'on_hold' are paused states.
        // Also handling 'resolved' and 'closed' as effectively paused/stopped to prevent late breaches.
        if (in_array($ticket->status(), ['pending', 'on_hold', 'resolved', 'closed'], true)) {
            return SlaState::PAUSED;
        }

        // 2. Check Resolution Breach
        if ($ticket->resolutionDueAt() && $now > $ticket->resolutionDueAt()) {
            return SlaState::BREACHED;
        }

        // 3. Check Response Breach (if not yet responded)
        if ($ticket->responseDueAt() && !$ticket->respondedAt() && $now > $ticket->responseDueAt()) {
            return SlaState::BREACHED;
        }

        // 4. Check Warning (e.g., < 1 hour to breach)
        // Check Resolution Warning
        if ($ticket->resolutionDueAt()) {
            $warningThreshold = $ticket->resolutionDueAt()->modify('-1 hour');
            if ($now > $warningThreshold) {
                return SlaState::WARNING;
            }
        }

        // Check Response Warning
        if ($ticket->responseDueAt() && !$ticket->respondedAt()) {
            $warningThreshold = $ticket->responseDueAt()->modify('-1 hour');
            if ($now > $warningThreshold) {
                return SlaState::WARNING;
            }
        }

        return SlaState::ACTIVE;
    }

    private function handleTransition(Ticket $ticket, string $newState, SlaClockState $clockState): void
    {
        if ($newState === SlaState::WARNING) {
            $this->eventDispatcher->dispatch(new TicketWarningEvent($ticket->id()));
        } elseif ($newState === SlaState::BREACHED) {
            $this->eventDispatcher->dispatch(new TicketBreachedEvent($ticket->id()));

            if ($clockState->getEscalationStage() === 0) {
                $clockState->setEscalationStage(1);
                $this->eventDispatcher->dispatch(new EscalationTriggeredEvent($ticket->id(), 1));
            }
        }
    }

    protected function getNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

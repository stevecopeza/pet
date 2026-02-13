<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Support\Cron;

use Pet\Application\Support\Cron\SlaAutomationJob;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\Service\SlaAutomationService;
use PHPUnit\Framework\TestCase;

class SlaAutomationJobTest extends TestCase
{
    public function testRunEvaluatesAllActiveTickets(): void
    {
        $ticketRepo = $this->createMock(TicketRepository::class);
        $slaService = $this->createMock(SlaAutomationService::class);

        $ticket1 = $this->createMock(Ticket::class);
        $ticket1->method('id')->willReturn(1);
        $ticket2 = $this->createMock(Ticket::class);
        $ticket2->method('id')->willReturn(2);

        $ticketRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$ticket1, $ticket2]);

        $slaService->expects($this->exactly(2))
            ->method('evaluate')
            ->withConsecutive(
                [$ticket1],
                [$ticket2]
            );

        $job = new SlaAutomationJob($ticketRepo, $slaService);
        $job->run();
    }

    public function testRunContinuesAfterError(): void
    {
        $ticketRepo = $this->createMock(TicketRepository::class);
        $slaService = $this->createMock(SlaAutomationService::class);

        $ticket1 = $this->createMock(Ticket::class);
        $ticket1->method('id')->willReturn(1);
        $ticket2 = $this->createMock(Ticket::class);
        $ticket2->method('id')->willReturn(2);

        $ticketRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$ticket1, $ticket2]);

        // First call throws exception
        $slaService->expects($this->exactly(2))
            ->method('evaluate')
            ->withConsecutive(
                [$ticket1],
                [$ticket2]
            )
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Failed')),
                null
            );

        $job = new SlaAutomationJob($ticketRepo, $slaService);
        
        // Should not throw exception
        $job->run();
    }
}

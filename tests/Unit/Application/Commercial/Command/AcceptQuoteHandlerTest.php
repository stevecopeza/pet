<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Event\EventBus;
use Pet\Application\System\Service\TouchedTracker;
use Pet\Application\Support\Command\CreateTicketCommand;

class AcceptQuoteHandlerTest extends TestCase
{
    private $quoteRepository;
    private $eventBus;
    private $touchedTracker;
    private $createTicketHandler;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->touchedTracker = null;
        $this->createTicketHandler = $this->createMock(CreateTicketHandler::class);
    }

    public function testCreatesExecutionTicketsForImplementationTasks(): void
    {
        $quoteId = 42;
        $customerId = 1001;

        $task1 = new QuoteTask('Task A', 2.0, 10, 100.0, 200.0, 'Desc A', 1001);
        $task2 = new QuoteTask('Task B', 1.0, 11, 120.0, 220.0, null, 1002);

        $milestone = new QuoteMilestone('Milestone 1', [$task1, $task2], 'M1', 501);
        $component = new ImplementationComponent([$milestone], 'Implementation', 301);

        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        $quote->method('customerId')->willReturn($customerId);
        $quote->method('components')->willReturn([$component]);

        $quote->expects($this->once())
            ->method('accept');

        $this->quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn($quote);

        $this->quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $this->eventBus->expects($this->once())
            ->method('dispatch');

        $this->createTicketHandler->expects($this->exactly(2))
            ->method('handle')
            ->withConsecutive(
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Task A'
                            && $command->description() === 'Desc A'
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 301
                            && $data['quote_milestone_id'] === 501
                            && $data['quote_task_row_id'] === 1001
                            && $data['sold_hours'] === 2.0
                            && $data['role_id'] === 10
                            && $data['ticket_mode'] === 'execution';
                    }),
                ],
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Task B'
                            && $command->description() === ''
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 301
                            && $data['quote_milestone_id'] === 501
                            && $data['quote_task_row_id'] === 1002
                            && $data['sold_hours'] === 1.0
                            && $data['role_id'] === 11
                            && $data['ticket_mode'] === 'execution';
                    }),
                ]
            );

        $handler = new AcceptQuoteHandler(
            $this->quoteRepository,
            $this->eventBus,
            $this->touchedTracker,
            $this->createTicketHandler
        );

        $handler->handle(new AcceptQuoteCommand($quoteId));
    }

    public function testNoTicketsCreatedWhenHandlerIsNull(): void
    {
        $quoteId = 7;

        $component = new ImplementationComponent([], 'Implementation', 1);

        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        $quote->method('components')->willReturn([$component]);

        $this->quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn($quote);

        $this->quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $handler = new AcceptQuoteHandler(
            $this->quoteRepository,
            $this->eventBus,
            null,
            null
        );

        $handler->handle(new AcceptQuoteCommand($quoteId));
    }
}

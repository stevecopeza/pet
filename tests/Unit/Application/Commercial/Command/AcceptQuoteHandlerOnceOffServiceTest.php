<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Event\EventBus;

final class AcceptQuoteHandlerOnceOffServiceTest extends TestCase
{
    public function testCreatesOneTicketPerSimpleUnit(): void
    {
        $quoteId = 99;
        $customerId = 5001;

        $unit1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0, 'Sweep desc', 201);
        $unit2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0, null, 202);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$unit1, $unit2],
            'Classroom quick prep',
            301
        );

        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        $quote->method('customerId')->willReturn($customerId);
        $quote->method('components')->willReturn([$component]);

        $quote->expects($this->once())
            ->method('accept');

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn($quote);
        $quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects($this->once())
            ->method('dispatch');

        $createTicketHandler = $this->createMock(CreateTicketHandler::class);
        $createTicketHandler->expects($this->exactly(2))
            ->method('handle')
            ->withConsecutive(
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Sweep classroom'
                            && $command->description() === 'Sweep desc'
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 301
                            && $data['quote_simple_unit_id'] === 201
                            && $data['unit_quantity'] === 2.0
                            && $data['unit_sell_price'] === 100.0
                            && $data['unit_internal_cost'] === 60.0
                            && $data['ticket_mode'] === 'execution';
                    }),
                ],
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Wash windows'
                            && $command->description() === ''
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 301
                            && $data['quote_simple_unit_id'] === 202
                            && $data['unit_quantity'] === 1.0
                            && $data['unit_sell_price'] === 150.0
                            && $data['unit_internal_cost'] === 80.0
                            && $data['ticket_mode'] === 'execution';
                    }),
                ]
            );

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new AcceptQuoteHandler(
            $transactionManager,
            $quoteRepository,
            $eventBus,
            null,
            $createTicketHandler
        );

        $handler->handle(new AcceptQuoteCommand($quoteId));
    }

    public function testCreatesTicketsForComplexOnceOffServiceComponent(): void
    {
        $quoteId = 100;
        $customerId = 5002;

        $phase1Unit1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0, 'Sweep desc', 501);
        $phase1Unit2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0, null, 502);
        $phase2Unit1 = new SimpleUnit('Pack decorations', 3.0, 75.0, 40.0, 'Pack desc', 503);

        $phase1 = new Phase('Prepare classroom', [$phase1Unit1, $phase1Unit2], 'Prep phase', 601);
        $phase2 = new Phase('Decorate classroom', [$phase2Unit1], 'Decorate phase', 602);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase1, $phase2],
            [],
            'End of term classroom refresh',
            701
        );

        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        $quote->method('customerId')->willReturn($customerId);
        $quote->method('components')->willReturn([$component]);

        $quote->expects($this->once())
            ->method('accept');

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn($quote);
        $quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects($this->once())
            ->method('dispatch');

        $createTicketHandler = $this->createMock(CreateTicketHandler::class);
        $createTicketHandler->expects($this->exactly(3))
            ->method('handle')
            ->withConsecutive(
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Sweep classroom'
                            && $command->description() === 'Sweep desc'
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 701
                            && $data['quote_simple_unit_id'] === 501
                            && $data['unit_quantity'] === 2.0
                            && $data['unit_sell_price'] === 100.0
                            && $data['unit_internal_cost'] === 60.0
                            && $data['ticket_mode'] === 'execution';
                    }),
                ],
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Wash windows'
                            && $command->description() === ''
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 701
                            && $data['quote_simple_unit_id'] === 502
                            && $data['unit_quantity'] === 1.0
                            && $data['unit_sell_price'] === 150.0
                            && $data['unit_internal_cost'] === 80.0
                            && $data['ticket_mode'] === 'execution';
                    }),
                ],
                [
                    $this->callback(function (CreateTicketCommand $command) use ($quoteId, $customerId) {
                        $data = $command->malleableData();
                        return $command->customerId() === $customerId
                            && $command->subject() === 'Pack decorations'
                            && $command->description() === 'Pack desc'
                            && $command->priority() === 'medium'
                            && $data['source'] === 'quote'
                            && $data['quote_id'] === $quoteId
                            && $data['quote_component_id'] === 701
                            && $data['quote_simple_unit_id'] === 503
                            && $data['unit_quantity'] === 3.0
                            && $data['unit_sell_price'] === 75.0
                            && $data['unit_internal_cost'] === 40.0
                            && $data['ticket_mode'] === 'execution';
                    }),
                ]
            );

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new AcceptQuoteHandler(
            $transactionManager,
            $quoteRepository,
            $eventBus,
            null,
            $createTicketHandler
        );

        $handler->handle(new AcceptQuoteCommand($quoteId));
    }
}

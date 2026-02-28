<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Commercial\Command\SetPaymentScheduleCommand;
use Pet\Application\Commercial\Command\SetPaymentScheduleHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Event\PaymentScheduleDefinedEvent;
use Pet\Domain\Event\EventBus;

class SetPaymentScheduleHandlerTest extends TestCase
{
    public function testDispatchesPaymentScheduleDefinedEvent(): void
    {
        $quote = new Quote(
            1,
            'Test',
            null,
            QuoteState::draft(),
            1,
            100.0,
            0.0,
            'USD',
            null,
            10,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [],
            [],
            [],
            []
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')->with(10)->willReturn($quote);
        $quoteRepository->expects($this->once())->method('save')->with($this->isInstanceOf(Quote::class));

        $capturedEvents = [];

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->method('dispatch')->willReturnCallback(function ($event) use (&$capturedEvents) {
            $capturedEvents[] = $event;
        });

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new SetPaymentScheduleHandler($transactionManager, $quoteRepository, $eventBus);

        $command = new SetPaymentScheduleCommand(10, [
            ['title' => 'Deposit', 'amount' => 100.0, 'dueDate' => null],
        ]);

        $handler->handle($command);

        $this->assertNotEmpty($capturedEvents);
        $found = false;

        foreach ($capturedEvents as $event) {
            if ($event instanceof PaymentScheduleDefinedEvent) {
                $found = true;
                $this->assertSame(10, $event->quoteId());
                $this->assertSame(100.0, $event->totalAmount());
                $this->assertCount(1, $event->items());
            }
        }

        $this->assertTrue($found);
    }

    public function testCannotChangeScheduleForFinalizedQuote(): void
    {
        $quote = new Quote(
            1,
            'Test',
            null,
            QuoteState::accepted(),
            1,
            100.0,
            0.0,
            'USD',
            new \DateTimeImmutable(),
            10,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [],
            [],
            [],
            []
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')->with(10)->willReturn($quote);
        $quoteRepository->expects($this->never())->method('save');

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects($this->never())->method('dispatch');

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new SetPaymentScheduleHandler($transactionManager, $quoteRepository, $eventBus);

        $command = new SetPaymentScheduleCommand(10, []);

        $this->expectException(\DomainException::class);
        $handler->handle($command);
    }
}


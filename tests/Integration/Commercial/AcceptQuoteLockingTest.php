<?php

declare(strict_types=1);

namespace Tests\Integration\Commercial;

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Event\EventBus;
use PHPUnit\Framework\TestCase;

class AcceptQuoteLockingTest extends TestCase
{
    public function testAcceptQuoteUsesLocking(): void
    {
        // Mock dependencies
        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        // Setup Quote mock
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn(123);
        $quote->method('version')->willReturn(1);
        $quote->method('paymentSchedule')->willReturn([]);
        $quote->method('components')->willReturn([]);
        $quote->method('customerId')->willReturn(456);

        // Expectation: findById must be called with lock=true
        $quoteRepository->expects($this->once())
            ->method('findById')
            ->with(
                $this->equalTo(123),
                $this->equalTo(true) // This asserts lock=true
            )
            ->willReturn($quote);

        // Instantiate Handler
        $handler = new AcceptQuoteHandler(
            $transactionManager,
            $quoteRepository,
            $eventBus
        );

        // Execute Command
        $command = new AcceptQuoteCommand(123);
        $handler->handle($command);
    }
}

<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Event\EventBus;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use Pet\Tests\Stubs\InMemoryWpdb;

class AcceptQuoteTransactionTest extends TestCase
{
    private $wpdb;
    private $transactionManager;
    private $quoteRepo;
    private $eventBus;
    private $handler;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        $this->transactionManager = new SqlTransaction($this->wpdb);
        $this->quoteRepo = $this->createMock(QuoteRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);

        $this->handler = new AcceptQuoteHandler(
            $this->transactionManager,
            $this->quoteRepo,
            $this->eventBus
        );
    }

    public function testTransactionRollsBackOnEventBusFailure(): void
    {
        // 1. Setup Quote
        $quoteId = 123;
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        
        $this->quoteRepo->method('findById')
            ->with($quoteId, true) // Expect locking
            ->willReturn($quote);

        // 2. Simulate EventBus failure
        $this->eventBus->method('dispatch')
            ->willThrowException(new \RuntimeException("Event bus failure"));

        // 3. Execute and expect exception
        try {
            $command = new AcceptQuoteCommand($quoteId);
            $this->handler->handle($command);
            $this->fail("Expected exception was not thrown");
        } catch (\RuntimeException $e) {
            $this->assertEquals("Event bus failure", $e->getMessage());
        }

        // 4. Verify transaction status
        $status = $this->wpdb->transactionStatus;
        $this->assertNotEmpty($status, "No transaction commands logged");
        $this->assertEquals('START TRANSACTION', $status[0]);
        // ROLLBACK should be the last command
        $this->assertEquals('ROLLBACK', end($status));
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        // 1. Setup Quote
        $quoteId = 124;
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn($quoteId);
        
        $this->quoteRepo->method('findById')->willReturn($quote);

        // 2. Execute
        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);

        // 3. Verify transaction status
        $status = $this->wpdb->transactionStatus;
        $this->assertNotEmpty($status, "No transaction commands logged");
        $this->assertEquals('START TRANSACTION', $status[0]);
        $this->assertEquals('COMMIT', end($status));
    }
}

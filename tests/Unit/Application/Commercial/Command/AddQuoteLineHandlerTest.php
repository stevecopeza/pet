<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AddQuoteLineCommand;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;

class AddQuoteLineHandlerTest extends TestCase
{
    private $quoteRepository;
    private $handler;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->handler = new AddQuoteLineHandler($this->quoteRepository);
    }

    public function testHandleAddsLineToQuote()
    {
        $quoteId = 1;
        $command = new AddQuoteLineCommand($quoteId, 'Item 1', 1.0, 100.0, 'product');

        $quote = $this->createMock(Quote::class);
        
        $this->quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn($quote);

        $quote->expects($this->once())
            ->method('addLine');

        $this->quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionIfQuoteNotFound()
    {
        $quoteId = 999;
        $command = new AddQuoteLineCommand($quoteId, 'Item 1', 1.0, 100.0, 'product');

        $this->quoteRepository->method('findById')
            ->with($quoteId)
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Quote not found: 999");

        $this->handler->handle($command);
    }
}

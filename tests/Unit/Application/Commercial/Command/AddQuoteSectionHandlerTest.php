<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AddQuoteSectionCommand;
use Pet\Application\Commercial\Command\AddQuoteSectionHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

final class AddQuoteSectionHandlerTest extends TestCase
{
    public function testAddsSectionWithSparseOrdering(): void
    {
        $quote = new Quote(
            123,
            'Test Quote',
            null,
            QuoteState::draft()
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(42)
            ->willReturn($quote);

        $existingSection = new QuoteSection(
            42,
            'Existing',
            2000,
            true,
            false,
            false,
            1
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->method('findByQuoteId')
            ->with(42)
            ->willReturn([$existingSection]);

        $quoteSectionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (QuoteSection $section) {
                return $section->quoteId() === 42
                    && $section->name() === 'New Section'
                    && $section->orderIndex() === 3000
                    && $section->showTotalValue() === true
                    && $section->showItemCount() === false
                    && $section->showTotalHours() === false;
            }))
            ->willReturnCallback(function (QuoteSection $section) {
                return new QuoteSection(
                    $section->quoteId(),
                    $section->name(),
                    $section->orderIndex(),
                    $section->showTotalValue(),
                    $section->showItemCount(),
                    $section->showTotalHours(),
                    99
                );
            });

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new AddQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository);

        $command = new AddQuoteSectionCommand(42, 'New Section');
        $result = $handler->handle($command);

        $this->assertInstanceOf(QuoteSection::class, $result);
        $this->assertSame(42, $result->quoteId());
        $this->assertSame('New Section', $result->name());
        $this->assertSame(3000, $result->orderIndex());
        $this->assertSame(99, $result->id());
    }

    public function testThrowsWhenQuoteNotFound(): void
    {
        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $handler = new AddQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository);
        $command = new AddQuoteSectionCommand(999, 'New Section');

        $this->expectException(\DomainException::class);

        $handler->handle($command);
    }
}


<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial;

use Pet\Application\Commercial\Command\DeleteQuoteSectionCommand;
use Pet\Application\Commercial\Command\DeleteQuoteSectionHandler;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use PHPUnit\Framework\TestCase;

final class DeleteQuoteSectionHandlerTest extends TestCase
{
    public function testThrowsWhenSectionHasNonTextBlocks(): void
    {
        $quote = new Quote(
            10,
            'Test',
            null,
            QuoteState::draft()
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(10)
            ->willReturn($quote);

        $section = new QuoteSection(
            10,
            'Section A',
            0,
            true,
            false,
            false,
            1
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);

        $hardwareBlock = new QuoteBlock(
            0,
            QuoteBlock::TYPE_HARDWARE,
            null,
            0.0,
            0.0,
            true,
            1,
            [],
            100
        );

        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $quoteBlockRepository
            ->method('findByQuoteId')
            ->with(10)
            ->willReturn([$hardwareBlock]);

        $handler = new DeleteQuoteSectionHandler(
            $quoteRepository,
            $quoteSectionRepository,
            $quoteBlockRepository
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete section that still contains blocks.');

        $command = new DeleteQuoteSectionCommand(10, 1);
        $handler->handle($command);
    }

    public function testDeletesTextBlocksAndSectionWhenOnlyTextBlocksPresent(): void
    {
        $quote = new Quote(
            20,
            'Test',
            null,
            QuoteState::draft()
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(20)
            ->willReturn($quote);

        $section = new QuoteSection(
            20,
            'Section Text',
            0,
            true,
            false,
            false,
            2
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->expects($this->once())
            ->method('delete')
            ->with(2);

        $textBlock1 = new QuoteBlock(
            0,
            QuoteBlock::TYPE_TEXT,
            null,
            0.0,
            0.0,
            false,
            2,
            ['text' => 'One'],
            200
        );

        $textBlock2 = new QuoteBlock(
            1,
            QuoteBlock::TYPE_TEXT,
            null,
            0.0,
            0.0,
            false,
            2,
            ['text' => 'Two'],
            201
        );

        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $quoteBlockRepository
            ->method('findByQuoteId')
            ->with(20)
            ->willReturn([$textBlock1, $textBlock2]);

        $quoteBlockRepository
            ->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive([200], [201]);

        $handler = new DeleteQuoteSectionHandler(
            $quoteRepository,
            $quoteSectionRepository,
            $quoteBlockRepository
        );

        $command = new DeleteQuoteSectionCommand(20, 2);
        $handler->handle($command);
    }
}


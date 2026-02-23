<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial;

use Pet\Application\Commercial\Command\CreateQuoteBlockCommand;
use Pet\Application\Commercial\Command\CreateQuoteBlockHandler;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use PHPUnit\Framework\TestCase;

final class CreateQuoteBlockHandlerTest extends TestCase
{
    public function testCreatesBlockInSectionWithIncrementedOrderIndex(): void
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
            1000,
            true,
            false,
            false,
            1
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->method('findByQuoteId')
            ->with(10)
            ->willReturn([$section]);

        $existingBlock = new QuoteBlock(
            1000,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            null,
            0.0,
            0.0,
            true,
            1
        );

        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $quoteBlockRepository
            ->method('findByQuoteId')
            ->with(10)
            ->willReturn([$existingBlock]);

        $quoteBlockRepository
            ->method('insert')
            ->willReturnCallback(function (QuoteBlock $block, int $quoteId) {
                if ($block->position() !== 2000) {
                    $this->fail('Expected position 2000');
                }
                if ($block->sectionId() !== 1) {
                    $this->fail('Expected sectionId 1');
                }
                if ($block->type() !== QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE) {
                    $this->fail('Unexpected block type');
                }

                return new QuoteBlock(
                    $block->position(),
                    $block->type(),
                    $block->componentId(),
                    $block->sellValue(),
                    $block->internalCost(),
                    $block->isPriced(),
                    $block->sectionId(),
                    $block->payload(),
                    55
                );
            });

        $handler = new CreateQuoteBlockHandler(
            $quoteRepository,
            $quoteSectionRepository,
            $quoteBlockRepository
        );

        $command = new CreateQuoteBlockCommand(
            10,
            1,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            ['foo' => 'bar']
        );

        $block = $handler->handle($command);

        $this->assertSame(2000, $block->position());
        $this->assertSame(1, $block->sectionId());
        $this->assertSame(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $block->type());
        $this->assertSame(['foo' => 'bar'], $block->payload());
        $this->assertSame(55, $block->id());
    }
}

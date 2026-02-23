<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use PHPUnit\Framework\TestCase;

final class QuoteBlockTest extends TestCase
{
    public function testBlockOrderingPreservedFromComponents(): void
    {
        $u1 = new SimpleUnit('Unit 1', 1.0, 100.0, 50.0);
        $u2 = new SimpleUnit('Unit 2', 1.0, 200.0, 120.0);

        $simple = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$u1],
            'Simple',
            10
        );

        $phase = new Phase('Phase A', [$u2], null, 20);
        $project = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase],
            [],
            'Project',
            11
        );

        $recurring = new RecurringServiceComponent(
            'Recurring Service',
            [],
            'monthly',
            12,
            'auto',
            300.0,
            150.0,
            'Recurring',
            12
        );

        $blocks = QuoteBlock::fromComponents([$simple, $project, $recurring]);

        $this->assertCount(3, $blocks);
        $this->assertSame(0, $blocks[0]->position());
        $this->assertSame(1, $blocks[1]->position());
        $this->assertSame(2, $blocks[2]->position());

        $this->assertSame(10, $blocks[0]->componentId());
        $this->assertSame(11, $blocks[1]->componentId());
        $this->assertSame(12, $blocks[2]->componentId());

        $this->assertSame(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $blocks[0]->type());
        $this->assertSame(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $blocks[1]->type());
        $this->assertSame(QuoteBlock::TYPE_REPEAT_SERVICE, $blocks[2]->type());
    }

    public function testTotalsRecalculateCorrectlyAfterReorderingBlocks(): void
    {
        $u1 = new SimpleUnit('Unit 1', 1.0, 100.0, 50.0);
        $u2 = new SimpleUnit('Unit 2', 2.0, 200.0, 120.0);

        $simple = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$u1],
            'Simple',
            1
        );

        $phase = new Phase('Phase A', [$u2]);
        $project = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase],
            [],
            'Project',
            2
        );

        $blocks = QuoteBlock::fromComponents([$simple, $project]);
        $originalSellTotal = QuoteBlock::totalSellValue($blocks);
        $originalCostTotal = QuoteBlock::totalInternalCost($blocks);

        $reordered = array_reverse($blocks);
        $reorderedSellTotal = QuoteBlock::totalSellValue($reordered);
        $reorderedCostTotal = QuoteBlock::totalInternalCost($reordered);

        $this->assertSame($originalSellTotal, $reorderedSellTotal);
        $this->assertSame($originalCostTotal, $reorderedCostTotal);
    }

    public function testProjectBlockPhaseSubtotalDerivedFromUnitsOnly(): void
    {
        $u1 = new SimpleUnit('Unit 1', 1.0, 100.0, 50.0);
        $u2 = new SimpleUnit('Unit 2', 2.0, 200.0, 120.0);

        $phase = new Phase('Phase A', [$u1, $u2]);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase],
            [],
            'Project'
        );

        $blocks = QuoteBlock::fromComponents([$component]);

        $this->assertCount(1, $blocks);
        $this->assertSame($phase->sellValue(), $blocks[0]->sellValue());
        $this->assertSame($phase->internalCost(), $blocks[0]->internalCost());
    }
}


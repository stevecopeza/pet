<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial;

use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use PHPUnit\Framework\TestCase;

final class OnceOffServiceComponentPricingTest extends TestCase
{
    public function testSimpleTopologyTotalsAreSumOfUnitTotals(): void
    {
        $u1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0);
        $u2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$u1, $u2],
            'Classroom quick prep'
        );

        $expectedSell = $u1->sellValue() + $u2->sellValue();
        $expectedCost = $u1->internalCost() + $u2->internalCost();

        $this->assertSame($expectedSell, $component->sellValue());
        $this->assertSame($expectedCost, $component->internalCost());
    }

    public function testComplexTopologyPhaseSubtotalEqualsSumOfUnitTotals(): void
    {
        $u1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0);
        $u2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0);

        $phase = new Phase('Prepare classroom', [$u1, $u2]);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase],
            [],
            'End of term decoration'
        );

        $this->assertSame($u1->sellValue() + $u2->sellValue(), $phase->sellValue());
        $this->assertSame($u1->internalCost() + $u2->internalCost(), $phase->internalCost());

        $this->assertSame($phase->sellValue(), $component->sellValue());
        $this->assertSame($phase->internalCost(), $component->internalCost());
    }

    public function testQuoteTotalDerivedFromAllUnitsAcrossPhases(): void
    {
        $u1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0);
        $u2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0);
        $u3 = new SimpleUnit('Remove posters', 3.0, 90.0, 50.0);

        $phase1 = new Phase('Prepare classroom', [$u1, $u2]);
        $phase2 = new Phase('Prepare corridor', [$u3]);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [$phase1, $phase2],
            [],
            'End of term decoration'
        );

        $expectedSell = $u1->sellValue() + $u2->sellValue() + $u3->sellValue();
        $expectedCost = $u1->internalCost() + $u2->internalCost() + $u3->internalCost();

        $this->assertSame($expectedSell, $component->sellValue());
        $this->assertSame($expectedCost, $component->internalCost());
    }

    public function testSimpleTopologyCannotContainPhases(): void
    {
        $this->expectException(\DomainException::class);

        $u1 = new SimpleUnit('Unit', 1.0, 100.0, 50.0);
        $phase = new Phase('Phase', [$u1]);

        new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [$phase],
            [],
            'Invalid'
        );
    }

    public function testComplexTopologyUnitsMustBeInsidePhases(): void
    {
        $this->expectException(\DomainException::class);

        $u1 = new SimpleUnit('Unit', 1.0, 100.0, 50.0);

        new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_COMPLEX,
            [],
            [$u1],
            'Invalid'
        );
    }
}


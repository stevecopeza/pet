<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\AddComponentCommand;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;

final class AddComponentHandlerOnceOffServiceTest extends TestCase
{
    public function testAddsSimpleOnceOffServiceComponentWithSingleUnit(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->expects($this->once())
            ->method('addComponent')
            ->with($this->callback(function ($component) {
                if (!$component instanceof OnceOffServiceComponent) {
                    return false;
                }

                if ($component->topology() !== OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
                    return false;
                }

                $units = $component->units();
                if (count($units) !== 1) {
                    return false;
                }

                $unit = $units[0];
                return $unit->title() === 'Classroom quick prep'
                    && $unit->quantity() === 2.0
                    && $unit->unitSellPrice() === 100.0
                    && $unit->unitInternalCost() === 60.0;
            }));

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')
            ->with(42)
            ->willReturn($quote);
        $quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $catalogItemRepository = $this->createMock(CatalogItemRepository::class);

        $handler = new AddComponentHandler($quoteRepository, $catalogItemRepository);

        $command = new AddComponentCommand(
            42,
            'once_off_service',
            [
                'description' => 'Classroom quick prep',
                'section' => 'Labor',
                'topology' => OnceOffServiceComponent::TOPOLOGY_SIMPLE,
                'units' => [
                    [
                        'title' => 'Classroom quick prep',
                        'description' => 'Classroom quick prep',
                        'quantity' => 2.0,
                        'unit_sell_price' => 100.0,
                        'unit_internal_cost' => 60.0,
                    ],
                ],
            ]
        );

        $handler->handle($command);
    }

    public function testAddsComplexOnceOffServiceComponentWithPhases(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->expects($this->once())
            ->method('addComponent')
            ->with($this->callback(function ($component) {
                if (!$component instanceof OnceOffServiceComponent) {
                    return false;
                }

                if ($component->topology() !== OnceOffServiceComponent::TOPOLOGY_COMPLEX) {
                    return false;
                }

                $phases = $component->phases();
                if (count($phases) !== 1) {
                    return false;
                }

                $phase = $phases[0];
                if ($phase->name() !== 'Prepare classroom') {
                    return false;
                }

                $units = $phase->units();
                if (count($units) !== 1) {
                    return false;
                }

                $unit = $units[0];
                return $unit->title() === 'Sweep classroom'
                    && $unit->quantity() === 2.0
                    && $unit->unitSellPrice() === 100.0
                    && $unit->unitInternalCost() === 60.0;
            }));

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->method('findById')
            ->with(43)
            ->willReturn($quote);
        $quoteRepository->expects($this->once())
            ->method('save')
            ->with($quote);

        $catalogItemRepository = $this->createMock(CatalogItemRepository::class);

        $handler = new AddComponentHandler($quoteRepository, $catalogItemRepository);

        $command = new AddComponentCommand(
            43,
            'once_off_service',
            [
                'description' => 'End of term decoration',
                'section' => 'Labor',
                'topology' => OnceOffServiceComponent::TOPOLOGY_COMPLEX,
                'phases' => [
                    [
                        'name' => 'Prepare classroom',
                        'description' => 'Prepare classroom',
                        'units' => [
                            [
                                'title' => 'Sweep classroom',
                                'description' => 'Sweep classroom',
                                'quantity' => 2.0,
                                'unit_sell_price' => 100.0,
                                'unit_internal_cost' => 60.0,
                            ],
                        ],
                    ],
                ],
            ]
        );

        $handler->handle($command);
    }
}

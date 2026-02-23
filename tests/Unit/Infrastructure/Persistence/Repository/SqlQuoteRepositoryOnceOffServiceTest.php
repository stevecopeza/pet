<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;

final class SqlQuoteRepositoryOnceOffServiceTest extends TestCase
{
    private $wpdb;
    private $costAdjustmentRepo;
    private $repo;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'pet_';
        $this->costAdjustmentRepo = $this->createMock(CostAdjustmentRepository::class);
        $this->repo = new SqlQuoteRepository($this->wpdb, $this->costAdjustmentRepo);
    }

    public function testSaveSimpleOnceOffServicePersistsUnits(): void
    {
        $this->wpdb->expects($this->any())->method('query');
        $this->wpdb->expects($this->any())->method('prepare')->willReturn('SQL');
        $this->wpdb->expects($this->any())->method('get_results')->willReturn([]);
        $this->wpdb->insert_id = 100;

        $u1 = new SimpleUnit('Sweep classroom', 2.0, 100.0, 60.0);
        $u2 = new SimpleUnit('Wash windows', 1.0, 150.0, 80.0);

        $component = new OnceOffServiceComponent(
            OnceOffServiceComponent::TOPOLOGY_SIMPLE,
            [],
            [$u1, $u2],
            'Classroom quick prep'
        );

        $quote = new Quote(
            1,
            'Title',
            'Desc',
            QuoteState::draft(),
            1,
            0.0,
            0.0,
            'USD',
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [$component],
            [],
            [],
            []
        );

        $this->wpdb->expects($this->atLeast(3))
            ->method('insert')
            ->withConsecutive(
                [
                    'pet_pet_quotes',
                    $this->anything(),
                    $this->anything()
                ],
                [
                    'pet_pet_quote_components',
                    $this->anything(),
                    $this->anything()
                ],
                [
                    'pet_pet_quote_onceoff_units',
                    $this->callback(function ($data) {
                        return isset($data['title'], $data['quantity'], $data['unit_sell_price']);
                    }),
                    $this->anything()
                ]
            );

        $this->repo->save($quote);
    }

    public function testLoadComplexOnceOffServiceHydratesPhasesAndUnits(): void
    {
        $quoteRow = (object)[
            'id' => 1,
            'customer_id' => 1,
            'title' => 'Title',
            'description' => 'Desc',
            'state' => 'draft',
            'version' => 1,
            'total_value' => 0.0,
            'total_internal_cost' => 0.0,
            'currency' => 'USD',
            'accepted_at' => null,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => null,
            'archived_at' => null,
            'malleable_data' => '{}'
        ];

        $this->wpdb->expects($this->any())
            ->method('get_row')
            ->willReturn($quoteRow);

        $componentRow = (object)[
            'id' => 10,
            'type' => 'once_off_service',
            'description' => 'End of term decoration',
            'section' => 'General'
        ];

        $phaseRow = (object)[
            'id' => 20,
            'component_id' => 10,
            'name' => 'Prepare classroom',
            'description' => 'Phase desc'
        ];

        $unitRow = (object)[
            'id' => 30,
            'component_id' => 10,
            'phase_id' => 20,
            'title' => 'Sweep classroom',
            'description' => 'Sweep desc',
            'quantity' => 2.0,
            'unit_sell_price' => 100.0,
            'unit_internal_cost' => 60.0
        ];

        $this->wpdb->expects($this->any())
            ->method('get_results')
            ->willReturnCallback(function ($sql) use ($componentRow, $phaseRow, $unitRow) {
                if (strpos($sql, 'pet_quote_components') !== false) {
                    return [$componentRow];
                }
                if (strpos($sql, 'pet_quote_onceoff_phases') !== false) {
                    return [$phaseRow];
                }
                if (strpos($sql, 'pet_quote_onceoff_units') !== false) {
                    return [$unitRow];
                }
                if (strpos($sql, 'pet_quote_payment_schedule') !== false) {
                    return [];
                }
                return [];
            });

        $this->wpdb->expects($this->any())->method('prepare')->willReturnArgument(0);

        $quote = $this->repo->findById(1);

        $reflection = new \ReflectionClass($quote);
        $prop = $reflection->getProperty('components');
        $prop->setAccessible(true);
        $components = $prop->getValue($quote);

        $this->assertCount(1, $components);
        $this->assertInstanceOf(OnceOffServiceComponent::class, $components[0]);

        $component = $components[0];
        $this->assertSame(OnceOffServiceComponent::TOPOLOGY_COMPLEX, $component->topology());

        $phases = $component->phases();
        $this->assertCount(1, $phases);
        $this->assertInstanceOf(Phase::class, $phases[0]);

        $units = $phases[0]->units();
        $this->assertCount(1, $units);
        $this->assertInstanceOf(SimpleUnit::class, $units[0]);
        $this->assertSame('Sweep classroom', $units[0]->title());
    }
}

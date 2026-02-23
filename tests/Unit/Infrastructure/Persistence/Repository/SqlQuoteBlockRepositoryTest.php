<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteBlockRepository;
use PHPUnit\Framework\TestCase;

class SqlQuoteBlockRepositoryTest extends TestCase
{
    public function testFindByQuoteIdBuildsQueryAndHydratesBlocks(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';

        $wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->stringContains('SELECT * FROM wp_pet_quote_blocks'),
                10
            )
            ->willReturn('SELECT * FROM wp_pet_quote_blocks WHERE quote_id = 10');

        $row = (object) [
            'id' => 5,
            'quote_id' => 10,
            'component_id' => 7,
            'section_id' => 3,
            'type' => QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            'order_index' => 1000,
            'priced' => 1,
            'payload_json' => json_encode(['foo' => 'bar']),
        ];

        $wpdb->expects($this->once())
            ->method('get_results')
            ->with('SELECT * FROM wp_pet_quote_blocks WHERE quote_id = 10')
            ->willReturn([$row]);

        $repo = new SqlQuoteBlockRepository($wpdb);

        $blocks = $repo->findByQuoteId(10);

        $this->assertCount(1, $blocks);
        $block = $blocks[0];
        $this->assertInstanceOf(QuoteBlock::class, $block);
        $this->assertSame(1000, $block->position());
        $this->assertSame(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $block->type());
        $this->assertSame(7, $block->componentId());
        $this->assertSame(3, $block->sectionId());
        $this->assertSame(['foo' => 'bar'], $block->payload());
        $this->assertSame(5, $block->id());
    }

    public function testInsertPersistsBlockAndReturnsWithId(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';

        $wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_quote_blocks',
                $this->arrayHasKey('quote_id'),
                $this->isType('array')
            );

        $wpdb->last_error = '';
        $wpdb->insert_id = 42;

        $repo = new SqlQuoteBlockRepository($wpdb);

        $block = new QuoteBlock(
            1000,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            7,
            0.0,
            0.0,
            true,
            3,
            ['foo' => 'bar']
        );

        $inserted = $repo->insert($block, 10);

        $this->assertSame(42, $inserted->id());
        $this->assertSame($block->position(), $inserted->position());
        $this->assertSame($block->payload(), $inserted->payload());
    }
}


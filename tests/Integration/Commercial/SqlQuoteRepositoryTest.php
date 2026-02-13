<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;

class SqlQuoteRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlQuoteRepository($this->wpdb);
    }

    public function testSaveInsertWithComponents()
    {
        // 1. Setup Data
        $item = new QuoteCatalogItem('Item 1', 2.0, 100.0, 50.0);
        $component = new CatalogComponent([$item], 'Component 1');
        
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft(),
            1,
            200.0,
            100.0,
            'USD',
            null,
            null,
            null,
            null,
            null,
            [$component]
        );

        // Mock get_results to return empty array for deleteComponents call
        $this->wpdb->method('get_results')->willReturn([]);

        // 2. Expect Insert Quote
        $this->wpdb->expects($this->exactly(3)) // 1 quote, 1 component, 1 catalog item
            ->method('insert')
            ->withConsecutive(
                [
                    'wp_pet_quotes',
                    $this->callback(function ($data) {
                        return $data['customer_id'] === 1 && $data['total_value'] === 200.0;
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_quote_components',
                    $this->callback(function ($data) {
                        return $data['type'] === 'catalog' && $data['description'] === 'Component 1';
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_quote_catalog_items',
                    $this->callback(function ($data) {
                        return $data['description'] === 'Item 1';
                    }),
                    $this->anything()
                ]
            );

        // Mock insert_id for quote and component
        $this->wpdb->insert_id = 123; 

        // 3. Execute
        $this->repository->save($quote);
    }

    public function testFindByIdHydratesComponents()
    {
        // 1. Mock Quote Row
        $quoteRow = (object) [
            'id' => '123',
            'customer_id' => '1',
            'state' => 'draft',
            'version' => '1',
            'total_value' => '200.00',
            'total_internal_cost' => '100.00',
            'currency' => 'USD',
            'accepted_at' => null,
            'malleable_data' => null,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => null,
            'archived_at' => null
        ];

        // 2. Mock Component Rows
        $componentRow = (object) [
            'id' => '10',
            'quote_id' => '123',
            'type' => 'catalog',
            'description' => 'Component 1'
        ];

        // 3. Mock Item Rows
        $itemRow = (object) [
            'id' => '5',
            'component_id' => '10',
            'description' => 'Item 1',
            'quantity' => '2.00',
            'unit_sell_price' => '100.00',
            'unit_internal_cost' => '50.00'
        ];

        // 4. Setup Expectations
        $this->wpdb->method('prepare')->willReturnArgument(0); // Simplistic
        
        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($quoteRow);

        $this->wpdb->expects($this->any())
            ->method('get_results')
            ->willReturnCallback(function ($sql) use ($componentRow, $itemRow) {
                if (strpos($sql, 'wp_pet_quote_components') !== false) {
                    return [$componentRow];
                }
                if (strpos($sql, 'wp_pet_quote_catalog_items') !== false) {
                    return [$itemRow];
                }
                return [];
            });

        // 5. Execute
        $quote = $this->repository->findById(123);

        // 6. Assert
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertCount(1, $quote->components());
        $this->assertInstanceOf(CatalogComponent::class, $quote->components()[0]);
        $this->assertEquals('Component 1', $quote->components()[0]->description());
        $this->assertCount(1, $quote->components()[0]->items());
        $this->assertEquals('Item 1', $quote->components()[0]->items()[0]->description());
    }
}

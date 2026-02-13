<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;

class SqlQuoteRepositoryComponentTest extends TestCase
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

    public function testSavePersistsCatalogItemFields()
    {
        // Mock DB transactions
        $this->wpdb->expects($this->any())->method('query');
        $this->wpdb->expects($this->any())->method('prepare')->willReturn('SQL');
        $this->wpdb->expects($this->any())->method('get_results')->willReturn([]);
        $this->wpdb->insert_id = 100;

        // Create Quote with Catalog Component
        $item = new QuoteCatalogItem(
            'Test Item',
            2.0,
            100.0,
            50.0,
            null,
            null,
            [],
            'service',
            'SKU-123',
            5 // role_id
        );
        $component = new CatalogComponent([$item], 'Test Component');
        
        $quote = new Quote(
            1, // customerId
            'Title',
            'Desc',
            QuoteState::draft(),
            1,
            200.0,
            100.0,
            'USD',
            null,
            null, // id
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [$component],
            [],
            [],
            [] // Payment schedule required by validation but maybe not for save? 
               // SqlQuoteRepository::save calls internal methods.
               // Let's add empty payment schedule to be safe if validation runs.
               // But validation runs in Quote::validateReadiness, not save.
        );

        // We expect wpdb->insert to be called 3 times:
        // 1. Quote
        // 2. Component
        // 3. Catalog Item
        
        $this->wpdb->expects($this->exactly(3))
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
                    'pet_pet_quote_catalog_items',
                    $this->callback(function($data) {
                        return $data['sku'] === 'SKU-123' && 
                               $data['role_id'] === 5 && 
                               $data['type'] === 'service' &&
                               $data['description'] === 'Test Item';
                    }),
                    $this->anything()
                ]
             );

        $this->repo->save($quote);
    }

    public function testLoadHydratesCatalogItemFields()
    {
        // Mock get_row for quote
        $quoteRow = (object)[
            'id' => 1,
            'customer_id' => 1,
            'title' => 'Title',
            'description' => 'Desc',
            'state' => 'draft',
            'version' => 1,
            'total_value' => 200.0,
            'total_internal_cost' => 100.0,
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

        // Mock loadComponents -> loadCatalogItems
        // 1. Get components
        $componentRow = (object)[
            'id' => 10,
            'type' => 'catalog',
            'description' => 'Test Component',
            'section' => 'General'
        ];
        
        // 2. Get catalog items
        $itemRow = (object)[
            'id' => 20,
            'component_id' => 10,
            'description' => 'Test Item',
            'quantity' => 2.0,
            'unit_sell_price' => 100.0,
            'unit_internal_cost' => 50.0,
            'catalog_item_id' => null,
            'wbs_snapshot' => null,
            'type' => 'service',
            'sku' => 'SKU-123',
            'role_id' => 5
        ];

        // We need to carefully mock get_results which is called multiple times
        // 1. loadComponents
        // 2. loadCatalogItems
        // 3. loadPaymentSchedule
        
        $this->wpdb->expects($this->any())
             ->method('get_results')
             ->willReturnCallback(function($sql) use ($componentRow, $itemRow) {
                 if (strpos($sql, 'pet_quote_components') !== false) {
                     return [$componentRow];
                 }
                 if (strpos($sql, 'pet_quote_catalog_items') !== false) {
                     return [$itemRow];
                 }
                 if (strpos($sql, 'pet_quote_payment_schedule') !== false) {
                     return [];
                 }
                 return [];
             });
             
        $this->wpdb->expects($this->any())->method('prepare')->willReturnArgument(0);

        $quote = $this->repo->findById(1);

        $this->assertNotNull($quote);
        $components = $quote->components(); // Quote doesn't expose components public getter directly?
        // Wait, Quote doesn't have public components() getter?
        // Let's check Quote.php
        
        // Reflection to get components
        $reflection = new \ReflectionClass($quote);
        $prop = $reflection->getProperty('components');
        $prop->setAccessible(true);
        $components = $prop->getValue($quote);
        
        $this->assertCount(1, $components);
        $this->assertInstanceOf(CatalogComponent::class, $components[0]);
        
        $items = $components[0]->items();
        $this->assertCount(1, $items);
        $item = $items[0];
        
        $this->assertEquals('SKU-123', $item->sku());
        $this->assertEquals(5, $item->roleId());
        $this->assertEquals('service', $item->type());
    }
}

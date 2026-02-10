<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Commercial\Entity\Quote;
    use Pet\Domain\Commercial\Entity\QuoteLine;
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

        public function testSaveInsertsNewQuote()
        {
            $quote = new Quote(1, QuoteState::draft());
            $quote->addLine(new QuoteLine('Item 1', 1.0, 100.0, 'product'));

            $this->wpdb->expects($this->exactly(2))
                ->method('insert')
                ->withConsecutive(
                    [
                        'wp_pet_quotes',
                        $this->callback(function ($data) {
                            return $data['customer_id'] === 1
                                && $data['state'] === 'draft'
                                && $data['version'] === 1;
                        }),
                        $this->anything()
                    ],
                    [
                        'wp_pet_quote_lines',
                        $this->callback(function ($data) {
                            return $data['description'] === 'Item 1'
                                && $data['quantity'] === 1.0
                                && $data['unit_price'] === 100.0;
                        }),
                        $this->anything()
                    ]
                );
            
            // Mock insert_id for the quote
            $this->wpdb->insert_id = 123;
                
            $this->repository->save($quote);
        }

        public function testFindByIdReturnsHydratedQuote()
        {
            $row = (object) [
                'id' => '123',
                'customer_id' => '1',
                'state' => 'draft',
                'version' => '1',
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => null,
                'archived_at' => null,
            ];

            $lineRow = (object) [
                'id' => '1',
                'quote_id' => '123',
                'description' => 'Item 1',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'line_group_type' => 'product',
            ];
            
            // Re-setup mock for this test
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repository = new SqlQuoteRepository($this->wpdb);
            
            $this->wpdb->method('prepare')->willReturn('SQL');
            $this->wpdb->method('get_row')->willReturn($row);
            $this->wpdb->method('get_results')->willReturn([$lineRow]);

            $quote = $this->repository->findById(123);

            $this->assertInstanceOf(Quote::class, $quote);
            $this->assertEquals(123, $quote->id());
            $this->assertEquals(1, $quote->customerId());
            $this->assertCount(1, $quote->lines());
            $this->assertEquals('Item 1', $quote->lines()[0]->description());
        }
    }
}

namespace {
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!class_exists('wpdb')) {
        class wpdb {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public function prepare($query, ...$args) { return $query; }
            public function insert($table, $data, $format = null) { return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function get_row($query, $output = OBJECT, $y = 0) { return null; }
            public function get_results($query, $output = OBJECT) { return []; }
        }
    }
}

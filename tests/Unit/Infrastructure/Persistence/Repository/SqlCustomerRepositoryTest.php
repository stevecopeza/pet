<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Identity\Entity\Customer;
    use Pet\Infrastructure\Persistence\Repository\SqlCustomerRepository;

    class SqlCustomerRepositoryTest extends TestCase
    {
        private $wpdb;
        private $repository;

        protected function setUp(): void
        {
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repository = new SqlCustomerRepository($this->wpdb);
        }

        public function testSaveInsertsNewCustomer()
        {
            $customer = new Customer('Acme Corp', 'contact@acme.com');

            $this->wpdb->expects($this->once())
                ->method('insert')
                ->with(
                    'wp_pet_customers',
                    $this->callback(function ($data) {
                        return $data['name'] === 'Acme Corp'
                            && $data['contact_email'] === 'contact@acme.com';
                    })
                );

            $this->repository->save($customer);
        }

        public function testFindByIdReturnsHydratedCustomer()
        {
            $row = (object) [
                'id' => '1',
                'name' => 'Acme Corp',
                'contact_email' => 'contact@acme.com',
                'created_at' => '2023-01-01 00:00:00',
                'archived_at' => null,
            ];

            $this->wpdb->method('prepare')->willReturn('SQL');
            $this->wpdb->method('get_row')->willReturn($row);

            $customer = $this->repository->findById(1);

            $this->assertInstanceOf(Customer::class, $customer);
            $this->assertEquals(1, $customer->id());
            $this->assertEquals('Acme Corp', $customer->name());
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

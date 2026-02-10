<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Identity\Entity\Employee;
    use Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository;

    class SqlEmployeeRepositoryTest extends TestCase
    {
        private $wpdb;
        private $repository;

        protected function setUp(): void
        {
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repository = new SqlEmployeeRepository($this->wpdb);
        }

        public function testSaveInsertsNewEmployee()
        {
            $employee = new Employee(1, 'John', 'Doe', 'john@example.com');

            $this->wpdb->expects($this->once())
                ->method('insert')
                ->with(
                    'wp_pet_employees',
                    $this->callback(function ($data) {
                        return $data['wp_user_id'] === 1
                            && $data['first_name'] === 'John'
                            && $data['last_name'] === 'Doe'
                            && $data['email'] === 'john@example.com';
                    })
                );

            $this->repository->save($employee);
        }

        public function testFindByIdReturnsHydratedEmployee()
        {
            $row = (object) [
                'id' => '1',
                'wp_user_id' => '10',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'created_at' => '2023-01-01 00:00:00',
                'archived_at' => null,
            ];

            $this->wpdb->method('prepare')->willReturn('SQL');
            $this->wpdb->method('get_row')->willReturn($row);

            $employee = $this->repository->findById(1);

            $this->assertInstanceOf(Employee::class, $employee);
            $this->assertEquals(1, $employee->id());
            $this->assertEquals(10, $employee->wpUserId());
            $this->assertEquals('Jane', $employee->firstName());
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

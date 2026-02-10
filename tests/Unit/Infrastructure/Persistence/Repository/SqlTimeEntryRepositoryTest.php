<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Time\Entity\TimeEntry;
    use Pet\Infrastructure\Persistence\Repository\SqlTimeEntryRepository;

    class SqlTimeEntryRepositoryTest extends TestCase
    {
        private $wpdb;
        private $repository;

        protected function setUp(): void
        {
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repository = new SqlTimeEntryRepository($this->wpdb);
        }

        public function testSaveInsertsNewTimeEntry()
        {
            $start = new \DateTimeImmutable('2023-01-01 10:00:00');
            $end = new \DateTimeImmutable('2023-01-01 12:00:00');
            $entry = new TimeEntry(1, 10, $start, $end, true, 'Work');

            $this->wpdb->expects($this->once())
                ->method('insert')
                ->with(
                    'wp_pet_time_entries',
                    $this->callback(function ($data) {
                        return $data['employee_id'] === 1
                            && $data['task_id'] === 10
                            && $data['duration_minutes'] === 120
                            && $data['is_billable'] === 1;
                    })
                );

            $this->repository->save($entry);
        }

        public function testFindByIdReturnsHydratedEntry()
        {
            $row = (object) [
                'id' => '1',
                'employee_id' => '1',
                'task_id' => '10',
                'start_time' => '2023-01-01 10:00:00',
                'end_time' => '2023-01-01 12:00:00',
                'duration_minutes' => '120',
                'is_billable' => '1',
                'description' => 'Work',
                'status' => 'draft',
            ];

            $this->wpdb->method('prepare')->willReturn('SQL');
            $this->wpdb->method('get_row')->willReturn($row);

            $entry = $this->repository->findById(1);

            $this->assertInstanceOf(TimeEntry::class, $entry);
            $this->assertEquals(1, $entry->id());
            $this->assertEquals(120, $entry->durationMinutes());
            $this->assertEquals('Work', $entry->description());
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

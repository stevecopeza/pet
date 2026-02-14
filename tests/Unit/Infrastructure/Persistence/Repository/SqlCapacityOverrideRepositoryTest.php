<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Infrastructure\Persistence\Repository\SqlCapacityOverrideRepository;

    class SqlCapacityOverrideRepositoryTest extends TestCase
    {
        private $wpdb;
        private $repo;

        protected function setUp(): void
        {
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repo = new SqlCapacityOverrideRepository($this->wpdb);
        }

        public function testSetOverrideInsertWhenNotExists(): void
        {
            $date = new \DateTimeImmutable('2025-02-01');
            $this->wpdb->method('get_var')->willReturn(null);
            $this->wpdb->expects($this->once())->method('insert');
            $this->repo->setOverride(10, $date, 80, 'reason');
        }

        public function testSetOverrideUpdateWhenExists(): void
        {
            $date = new \DateTimeImmutable('2025-02-01');
            $this->wpdb->method('get_var')->willReturn('1');
            $this->wpdb->expects($this->once())->method('update');
            $this->repo->setOverride(10, $date, 75, 'revised');
        }

        public function testFindForDateHydratesEntity(): void
        {
            $row = [
                'id' => 3,
                'employee_id' => 10,
                'effective_date' => '2025-02-01',
                'capacity_pct' => 60,
                'reason' => 'leave',
                'created_at' => '2025-01-31 10:00:00',
            ];
            $this->wpdb->method('get_row')->willReturn($row);
            $override = $this->repo->findForDate(10, new \DateTimeImmutable('2025-02-01'));
            $this->assertNotNull($override);
            $this->assertEquals(60, $override->capacityPct());
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
            public $insert_id = 1;
            public function prepare($query, ...$args) { return $query; }
            public function insert($table, $data, $format = null) { return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function get_row($query, $output = OBJECT, $y = 0) { return null; }
            public function get_results($query, $output = OBJECT) { return []; }
            public function get_var($query) { return null; }
        }
    }
}

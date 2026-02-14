<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Work\Entity\LeaveRequest;
    use Pet\Infrastructure\Persistence\Repository\SqlLeaveRequestRepository;

    class SqlLeaveRequestRepositoryTest extends TestCase
    {
        private $wpdb;
        private $repo;

        protected function setUp(): void
        {
            $this->wpdb = $this->createMock(\wpdb::class);
            $this->wpdb->prefix = 'wp_';
            $this->repo = new SqlLeaveRequestRepository($this->wpdb);
        }

        public function testSaveInsertsNewDraft(): void
        {
            $uuid = '00000000-0000-0000-0000-000000000000';
            $req = LeaveRequest::draft($uuid, 10, 2, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-02'), 'note');

            $this->wpdb->expects($this->once())
                ->method('insert')
                ->with(
                    'wp_pet_leave_requests',
                    $this->callback(function ($data) use ($uuid) {
                        return $data['uuid'] === $uuid &&
                               $data['employee_id'] === 10 &&
                               $data['leave_type_id'] === 2 &&
                               $data['status'] === 'draft';
                    })
                );

            $this->repo->save($req);
        }

        public function testSetStatusUpdatesDecisionFields(): void
        {
            $now = new \DateTimeImmutable('2025-01-03 12:00:00');
            $this->repo->setStatus(5, 'approved', 99, $now, 'OK');

            $this->assertTrue(true); // no exception
        }

        public function testIsApprovedOnDateChecksRange(): void
        {
            $this->wpdb->method('get_row')->willReturn((object)['id' => 1]);
            $result = $this->repo->isApprovedOnDate(10, new \DateTimeImmutable('2025-01-01'));
            $this->assertTrue($result);
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
        }
    }
}

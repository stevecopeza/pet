<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use PHPUnit\Framework\TestCase;
use Pet\Application\System\Service\DemoPreFlightCheck;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Domain\Support\Repository\SlaClockStateRepository;

class PreFlightLeaveCapacitySchemaTest extends TestCase
{
    private $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    public function testLeaveCapacitySchemaPass(): void
    {
        $this->wpdb->method('get_var')->willReturnCallback(function ($sql) {
            if (strpos($sql, "SHOW TABLES LIKE 'wp_pet_leave_types'") !== false) {
                return 'wp_pet_leave_types';
            }
            if (strpos($sql, "SHOW TABLES LIKE 'wp_pet_leave_requests'") !== false) {
                return 'wp_pet_leave_requests';
            }
            if (strpos($sql, "SHOW TABLES LIKE 'wp_pet_capacity_overrides'") !== false) {
                return 'wp_pet_capacity_overrides';
            }
            return null;
        });

        $this->wpdb->method('get_col')->willReturnCallback(function ($sql, $column = 0) {
            if (strpos($sql, 'DESCRIBE wp_pet_leave_requests') !== false) {
                return [
                    'id','uuid','employee_id','leave_type_id','start_date','end_date','status',
                    'submitted_at','decided_by_employee_id','decided_at','decision_reason','notes',
                    'created_at','updated_at'
                ];
            }
            if (strpos($sql, 'DESCRIBE wp_pet_capacity_overrides') !== false) {
                return ['id','employee_id','effective_date','capacity_pct','reason','created_at'];
            }
            return [];
        });

        $eventBus = new InMemoryEventBus();
        $slaRepo = $this->createMock(SlaClockStateRepository::class);
        $preFlight = new DemoPreFlightCheck($eventBus, $slaRepo);

        $result = $preFlight->run();
        $this->assertEquals('PASS', $result['leave_capacity']);
    }

    public function testLeaveCapacitySchemaFailOnMissingColumn(): void
    {
        $this->wpdb->method('get_var')->willReturnCallback(function ($sql) {
            return 'wp_pet_leave_types'; // simulate only leave_types present for simplicity
        });
        $this->wpdb->method('get_col')->willReturn([]);

        $eventBus = new InMemoryEventBus();
        $slaRepo = $this->createMock(SlaClockStateRepository::class);
        $preFlight = new DemoPreFlightCheck($eventBus, $slaRepo);

        $result = $preFlight->run();
        $this->assertEquals('FAIL', $result['leave_capacity']);
        $this->assertEquals('FAIL', $result['overall']);
    }
}

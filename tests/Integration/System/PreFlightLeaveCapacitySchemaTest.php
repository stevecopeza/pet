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
    private $originalWpdb;

    protected function setUp(): void
    {
        if (isset($GLOBALS['wpdb'])) {
            $this->originalWpdb = $GLOBALS['wpdb'];
        }
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->method('get_charset_collate')->willReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->wpdb->method('tables')->willReturn([]);
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        if (isset($this->originalWpdb)) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
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
        $leaveCheck = null;
        foreach ($result['checks'] as $check) {
            if (($check['key'] ?? '') === 'db.leave_schema') {
                $leaveCheck = $check;
                break;
            }
        }
        $this->assertNotNull($leaveCheck);
        $this->assertEquals('PASS', $leaveCheck['status']);
    }

    public function testLeaveCapacitySchemaFailOnMissingColumn(): void
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
                    // Intentionally omit created_at to simulate missing column
                    'updated_at'
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
        $schemaCheck = null;
        foreach ($result['checks'] as $check) {
            if (in_array($check['key'] ?? '', [
                'db.columns_present.leave_requests',
                'db.columns_present.capacity_overrides',
            ], true)) {
                $schemaCheck = $check;
                break;
            }
        }
        $this->assertNotNull($schemaCheck);
        $this->assertEquals('FAIL', $schemaCheck['status']);
        $this->assertEquals('FAIL', $result['overall']);
    }
}

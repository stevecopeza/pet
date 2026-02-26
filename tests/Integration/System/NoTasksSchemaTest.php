<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use PHPUnit\Framework\TestCase;
use wpdb;

final class NoTasksSchemaTest extends TestCase
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

    public function testTasksTableAndLegacyColumnsDoNotExist(): void
    {
        global $wpdb;
        $this->assertInstanceOf(wpdb::class, $wpdb);

        $tasksTable = $wpdb->prefix . 'pet_tasks';
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tasksTable));
        $this->assertNull($result, 'wp_pet_tasks table must not exist');

        $timeEntries = $wpdb->prefix . 'pet_time_entries';
        $taskColumn = $wpdb->get_var("SHOW COLUMNS FROM $timeEntries LIKE 'task_id'");
        $this->assertNull($taskColumn, 'task_id column must not exist on wp_pet_time_entries');

        $workItems = $wpdb->prefix . 'pet_work_items';
        $row = $wpdb->get_row("SHOW COLUMNS FROM $workItems LIKE 'source_type'");
        if ($row !== null && isset($row->Type)) {
            $this->assertStringNotContainsString(
                'project_task',
                (string)$row->Type,
                'source_type enum must not contain project_task'
            );
        }
    }
}

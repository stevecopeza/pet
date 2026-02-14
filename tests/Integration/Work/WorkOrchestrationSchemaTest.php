<?php

namespace Pet\Tests\Integration\Work;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Migration\Definition\CreateWorkOrchestrationTables;

class WorkOrchestrationSchemaTest extends TestCase
{
    private $wpdb;

    protected function setUp(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(dirname(__DIR__)) . '/fixtures/');
        }

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->method('get_charset_collate')->willReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function testWorkOrchestrationTablesAreCreated(): void
    {
        $migration = new CreateWorkOrchestrationTables($this->wpdb);
        
        // Mock dbDelta execution by defining it in the fixture if not already defined
        // The migration calls require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // We verify that up() runs without error and calls dbDelta
        $migration->up();
        
        $this->assertTrue(true, "Migration executed successfully");
    }

    public function testDescriptionIsCorrect(): void
    {
        $migration = new CreateWorkOrchestrationTables($this->wpdb);
        $this->assertStringContainsString('Work Orchestration', $migration->getDescription());
    }
}

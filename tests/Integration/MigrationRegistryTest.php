<?php

namespace Pet\Tests\Integration;

use Pet\Infrastructure\Persistence\Migration\Migration;
use Pet\Infrastructure\Persistence\Migration\MigrationRegistry;
use Pet\Infrastructure\Persistence\Migration\MigrationRunner;
use Pet\Tests\Stubs\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

class MockMigration1 implements Migration {
    public static $runCount = 0;
    public function __construct($wpdb) {}
    public function up(): void { self::$runCount++; }
    public function getDescription(): string { return '1'; }
}

class MockMigration2 implements Migration {
    public static $runCount = 0;
    public function __construct($wpdb) {}
    public function up(): void { self::$runCount++; }
    public function getDescription(): string { return '2'; }
}

class MigrationRegistryTest extends TestCase
{
    private $wpdb;
    private $runner;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        $this->runner = new MigrationRunner($this->wpdb);
        
        // Reset mock counters
        MockMigration1::$runCount = 0;
        MockMigration2::$runCount = 0;
    }

    public function testMigrationsRunInOrder(): void
    {
        $migrations = [
            MockMigration1::class,
            MockMigration2::class,
        ];

        $this->runner->run($migrations);

        $this->assertEquals(1, MockMigration1::$runCount);
        $this->assertEquals(1, MockMigration2::$runCount);
        
        // Verify recorded in db
        $applied = $this->wpdb->get_col("SELECT migration_class FROM wp_pet_migrations");
        $this->assertEquals([MockMigration1::class, MockMigration2::class], $applied);
    }

    public function testMigrationsAreIdempotent(): void
    {
        $migrations = [
            MockMigration1::class,
        ];

        // First run
        $this->runner->run($migrations);
        $this->assertEquals(1, MockMigration1::$runCount);

        // Second run
        $this->runner->run($migrations);
        $this->assertEquals(1, MockMigration1::$runCount, 'Migration should not run twice');
    }

    public function testRegistryReturnsArrayOfStrings(): void
    {
        $all = MigrationRegistry::all();
        $this->assertIsArray($all);
        foreach ($all as $class) {
            $this->assertIsString($class);
            $this->assertTrue(class_exists($class) || interface_exists($class), "Class $class should exist");
        }
    }
    
    public function testNewMigrationsRunWhileOldSkipped(): void
    {
        // 1. Run Migration 1
        $this->runner->run([MockMigration1::class]);
        $this->assertEquals(1, MockMigration1::$runCount);
        
        // 2. Run Migration 1 and 2
        $this->runner->run([MockMigration1::class, MockMigration2::class]);
        
        $this->assertEquals(1, MockMigration1::$runCount, 'Migration 1 should not run again');
        $this->assertEquals(1, MockMigration2::$runCount, 'Migration 2 should run once');
        
        $applied = $this->wpdb->get_col("SELECT migration_class FROM wp_pet_migrations");
        $this->assertCount(2, $applied);
        $this->assertContains(MockMigration1::class, $applied);
        $this->assertContains(MockMigration2::class, $applied);
    }
}

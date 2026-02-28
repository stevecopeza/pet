<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Migration;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Migration\MigrationRegistry;
use Pet\Infrastructure\Persistence\Migration\Migration;

class MigrationRegistryTest extends TestCase
{
    public function testAllMigrationsAreValid()
    {
        $migrations = MigrationRegistry::all();

        $this->assertIsArray($migrations);
        $this->assertNotEmpty($migrations);

        $errors = [];
        foreach ($migrations as $migrationClass) {
            if (!class_exists($migrationClass)) {
                $errors[] = "Migration class $migrationClass does not exist";
                continue;
            }
            if (!is_subclass_of($migrationClass, Migration::class)) {
                $errors[] = "Class $migrationClass must implement Migration interface";
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    public function testNoDuplicatesInRegistry()
    {
        $migrations = MigrationRegistry::all();
        $uniqueMigrations = array_unique($migrations);

        $this->assertEquals(count($migrations), count($uniqueMigrations), "MigrationRegistry contains duplicate entries");
    }

    public function testCriticalMigrationsArePresent()
    {
        $migrations = MigrationRegistry::all();
        
        $criticalMigrations = [
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateIdentityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSupportTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketSlaFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteSectionsTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateQuoteBlocksAddPayloadAndCreatedAt::class,
        ];

        foreach ($criticalMigrations as $required) {
            $this->assertContains($required, $migrations, "MigrationRegistry is missing required migration: $required");
        }
    }

    public function testOrderConstraints()
    {
        $migrations = MigrationRegistry::all();
        $list = array_values($migrations);

        // AddTicketSlaFields must come after CreateSupportTables (which creates pet_tickets)
        $createSupportIdx = array_search(\Pet\Infrastructure\Persistence\Migration\Definition\CreateSupportTables::class, $list);
        $addSlaFieldsIdx = array_search(\Pet\Infrastructure\Persistence\Migration\Definition\AddTicketSlaFields::class, $list);

        $this->assertNotFalse($createSupportIdx, 'CreateSupportTables not found');
        $this->assertNotFalse($addSlaFieldsIdx, 'AddTicketSlaFields not found');
        $this->assertGreaterThan($createSupportIdx, $addSlaFieldsIdx, 'AddTicketSlaFields must run after CreateSupportTables');

        // CreateQuoteSectionsTables must come after CreateQuoteComponentTables (if they were related, but let's check basic sanity)
        // AddSectionToQuoteComponents must come after CreateQuoteComponentTables
        $createQuoteCompIdx = array_search(\Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteComponentTables::class, $list);
        $addSectionIdx = array_search(\Pet\Infrastructure\Persistence\Migration\Definition\AddSectionToQuoteComponents::class, $list);

        $this->assertNotFalse($createQuoteCompIdx, 'CreateQuoteComponentTables not found');
        $this->assertNotFalse($addSectionIdx, 'AddSectionToQuoteComponents not found');
        $this->assertGreaterThan($createQuoteCompIdx, $addSectionIdx, 'AddSectionToQuoteComponents must run after CreateQuoteComponentTables');
    }
}

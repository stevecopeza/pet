<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ManifestUsageTest extends TestCase
{
    public function testPetPhpReferencesMigrationManifestOnly(): void
    {
        $path = dirname(__DIR__, 3) . '/pet.php';
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'MigrationManifest::getAll()',
            $contents,
            'pet.php must call MigrationManifest::getAll()'
        );

        $this->assertStringNotContainsString(
            'MigrationRegistry::all(',
            $contents,
            'pet.php must not reference MigrationRegistry::all()'
        );
    }
}

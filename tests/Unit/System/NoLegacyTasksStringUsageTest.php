<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\System;

use PHPUnit\Framework\TestCase;

final class NoLegacyTasksStringUsageTest extends TestCase
{
    /**
     * @dataProvider forbiddenProvider
     */
    public function testNoForbiddenStringsInSource(string $label, string $needle): void
    {
        $root = dirname(__DIR__, 3);
        $srcDir = $root . '/src';
        $migrationDir = $root . '/src/Infrastructure/Persistence/Migration/Definition';

        $files = $this->phpFilesIn([$srcDir, $migrationDir]);

        $allowedDocs = [
            $root . '/docs',
        ];

        foreach ($files as $file) {
            foreach ($allowedDocs as $allowed) {
                if (str_starts_with($file, $allowed)) {
                    continue 2;
                }
            }

            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                $needle,
                $contents,
                sprintf('Forbidden string "%s" found in %s (%s)', $needle, $file, $label)
            );
        }
    }

    public function forbiddenProvider(): array
    {
        return [
            ['project_task', 'project_task'],
            ['task_id', 'task_id'],
            ['wp_pet_tasks', 'wp_pet_tasks'],
        ];
    }

    private function phpFilesIn(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $file->getRealPath();
            }
        }
        return $files;
    }
}


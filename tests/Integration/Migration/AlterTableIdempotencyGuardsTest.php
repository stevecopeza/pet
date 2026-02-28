<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlterTableIdempotencyGuardsTest extends TestCase
{
    public function testAddTitleDescriptionToQuotesHasGuards(): void
    {
        $path = dirname(__DIR__, 3) . '/src/Infrastructure/Persistence/Migration/Definition/AddTitleDescriptionToQuotes.php';
        $this->assertFileExists($path);
        $c = file_get_contents($path);
        $this->assertStringContainsString("SHOW COLUMNS FROM \$table_name LIKE 'title'", $c);
        $this->assertStringContainsString("SHOW COLUMNS FROM \$table_name LIKE 'description'", $c);
    }

    public function testAddTypeToCatalogItemsHasGuards(): void
    {
        $path = dirname(__DIR__, 3) . '/src/Infrastructure/Persistence/Migration/Definition/AddTypeToCatalogItems.php';
        $this->assertFileExists($path);
        $c = file_get_contents($path);
        $this->assertStringContainsString("SHOW COLUMNS FROM \$table_name LIKE 'type'", $c);
        $this->assertStringContainsString("SHOW INDEX FROM \$table_name WHERE Key_name = %s", $c);
    }

    public function testAddWbsTemplateToCatalogItemsHasGuards(): void
    {
        $path = dirname(__DIR__, 3) . '/src/Infrastructure/Persistence/Migration/Definition/AddWbsTemplateToCatalogItems.php';
        $this->assertFileExists($path);
        $c = file_get_contents($path);
        $this->assertStringContainsString("SHOW COLUMNS FROM \$table_name LIKE 'wbs_template'", $c);
    }

    public function testCreateQuoteComponentTablesGuardsOnTotalInternalCost(): void
    {
        $path = dirname(__DIR__, 3) . '/src/Infrastructure/Persistence/Migration/Definition/CreateQuoteComponentTables.php';
        $this->assertFileExists($path);
        $c = file_get_contents($path);
        $this->assertStringContainsString("SHOW COLUMNS FROM \$quotesTable LIKE 'total_internal_cost'", $c);
    }
}

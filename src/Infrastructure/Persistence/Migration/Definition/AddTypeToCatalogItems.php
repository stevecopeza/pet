<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTypeToCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_catalog_items';
        
        // Add type column with default 'product'
        $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'product' AFTER sku");
        
        // Add index on type for faster filtering
        $this->wpdb->query("ALTER TABLE $table_name ADD INDEX type (type)");
    }

    public function getDescription(): string
    {
        return 'Add type column to catalog items table';
    }
}

<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddWbsTemplateToCatalogItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_catalog_items';
        
        // Add wbs_template column (JSON)
        // Using TEXT for compatibility, but JSON type if supported by MySQL version would be better.
        // Assuming standard WP environment, TEXT/LONGTEXT is safer.
        $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN wbs_template LONGTEXT DEFAULT NULL AFTER type");
    }

    public function getDescription(): string
    {
        return 'Add wbs_template column to catalog items table';
    }
}

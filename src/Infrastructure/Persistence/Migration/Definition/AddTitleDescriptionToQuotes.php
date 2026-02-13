<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddTitleDescriptionToQuotes implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table_name = $this->wpdb->prefix . 'pet_quotes';
        
        // Add title column
        $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_id");
        
        // Add description column
        $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN description TEXT NULL AFTER title");
    }

    public function getDescription(): string
    {
        return 'Add title and description columns to quotes table';
    }
}

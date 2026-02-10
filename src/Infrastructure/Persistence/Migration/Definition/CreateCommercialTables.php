<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCommercialTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Quotes Table
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';
        $sqlQuotes = "CREATE TABLE IF NOT EXISTS $quotesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            state varchar(20) NOT NULL,
            version int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY state (state)
        ) $charsetCollate;";

        // Quote Lines Table
        $quoteLinesTable = $this->wpdb->prefix . 'pet_quote_lines';
        $sqlQuoteLines = "CREATE TABLE IF NOT EXISTS $quoteLinesTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) UNSIGNED NOT NULL,
            description text NOT NULL,
            quantity decimal(10, 2) NOT NULL,
            unit_price decimal(10, 2) NOT NULL,
            line_group_type varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY quote_id (quote_id)
        ) $charsetCollate;";
        
        // Quote Versions Table (Immutable history)
        $quoteVersionsTable = $this->wpdb->prefix . 'pet_quote_versions';
        $sqlQuoteVersions = "CREATE TABLE IF NOT EXISTS $quoteVersionsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) UNSIGNED NOT NULL,
            version int(11) NOT NULL,
            payload longtext NOT NULL, -- JSON snapshot of the quote at this version
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            created_by_employee_id bigint(20) UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY quote_id_version (quote_id, version)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlQuotes);
        dbDelta($sqlQuoteLines);
        dbDelta($sqlQuoteVersions);
    }

    public function getDescription(): string
    {
        return 'Create commercial tables: quotes, quote_lines, and quote_versions.';
    }
}

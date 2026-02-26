<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Transaction;

class SqlTransaction
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function begin(): void
    {
        $this->wpdb->query('START TRANSACTION');
    }

    public function commit(): void
    {
        $this->wpdb->query('COMMIT');
    }

    public function rollback(): void
    {
        $this->wpdb->query('ROLLBACK');
    }
}

<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

final class SqlQbInvoiceRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function recordInvoiceSnapshot(int $customerId, array $payload): void
    {
        $table = $this->wpdb->prefix . 'pet_qb_invoices';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $qbInvoiceId = $this->generateId();
        $currency = isset($payload['currency']) ? (string)$payload['currency'] : 'ZAR';
        $total = isset($payload['total_amount']) ? (float)$payload['total_amount'] : 0.0;
        $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->wpdb->insert($table, [
            'customer_id' => $customerId,
            'qb_invoice_id' => $qbInvoiceId,
            'doc_number' => null,
            'status' => 'Open',
            'issue_date' => (new \DateTimeImmutable())->format('Y-m-d'),
            'due_date' => null,
            'currency' => $currency,
            'total' => round($total, 2),
            'balance' => round($total, 2),
            'raw_json' => $rawJson,
            'last_synced_at' => $now,
        ]);
    }

    private function generateId(): string
    {
        $hex = function ($len) {
            $str = '';
            for ($i = 0; $i < $len; $i++) {
                $str .= dechex(random_int(0, 15));
            }
            return $str;
        };
        return 'QB-' . $hex(8) . '-' . $hex(4) . '-' . $hex(4);
    }
}


<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteLine;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

class SqlQuoteRepository implements QuoteRepository
{
    private $wpdb;
    private $quotesTable;
    private $quoteLinesTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->quotesTable = $wpdb->prefix . 'pet_quotes';
        $this->quoteLinesTable = $wpdb->prefix . 'pet_quote_lines';
    }

    public function save(Quote $quote): void
    {
        $data = [
            'customer_id' => $quote->customerId(),
            'state' => $quote->state()->toString(),
            'version' => $quote->version(),
            'created_at' => $this->formatDate($quote->createdAt()),
            'updated_at' => $this->formatDate($quote->updatedAt()),
            'archived_at' => $this->formatDate($quote->archivedAt()),
        ];

        $format = ['%d', '%s', '%d', '%s', '%s', '%s'];

        if ($quote->id()) {
            $this->wpdb->update(
                $this->quotesTable,
                $data,
                ['id' => $quote->id()],
                $format,
                ['%d']
            );
            $quoteId = $quote->id();
        } else {
            $this->wpdb->insert(
                $this->quotesTable,
                $data,
                $format
            );
            $quoteId = $this->wpdb->insert_id;
        }

        if ($quoteId) {
            $this->saveLines((int)$quoteId, $quote->lines());
        }
    }

    public function findById(int $id): ?Quote
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable} WHERE customer_id = %d ORDER BY created_at DESC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->quotesTable} ORDER BY created_at DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function countPending(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->quotesTable} WHERE state = %s",
            QuoteState::DRAFT
        );
        
        return (int) $this->wpdb->get_var($sql);
    }

    public function sumRevenue(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        // Sum total of lines for Accepted quotes updated within the range
        $sql = $this->wpdb->prepare(
            "SELECT SUM(l.total) 
             FROM {$this->quotesTable} q
             JOIN {$this->quoteLinesTable} l ON q.id = l.quote_id
             WHERE q.state = %s
             AND q.updated_at >= %s
             AND q.updated_at <= %s",
            QuoteState::ACCEPTED,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        return (float) $this->wpdb->get_var($sql);
    }

    private function saveLines(int $quoteId, array $lines): void
    {
        foreach ($lines as $line) {
            $data = [
                'quote_id' => $quoteId,
                'description' => $line->description(),
                'quantity' => $line->quantity(),
                'unit_price' => $line->unitPrice(),
                'line_group_type' => $line->lineGroupType(),
            ];
            $format = ['%d', '%s', '%f', '%f', '%s'];

            if ($line->id()) {
                $this->wpdb->update(
                    $this->quoteLinesTable,
                    $data,
                    ['id' => $line->id()],
                    $format,
                    ['%d']
                );
            } else {
                $this->wpdb->insert(
                    $this->quoteLinesTable,
                    $data,
                    $format
                );
            }
        }
    }

    private function hydrate(object $row): Quote
    {
        $lines = $this->findLinesByQuoteId((int)$row->id);

        return new Quote(
            (int)$row->customer_id,
            QuoteState::fromString($row->state),
            (int)$row->version,
            (int)$row->id,
            $row->created_at ? new \DateTimeImmutable($row->created_at) : null,
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null,
            $lines
        );
    }

    private function findLinesByQuoteId(int $quoteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quoteLinesTable} WHERE quote_id = %d",
            $quoteId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map(function ($row) {
            return new QuoteLine(
                $row->description,
                (float)$row->quantity,
                (float)$row->unit_price,
                $row->line_group_type,
                (int)$row->id
            );
        }, $results);
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}

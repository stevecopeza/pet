<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;

class SqlTicketRepository implements TicketRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Ticket $ticket): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        
        $data = [
            'customer_id' => $ticket->customerId(),
            'subject' => $ticket->subject(),
            'description' => $ticket->description(),
            'status' => $ticket->status(),
            'priority' => $ticket->priority(),
            'created_at' => $ticket->createdAt()->format('Y-m-d H:i:s'),
            'resolved_at' => $ticket->resolvedAt() ? $ticket->resolvedAt()->format('Y-m-d H:i:s') : null,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($ticket->id()) {
            $this->wpdb->update($table, $data, ['id' => $ticket->id()], $formats, ['%d']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
            // We can't set the ID on the entity since it's immutable-ish and we don't have a setId method.
            // But usually we'd want to return the new ID or update the entity.
            // For now, adhering to void return.
        }
    }

    public function findById(int $id): ?Ticket
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByCustomerId(int $customerId): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE customer_id = %d ORDER BY created_at DESC", $customerId));

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate($row): Ticket
    {
        return new Ticket(
            (int) $row->customer_id,
            $row->subject,
            $row->description,
            $row->status,
            $row->priority,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at),
            $row->resolved_at ? new \DateTimeImmutable($row->resolved_at) : null
        );
    }
}

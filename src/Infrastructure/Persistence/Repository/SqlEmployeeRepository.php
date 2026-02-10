<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;

class SqlEmployeeRepository implements EmployeeRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_employees';
    }

    public function save(Employee $employee): void
    {
        $data = [
            'wp_user_id' => $employee->wpUserId(),
            'first_name' => $employee->firstName(),
            'last_name' => $employee->lastName(),
            'email' => $employee->email(),
            'created_at' => $this->formatDate($employee->createdAt()),
            'archived_at' => $this->formatDate($employee->archivedAt()),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s'];

        if ($employee->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $employee->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            // Ideally we'd update the ID on the object, but it's immutable-ish (no setId).
            // DDD Repositories usually return void or the entity.
            // If the entity needs the ID, we might need to recreate it or use reflection.
            // For now, void is fine as per interface.
        }
    }

    public function findById(int $id): ?Employee
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByWpUserId(int $wpUserId): ?Employee
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE wp_user_id = %d LIMIT 1",
            $wpUserId
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} ORDER BY last_name ASC, first_name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Employee
    {
        return new Employee(
            (int) $row->wp_user_id,
            $row->first_name,
            $row->last_name,
            $row->email,
            (int) $row->id,
            new \DateTimeImmutable($row->created_at),
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}

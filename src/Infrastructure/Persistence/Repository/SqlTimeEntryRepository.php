<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;

class SqlTimeEntryRepository implements TimeEntryRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
        $this->table = $db->prefix . 'pet_time_entries';
    }

    public function save(TimeEntry $timeEntry): void
    {
        $data = [
            'employee_id' => $timeEntry->employeeId(),
            'task_id' => $timeEntry->taskId(),
            'start_time' => $timeEntry->start()->format('Y-m-d H:i:s'),
            'end_time' => $timeEntry->end()->format('Y-m-d H:i:s'),
            'duration_minutes' => $timeEntry->durationMinutes(),
            'is_billable' => $timeEntry->isBillable() ? 1 : 0,
            'description' => $timeEntry->description(),
            'status' => $timeEntry->status(),
        ];

        if ($timeEntry->id()) {
            $this->db->update(
                $this->table,
                $data,
                ['id' => $timeEntry->id()]
            );
        } else {
            $this->db->insert($this->table, $data);
            
            // In a real implementation we would set the ID back on the entity via reflection or a setter
            // $timeEntry->setId($this->db->insert_id);
        }
    }

    public function findById(int $id): ?TimeEntry
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        );
        
        $row = $this->db->get_row($query);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY start_time DESC";
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE employee_id = %d ORDER BY start_time DESC",
            $employeeId
        );
        
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByTaskId(int $taskId): array
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE task_id = %d ORDER BY start_time DESC",
            $taskId
        );
        
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function sumBillableHours(): float
    {
        $query = "SELECT SUM(duration_minutes) FROM {$this->table} WHERE is_billable = 1";
        $minutes = (int) $this->db->get_var($query);
        return round($minutes / 60, 2);
    }

    private function hydrate(object $row): TimeEntry
    {
        return new TimeEntry(
            (int) $row->employee_id,
            (int) $row->task_id,
            new \DateTimeImmutable($row->start_time),
            new \DateTimeImmutable($row->end_time),
            (bool) $row->is_billable,
            $row->description,
            $row->status,
            (int) $row->id
        );
    }
}

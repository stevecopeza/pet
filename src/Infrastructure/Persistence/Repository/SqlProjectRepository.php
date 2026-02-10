<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Delivery\ValueObject\ProjectState;

class SqlProjectRepository implements ProjectRepository
{
    private $wpdb;
    private $projectsTable;
    private $tasksTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
        $this->tasksTable = $wpdb->prefix . 'pet_tasks';
    }

    public function save(Project $project): void
    {
        $data = [
            'customer_id' => $project->customerId(),
            'source_quote_id' => $project->sourceQuoteId(),
            'name' => $project->name(),
            'state' => $project->state()->toString(),
            'sold_hours' => $project->soldHours(),
            'created_at' => $this->formatDate($project->createdAt()),
            'updated_at' => $this->formatDate($project->updatedAt()),
            'archived_at' => $this->formatDate($project->archivedAt()),
        ];

        $format = ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s'];

        if ($project->id()) {
            $this->wpdb->update(
                $this->projectsTable,
                $data,
                ['id' => $project->id()],
                $format,
                ['%d']
            );
            $projectId = $project->id();
        } else {
            $this->wpdb->insert(
                $this->projectsTable,
                $data,
                $format
            );
            $projectId = $this->wpdb->insert_id;
        }

        if ($projectId) {
            $this->saveTasks((int)$projectId, $project->tasks());
        }
    }

    public function findById(int $id): ?Project
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->projectsTable} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->projectsTable} ORDER BY created_at DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->projectsTable} WHERE customer_id = %d ORDER BY created_at DESC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function countActive(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->projectsTable} WHERE state = %s",
            ProjectState::ACTIVE
        );
        
        return (int) $this->wpdb->get_var($sql);
    }

    public function sumSoldHours(): float
    {
        $sql = "SELECT SUM(sold_hours) FROM {$this->projectsTable}";
        return (float) $this->wpdb->get_var($sql);
    }

    private function saveTasks(int $projectId, array $tasks): void
    {
        foreach ($tasks as $task) {
            $data = [
                'project_id' => $projectId,
                'name' => $task->name(),
                'estimated_hours' => $task->estimatedHours(),
                'is_completed' => $task->isCompleted() ? 1 : 0,
            ];
            $format = ['%d', '%s', '%f', '%d'];

            if ($task->id()) {
                $this->wpdb->update(
                    $this->tasksTable,
                    $data,
                    ['id' => $task->id()],
                    $format,
                    ['%d']
                );
            } else {
                $this->wpdb->insert(
                    $this->tasksTable,
                    $data,
                    $format
                );
            }
        }
    }

    private function hydrate(object $row): Project
    {
        $tasks = $this->findTasksByProjectId((int)$row->id);

        return new Project(
            (int)$row->customer_id,
            $row->name,
            (float)$row->sold_hours,
            $row->source_quote_id ? (int)$row->source_quote_id : null,
            ProjectState::fromString($row->state),
            (int)$row->id,
            $row->created_at ? new \DateTimeImmutable($row->created_at) : null,
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null,
            $tasks
        );
    }

    private function findTasksByProjectId(int $projectId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tasksTable} WHERE project_id = %d",
            $projectId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map(function ($row) {
            return new Task(
                $row->name,
                (float)$row->estimated_hours,
                (bool)$row->is_completed,
                (int)$row->id
            );
        }, $results);
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}

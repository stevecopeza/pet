<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Repository;

use Pet\Domain\Advisory\Entity\AdvisorySignal;

interface AdvisorySignalRepository
{
    public function save(AdvisorySignal $signal): void;
    public function findByWorkItemId(string $workItemId): array;
    public function findByWorkItemIds(array $workItemIds): array;
    public function findActiveByWorkItemId(string $workItemId): array; // Assuming signals can be resolved?
    // Actually, Advisory Signals are events/snapshots.
    // If they persist until resolved, they need a status.
    // But Phase 7.3 says "AdvisorySignal Generator".
    // "Outputs derived, versioned... NEVER mutate operational truth."
    // Maybe they are just logs?
    // "Advisory outputs derived, versioned, annotated..."
    // Let's assume they are persistent records.
    
    public function clearForWorkItem(string $workItemId): void; // To clear old signals before regenerating?
}

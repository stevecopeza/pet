<?php

declare(strict_types=1);

namespace Pet\Domain\Event\Repository;

class EventRecord
{
    public int $id;
    public string $eventUuid;
    public string $occurredAt;
    public string $recordedAt;
    public string $aggregateType;
    public int $aggregateId;
    public int $aggregateVersion;
    public string $eventType;
    public int $eventSchemaVersion;
    public ?string $actorType;
    public ?int $actorId;
    public ?string $correlationId;
    public ?string $causationId;
    public string $payloadJson;
    public ?string $metadataJson;
}

interface EventStreamRepository
{
    /**
     * Fetch latest events, optionally filtered.
     *
     * @param int $limit
     * @param string|null $aggregateType
     * @param int|null $aggregateId
     * @param string|null $eventType
     * @return EventRecord[]
     */
    public function findLatest(
        int $limit = 100,
        ?string $aggregateType = null,
        ?int $aggregateId = null,
        ?string $eventType = null
    ): array;
}

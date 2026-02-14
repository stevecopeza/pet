<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Event\Repository\EventRecord;
use Pet\Domain\Event\Repository\EventStreamRepository;

class SqlEventStreamRepository implements EventStreamRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function findLatest(
        int $limit = 100,
        ?string $aggregateType = null,
        ?int $aggregateId = null,
        ?string $eventType = null
    ): array {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $where = [];
        $params = [];

        if ($aggregateType !== null) {
            $where[] = 'aggregate_type = %s';
            $params[] = $aggregateType;
        }
        if ($aggregateId !== null) {
            $where[] = 'aggregate_id = %d';
            $params[] = $aggregateId;
        }
        if ($eventType !== null) {
            $where[] = 'event_type = %s';
            $params[] = $eventType;
        }

        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT id, event_uuid, occurred_at, recorded_at, aggregate_type, aggregate_id, aggregate_version, event_type, event_schema_version, actor_type, actor_id, correlation_id, causation_id, payload_json, metadata_json
                FROM $table
                $whereSql
                ORDER BY id DESC
                LIMIT %d";
        $params[] = $limit;

        $prepared = $this->wpdb->prepare($sql, $params);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        $out = [];
        foreach ($rows as $row) {
            $rec = new EventRecord();
            $rec->id = (int)$row['id'];
            $rec->eventUuid = $row['event_uuid'];
            $rec->occurredAt = $row['occurred_at'];
            $rec->recordedAt = $row['recorded_at'];
            $rec->aggregateType = $row['aggregate_type'];
            $rec->aggregateId = (int)$row['aggregate_id'];
            $rec->aggregateVersion = (int)$row['aggregate_version'];
            $rec->eventType = $row['event_type'];
            $rec->eventSchemaVersion = (int)$row['event_schema_version'];
            $rec->actorType = $row['actor_type'] ?: null;
            $rec->actorId = $row['actor_id'] !== null ? (int)$row['actor_id'] : null;
            $rec->correlationId = $row['correlation_id'] ?: null;
            $rec->causationId = $row['causation_id'] ?: null;
            $rec->payloadJson = $row['payload_json'];
            $rec->metadataJson = $row['metadata_json'] ?: null;
            $out[] = $rec;
        }
        return $out;
    }
}

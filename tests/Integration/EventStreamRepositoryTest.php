<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventStreamRepositoryTest extends TestCase
{
    private \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository $repo;
    private $wpdb;

    protected function setUp(): void
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $this->repo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository::class);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function testAppendInsertsRow(): void
    {
        $aggregateId = $this->nextAggregateId();
        $id = $this->repo->append('billing_export', $aggregateId, 1, 'TestEvent', json_encode(['x' => 1]));
        $this->assertGreaterThan(0, $id);
        $row = $this->repo->findById($id);
        $this->assertNotNull($row);
        $this->assertSame('billing_export', $row->aggregateType);
        $this->assertSame($aggregateId, $row->aggregateId);
        $this->assertSame(1, $row->aggregateVersion);
    }

    public function testEventUuidUniqueness(): void
    {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $uuid = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : substr(uniqid('uuid_', true), 0, 36);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->wpdb->insert($table, [
            'event_uuid' => $uuid,
            'occurred_at' => $now,
            'recorded_at' => $now,
            'aggregate_type' => 'billing_export',
            'aggregate_id' => 200,
            'aggregate_version' => 1,
            'event_type' => 'TestEvent',
            'event_schema_version' => 1,
            'payload_json' => '{}',
            'metadata_json' => null,
        ]);
        $this->assertGreaterThan(0, (int)$this->wpdb->insert_id);
        $ok = $this->wpdb->insert($table, [
            'event_uuid' => $uuid,
            'occurred_at' => $now,
            'recorded_at' => $now,
            'aggregate_type' => 'billing_export',
            'aggregate_id' => 200,
            'aggregate_version' => 2,
            'event_type' => 'TestEvent2',
            'event_schema_version' => 1,
            'payload_json' => '{}',
            'metadata_json' => null,
        ]);
        $this->assertFalse($ok);
    }

    public function testAggregateVersionMonotonic(): void
    {
        $aggregateId = $this->nextAggregateId();
        $id1 = $this->repo->append('billing_export', $aggregateId, 1, 'A', '{}');
        $this->assertGreaterThan(0, $id1);
        $this->expectException(RuntimeException::class);
        $this->repo->append('billing_export', $aggregateId, 1, 'B', '{}');
    }

    private function nextAggregateId(): int
    {
        $table = $this->wpdb->prefix . 'pet_domain_event_stream';
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(MAX(aggregate_id), 0) + 1000 FROM $table WHERE aggregate_type = %s",
            ['billing_export']
        );
        return (int) $this->wpdb->get_var($sql);
    }
}

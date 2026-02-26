<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventStreamRepositoryTest extends TestCase
{
    private \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository $repo;
    private $wpdb;
    private $originalWpdb;
    private $insertedUuids = [];
    private $rows = [];

    protected function setUp(): void
    {
        global $wpdb;
        $this->originalWpdb = $wpdb;
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->insert_id = 0;
        $this->insertedUuids = [];
        $this->rows = [];
        
        $this->wpdb->method('prepare')->will($this->returnCallback(function($query, ...$args) {
             if (isset($args[0]) && is_array($args[0])) {
                 $args = $args[0];
             }
             foreach ($args as $arg) {
                 $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
                 $query = preg_replace('/%[sdfF]/', (string)$val, $query, 1);
             }
             return $query;
        }));

        $this->wpdb->method('insert')->will($this->returnCallback(function($table, $data) {
             if (isset($data['event_uuid'])) {
                 if (in_array($data['event_uuid'], $this->insertedUuids)) {
                     return false;
                 }
                 $this->insertedUuids[] = $data['event_uuid'];
             }
             $this->wpdb->insert_id++;
             $data['id'] = $this->wpdb->insert_id;
             $this->rows[] = $data;
             return 1;
        }));

        $this->wpdb->method('get_row')->will($this->returnCallback(function($query = null, $output = OBJECT, $y = 0) {
             // Handle MAX(aggregate_version)
             if (preg_match("/SELECT MAX\(aggregate_version\) AS maxv .* aggregate_type = '([^']+)' AND aggregate_id = (\d+)/", $query, $matches)) {
                 $type = $matches[1];
                 $id = (int)$matches[2];
                 $max = 0;
                 foreach ($this->rows as $r) {
                     if ($r['aggregate_type'] === $type && $r['aggregate_id'] == $id) {
                         if ($r['aggregate_version'] > $max) $max = $r['aggregate_version'];
                     }
                 }
                 $row = ['maxv' => $max > 0 ? $max : null];
                 return $output === ARRAY_A ? $row : (object)$row;
             }
             
             // Handle Find By ID
             if (preg_match("/SELECT id, event_uuid.*WHERE id = (\d+)/s", $query, $matches)) {
                 $id = (int)$matches[1];
                 foreach ($this->rows as $r) {
                     if ($r['id'] == $id) {
                         // Ensure all fields exist
                         $defaults = [
                             'event_uuid' => '', 'occurred_at' => '', 'recorded_at' => '',
                             'aggregate_type' => '', 'aggregate_id' => 0, 'aggregate_version' => 0,
                             'event_type' => '', 'event_schema_version' => 1,
                             'actor_type' => null, 'actor_id' => null,
                             'correlation_id' => null, 'causation_id' => null,
                             'payload_json' => '{}', 'metadata_json' => null
                         ];
                         $row = array_merge($defaults, $r);
                         return $output === ARRAY_A ? $row : (object)$row;
                     }
                 }
                 return null;
             }
             
             // Debug unmatched queries
             if (strpos($query, 'SELECT id, event_uuid') !== false) {
                 fwrite(STDERR, "Unmatched query: $query\n");
                 fwrite(STDERR, "Rows: " . print_r($this->rows, true) . "\n");
             }
             
             return null;
        }));
        
        $this->wpdb->method('get_var')->will($this->returnCallback(function($query) {
             // Handle nextAggregateId
             if (strpos($query, 'SELECT COALESCE(MAX(aggregate_id)') !== false) {
                 $max = 0;
                 foreach ($this->rows as $r) {
                     if ($r['aggregate_id'] > $max) {
                         $max = $r['aggregate_id'];
                     }
                 }
                 return $max + 1000;
             }
             return 0;
        }));
        
        $GLOBALS['wpdb'] = $this->wpdb;
        
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $this->repo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository::class);
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->originalWpdb;
        \Pet\Infrastructure\DependencyInjection\ContainerFactory::reset();
    }

    public function testAppendInsertsRow(): void
    {
        fwrite(STDERR, "Repo wpdb ID: " . spl_object_id($this->getRepoWpdb()) . "\n");
        fwrite(STDERR, "Test wpdb ID: " . spl_object_id($this->wpdb) . "\n");
        
        $aggregateId = $this->nextAggregateId();
        $id = $this->repo->append('billing_export', $aggregateId, 1, 'A', '{}');
        $this->assertGreaterThan(0, $id);
        
        $row = $this->repo->findById($id);
        $this->assertNotNull($row);
        $this->assertEquals('billing_export', $row->aggregateType);
        $this->assertEquals($aggregateId, $row->aggregateId);
        $this->assertEquals(1, $row->aggregateVersion);
        $this->assertNotEmpty($row->eventUuid);
    }

    private function getRepoWpdb() {
        $r = new \ReflectionClass($this->repo);
        $p = $r->getProperty('wpdb');
        $p->setAccessible(true);
        return $p->getValue($this->repo);
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

<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Configuration;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Infrastructure\Persistence\Repository\SqlSchemaDefinitionRepository;

class SqlSchemaDefinitionRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlSchemaDefinitionRepository($this->wpdb);
    }

    public function testSaveInsert()
    {
        $schema = new SchemaDefinition('customer', 1, ['fields' => []]);

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_schema_definitions',
                $this->callback(function ($data) {
                    return $data['entity_type'] === 'customer' && $data['version'] === 1;
                }),
                $this->anything()
            );

        $this->repository->save($schema);
    }

    public function testFindLatestByEntityType()
    {
        $row = (object) [
            'id' => '1',
            'entity_type' => 'customer',
            'version' => '2',
            'schema_json' => '{"fields": []}',
            'status' => 'active',
            'published_at' => null,
            'published_by' => null,
            'created_at' => '2023-01-01 00:00:00',
            'created_by_employee_id' => null
        ];

        $this->wpdb->method('get_row')->willReturn($row);

        $schema = $this->repository->findLatestByEntityType('customer');

        $this->assertInstanceOf(SchemaDefinition::class, $schema);
        $this->assertEquals(2, $schema->version());
    }
}

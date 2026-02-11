<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Infrastructure\Persistence\Repository;

use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Domain\Configuration\Entity\SchemaStatus;
use Pet\Infrastructure\Persistence\Repository\SqlSchemaDefinitionRepository;
use PHPUnit\Framework\TestCase;

class SchemaStatusIntegrationTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlSchemaDefinitionRepository($this->wpdb);
    }

    public function testSaveDraftSchema(): void
    {
        $schema = new SchemaDefinition(
            'customer',
            1,
            ['fields' => []],
            null,
            SchemaStatus::DRAFT
        );

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_schema_definitions',
                $this->callback(function ($data) {
                    return $data['entity_type'] === 'customer' &&
                           $data['version'] === 1 &&
                           $data['status'] === 'draft' &&
                           $data['published_at'] === null &&
                           $data['published_by'] === null;
                }),
                $this->anything()
            );

        $this->repository->save($schema);
    }

    public function testSaveActiveSchema(): void
    {
        $publishedAt = new \DateTimeImmutable();
        $schema = new SchemaDefinition(
            'customer',
            2,
            ['fields' => []],
            10,
            SchemaStatus::ACTIVE,
            $publishedAt,
            123
        );

        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_schema_definitions',
                $this->callback(function ($data) use ($publishedAt) {
                    return $data['entity_type'] === 'customer' &&
                           $data['version'] === 2 &&
                           $data['status'] === 'active' &&
                           $data['published_at'] === $publishedAt->format('Y-m-d H:i:s') &&
                           $data['published_by'] === 123;
                }),
                ['id' => 10],
                $this->anything(),
                $this->anything()
            );

        $this->repository->save($schema);
    }

    public function testHydrateSchemaWithStatus(): void
    {
        $row = (object) [
            'id' => 1,
            'entity_type' => 'customer',
            'version' => '1',
            'schema_json' => '[]',
            'status' => 'active',
            'published_at' => '2023-10-01 12:00:00',
            'published_by' => '123',
            'created_at' => '2023-09-01 12:00:00',
            'created_by_employee_id' => '100'
        ];

        $this->wpdb->method('prepare')->willReturn('SQL');
        $this->wpdb->method('get_row')->willReturn($row);

        $schema = $this->repository->findById(1);

        $this->assertNotNull($schema);
        $this->assertEquals(SchemaStatus::ACTIVE, $schema->status());
        $this->assertEquals(123, $schema->publishedByEmployeeId());
        $this->assertEquals('2023-10-01 12:00:00', $schema->publishedAt()->format('Y-m-d H:i:s'));
    }
}

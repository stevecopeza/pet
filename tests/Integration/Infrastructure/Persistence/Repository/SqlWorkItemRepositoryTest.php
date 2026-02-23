<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class SqlWorkItemRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlWorkItemRepository($this->wpdb);
    }

    public function testSaveNewWorkItemPersistsCommercialAndOverrideFields(): void
    {
        $workItem = new WorkItem(
            'item-123',
            'ticket',
            'ticket-1',
            'user-1',
            'dept-1',
            null,
            'snapshot-1',
            60,
            100.0,
            new DateTimeImmutable('2023-01-01 10:00:00'),
            new DateTimeImmutable('2023-01-02 10:00:00'),
            50.0,
            'active',
            1,
            new DateTimeImmutable('2023-01-01 09:00:00'),
            new DateTimeImmutable('2023-01-01 09:00:00'),
            15000.0, // Revenue
            2,       // Client Tier
            300.0    // Manager Override
        );

        // Mock findById to return null (simulate new record)
        $this->wpdb->method('get_row')->willReturn(null);

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_work_items',
                $this->callback(function ($data) {
                    return $data['revenue'] === 15000.0 &&
                           $data['client_tier'] === 2 &&
                           $data['manager_priority_override'] === 300.0;
                }),
                $this->anything()
            );

        $this->repository->save($workItem);
    }

    public function testUpdateWorkItemPersistsCommercialAndOverrideFields(): void
    {
        $workItem = new WorkItem(
            'item-123',
            'ticket',
            'ticket-1',
            'user-1',
            'dept-1',
            null,
            'snapshot-1',
            60,
            100.0,
            new DateTimeImmutable('2023-01-01 10:00:00'),
            new DateTimeImmutable('2023-01-02 10:00:00'),
            50.0,
            'active',
            1,
            new DateTimeImmutable('2023-01-01 09:00:00'),
            new DateTimeImmutable('2023-01-01 09:00:00'),
            20000.0, // Updated Revenue
            3,       // Updated Client Tier
            400.0    // Updated Manager Override
        );

        // Mock findById to return a row (simulate existing record)
        $existingRow = (object) [
            'id' => 'item-123',
            'source_type' => 'ticket',
            'source_id' => 'ticket-1',
            'assigned_user_id' => 'user-1',
            'department_id' => 'dept-1',
            'sla_snapshot_id' => 'snapshot-1',
            'sla_time_remaining_minutes' => 60,
            'priority_score' => 100.0,
            'scheduled_start_utc' => '2023-01-01 10:00:00',
            'scheduled_due_utc' => '2023-01-02 10:00:00',
            'capacity_allocation_percent' => 50.0,
            'status' => 'active',
            'escalation_level' => 1,
            'created_at' => '2023-01-01 09:00:00',
            'updated_at' => '2023-01-01 09:00:00',
            'revenue' => 15000.0,
            'client_tier' => 2,
            'manager_priority_override' => 300.0
        ];
        $this->wpdb->method('get_row')->willReturn($existingRow);
        $this->wpdb->method('prepare')->willReturn("SELECT * FROM wp_pet_work_items WHERE id = 'item-123'");

        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_work_items',
                $this->callback(function ($data) {
                    return $data['revenue'] === 20000.0 &&
                           $data['client_tier'] === 3 &&
                           $data['manager_priority_override'] === 400.0;
                }),
                ['id' => 'item-123'],
                $this->anything(),
                $this->anything()
            );

        $this->repository->save($workItem);
    }

    public function testFindByIdHydratesCommercialAndOverrideFields(): void
    {
        $row = (object) [
            'id' => 'item-123',
            'source_type' => 'ticket',
            'source_id' => 'ticket-1',
            'assigned_user_id' => 'user-1',
            'department_id' => 'dept-1',
            'sla_snapshot_id' => 'snapshot-1',
            'sla_time_remaining_minutes' => 60,
            'priority_score' => 100.0,
            'scheduled_start_utc' => '2023-01-01 10:00:00',
            'scheduled_due_utc' => '2023-01-02 10:00:00',
            'capacity_allocation_percent' => 50.0,
            'status' => 'active',
            'escalation_level' => 1,
            'created_at' => '2023-01-01 09:00:00',
            'updated_at' => '2023-01-01 09:00:00',
            'revenue' => 15000.0,
            'client_tier' => 2,
            'manager_priority_override' => 300.0
        ];

        $this->wpdb->method('get_row')->willReturn($row);
        $this->wpdb->method('prepare')->willReturn("SELECT * FROM wp_pet_work_items WHERE id = 'item-123'");

        $workItem = $this->repository->findById('item-123');

        $this->assertNotNull($workItem);
        $this->assertEquals(15000.0, $workItem->getRevenue());
        $this->assertEquals(2, $workItem->getClientTier());
        $this->assertEquals(300.0, $workItem->getManagerPriorityOverride());
    }
}

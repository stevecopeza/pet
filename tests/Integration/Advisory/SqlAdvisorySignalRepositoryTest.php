<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Advisory;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Infrastructure\Persistence\Repository\SqlAdvisorySignalRepository;
use DateTimeImmutable;

class SqlAdvisorySignalRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $wpdb = $this->wpdb;

        $this->repository = new SqlAdvisorySignalRepository($this->wpdb);
    }

    public function testSaveCallsReplace()
    {
        $id = 'sig-1';
        $workItemId = 'wi-1';
        $now = new DateTimeImmutable();

        $signal = new AdvisorySignal(
            $id,
            $workItemId,
            AdvisorySignal::TYPE_SLA_RISK,
            AdvisorySignal::SEVERITY_WARNING,
            'Risk detected',
            $now
        );

        $this->wpdb->expects($this->once())
            ->method('replace')
            ->with(
                'wp_pet_advisory_signals',
                [
                    'id' => $id,
                    'work_item_id' => $workItemId,
                    'signal_type' => AdvisorySignal::TYPE_SLA_RISK,
                    'severity' => AdvisorySignal::SEVERITY_WARNING,
                    'message' => 'Risk detected',
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]
            );

        $this->repository->save($signal);
    }

    public function testFindByWorkItemIdReturnsEntities()
    {
        $workItemId = 'wi-1';
        $now = new DateTimeImmutable();
        
        $row = (object)[
            'id' => 'sig-1',
            'work_item_id' => $workItemId,
            'signal_type' => AdvisorySignal::TYPE_SLA_RISK,
            'severity' => AdvisorySignal::SEVERITY_WARNING,
            'message' => 'Risk detected',
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn("SELECT * FROM wp_pet_advisory_signals WHERE work_item_id = '$workItemId' ORDER BY created_at DESC");

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([$row]);

        $results = $this->repository->findByWorkItemId($workItemId);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(AdvisorySignal::class, $results[0]);
        $this->assertEquals('sig-1', $results[0]->getId());
    }

    public function testClearForWorkItemCallsDelete()
    {
        $workItemId = 'wi-1';

        $this->wpdb->expects($this->once())
            ->method('delete')
            ->with(
                'wp_pet_advisory_signals',
                ['work_item_id' => $workItemId]
            );

        $this->repository->clearForWorkItem($workItemId);
    }

    public function testFindByWorkItemIdsReturnsEntities()
    {
        $ids = ['wi-1', 'wi-2'];
        $now = new DateTimeImmutable();
        
        $row1 = (object)[
            'id' => 'sig-1',
            'work_item_id' => 'wi-1',
            'signal_type' => AdvisorySignal::TYPE_SLA_RISK,
            'severity' => AdvisorySignal::SEVERITY_WARNING,
            'message' => 'Risk detected',
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];
        
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                "SELECT * FROM wp_pet_advisory_signals WHERE work_item_id IN (%s,%s) ORDER BY created_at DESC", 
                'wi-1', 
                'wi-2'
            )
            ->willReturn("SQL_STRING");

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->with("SQL_STRING")
            ->willReturn([$row1]);

        $results = $this->repository->findByWorkItemIds($ids);

        $this->assertCount(1, $results);
        $this->assertEquals('sig-1', $results[0]->getId());
    }
}

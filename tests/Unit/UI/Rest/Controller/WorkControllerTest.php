<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

// Mock global WordPress functions in the same namespace
function get_current_user_id() {
    return 123;
}

function is_user_logged_in() {
    return true;
}

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\UI\Rest\Controller\WorkController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class WorkControllerTest extends TestCase
{
    private $workItemRepository;
    private $signalRepository;
    private $controller;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->signalRepository = $this->createMock(AdvisorySignalRepository::class);
        
        $this->controller = new WorkController(
            $this->workItemRepository,
            $this->signalRepository
        );
    }

    public function testGetMyWorkItemsReturnsMappedItemsWithSignals(): void
    {
        // Arrange
        $item = WorkItem::create(
            'wi-1',
            'ticket',
            't-1',
            'dept-1',
            50.0,
            'active',
            new \DateTimeImmutable('2023-01-01 10:00:00')
        );

        $signal = new AdvisorySignal(
            'sig-1',
            'wi-1',
            'sla_risk',
            'warning',
            'SLA Risk',
            new \DateTimeImmutable('2023-01-01 12:00:00')
        );

        $this->workItemRepository->expects($this->once())
            ->method('findByAssignedUser')
            ->with('123')
            ->willReturn([$item]);

        $this->signalRepository->expects($this->once())
            ->method('findByWorkItemIds')
            ->with(['wi-1'])
            ->willReturn([$signal]);

        $request = new WP_REST_Request();

        // Act
        $response = $this->controller->getMyWorkItems($request);

        // Assert
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        
        $itemData = $data[0];
        $this->assertEquals('wi-1', $itemData['id']);
        $this->assertEquals('dept-1', $itemData['departmentId']);
        $this->assertCount(1, $itemData['signals']);
        $this->assertEquals('sla_risk', $itemData['signals'][0]['type']);
    }

    public function testGetDepartmentQueueReturnsMappedItems(): void
    {
        // Arrange
        $item = WorkItem::create(
            'wi-2',
            'project_task',
            'pt-1',
            'dept-2',
            80.0,
            'waiting',
            new \DateTimeImmutable('2023-01-01 10:00:00')
        );

        $this->workItemRepository->expects($this->once())
            ->method('findByDepartmentUnassigned')
            ->with('dept-2')
            ->willReturn([$item]);

        $this->signalRepository->expects($this->once())
            ->method('findByWorkItemIds')
            ->with(['wi-2'])
            ->willReturn([]);

        $request = new WP_REST_Request();
        $request->set_param('id', 'dept-2');

        // Act
        $response = $this->controller->getDepartmentQueue($request);

        // Assert
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals('wi-2', $data[0]['id']);
        $this->assertEmpty($data[0]['signals']);
    }
    
    public function testCheckPermissionReturnsTrue(): void
    {
        $this->assertTrue($this->controller->checkPermission());
    }
}

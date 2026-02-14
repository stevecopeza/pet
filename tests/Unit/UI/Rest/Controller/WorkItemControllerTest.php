<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\Work\Command\AssignWorkItemCommand;
use Pet\Application\Work\Command\AssignWorkItemHandler;
use Pet\Application\Work\Command\OverrideWorkItemPriorityCommand;
use Pet\Application\Work\Command\OverrideWorkItemPriorityHandler;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\UI\Rest\Controller\WorkItemController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class WorkItemControllerTest extends TestCase
{
    private $workItemRepository;
    private $signalRepository;
    private $assignHandler;
    private $overrideHandler;
    private $controller;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->signalRepository = $this->createMock(AdvisorySignalRepository::class);
        $this->assignHandler = $this->createMock(AssignWorkItemHandler::class);
        $this->overrideHandler = $this->createMock(OverrideWorkItemPriorityHandler::class);

        $this->controller = new WorkItemController(
            $this->workItemRepository,
            $this->signalRepository,
            $this->assignHandler,
            $this->overrideHandler
        );
    }

    public function testAssignItemSuccess()
    {
        $request = new WP_REST_Request();
        $request->set_param('id', 'wi-1');
        $request->set_param('assigned_user_id', 'user-123');

        $this->assignHandler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (AssignWorkItemCommand $command) {
                return $command->workItemId() === 'wi-1' && $command->assignedUserId() === 'user-123';
            }));

        $response = $this->controller->assignItem($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(['message' => 'Assigned'], $response->get_data());
    }

    public function testAssignItemMissingUser()
    {
        $request = new WP_REST_Request();
        $request->set_param('id', 'wi-1');
        // No assigned_user_id

        $this->assignHandler->expects($this->never())->method('handle');

        $response = $this->controller->assignItem($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function testPrioritizeItemSuccess()
    {
        $request = new WP_REST_Request();
        $request->set_param('id', 'wi-1');
        $request->set_param('override_value', '99.5');

        $this->overrideHandler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (OverrideWorkItemPriorityCommand $command) {
                return $command->workItemId() === 'wi-1' && abs($command->overrideValue() - 99.5) < 0.001;
            }));

        $response = $this->controller->prioritizeItem($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(['message' => 'Priority Override Applied'], $response->get_data());
    }

    public function testPrioritizeItemInvalidValue()
    {
        $request = new WP_REST_Request();
        $request->set_param('id', 'wi-1');
        $request->set_param('override_value', 'not-a-number');

        $this->overrideHandler->expects($this->never())->method('handle');

        $response = $this->controller->prioritizeItem($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function testGetItemsReturnsSerializedData()
    {
        $item = WorkItem::create(
            'wi-1',
            'ticket',
            't-1',
            'dept-1',
            50.0,
            'active',
            new \DateTimeImmutable('2023-01-01 10:00:00')
        );

        $this->workItemRepository->method('findActive')->willReturn([$item]);

        $signal = new AdvisorySignal(
            'sig-1',
            'wi-1',
            'sla_risk',
            'warning',
            'SLA Risk',
            new \DateTimeImmutable('2023-01-01 12:00:00')
        );

        $this->signalRepository->method('findActiveByWorkItemId')
            ->with('wi-1')
            ->willReturn([$signal]);

        $request = new WP_REST_Request();
        $response = $this->controller->getItems($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals('wi-1', $data[0]['id']);
        $this->assertCount(1, $data[0]['signals']);
        $this->assertEquals('sla_risk', $data[0]['signals'][0]['type']);
    }
}

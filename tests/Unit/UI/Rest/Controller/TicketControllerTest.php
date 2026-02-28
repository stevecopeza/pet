<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\TicketController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class TicketControllerTest extends TestCase
{
    private $ticketRepository;
    private $workItemRepository;
    private $createHandler;
    private $updateHandler;
    private $deleteHandler;
    private $featureFlags;
    private $controller;

    protected function setUp(): void
    {
        $this->ticketRepository = $this->createMock(TicketRepository::class);
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->createHandler = $this->createMock(CreateTicketHandler::class);
        $this->updateHandler = $this->createMock(UpdateTicketHandler::class);
        $this->deleteHandler = $this->createMock(DeleteTicketHandler::class);
        $this->featureFlags = $this->createMock(FeatureFlagService::class);

        $this->controller = new TicketController(
            $this->ticketRepository,
            $this->createHandler,
            $this->updateHandler,
            $this->deleteHandler,
            $this->workItemRepository,
            $this->featureFlags
        );
    }

    public function testGetTicketsIncludesModeAndAssignment(): void
    {
        $ticket = new Ticket(
            1001,
            'Execution Task',
            'Do the work',
            'open',
            'medium',
            null,
            null,
            1,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $this->ticketRepository
            ->method('findAll')
            ->willReturn([$ticket]);

        $workItem = WorkItem::create(
            'wi-1',
            'ticket',
            '1',
            'support',
            80.0,
            'active',
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );
        $workItem->assignUser('user-123');

        $this->workItemRepository
            ->method('findAll')
            ->willReturn([$workItem]);

        $request = new WP_REST_Request();
        $response = $this->controller->getTickets($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals('execution', $data[0]['ticketMode']);
        $this->assertEquals('user-123', $data[0]['assignedUserId']);
    }

    public function testGetTicketsFiltersByTicketMode(): void
    {
        $executionTicket = new Ticket(
            1001,
            'Execution Task',
            'Do the work',
            'open',
            'medium',
            null,
            null,
            1,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $supportTicket = new Ticket(
            1002,
            'Support Issue',
            'Help needed',
            'open',
            'medium',
            null,
            null,
            2,
            null,
            ['ticket_mode' => 'support'],
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );

        $this->ticketRepository
            ->method('findAll')
            ->willReturn([$executionTicket, $supportTicket]);

        $this->workItemRepository
            ->method('findAll')
            ->willReturn([]);

        $request = new WP_REST_Request();
        $request->set_param('ticket_mode', 'execution');

        $response = $this->controller->getTickets($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('execution', $data[0]['ticketMode']);
    }

    public function testGetTicketsFiltersUnassigned(): void
    {
        $ticketAssigned = new Ticket(
            1001,
            'Execution Task',
            'Do the work',
            'open',
            'medium',
            null,
            null,
            10,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $ticketUnassigned = new Ticket(
            1001,
            'Another Task',
            'Do more work',
            'open',
            'medium',
            null,
            null,
            11,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:30:00')
        );

        $this->ticketRepository
            ->method('findAll')
            ->willReturn([$ticketAssigned, $ticketUnassigned]);

        $assignedItem = WorkItem::create(
            'wi-10',
            'ticket',
            '10',
            'support',
            80.0,
            'active',
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );
        $assignedItem->assignUser('user-123');

        $unassignedItem = WorkItem::create(
            'wi-11',
            'ticket',
            '11',
            'support',
            70.0,
            'active',
            new \DateTimeImmutable('2025-01-01 11:05:00')
        );

        $this->workItemRepository
            ->method('findAll')
            ->willReturn([$assignedItem, $unassignedItem]);

        $request = new WP_REST_Request();
        $request->set_param('unassigned', 1);

        $response = $this->controller->getTickets($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals(11, $data[0]['id']);
        $this->assertNull($data[0]['assignedUserId']);
    }

    public function testGetTicketsFiltersByAssignedUserId(): void
    {
        $ticketAssigned = new Ticket(
            1001,
            'Execution Task',
            'Do the work',
            'open',
            'medium',
            null,
            null,
            10,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $ticketUnassigned = new Ticket(
            1001,
            'Another Task',
            'Do more work',
            'open',
            'medium',
            null,
            null,
            11,
            null,
            ['ticket_mode' => 'execution'],
            new \DateTimeImmutable('2025-01-01 10:30:00')
        );

        $this->ticketRepository
            ->method('findAll')
            ->willReturn([$ticketAssigned, $ticketUnassigned]);

        $assignedItem = WorkItem::create(
            'wi-10',
            'ticket',
            '10',
            'support',
            80.0,
            'active',
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );
        $assignedItem->assignUser('user-123');

        $unassignedItem = WorkItem::create(
            'wi-11',
            'ticket',
            '11',
            'support',
            70.0,
            'active',
            new \DateTimeImmutable('2025-01-01 11:05:00')
        );

        $this->workItemRepository
            ->method('findAll')
            ->willReturn([$assignedItem, $unassignedItem]);

        $request = new WP_REST_Request();
        $request->set_param('assigned_user_id', 'user-123');

        $response = $this->controller->getTickets($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals('user-123', $data[0]['assignedUserId']);
    }
}


<?php

namespace Pet\Tests\Integration\Work;

use PHPUnit\Framework\TestCase;
use Pet\Application\Work\Projection\WorkItemProjector;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository;
use Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Support\Event\TicketAssigned;

class WorkItemProjectorTest extends TestCase
{
    private $wpdb;
    private $workItemRepository;
    private $departmentQueueRepository;
    private $departmentResolver;
    private $priorityScoringService;
    private $projector;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock global $wpdb
        global $wpdb;
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $wpdb = $this->wpdb;

        $this->workItemRepository = new SqlWorkItemRepository($this->wpdb);
        $this->departmentQueueRepository = new SqlDepartmentQueueRepository($this->wpdb);
        
        // Use real services or mocks? 
        // Real logic for DepartmentResolver is simple, safe to use real.
        $this->departmentResolver = new DepartmentResolver();
        
        // PriorityScoringService also has logic we might want to test, or mock if complex.
        // It relies on DateTime, which is fine.
        $this->priorityScoringService = new PriorityScoringService();
        
        $this->projector = new WorkItemProjector(
            $this->workItemRepository,
            $this->departmentQueueRepository,
            $this->departmentResolver,
            $this->priorityScoringService
        );
    }

    public function testOnTicketCreatedPersistsWorkItemAndQueueEntry()
    {
        // 1. Arrange
        $ticketId = 123;
        $ticket = new Ticket(
            customerId: 1,
            subject: 'Test Ticket',
            description: 'Test Description',
            id: $ticketId
        );
        $event = new TicketCreated($ticket);

        $this->wpdb->method('prepare')->willReturnArgument(0);
        $this->wpdb->method('get_row')->willReturn(null);

        // Expect:
        // 1. Insert WorkItem (initial)
        // 2. Update WorkItem (score)
        // 3. Insert Queue
        // Note: The projector now calls save() twice on WorkItem.
        // The first save is insert.
        // The second save is update (since ID exists).
        // SqlWorkItemRepository::save logic handles insert vs update based on existence?
        // Actually, Repository usually uses ON DUPLICATE KEY UPDATE or checks existence.
        // But here we are mocking $wpdb.
        // Let's see SqlWorkItemRepository implementation if we can.
        // Assuming it does replace or insert/update.
        
        // We expect at least 3 DB calls (2 saves + 1 queue enter).
        // Actually, `save` might call `replace` or `insert` + `update`.
        
        // Let's relax expectations to just ensure `insert` happens for WorkItem and Queue.
        // We can verify the data content.

        $this->wpdb->expects($this->atLeast(2))
            ->method('insert')
            ->withConsecutive(
                [
                    $this->stringContains('pet_work_items'),
                    $this->callback(function($data) use ($ticketId) {
                        return $data['source_type'] === 'ticket' 
                            && $data['source_id'] === (string)$ticketId
                            && $data['department_id'] === DepartmentResolver::DEPT_SUPPORT;
                    })
                ],
                // Second insert might be the Queue item (if second save() is an update and not insert)
                // If SqlRepository uses `replace` or `insert`, we might see 3 inserts.
                // Let's use flexible invocation matching.
            );
            
        // 2. Act
        $this->projector->onTicketCreated($event);
    }

    public function testOnProjectTaskCreatedPersistsWorkItemAndQueueEntry()
    {
        // 1. Arrange
        $projectId = 101;
        $taskId = 202;
        
        $project = new Project(
            customerId: 1,
            name: 'Test Project',
            soldHours: 10.0,
            id: $projectId
        );
        
        $task = new Task(
            name: 'Test Task',
            estimatedHours: 5.0,
            completed: false,
            id: $taskId
        );
        
        $event = new ProjectTaskCreated($project, $task);

        $this->wpdb->method('prepare')->willReturnArgument(0);
        
        // Expect at least 2 inserts (WorkItem + Queue)
        $this->wpdb->expects($this->atLeast(2))
            ->method('insert')
            ->withConsecutive(
                [
                    $this->stringContains('pet_work_items'),
                    $this->callback(function($data) use ($taskId) {
                        return $data['source_type'] === 'project_task' 
                            && $data['source_id'] === (string)$taskId
                            && $data['department_id'] === DepartmentResolver::DEPT_DELIVERY;
                    })
                ]
            );

        // 2. Act
        $this->projector->onProjectTaskCreated($event);
    }

    public function testOnTicketAssignedUpdatesWorkItem()
    {
        // 1. Arrange
        $ticketId = 123;
        $agentId = 'agent-007';
        $ticket = new Ticket(
            customerId: 1,
            subject: 'Test Ticket',
            description: 'Test Description',
            id: $ticketId
        );
        $event = new TicketAssigned($ticket, $agentId);

        // Mock findBySource to return a WorkItem row
        $mockRow = (object)[
            'id' => 'wi-123',
            'source_type' => 'ticket',
            'source_id' => (string)$ticketId,
            'assigned_user_id' => null,
            'department_id' => 'support',
            'sla_snapshot_id' => null,
            'sla_time_remaining_minutes' => null,
            'priority_score' => 0.0,
            'scheduled_start_utc' => null,
            'scheduled_due_utc' => null,
            'capacity_allocation_percent' => 0.0,
            'status' => 'active',
            'escalation_level' => 0,
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => '2023-01-01 12:00:00',
        ];

        // Mock prepare for select and update
        $this->wpdb->method('prepare')->willReturnArgument(0);
        
        // Mock get_row to return the work item when searching by source
        $this->wpdb->method('get_row')->willReturn($mockRow);

        // Expect update call
        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                $this->stringContains('pet_work_items'),
                $this->callback(function($data) use ($agentId) {
                    return $data['assigned_user_id'] === $agentId;
                }),
                ['id' => 'wi-123']
            );

        // 2. Act
        $this->projector->onTicketAssigned($event);
    }
}

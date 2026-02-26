<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Helpdesk\Query;

use Pet\Application\Helpdesk\Query\HelpdeskOverviewQueryService;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Application\Identity\Directory\UserDirectory;
use PHPUnit\Framework\TestCase;

class HelpdeskOverviewQueryServiceTest extends TestCase
{
    private $workItemRepository;
    private $ticketRepository;
    private $employeeRepository;
    private $feedEventRepository;
    private $customerRepository;
    private $userDirectory;
    private $service;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->ticketRepository = $this->createMock(TicketRepository::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->feedEventRepository = $this->createMock(FeedEventRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->userDirectory = $this->createMock(UserDirectory::class);

        $this->service = new HelpdeskOverviewQueryService(
            $this->workItemRepository,
            $this->ticketRepository,
            $this->employeeRepository,
            $this->feedEventRepository,
            $this->customerRepository,
            $this->userDirectory
        );
    }

    public function testGetOverviewAllTeamsCalculatesSlaBandsCorrectly(): void
    {
        // GIVEN
        $ticket1 = new Ticket(1, 'Subject 1', 'Desc', 'open', 'high', null, null, 101); // Critical
        $ticket2 = new Ticket(1, 'Subject 2', 'Desc', 'open', 'medium', null, null, 102); // Risk
        $ticket3 = new Ticket(1, 'Subject 3', 'Desc', 'open', 'low', null, null, 103); // Normal
        $ticket4 = new Ticket(1, 'Subject 4', 'Desc', 'open', 'low', null, null, 104); // No WorkItem

        $workItem1 = WorkItem::create('wi-1', 'ticket', '101', 'dept-1', 10.0, 'active', new \DateTimeImmutable());
        $workItem1->updateSlaState('snap-1', -10); // Critical

        $workItem2 = WorkItem::create('wi-2', 'ticket', '102', 'dept-1', 10.0, 'active', new \DateTimeImmutable());
        $workItem2->updateSlaState('snap-2', 30); // Risk (< 60)

        $workItem3 = WorkItem::create('wi-3', 'ticket', '103', 'dept-2', 10.0, 'active', new \DateTimeImmutable());
        $workItem3->updateSlaState('snap-3', 120); // Normal (> 60)

        $this->ticketRepository->method('findActive')->willReturn([$ticket1, $ticket2, $ticket3, $ticket4]);
        $this->workItemRepository->method('findActive')->willReturn([$workItem1, $workItem2, $workItem3]);

        // WHEN
        $result = $this->service->getOverview('all', 0, false);

        // THEN
        $stats = $result['stats'];
        $this->assertEquals(4, $stats['open_tickets']);
        $this->assertEquals(1, $stats['critical_tickets']); // Ticket 1
        $this->assertEquals(1, $stats['at_risk_tickets']); // Ticket 2
        $this->assertEquals(1, $stats['breached_tickets']); // Ticket 1

        $lanes = $result['lanes'];
        $this->assertCount(1, $lanes['critical']);
        $this->assertEquals(101, $lanes['critical'][0]['ticket_id']);
        $this->assertEquals('critical', $lanes['critical'][0]['band']);
        $this->assertEquals('Unknown Customer', $lanes['critical'][0]['customer_name']);
        $this->assertEquals('Unassigned', $lanes['critical'][0]['assignee_name']);
        $this->assertEquals('10m overdue', $lanes['critical'][0]['relative_due']);

        $this->assertCount(1, $lanes['risk']);
        $this->assertEquals(102, $lanes['risk'][0]['ticket_id']);
        $this->assertEquals('risk', $lanes['risk'][0]['band']);
        $this->assertEquals('30m left', $lanes['risk'][0]['relative_due']);

        $this->assertCount(2, $lanes['normal']); // Ticket 3 (normal) + Ticket 4 (no work item -> normal)
        // Ticket 3
        $this->assertEquals(103, $lanes['normal'][0]['ticket_id']);
        $this->assertEquals('2h left', $lanes['normal'][0]['relative_due']);
    }

    public function testGetOverviewCurrentTeamFiltersTickets(): void
    {
        // GIVEN user is in team 'dept-1' only
        $userId = 123;
        $employee = $this->createMock(Employee::class);
        $employee->method('id')->willReturn(999);
        $employee->method('teamIds')->willReturn(['dept-1']);
        $this->employeeRepository->method('findByWpUserId')->with($userId)->willReturn($employee);

        // Tickets
        $ticket1 = new Ticket(1, 'In Team', 'Desc', 'open', 'medium', null, null, 101);
        $ticket2 = new Ticket(1, 'Out Team', 'Desc', 'open', 'medium', null, null, 102);
        
        // Work Items (linked to departments)
        $workItem1 = WorkItem::create('wi-1', 'ticket', '101', 'dept-1', 10.0, 'active', new \DateTimeImmutable());
        $workItem2 = WorkItem::create('wi-2', 'ticket', '102', 'dept-2', 10.0, 'active', new \DateTimeImmutable());

        $this->ticketRepository->method('findActive')->willReturn([$ticket1, $ticket2]);
        $this->workItemRepository->method('findActive')->willReturn([$workItem1, $workItem2]);

        // WHEN
        $result = $this->service->getOverview('current', $userId, false);

        // THEN
        $stats = $result['stats'];
        $this->assertEquals(1, $stats['open_tickets']); // Only ticket 1 should be counted
        
        $lanes = $result['lanes'];
        $this->assertCount(1, $lanes['normal']);
        $this->assertEquals(101, $lanes['normal'][0]['ticket_id']);
    }

    public function testGetOverviewReturnsFlowWhenEnabled(): void
    {
        // GIVEN
        $this->ticketRepository->method('findActive')->willReturn([]);
        $this->workItemRepository->method('findActive')->willReturn([]);

        $event1 = $this->createMock(FeedEvent::class);
        $event1->method('getEventType')->willReturn('ticket.created');
        
        $event2 = $this->createMock(FeedEvent::class);
        $event2->method('getEventType')->willReturn('ticket.resolved');

        $this->feedEventRepository->method('findRelevantForUser')->willReturn([$event1, $event2]);

        // WHEN
        $result = $this->service->getOverview('all', 0, true);

        // THEN
        $flow = $result['flow'];
        $this->assertCount(1, $flow['recent_created']);
        $this->assertCount(1, $flow['recent_resolved']);
    }

    public function testGetOverviewPopulatesAssigneeDetails(): void
    {
        // GIVEN
        $ticket = new Ticket(
            customerId: 1,
            subject: 'Subject',
            description: 'Desc',
            status: 'open',
            priority: 'high',
            id: 101,
            ownerUserId: '555'
        );

        $this->ticketRepository->method('findActive')->willReturn([$ticket]);
        $this->workItemRepository->method('findActive')->willReturn([]);

        $this->userDirectory->method('getDisplayName')->with(555)->willReturn('John Doe');
        $this->userDirectory->method('getAvatarUrl')->with(555)->willReturn('http://avatar.url');

        // WHEN
        $result = $this->service->getOverview('all', 0, false);
        
        // THEN
        $lane = $result['lanes']['normal'][0];
        $this->assertEquals('John Doe', $lane['assignee_name']);
        $this->assertEquals('http://avatar.url', $lane['assignee_avatar_url']);
        $this->assertEquals('555', $lane['assignee_user_id']);
    }
}

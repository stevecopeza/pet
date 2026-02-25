<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Application\Work;

use PHPUnit\Framework\TestCase;
use Pet\Application\Work\Projection\WorkItemProjector;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;
use Pet\Domain\Delivery\ValueObject\ProjectState;
use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;

class WorkItemPriorityIntegrationTest extends TestCase
{
    private $workItemRepository;
    private $departmentQueueRepository;
    private $customerRepository;
    private $advisorySignalRepository;
    private $priorityScoringService;
    private $slaClockCalculator;
    private $departmentResolver;
    private $projector;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->departmentQueueRepository = $this->createMock(DepartmentQueueRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->advisorySignalRepository = $this->createMock(AdvisorySignalRepository::class);
        
        $this->priorityScoringService = new PriorityScoringService();
        // Mock DepartmentResolver to avoid external dependencies if any (though it's usually pure logic)
        // But the previous code used `new DepartmentResolver()`. Let's stick to that if it's simple.
        // Wait, DepartmentResolver might need repositories? 
        // Let's check DepartmentResolver constructor.
        $this->departmentResolver = $this->createMock(DepartmentResolver::class);
        $this->departmentResolver->method('resolveForProjectTask')->willReturn('delivery');
        $this->departmentResolver->method('resolveForTicket')->willReturn('support');

        $this->slaClockCalculator = new SlaClockCalculator(
            $this->workItemRepository,
            $this->priorityScoringService,
            $this->advisorySignalRepository
        );

        $this->projector = new WorkItemProjector(
            $this->workItemRepository,
            $this->departmentQueueRepository,
            $this->departmentResolver,
            $this->slaClockCalculator,
            $this->customerRepository
        );
    }

    public function testTicketCreatedGeneratesPriorityScoreFromCustomerTier()
    {
        $customerId = 101;
        $customer = new Customer(
            'Test Client',
            'test@client.com',
            $customerId,
            null,
            'active',
            1,
            ['tier' => 2]
        );

        $this->customerRepository->method('findById')
            ->with($customerId)
            ->willReturn($customer);

        $ticket = new \Pet\Domain\Support\Entity\Ticket(
            customerId: $customerId,
            subject: 'High Value Ticket',
            description: 'Priority scoring via ticket backbone',
            id: 5001
        );

        $event = new \Pet\Domain\Support\Event\TicketCreated($ticket);

        $savedWorkItem = null;
        $this->workItemRepository->expects($this->atLeastOnce())
            ->method('save')
            ->willReturnCallback(function (WorkItem $item) use (&$savedWorkItem) {
                $savedWorkItem = $item;
            });

        $this->projector->onTicketCreated($event);

        $this->assertNotNull($savedWorkItem);
        $this->assertEquals('ticket', $savedWorkItem->getSourceType());
        $this->assertEquals(2, $savedWorkItem->getClientTier());
        $this->assertGreaterThan(0.0, $savedWorkItem->getPriorityScore());
    }

    public function testManagerOverrideUpdatesScore()
    {
        // For this test, we are simulating the update flow which usually happens via a Command Handler.
        // But here we can test that IF a WorkItem has an override, PriorityScoringService respects it.
        // We can use the service directly for this part.
        
        $workItem = WorkItem::create(
            'override-test-id',
            'admin', // admin source type
            'manual-1',
            'dept_admin',
            50.0,
            'active',
            new \DateTimeImmutable()
        );

        // Apply Override
        $workItem->setManagerPriorityOverride(100.0);
        
        // Calculate
        $score = $this->priorityScoringService->calculate($workItem);
        
        // Assert
        // Base 0 + Override 100 = 100
        $this->assertEquals(100.0, $score);
    }
}

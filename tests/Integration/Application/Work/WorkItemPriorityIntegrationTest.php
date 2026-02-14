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

    public function testProjectTaskCreatedGeneratesCorrectPriorityScore()
    {
        // 1. Arrange Customer with Tier 2 (Silver)
        $customerId = 101;
        $customer = new Customer(
            'Test Client',
            'test@client.com',
            $customerId,
            null,
            'active',
            1,
            ['tier' => 2] // Tier 2 = +25 points
        );

        $this->customerRepository->method('findById')
            ->with($customerId)
            ->willReturn($customer);

        // 2. Arrange Project and Task
        // Revenue 15000 -> +10 points
        $project = new Project(
            $customerId,
            'High Value Project',
            100.0,
            null,
            null, // State defaults to planned
            15000.00
        );

        $task = new Task('Critical Task', 10.0, false, 999);
        $event = new ProjectTaskCreated($project, $task);

        // 3. Expect WorkItem Save
        // We expect save to be called twice (initial, then updated score)
        // We want to capture the final state.
        $savedWorkItem = null;
        $this->workItemRepository->expects($this->atLeastOnce())
            ->method('save')
            ->willReturnCallback(function (WorkItem $item) use (&$savedWorkItem) {
                $savedWorkItem = $item;
            });

        // 4. Act
        $this->projector->onProjectTaskCreated($event);

        // 5. Assert
        $this->assertNotNull($savedWorkItem);
        
        // Check Commercial Data
        $this->assertEquals(15000.00, $savedWorkItem->getRevenue(), 'Revenue should be 15000');
        $this->assertEquals(2, $savedWorkItem->getClientTier(), 'Tier should be 2');

        // Check Priority Score
        // Base: 0
        // Tier 2: 25.0
        // Revenue > 10000: 10.0
        // Total Expected: 35.0
        $this->assertEquals(35.0, $savedWorkItem->getPriorityScore(), 'Priority Score should be 35.0');
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

<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Safety;

use PHPUnit\Framework\TestCase;
use Pet\UI\Rest\Controller\WorkController;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Work\Service\CapacityCalendar;
use WP_REST_Request;

class AdvisoryReadDoesNotMutateTest extends TestCase
{
    private $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // STRICT SAFETY CHECK: No mutations allowed on read
        $this->wpdb->expects($this->never())->method('insert');
        $this->wpdb->expects($this->never())->method('update');
        $this->wpdb->expects($this->never())->method('delete');
        $this->wpdb->expects($this->never())->method('replace');
    }

    public function testWorkItemReadDoesNotGenerateAdvisorySignals()
    {
        // 1. Mock Dependencies
        $workItemRepo = $this->createMock(WorkItemRepository::class);
        $signalRepo = $this->createMock(AdvisorySignalRepository::class);
        $featureFlags = $this->createMock(FeatureFlagService::class);
        $capacityCalendar = $this->createMock(CapacityCalendar::class);

        // 2. Setup Feature Flag
        // Even if Advisory is enabled, reading work items should NOT trigger generation
        $featureFlags->method('isQueueVisibilityEnabled')->willReturn(true);
        $featureFlags->method('isAdvisoryEnabled')->willReturn(true);

        // 3. Setup Work Items Return
        // Return some work items to ensure mapping logic runs
        
        // If items empty, findByWorkItemId won't be called. Let's return one item.
        $mockItem = $this->createMock(\Pet\Domain\Work\Entity\WorkItem::class);
        $mockItem->method('getId')->willReturn('wi-1');
        
        // Configure the mock
        $workItemRepo->expects($this->once())->method('findByAssignedUser')->willReturn([$mockItem]);

        // Expect READ only
        // Note: The controller uses findByWorkItemIds (plural)
        $signalRepo->expects($this->once())->method('findByWorkItemIds')->with(['wi-1'])->willReturn([]);
        // Expect NO writes (save/delete/clear)
        $signalRepo->expects($this->never())->method('save');
        $signalRepo->expects($this->never())->method('clearForWorkItem');

        $controller = new WorkController(
            $workItemRepo,
            $signalRepo,
            $featureFlags,
            $capacityCalendar
        );

        // 4. Call Endpoint
        $request = new WP_REST_Request('GET', '/pet/v1/work/my-items');
        $response = $controller->getMyWorkItems($request);

        $this->assertEquals(200, $response->get_status());
    }
}

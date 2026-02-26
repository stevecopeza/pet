<?php

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Controller\WorkController;
use Pet\UI\Rest\Controller\WPMocks;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Domain\Work\Entity\WorkItem;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class AdvisoryPayloadFlagOffTest extends TestCase
{
    private $workItemRepository;
    private $signalRepository;
    private $featureFlags;
    private $capacityCalendar;
    private $controller;

    protected function setUp(): void
    {
        WPMocks::reset();

        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->signalRepository = $this->createMock(AdvisorySignalRepository::class);
        $this->featureFlags = $this->createMock(FeatureFlagService::class);
        $this->capacityCalendar = $this->createMock(CapacityCalendar::class);

        $this->controller = new WorkController(
            $this->workItemRepository,
            $this->signalRepository,
            $this->featureFlags,
            $this->capacityCalendar
        );
    }

    public function testAdvisoryDataOmittedWhenDisabled(): void
    {
        // GIVEN advisory is disabled
        $this->featureFlags->method('isAdvisoryEnabled')->willReturn(false);

        // AND we have a work item
        $workItem = WorkItem::create(
            'wi-1',
            'ticket',
            '100',
            'support',
            50.0,
            'active',
            new \DateTimeImmutable()
        );
        $this->workItemRepository->method('findByAssignedUser')->willReturn([$workItem]);

        // WHEN getting work items
        $request = new WP_REST_Request();
        $response = $this->controller->getMyWorkItems($request);

        // THEN signals key should be missing from the response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertArrayNotHasKey('signals', $data[0], 'Signals key should be omitted when disabled');
    }

    public function testAdvisoryDataIncludedWhenEnabled(): void
    {
        // GIVEN advisory is enabled
        $this->featureFlags->method('isAdvisoryEnabled')->willReturn(true);

        // AND we have a work item
        $workItem = WorkItem::create(
            'wi-1',
            'ticket',
            '100',
            'support',
            50.0,
            'active',
            new \DateTimeImmutable()
        );
        $this->workItemRepository->method('findByAssignedUser')->willReturn([$workItem]);
        
        // AND we have signals (repo will return empty array by default mock, but that's fine, we just check key existence)
        $this->signalRepository->method('findByWorkItemIds')->willReturn([]);

        // WHEN getting work items
        $request = new WP_REST_Request();
        $response = $this->controller->getMyWorkItems($request);

        // THEN signals key should be present
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('signals', $data[0], 'Signals key should be present when enabled');
    }
}

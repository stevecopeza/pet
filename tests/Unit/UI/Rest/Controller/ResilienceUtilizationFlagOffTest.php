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
use PHPUnit\Framework\TestCase;

class ResilienceUtilizationFlagOffTest extends TestCase
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

    public function testUtilizationRouteNotRegisteredWhenDisabled(): void
    {
        // GIVEN resilience indicators are disabled
        $this->featureFlags->method('isResilienceIndicatorsEnabled')->willReturn(false);
        // Queue visibility can be anything, let's say false to isolate
        $this->featureFlags->method('isQueueVisibilityEnabled')->willReturn(false);

        // WHEN registering routes
        $this->controller->registerRoutes();

        // THEN utilization route should NOT be registered
        $found = false;
        foreach (WPMocks::$registerRestRouteCalls as $call) {
            if (strpos($call['route'], '/utilization') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Utilization route should not be registered when disabled');
    }

    public function testUtilizationRouteRegisteredWhenEnabled(): void
    {
        // GIVEN resilience indicators are enabled
        $this->featureFlags->method('isResilienceIndicatorsEnabled')->willReturn(true);
        $this->featureFlags->method('isQueueVisibilityEnabled')->willReturn(false);

        // WHEN registering routes
        $this->controller->registerRoutes();

        // THEN utilization route should be registered
        $found = false;
        foreach (WPMocks::$registerRestRouteCalls as $call) {
            if (strpos($call['route'], '/utilization') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Utilization route should be registered when enabled');
    }
}

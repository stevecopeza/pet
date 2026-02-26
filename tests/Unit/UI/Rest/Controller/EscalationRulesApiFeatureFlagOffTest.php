<?php

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Controller\EscalationRuleController;
use Pet\UI\Rest\Controller\WPMocks;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;
use PHPUnit\Framework\TestCase;

class EscalationRulesApiFeatureFlagOffTest extends TestCase
{
    private $repository;
    private $featureFlags;
    private $controller;

    protected function setUp(): void
    {
        WPMocks::reset();

        $this->repository = $this->createMock(EscalationRuleRepository::class);
        $this->featureFlags = $this->createMock(FeatureFlagService::class);

        $this->controller = new EscalationRuleController(
            $this->repository,
            $this->featureFlags
        );
    }

    public function testRoutesNotRegisteredWhenEscalationEngineDisabled(): void
    {
        // GIVEN escalation engine is disabled
        $this->featureFlags->method('isEscalationEngineEnabled')->willReturn(false);

        // WHEN registering routes
        $this->controller->registerRoutes();

        // THEN no routes should be registered
        $this->assertEmpty(WPMocks::$registerRestRouteCalls);
    }

    public function testRoutesRegisteredWhenEscalationEngineEnabled(): void
    {
        // GIVEN escalation engine is enabled
        $this->featureFlags->method('isEscalationEngineEnabled')->willReturn(true);

        // WHEN registering routes
        $this->controller->registerRoutes();

        // THEN routes should be registered
        $this->assertNotEmpty(WPMocks::$registerRestRouteCalls);
        
        // Verify escalation-rules route is present
        $found = false;
        foreach (WPMocks::$registerRestRouteCalls as $call) {
            if ($call['route'] === '/escalation-rules') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Escalation rules route should be registered');
    }
}

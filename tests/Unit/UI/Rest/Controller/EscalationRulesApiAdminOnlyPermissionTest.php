<?php

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Controller\EscalationRuleController;
use Pet\UI\Rest\Controller\WPMocks;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;
use PHPUnit\Framework\TestCase;

class EscalationRulesApiAdminOnlyPermissionTest extends TestCase
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

    public function testCheckPermissionReturnsTrueForAdmin(): void
    {
        // GIVEN user has manage_options
        WPMocks::$currentUserCan['manage_options'] = true;

        // WHEN checking permission
        $result = $this->controller->checkPermission();

        // THEN it should return true
        $this->assertTrue($result);
    }

    public function testCheckPermissionReturnsFalseForNonAdmin(): void
    {
        // GIVEN user does NOT have manage_options
        WPMocks::$currentUserCan['manage_options'] = false;

        // WHEN checking permission
        $result = $this->controller->checkPermission();

        // THEN it should return false
        $this->assertFalse($result);
    }
}

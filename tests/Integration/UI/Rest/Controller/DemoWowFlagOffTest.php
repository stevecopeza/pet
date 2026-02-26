<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller {
    if (!function_exists('Pet\UI\Rest\Controller\admin_url')) {
        function admin_url($path = '') {
            return 'http://test.local/wp-admin/' . $path;
        }
    }
}

namespace Pet\Tests\Integration\UI\Rest\Controller {

use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Controller\DashboardController;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class DemoWowFlagOffTest extends TestCase
{
    private $featureFlagService;
    private $controller;

    protected function setUp(): void
    {
        $this->featureFlagService = $this->createMock(FeatureFlagService::class);
        
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('countActive')->willReturn(0);
        $projectRepo->method('sumSoldHours')->willReturn(0.0);

        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('countPending')->willReturn(0);
        $quoteRepo->method('sumRevenue')->willReturn(0.0);

        $activityRepo = $this->createMock(ActivityLogRepository::class);
        $activityRepo->method('findAll')->willReturn([]);

        $timeEntryRepo = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepo->method('sumBillableHours')->willReturn(0.0);

        $personSkillRepo = $this->createMock(PersonSkillRepository::class);
        $personSkillRepo->method('getAverageProficiencyBySkill')->willReturn([]);

        $personKpiRepo = $this->createMock(PersonKpiRepository::class);
        $personKpiRepo->method('getAverageAchievementByKpi')->willReturn([]);

        $escalationRepo = $this->createMock(EscalationRuleRepository::class);
        $escalationRepo->method('getDashboardStats')->willReturn(['enabledCount' => 0, 'totalCount' => 0]);

        $slaClockRepo = $this->createMock(SlaClockStateRepository::class);
        $slaClockRepo->method('getDashboardStats')->willReturn(['warningCount' => 0, 'breachedCount' => 0]);

        $ticketRepo = $this->createMock(TicketRepository::class);
        $ticketRepo->method('findActive')->willReturn([]);

        $this->controller = new DashboardController(
            $projectRepo,
            $quoteRepo,
            $activityRepo,
            $timeEntryRepo,
            $personSkillRepo,
            $personKpiRepo,
            $escalationRepo,
            $slaClockRepo,
            $ticketRepo,
            $this->featureFlagService
        );
    }

    public function testDemoWowKeyAbsentWhenFlagsDisabled(): void
    {
        // GIVEN both flags are disabled
        $this->featureFlagService->method('isEscalationEngineEnabled')->willReturn(false);
        $this->featureFlagService->method('isHelpdeskEnabled')->willReturn(false);

        // WHEN fetching dashboard data
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $data = $response->get_data();

        // THEN demoWow key should be absent
        $this->assertArrayNotHasKey('demoWow', $data);
    }

    public function testDemoWowKeyPresentWhenEscalationEnabled(): void
    {
        // GIVEN escalation is enabled
        $this->featureFlagService->method('isEscalationEngineEnabled')->willReturn(true);
        $this->featureFlagService->method('isHelpdeskEnabled')->willReturn(false);

        // WHEN fetching dashboard data
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $data = $response->get_data();

        // THEN demoWow key should be present
        $this->assertArrayHasKey('demoWow', $data);
    }

    public function testDemoWowKeyPresentWhenHelpdeskEnabled(): void
    {
        // GIVEN helpdesk is enabled
        $this->featureFlagService->method('isEscalationEngineEnabled')->willReturn(false);
        $this->featureFlagService->method('isHelpdeskEnabled')->willReturn(true);

        // WHEN fetching dashboard data
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $data = $response->get_data();

        // THEN demoWow key should be present
        $this->assertArrayHasKey('demoWow', $data);
    }
}
}

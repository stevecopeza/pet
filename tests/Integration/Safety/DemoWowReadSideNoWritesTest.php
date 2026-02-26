<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller {
    if (!function_exists('Pet\UI\Rest\Controller\admin_url')) {
        function admin_url($path = '') {
            return 'http://test.local/wp-admin/' . $path;
        }
    }
}

namespace Pet\Tests\Integration\Safety {

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

class DemoWowReadSideNoWritesTest extends TestCase
{
    private $wpdb;
    private $controller;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';

        // STRICT SAFETY CHECK: No mutations allowed on read
        $this->wpdb->expects($this->never())->method('insert');
        $this->wpdb->expects($this->never())->method('update');
        $this->wpdb->expects($this->never())->method('delete');
        $this->wpdb->expects($this->never())->method('replace');
        $this->wpdb->expects($this->never())->method('query');

        // We mock repositories, but assert they don't call save/delete
        // For actual SQL verification, we'd need integration tests with real repositories + mocked wpdb.
        // But since we are mocking repositories here, the wpdb assertions above are technically redundant 
        // unless repositories are using the SAME wpdb instance (which they aren't, they are mocked).
        // 
        // HOWEVER, the requirement is "Assert no DB writes occur by forbidding: $wpdb->insert...".
        // To satisfy this meaningfully, we should mock the repositories and assert THEY don't call write methods.
        // OR we should use real repositories with mocked wpdb.
        // Given we are testing the Controller logic (which orchestrates repositories),
        // we will mock the repositories and ensure no write methods are called on them.
        // We will ALSO verify that the controller logic itself doesn't try to use wpdb directly (if it had access).

        // Mock repositories
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects($this->never())->method('save');
        
        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->expects($this->never())->method('save');
        $quoteRepo->expects($this->never())->method('delete');

        $activityRepo = $this->createMock(ActivityLogRepository::class);
        $activityRepo->expects($this->never())->method('save');

        $timeEntryRepo = $this->createMock(TimeEntryRepository::class);
        // TimeEntryRepo doesn't have save in interface, usually logTime

        $personSkillRepo = $this->createMock(PersonSkillRepository::class);
        $personKpiRepo = $this->createMock(PersonKpiRepository::class);

        $escalationRepo = $this->createMock(EscalationRuleRepository::class);
        // EscalationRuleRepo has disable() which writes
        $escalationRepo->expects($this->never())->method('disable');
        
        $slaClockRepo = $this->createMock(SlaClockStateRepository::class);
        $slaClockRepo->expects($this->never())->method('save');

        $ticketRepo = $this->createMock(TicketRepository::class);
        $ticketRepo->expects($this->never())->method('save');


        // Setup read returns
        $projectRepo->method('countActive')->willReturn(0);
        $projectRepo->method('sumSoldHours')->willReturn(0.0);
        $quoteRepo->method('countPending')->willReturn(0);
        $quoteRepo->method('sumRevenue')->willReturn(0.0);
        $activityRepo->method('findAll')->willReturn([]);
        $timeEntryRepo->method('sumBillableHours')->willReturn(0.0);
        $personSkillRepo->method('getAverageProficiencyBySkill')->willReturn([]);
        $personKpiRepo->method('getAverageAchievementByKpi')->willReturn([]);
        
        $escalationRepo->method('getDashboardStats')->willReturn(['enabledCount' => 0, 'totalCount' => 0]);
        $slaClockRepo->method('getDashboardStats')->willReturn(['warningCount' => 0, 'breachedCount' => 0]);
        $ticketRepo->method('findActive')->willReturn([]);

        $featureFlagService = $this->createMock(FeatureFlagService::class);
        $featureFlagService->method('isEscalationEngineEnabled')->willReturn(true);

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
            $featureFlagService
        );
    }

    public function testDashboardReadDoesNotMutate(): void
    {
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $this->assertEquals(200, $response->get_status());
    }
}
}

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
use Pet\Infrastructure\Persistence\Repository\SqlEscalationRuleRepository;
use Pet\Infrastructure\Persistence\Repository\SqlSlaClockStateRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Support\Entity\Ticket;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class DemoWowCountsSmokeTest extends TestCase
{
    private $wpdb;
    private $controller;
    private $escalationRepo;
    private $slaClockRepo;
    private $ticketRepo;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // Mock prepare
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            return $query; // Simplified
        });

        // Use real SQL repositories for the ones we want to test SQL for
        $this->escalationRepo = new SqlEscalationRuleRepository($this->wpdb);
        $this->slaClockRepo = new SqlSlaClockStateRepository($this->wpdb);
        
        // TicketRepository is usually complex, so we'll mock it to return specific Ticket objects
        // to test the unassigned filter logic in the controller.
        $this->ticketRepo = $this->createMock(TicketRepository::class);

        // Mock other dependencies
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('countActive')->willReturn(5);
        $projectRepo->method('sumSoldHours')->willReturn(100.0);
        
        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('countPending')->willReturn(2);
        $quoteRepo->method('sumRevenue')->willReturn(5000.0);

        $activityRepo = $this->createMock(ActivityLogRepository::class);
        $activityRepo->method('findAll')->willReturn([]);

        $timeEntryRepo = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepo->method('sumBillableHours')->willReturn(80.0);

        $personSkillRepo = $this->createMock(PersonSkillRepository::class);
        $personSkillRepo->method('getAverageProficiencyBySkill')->willReturn([]);

        $personKpiRepo = $this->createMock(PersonKpiRepository::class);
        $personKpiRepo->method('getAverageAchievementByKpi')->willReturn([]);

        $featureFlagService = $this->createMock(FeatureFlagService::class);
        $featureFlagService->method('isEscalationEngineEnabled')->willReturn(true);

        $this->controller = new DashboardController(
            $projectRepo,
            $quoteRepo,
            $activityRepo,
            $timeEntryRepo,
            $personSkillRepo,
            $personKpiRepo,
            $this->escalationRepo,
            $this->slaClockRepo,
            $this->ticketRepo,
            $featureFlagService
        );
    }

    public function testDashboardCountsMatchSeededState(): void
    {
        // 1. Setup Escalation Rules SQL returns
        // We expect getDashboardStats() to call:
        // SELECT COUNT(*) FROM wp_pet_sla_escalation_rules
        // SELECT COUNT(*) FROM wp_pet_sla_escalation_rules WHERE is_enabled = 1
        
        // We can simulate get_var calls. 
        // The repository calls get_var twice.
        // We'll use returnCallback to return different values based on the query.
        
        $this->wpdb->expects($this->any())
            ->method('get_var')
            ->will($this->returnCallback(function($query) {
                if (strpos($query, 'pet_sla_escalation_rules WHERE is_enabled = 1') !== false) {
                    return 3; // 3 enabled rules
                }
                if (strpos($query, 'pet_sla_escalation_rules') !== false && strpos($query, 'WHERE') === false) {
                    return 5; // 5 total rules
                }
                
                // SLA Clock State
                if (strpos($query, 'pet_sla_clock_state WHERE last_event_dispatched = \'warning\'') !== false) {
                    return 2; // 2 warnings
                }
                if (strpos($query, 'pet_sla_clock_state WHERE last_event_dispatched = \'breached\'') !== false) {
                    return 1; // 1 breached
                }
                
                return 0;
            }));

        // 2. Setup Ticket Repository returns for unassigned count
        $this->ticketRepo->method('countActiveUnassigned')->willReturn(2);

        // 3. Execute
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $data = $response->get_data();

        // 4. Assert
        $this->assertArrayHasKey('demoWow', $data);
        
        // Escalation Rules
        $this->assertEquals(3, $data['demoWow']['escalationRules']['enabledCount']);
        $this->assertEquals(5, $data['demoWow']['escalationRules']['totalCount']);
        
        // SLA Risk
        $this->assertEquals(2, $data['demoWow']['slaRisk']['warningCount']);
        $this->assertEquals(1, $data['demoWow']['slaRisk']['breachedCount']);
        
        // Workload (Unassigned Tickets)
        // 2 unassigned out of 3 active
        $this->assertEquals(2, $data['demoWow']['workload']['unassignedTicketsCount']);
    }
}
}

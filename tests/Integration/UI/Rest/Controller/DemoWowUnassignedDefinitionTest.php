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
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Infrastructure\Persistence\Repository\SqlEscalationRuleRepository;
use Pet\Infrastructure\Persistence\Repository\SqlSlaClockStateRepository;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Tests\Stubs\InMemoryWpdb;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class DemoWowUnassignedDefinitionTest extends TestCase
{
    private $wpdb;
    private $controller;
    private $ticketRepo;

    protected function setUp(): void
    {
        // Use InMemoryWpdb to simulate database
        $this->wpdb = new InMemoryWpdb();
        $this->wpdb->prefix = 'wp_';
        
        // Initialize tables
        $this->wpdb->query("CREATE TABLE wp_pet_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255),
            status VARCHAR(50),
            owner_user_id INT NULL,
            queue_id VARCHAR(50) NULL
        )");
        
        // We also need escalation/sla tables since controller uses them for stats
        $this->wpdb->query("CREATE TABLE wp_pet_sla_escalation_rules (id INT)");
        $this->wpdb->query("CREATE TABLE wp_pet_sla_clock_state (id INT)");

        // Real TicketRepository using InMemoryWpdb
        $this->ticketRepo = new SqlTicketRepository($this->wpdb);

        // Mock other dependencies
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

        $escalationRepo = new SqlEscalationRuleRepository($this->wpdb);
        $slaClockRepo = new SqlSlaClockStateRepository($this->wpdb);

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
            $this->ticketRepo,
            $featureFlagService
        );
    }

    public function testUnassignedCountOnlyIncludesActiveTicketsWithNoUserAndNoQueue(): void
    {
        // Seed tickets
        
        // 1. Valid Unassigned: Active, No Owner, No Queue => Should count
        $this->wpdb->insert('wp_pet_tickets', [
            'subject' => 'Valid Unassigned',
            'status' => 'new',
            'owner_user_id' => null,
            'queue_id' => null
        ]);

        // 2. Assigned to User: Active, Has Owner, No Queue => Should NOT count
        $this->wpdb->insert('wp_pet_tickets', [
            'subject' => 'Assigned to User',
            'status' => 'in_progress',
            'owner_user_id' => 123,
            'queue_id' => null
        ]);

        // 3. Assigned to Queue: Active, No Owner, Has Queue => Should NOT count
        $this->wpdb->insert('wp_pet_tickets', [
            'subject' => 'Assigned to Queue',
            'status' => 'new',
            'owner_user_id' => null,
            'queue_id' => 'dept_support'
        ]);

        // 4. Resolved: Resolved, No Owner, No Queue => Should NOT count
        $this->wpdb->insert('wp_pet_tickets', [
            'subject' => 'Resolved',
            'status' => 'resolved',
            'owner_user_id' => null,
            'queue_id' => null
        ]);

        // 5. Closed: Closed, No Owner, No Queue => Should NOT count
        $this->wpdb->insert('wp_pet_tickets', [
            'subject' => 'Closed',
            'status' => 'closed',
            'owner_user_id' => null,
            'queue_id' => null
        ]);

        // Execute
        $request = new WP_REST_Request('GET', '/pet/v1/dashboard');
        $response = $this->controller->getDashboardData($request);
        $data = $response->get_data();

        // Assert
        $this->assertArrayHasKey('demoWow', $data);
        $this->assertEquals(1, $data['demoWow']['workload']['unassignedTicketsCount'], 
            'Only the active ticket with no owner and no queue should be counted.');
    }
}
}

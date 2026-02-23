<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\Application\System\Service\DemoSeedService;
use Pet\Application\System\Service\DemoPurgeService;

final class DemoSeedValidationTest extends TestCase
{
    private \DI\Container $c;

    protected function setUp(): void
    {
        $this->c = ContainerFactory::create();
        $eventBus = $this->c->get(\Pet\Domain\Event\EventBus::class);

        $ticketCreatedListener = $this->c->get(\Pet\Application\Activity\Listener\TicketCreatedListener::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, $ticketCreatedListener);

        $quoteAcceptedListener = $this->c->get(\Pet\Application\Commercial\Listener\QuoteAcceptedListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $quoteAcceptedListener);

        $createProjectFromQuoteListener = $this->c->get(\Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createProjectFromQuoteListener);

        $createForecastFromQuoteListener = $this->c->get(\Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createForecastFromQuoteListener);

        $feedProjectionListener = $this->c->get(\Pet\Application\Projection\Listener\FeedProjectionListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, [$feedProjectionListener, 'onQuoteAccepted']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectCreated::class, [$feedProjectionListener, 'onProjectCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$feedProjectionListener, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketWarningEvent::class, [$feedProjectionListener, 'onTicketWarning']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketBreachedEvent::class, [$feedProjectionListener, 'onTicketBreached']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\EscalationTriggeredEvent::class, [$feedProjectionListener, 'onEscalationTriggered']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\MilestoneCompletedEvent::class, [$feedProjectionListener, 'onMilestoneCompleted']);

        $workItemProjector = $this->c->get(\Pet\Application\Work\Projection\WorkItemProjector::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$workItemProjector, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketAssigned::class, [$workItemProjector, 'onTicketAssigned']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectTaskCreated::class, [$workItemProjector, 'onProjectTaskCreated']);
    }

    public function testCommercialChainToProjects(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $q1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_quotes WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q1 Website Implementation & Advisory'));
        $this->assertNotNull($q1);
        $this->assertEquals('accepted', $q1->state);
        $contract = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_contracts WHERE quote_id = %d LIMIT 1", (int)$q1->id));
        $this->assertNotNull($contract);
        $baseline = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_baselines WHERE contract_id = %d LIMIT 1", (int)$contract->id));
        $this->assertNotNull($baseline);
        $components = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pet_baseline_components WHERE baseline_id = %d", (int)$baseline->id));
        $this->assertGreaterThanOrEqual(1, $components);
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_projects WHERE source_quote_id = %d LIMIT 1", (int)$q1->id));
        $this->assertNotNull($project);
    }

    public function testSlaSnapshotTicketsAndClockMatrix(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $project = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}pet_projects WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 1");
        $this->assertNotNull($project);
        $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_contract_sla_snapshots WHERE project_id = %d LIMIT 1", (int)$project->id));
        $this->assertNotNull($snapshot);
        $tickets = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pet_tickets ORDER BY id DESC LIMIT 7");
        $this->assertCount(7, $tickets);
        foreach ($tickets as $t) {
            $this->assertNotEmpty($t->sla_snapshot_id);
            $this->assertNotEmpty($t->response_due_at);
            $this->assertNotEmpty($t->resolution_due_at);
            $clock = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_sla_clock_state WHERE ticket_id = %d", (int)$t->id));
            $this->assertNotNull($clock);
        }
        $nearBreach = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_sla_clock_state WHERE escalation_stage >= 1");
        $paused = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_sla_clock_state WHERE paused_flag = 1");
        $breached = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_sla_clock_state WHERE breach_at IS NOT NULL");
        $closedCompliant = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_tickets WHERE status = 'closed' AND resolved_at IS NOT NULL");
        $unassignedCritical = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_tickets WHERE priority = 'critical' AND status = 'open'");
        $this->assertGreaterThanOrEqual(1, $nearBreach);
        $this->assertGreaterThanOrEqual(1, $paused);
        $this->assertGreaterThanOrEqual(1, $breached);
        $this->assertGreaterThanOrEqual(1, $closedCompliant);
        $this->assertGreaterThanOrEqual(1, $unassignedCritical);
    }

    public function testWorkOrchestrationMatrix(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $ticketItems = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_work_items WHERE source_type = 'ticket'");
        $unassigned = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_work_items WHERE assigned_user_id IS NULL OR assigned_user_id = ''");
        $escalated = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_work_items WHERE escalation_level >= 1");
        $reassigned = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_department_queues WHERE picked_up_at IS NOT NULL AND picked_up_at > entered_queue_at");
        $this->assertGreaterThanOrEqual(1, $ticketItems);
        $this->assertGreaterThanOrEqual(1, $unassigned);
        $this->assertGreaterThanOrEqual(1, $escalated);
        $this->assertGreaterThanOrEqual(1, $reassigned);
    }

    public function testTimeEntriesMatrixAndPurgeSurvival(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $entriesTable = $wpdb->prefix . 'pet_time_entries';
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $entriesTable WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s", $seedRunId));
        $submitted = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $entriesTable WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s AND status = 'submitted'", $seedRunId));
        $locked = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $entriesTable WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s AND status = 'locked'", $seedRunId));
        $linkedTicket = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $entriesTable WHERE JSON_EXTRACT(malleable_data, '$.seed_run_id') = %s AND JSON_EXTRACT(malleable_data, '$.ticket_id') IS NOT NULL", $seedRunId));
        $this->assertEquals(20, $total);
        $this->assertEquals(8, $submitted);
        $this->assertEquals(2, $locked);
        $this->assertGreaterThanOrEqual(1, $linkedTicket);
        /** @var DemoPurgeService $purge */
        $purge = $this->c->get(DemoPurgeService::class);
        $purge->purgeBySeedRunId($seedRunId);
        $remainingSubmitted = (int)$wpdb->get_var("SELECT COUNT(*) FROM $entriesTable WHERE status = 'submitted'");
        $this->assertGreaterThanOrEqual(8, $remainingSubmitted);
    }

    public function testBillingQbMatrixAndFailedScenarioVisibility(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $export = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}pet_billing_exports ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($export);
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pet_billing_export_items WHERE export_id = %d", (int)$export->id));
        $this->assertNotEmpty($items);
        $hasTime = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pet_billing_export_items WHERE export_id = %d AND source_type = %s", (int)$export->id, 'time_entry'));
        $hasBaseline = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pet_billing_export_items WHERE export_id = %d AND source_type IN ('baseline','baseline_component')", (int)$export->id));
        $this->assertGreaterThanOrEqual(1, $hasTime);
        $this->assertGreaterThanOrEqual(1, $hasBaseline);
        $invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pet_qb_invoices ORDER BY id ASC");
        $this->assertCount(3, $invoices);
        $payments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pet_qb_payments ORDER BY id ASC");
        $this->assertGreaterThanOrEqual(2, count($payments));
        $mappingExport = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pet_external_mappings WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d", 'quickbooks', 'billing_export', (int)$export->id));
        $this->assertGreaterThanOrEqual(1, $mappingExport);
        $failedRun = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pet_integration_runs WHERE `system` = %s AND status = %s", 'quickbooks', 'failed'));
        $this->assertGreaterThanOrEqual(1, $failedRun);
    }

    public function testSeedCompletenessAndRelationshipsBasic(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $summary = $seed->seedFull($seedRunId, 'demo_full');
        $this->assertArrayHasKey('employees', $summary);
        global $wpdb;
        $employees = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_employees");
        $this->assertGreaterThanOrEqual(6, $employees);

        $customers = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_customers");
        $sites = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_sites");
        $contacts = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_contacts");
        $this->assertGreaterThanOrEqual(2, $customers);
        $this->assertGreaterThanOrEqual(3, $sites);
        $this->assertGreaterThanOrEqual(4, $contacts);

        $quotesTable = $wpdb->prefix . 'pet_quotes';
        $q1 = $wpdb->get_row($wpdb->prepare("SELECT id, title, state FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q1 Website Implementation & Advisory'), ARRAY_A);
        $this->assertNotNull($q1);
        $q2 = $wpdb->get_row($wpdb->prepare("SELECT id, title, state FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q2 ERP Migration Plan'), ARRAY_A);
        $this->assertNotNull($q2);
        $q3 = $wpdb->get_row($wpdb->prepare("SELECT id, title, state FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q3 Managed Support'), ARRAY_A);
        $this->assertNotNull($q3);
        $q4 = $wpdb->get_row($wpdb->prepare("SELECT id, title, state FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q4 Catalog Services'), ARRAY_A);
        $this->assertNotNull($q4);
        $componentsTable = $wpdb->prefix . 'pet_quote_components';
        $recurringTable = $wpdb->prefix . 'pet_quote_recurring_services';
        $catalogTable = $wpdb->prefix . 'pet_quote_catalog_items';
        $milestonesTable = $wpdb->prefix . 'pet_quote_milestones';

        $q1CompTypes = $wpdb->get_col($wpdb->prepare("SELECT type FROM $componentsTable WHERE quote_id = %d", $q1['id']));
        $this->assertContains('implementation', $q1CompTypes);
        $this->assertContains('catalog', $q1CompTypes);

        $q2CompTypes = $wpdb->get_col($wpdb->prepare("SELECT type FROM $componentsTable WHERE quote_id = %d", $q2['id']));
        $this->assertEquals(['implementation'], array_values(array_unique($q2CompTypes)));

        $q3CompTypes = $wpdb->get_col($wpdb->prepare("SELECT type FROM $componentsTable WHERE quote_id = %d", $q3['id']));
        $this->assertEquals(['recurring'], array_values(array_unique($q3CompTypes)));
        $q3CompId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $componentsTable WHERE quote_id = %d AND type = 'recurring' LIMIT 1", $q3['id']));
        $this->assertNotEmpty($wpdb->get_row($wpdb->prepare("SELECT * FROM $recurringTable WHERE component_id = %d", $q3CompId)));

        $q4CompTypes = $wpdb->get_col($wpdb->prepare("SELECT type FROM $componentsTable WHERE quote_id = %d", $q4['id']));
        $this->assertEquals(['catalog'], array_values(array_unique($q4CompTypes)));
        $q4CompId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $componentsTable WHERE quote_id = %d AND type = 'catalog' LIMIT 1", $q4['id']));
        $this->assertGreaterThanOrEqual(1, (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $catalogTable WHERE component_id = %d", $q4CompId)));
    }

    public function testPurgeCorrectnessPreservesImmutable(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        /** @var DemoPurgeService $purge */
        $purge = $this->c->get(DemoPurgeService::class);
        $summary = $purge->purgeBySeedRunId($seedRunId);
        $eventStreamCount = (int)$summary['event_stream_preserved'];
        $this->assertGreaterThanOrEqual(0, $eventStreamCount);

        global $wpdb;
        // Accepted quotes should remain
        $acceptedQuotes = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_quotes WHERE state = 'accepted'");
        $this->assertGreaterThanOrEqual(1, $acceptedQuotes);
    }

    public function testPurgeRemovesSeededRowsByRegistry(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $seed->seedFull($seedRunId, 'demo_full');
        global $wpdb;
        $projectsTable = $wpdb->prefix . 'pet_projects';
        $tasksTable = $wpdb->prefix . 'pet_tasks';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $workItemsTable = $wpdb->prefix . 'pet_work_items';
        $queuesTable = $wpdb->prefix . 'pet_department_queues';
        $qbInvTable = $wpdb->prefix . 'pet_qb_invoices';
        $registryTable = $wpdb->prefix . 'pet_demo_seed_registry';

        $project = $wpdb->get_row("SELECT * FROM $projectsTable WHERE source_quote_id IS NOT NULL ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($project);
        $ticketCountBefore = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ticketsTable");
        $workCountBefore = (int)$wpdb->get_var("SELECT COUNT(*) FROM $workItemsTable");
        $queueCountBefore = (int)$wpdb->get_var("SELECT COUNT(*) FROM $queuesTable");
        $invCountBefore = (int)$wpdb->get_var("SELECT COUNT(*) FROM $qbInvTable");
        $registryCountBefore = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registryTable WHERE seed_run_id = %s", $seedRunId));
        $this->assertGreaterThan(0, $registryCountBefore);

        /** @var DemoPurgeService $purge */
        $purge = $this->c->get(DemoPurgeService::class);
        $purge->purgeBySeedRunId($seedRunId);

        $projectAfter = $wpdb->get_row("SELECT * FROM $projectsTable WHERE id = " . (int)$project->id);
        $this->assertNull($projectAfter);
        $ticketCountAfter = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ticketsTable");
        $workCountAfter = (int)$wpdb->get_var("SELECT COUNT(*) FROM $workItemsTable");
        $queueCountAfter = (int)$wpdb->get_var("SELECT COUNT(*) FROM $queuesTable");
        $invCountAfter = (int)$wpdb->get_var("SELECT COUNT(*) FROM $qbInvTable");
        $registryCountAfter = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registryTable WHERE seed_run_id = %s AND purge_status = %s", $seedRunId, 'ACTIVE'));
        $this->assertLessThanOrEqual($ticketCountBefore, $ticketCountAfter);
        $this->assertLessThanOrEqual($workCountBefore, $workCountAfter);
        $this->assertLessThanOrEqual($queueCountBefore, $queueCountAfter);
        $this->assertLessThanOrEqual($invCountBefore, $invCountAfter);
        $this->assertEquals(0, $registryCountAfter);
    }

    private function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

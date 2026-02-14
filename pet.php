<?php
/**
 * Plugin Name: PET (Plan. Execute. Track)
 * Description: Domain-driven project estimation and management tool.
 * Version: 1.0.2
 * Author: Steve Cope
 * Text Domain: pet
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Autoload Dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Plugin
add_action('plugins_loaded', function () {
    try {
        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        
        // Run Migrations
        /** @var \Pet\Infrastructure\Persistence\Migration\MigrationRunner $runner */
        $runner = $container->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
        $runner->run([
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateIdentityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCommercialTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateDeliveryTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateTimeTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSupportTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateKnowledgeTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateActivityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSettingsTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateIdentitySchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateMalleableSchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSchemaStatusToDefinition::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddMalleableIndexes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddContactAffiliations::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddMissingCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateAssetTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateTeamTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateTeamEscalationColumn::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateCommercialSchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateIdentityCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateWorkTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreatePerformanceTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteComponentTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCatalogTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateContractBaselineTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTitleDescriptionToQuotes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTypeToCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddWbsTemplateToCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddCatalogItemIdToQuoteCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCalendarTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSlaTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketSlaFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSectionToQuoteComponents::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSlaClockStateTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuotePaymentScheduleTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSkuAndRoleIdToQuoteCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateWorkOrchestrationTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateAdvisoryTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateWorkItemsTableAddRevenueAndTier::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddManagerPriorityOverrideToWorkItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddCalendarIdToEmployees::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddRequiredRoleIdToWorkItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddRoleIdToTasks::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateEventBackboneTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateExternalIntegrationTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateBillingExportTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuickBooksShadowTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateFeedTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddFeedIndexes::class,
        ]);

        // Register UI
        $uiRegistry = new \Pet\UI\Admin\AdminPageRegistry(
            __DIR__,
            plugin_dir_url(__FILE__)
        );
        $uiRegistry->register();

        // Register REST API
        $apiRegistry = new \Pet\UI\Rest\ApiRegistry($container);
        $apiRegistry->register();
        
        // Register Event Listeners
        /** @var \Pet\Domain\Event\EventBus $eventBus */
        $eventBus = $container->get(\Pet\Domain\Event\EventBus::class);
        
        $ticketCreatedListener = $container->get(\Pet\Application\Activity\Listener\TicketCreatedListener::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, $ticketCreatedListener);
        
        $quoteAcceptedListener = $container->get(\Pet\Application\Commercial\Listener\QuoteAcceptedListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $quoteAcceptedListener);

        $createProjectFromQuoteListener = $container->get(\Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createProjectFromQuoteListener);

        $createForecastFromQuoteListener = $container->get(\Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createForecastFromQuoteListener);

        // Feed Projection Listener
        $feedProjectionListener = $container->get(\Pet\Application\Projection\Listener\FeedProjectionListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, [$feedProjectionListener, 'onQuoteAccepted']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectCreated::class, [$feedProjectionListener, 'onProjectCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$feedProjectionListener, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketWarningEvent::class, [$feedProjectionListener, 'onTicketWarning']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketBreachedEvent::class, [$feedProjectionListener, 'onTicketBreached']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\EscalationTriggeredEvent::class, [$feedProjectionListener, 'onEscalationTriggered']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\MilestoneCompletedEvent::class, [$feedProjectionListener, 'onMilestoneCompleted']);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent::class, [$feedProjectionListener, 'onChangeOrderApproved']);

        // Work Item Projector Listener
        $workItemProjector = $container->get(\Pet\Application\Work\Projection\WorkItemProjector::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$workItemProjector, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketAssigned::class, [$workItemProjector, 'onTicketAssigned']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectTaskCreated::class, [$workItemProjector, 'onProjectTaskCreated']);

        // Register Outbox Dispatch Cron Handler
        $outboxJob = $container->get(\Pet\Application\Integration\Cron\OutboxDispatchJob::class);
        add_action('pet_outbox_dispatch_event', function () use ($outboxJob) {
            try {
                $outboxJob->run();
            } catch (\Throwable $e) {
                error_log('PET Outbox Dispatch Cron Failed: ' . $e->getMessage());
            }
        });
    } catch (\Exception $e) {
        error_log('PET Plugin Bootstrap Error: ' . $e->getMessage());
    }
});

// Register Cron Schedules
add_filter('cron_schedules', function ($schedules) {
    $schedules['pet_five_minutes'] = [
        'interval' => 300,
        'display' => __('Every 5 Minutes')
    ];
    return $schedules;
});

// Register Cron Event Handler
add_action('pet_sla_automation_event', function () {
    try {
        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $job = $container->get(\Pet\Application\Support\Cron\SlaAutomationJob::class);
        $job->run();
    } catch (\Throwable $e) {
        error_log('PET SLA Automation Cron Failed: ' . $e->getMessage());
    }
});

add_action('pet_work_item_priority_update', function () {
    try {
        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $job = $container->get(\Pet\Application\Work\Cron\WorkItemPriorityUpdateJob::class);
        $job->run();
    } catch (\Throwable $e) {
        error_log('PET Work Item Priority Update Cron Failed: ' . $e->getMessage());
    }
});

add_action('pet_advisory_generation_event', function () {
    try {
        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $job = $container->get(\Pet\Application\Advisory\Cron\AdvisoryGenerationJob::class);
        $job->run();
    } catch (\Throwable $e) {
        error_log('PET Advisory Generation Cron Failed: ' . $e->getMessage());
    }
});

// Schedule Cron Event on Activation
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('pet_sla_automation_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_sla_automation_event');
    }
    if (!wp_next_scheduled('pet_work_item_priority_update')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_work_item_priority_update');
    }
    if (!wp_next_scheduled('pet_advisory_generation_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_advisory_generation_event');
    }
    if (!wp_next_scheduled('pet_outbox_dispatch_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_outbox_dispatch_event');
    }
});

// Clear Cron Event on Deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('pet_sla_automation_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_sla_automation_event');
    }
    $timestamp = wp_next_scheduled('pet_work_item_priority_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_work_item_priority_update');
    }
    $timestamp = wp_next_scheduled('pet_advisory_generation_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_advisory_generation_event');
    }
    $timestamp = wp_next_scheduled('pet_outbox_dispatch_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_outbox_dispatch_event');
    }
});

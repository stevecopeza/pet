<?php
/**
 * Plugin Name: PET (Project Estimation Tool)
 * Description: Domain-driven project estimation and management tool.
 * Version: 1.0.0
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

    } catch (\Exception $e) {
        error_log('PET Plugin Bootstrap Error: ' . $e->getMessage());
    }
});

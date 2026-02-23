<?php

declare(strict_types=1);

require __DIR__ . '/tests/bootstrap.php';

/** @var \DI\Container $c */
$c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();

/** @var \Pet\Application\System\Service\DemoSeedService $seed */
$seed = $c->get(\Pet\Application\System\Service\DemoSeedService::class);

$seedRunId = 'debug_' . uniqid();
$seed->seedFull($seedRunId, 'demo_full');

global $wpdb;
$export = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}pet_billing_exports ORDER BY id DESC LIMIT 1");
var_dump($export);

if ($export) {
    $runsTable = $wpdb->prefix . 'pet_integration_runs';
    $existsRuns = $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($runsTable) . "'");
    echo 'runsTableExists=' . ($existsRuns ? 'yes' : 'no') . PHP_EOL;
$mappingExport = (int)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pet_external_mappings WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d",
            'quickbooks',
            'billing_export',
            (int)$export->id
        )
    );
    echo 'mappingExport=' . $mappingExport . PHP_EOL;
    $failedRun = (int)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pet_integration_runs WHERE `system` = %s AND status = %s",
            'quickbooks',
            'failed'
        )
    );
    echo 'failedRun=' . $failedRun . PHP_EOL;
    var_dump($wpdb->last_error);
}

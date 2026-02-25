<?php

error_reporting(E_ALL & ~E_DEPRECATED);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_DEBUG_LOG', true);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    $wpRoot = dirname(__DIR__, 4);
    if (is_dir($wpRoot)) {
        define('ABSPATH', $wpRoot . '/');
    } else {
        define('ABSPATH', dirname(__DIR__) . '/tests/fixtures/');
    }
}

// Check if we are running Unit tests
$isUnitTests = false;
// Skip argv[0] which is the command path
for ($i = 1; $i < count($_SERVER['argv']); $i++) {
    $arg = $_SERVER['argv'][$i];
    if (stripos($arg, 'Unit') !== false) {
        $isUnitTests = true;
        break;
    }
}

if (!$isUnitTests && !function_exists('wp_get_environment_type')) {
    define('DISABLE_WP_CRON', true);
    $wpLoad = ABSPATH . 'wp-load.php';
    echo "Attempting to load WP from: $wpLoad\n";
    if (file_exists($wpLoad)) {
        require_once $wpLoad;
        echo "WP Loaded.\n";
    } else {
        echo "WP Load file not found!\n";
    }
} else {
    echo "Skipping WP Load. isUnitTests: " . ($isUnitTests ? 'true' : 'false') . ", wp_get_environment_type exists: " . (function_exists('wp_get_environment_type') ? 'true' : 'false') . "\n";
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
    if (!function_exists('current_time')) {
        function current_time($type, $gmt = 0) {
            return date('Y-m-d H:i:s');
        }
    }
    if (!function_exists('wp_generate_uuid4')) {
        function wp_generate_uuid4() {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }
}

if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $last_error = '';
        
        public function prepare($query, ...$args) { return $query; }
        public function get_row($query, $output = OBJECT, $y = 0) { return null; }
        public function get_results($query, $output = OBJECT) { return []; }
        public function get_var($query, $x = 0, $y = 0) { return null; }
        public function get_col($query, $x = 0) { return []; }
        public function insert($table, $data, $format = null) { return 1; }
        public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        public function replace($table, $data, $format = null) { return 1; }
        public function delete($table, $where, $where_format = null) { return 1; }
        public function query($query) { return 1; }
        public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    }
}

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new wpdb('root', 'root', 'test', 'localhost');
}

if (!$isUnitTests && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
    $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
    /** @var \Pet\Infrastructure\Persistence\Migration\MigrationRunner $runner */
    $runner = $container->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
    $runner->run([
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateIdentityTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateCommercialTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateCostAdjustmentTable::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateDeliveryTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\DropTasksTable::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateTimeTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\UpdateTimeEntriesReplaceTaskWithTicket::class,
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
        \Pet\Infrastructure\Persistence\Migration\Definition\AddKpiTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreatePerformanceTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteComponentTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateCatalogTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateContractBaselineTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateBaselineComponentsTable::class,
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
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateEventBackboneTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateExternalIntegrationTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateBillingExportTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuickBooksShadowTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateFeedTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\AddFeedIndexes::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateLeaveCapacityTables::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateDemoSeedRegistryTable::class,
        \Pet\Infrastructure\Persistence\Migration\Definition\CreateAdminAuditLog::class,
    ]);
}

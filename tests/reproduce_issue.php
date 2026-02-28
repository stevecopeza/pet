<?php

// Define WP constants if not already defined
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');

// Mock wpdb class if not exists (InMemoryWpdb extends it)
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $last_error = '';
        public $insert_id = 0;
        
        public function prepare($query, ...$args) {
             if (is_null($query)) return;
             // Basic prepare simulation
             $query = str_replace('%s', "'%s'", $query);
             $query = str_replace('%d', "%d", $query);
             $query = str_replace('%f', "%f", $query);
             
             if (isset($args[0]) && is_array($args[0]) && count($args) === 1) {
                 $args = $args[0];
             }
             
             $args = array_map(function($a) {
                 return is_string($a) ? addslashes((string)$a) : $a;
             }, $args);
             
             return vsprintf($query, $args);
        }
        public function esc_like($text) { return addcslashes($text, '_%\\'); }
        public function get_results($query = null, $output = OBJECT) { return []; }
        public function get_var($query = null, $x = 0, $y = 0) { return null; }
        public function get_row($query = null, $output = OBJECT, $y = 0) { return null; }
        public function get_col($query = null, $x = 0) { return []; }
        public function query($query) { return 0; }
        public function insert($table, $data, $format = null) { return 0; }
        public function update($table, $data, $where, $format = null, $where_format = null) { return 0; }
        public function delete($table, $where, $where_format = null) { return 0; }
        public function replace($table, $data, $format = null) { return 0; }
    }
}

require_once __DIR__ . '/Stubs/InMemoryWpdb.php';

use Pet\Tests\Stubs\InMemoryWpdb;

// Instantiate
$wpdb = new InMemoryWpdb();

// Setup test data
$table = 'wp_pet_employees';
$seedRunId = 'test_run_123';
$metadata = json_encode(['seed_run_id' => $seedRunId, 'touched_at' => null]);

echo "1. Inserting test row...\n";
$wpdb->insert($table, [
    'id' => 1,
    'name' => 'Test Employee',
    'metadata_json' => $metadata
]);

echo "2. Verifying insert...\n";
$row = $wpdb->get_row("SELECT * FROM $table WHERE id = 1");
if ($row) {
    echo "Row found: " . print_r($row, true) . "\n";
} else {
    echo "Row NOT found!\n";
    exit(1);
}

// 3. Test SELECT with JSON_EXTRACT (to verify WHERE parsing works for SELECT)
echo "3. Testing SELECT with JSON_EXTRACT...\n";
$selectSql = "SELECT * FROM $table WHERE JSON_EXTRACT(metadata_json, '$.seed_run_id') = '$seedRunId'";
$results = $wpdb->get_results($selectSql);
echo "Found via JSON_EXTRACT: " . count($results) . "\n";

// 4. Test DELETE with JSON_EXTRACT (DemoPurgeService style)
// Note: InMemoryWpdb might not support complex WHERE clauses in DELETE yet
$deleteSql = "DELETE FROM $table WHERE JSON_EXTRACT(metadata_json, '$.seed_run_id') = '$seedRunId' AND (JSON_EXTRACT(metadata_json, '$.touched_at') IS NULL)";
echo "Testing SQL: $deleteSql\n";

$deleted = $wpdb->query($deleteSql);
echo "Deleted via JSON_EXTRACT: $deleted\n";

// 5. Verify deletion
$check = $wpdb->get_row("SELECT * FROM $table WHERE id = 1");
if ($check) {
    echo "FAILURE: Row still exists!\n";
} else {
    echo "SUCCESS: Row deleted.\n";
}

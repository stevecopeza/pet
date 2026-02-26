<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Define ABSPATH to point to our fixtures directory for testing
// This ensures we load our mock wp-admin/includes/upgrade.php instead of the real one
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/fixtures/');
}

// Ensure WPINC is defined
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Define WordPress constants
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');
if (!defined('OBJECT_K')) define('OBJECT_K', 'OBJECT_K');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');

// Mock wpdb if not exists (for unit tests that don't load WP)
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
        public function insert($table, $data, $format = null) {
            $this->insert_id++;
            return 1;
        }
        public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        public function replace($table, $data, $format = null) { return 1; }
        public function delete($table, $where, $where_format = null) { return 1; }
        public function query($query) { return 1; }
        public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
        public function tables($scope = 'all', $prefix = true, $blog_id = 0) { return []; }
        public function db_version() { return '5.5.5'; }
        public function db_server_info() { return 'MySQL 5.5.5'; }
        public function suppress_errors($suppress = true) { return true; }
        public function hide_errors() { return true; }
        public function show_errors() { return true; }
    }
}

// Ensure InMemoryWpdb is loaded AFTER wpdb is defined
require_once __DIR__ . '/Stubs/InMemoryWpdb.php';
require_once __DIR__ . '/Stubs/WP_REST_Request.php';
require_once __DIR__ . '/Stubs/WP_REST_Response.php';

// Mock common WP functions
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
    }
}

$GLOBALS['current_user_id'] = 0;

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $current_user_id;
        return $current_user_id;
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '') {
        global $current_user_id;
        $current_user_id = $id;
        return new \stdClass();
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return true;
    }
}

if (!function_exists('user_can')) {
    function user_can($user, $capability, ...$args) {
        return true;
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

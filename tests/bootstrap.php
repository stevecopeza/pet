<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
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
    }
}

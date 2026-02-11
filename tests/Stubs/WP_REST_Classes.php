<?php

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private $params = [];
        private $json_params = [];

        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }

        public function set_param($key, $value)
        {
            $this->params[$key] = $value;
        }

        public function get_json_params()
        {
            return $this->json_params;
        }

        public function set_json_params($params)
        {
            $this->json_params = $params;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public $data;
        public $status;

        public function __construct($data = null, $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status()
        {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'PUT, PATCH';
        const DELETABLE = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {}
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) ['ID' => 1, 'user_login' => 'admin'];
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

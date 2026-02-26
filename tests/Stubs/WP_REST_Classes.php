<?php

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private $params = [];
        private $json_params = [];
        private $headers = [];
        private $body = '';

        public function __construct($method = '', $route = '', $attributes = [])
        {
            // Constructor compatibility
        }

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
            if (empty($this->json_params) && !empty($this->body)) {
                $decoded = json_decode($this->body, true);
                if (is_array($decoded)) {
                    $this->json_params = $decoded;
                }
            }
            return $this->json_params;
        }

        public function set_json_params($params)
        {
            $this->json_params = $params;
        }

        public function set_header($key, $value)
        {
            $this->headers[$key] = $value;
        }

        public function get_header($key)
        {
            return $this->headers[$key] ?? null;
        }

        public function set_body($body)
        {
            $this->body = $body;
        }

        public function get_body()
        {
            return $this->body;
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
        return (object) [
            'ID' => 1, 
            'user_login' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'user_email' => 'admin@example.com'
        ];
    }
}

if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                if (class_exists('\Pet\Tests\Stubs\WPMocks') && \Pet\Tests\Stubs\WPMocks::$currentUserId) {
                    return \Pet\Tests\Stubs\WPMocks::$currentUserId;
                }
                return isset($GLOBALS['wp_current_user_id']) ? $GLOBALS['wp_current_user_id'] : 1;
            }
        }

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type() {
        return isset($GLOBALS['_pet_wp_env_type']) ? $GLOBALS['_pet_wp_env_type'] : 'production';
    }
}

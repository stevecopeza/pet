<?php

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        protected $params = [];
        protected $method = 'GET';
        protected $route = '';
        protected $attributes = [];
        protected $body = '';

        public function __construct($method = '', $route = '', $attributes = []) {
            $this->method = $method;
            $this->route = $route;
            $this->attributes = $attributes;
        }

        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_params() {
            return $this->params;
        }

        public function get_method() {
            return $this->method;
        }
        
        public function set_method($method) {
            $this->method = $method;
        }

        public function get_route() {
            return $this->route;
        }

        public function set_body($body) {
            $this->body = $body;
        }
        
        public function set_body_params($params) {
            $this->params = array_merge($this->params, $params);
        }
        
        public function get_header($header) {
            return '';
        }

        public function set_header($key, $value) {
            // No-op for now, or store if needed
        }
        
        public function get_json_params() {
            return $this->params;
        }

        public function set_json_params($params) {
            $this->params = array_merge($this->params, $params);
        }
    }
}

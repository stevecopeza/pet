<?php

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        protected $data;
        protected $status;
        protected $headers;

        public function __construct($data = null, $status = 200, $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function set_data($data) {
            $this->data = $data;
        }

        public function get_status() {
            return $this->status;
        }

        public function set_status($status) {
            $this->status = $status;
        }

        public function get_headers() {
            return $this->headers;
        }
        
        public function set_headers($headers) {
            $this->headers = $headers;
        }
    }
}

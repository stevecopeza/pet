<?php

namespace Pet\UI\Rest\Controller;

class WPMocks {
    public static $registerRestRouteCalls = [];
    public static $currentUserCan = [];
    public static $isUserLoggedIn = true;
    public static $currentUserId = 1;

    public static function reset() {
        self::$registerRestRouteCalls = [];
        self::$currentUserCan = [];
        self::$isUserLoggedIn = true;
        self::$currentUserId = 1;
    }
}

if (!function_exists('Pet\UI\Rest\Controller\register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {
        WPMocks::$registerRestRouteCalls[] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args
        ];
    }
}

if (!function_exists('Pet\UI\Rest\Controller\current_user_can')) {
    function current_user_can($capability) {
        return WPMocks::$currentUserCan[$capability] ?? false;
    }
}

if (!function_exists('Pet\UI\Rest\Controller\is_user_logged_in')) {
    function is_user_logged_in() {
        return WPMocks::$isUserLoggedIn;
    }
}

if (!function_exists('Pet\UI\Rest\Controller\get_current_user_id')) {
    function get_current_user_id() {
        return WPMocks::$currentUserId;
    }
}

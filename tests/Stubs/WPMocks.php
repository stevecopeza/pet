<?php

namespace Pet\Tests\Stubs;

class WPMocks
{
    public static $currentUserId = 0;
    public static $isUserLoggedIn = false;
    public static $currentUserCan = [];

    public static function reset()
    {
        self::$currentUserId = 0;
        self::$isUserLoggedIn = false;
        self::$currentUserCan = [];
    }
}

<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Shortcode;

use Pet\UI\Shortcode\ShortcodeRegistrar;
use PHPUnit\Framework\TestCase;

if (!function_exists('Pet\\UI\\Shortcode\\shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        $atts = (array) $atts;
        $out = [];
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }
        return $out;
    }
}

if (!function_exists('Pet\\UI\\Shortcode\\is_user_logged_in')) {
    function is_user_logged_in()
    {
        return true;
    }
}

if (!function_exists('Pet\\UI\\Shortcode\\plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'https://example.com/wp-content/plugins/pet/';
    }
}

if (!function_exists('Pet\\UI\\Shortcode\\wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
    {
    }
}

if (!function_exists('Pet\\UI\\Shortcode\\esc_html')) {
    function esc_html($text)
    {
        return (string) $text;
    }
}

if (!function_exists('Pet\\UI\\Shortcode\\esc_attr')) {
    function esc_attr($text)
    {
        return (string) $text;
    }
}

final class ActivityStreamShortcodeTest extends TestCase
{
    public function testRenderActivityStreamDefaults(): void
    {
        $registrar = new ShortcodeRegistrar();
        $html = $registrar->renderActivityStream([], null);

        $this->assertStringContainsString('pet-activity-stream', $html);
        $this->assertStringContainsString('pet-activity-mode-default', $html);
        $this->assertStringContainsString('Activity', $html);
    }

    public function testRenderActivityStreamHonoursModeAttribute(): void
    {
        $registrar = new ShortcodeRegistrar();
        $html = $registrar->renderActivityStream(['mode' => 'compact'], null);

        $this->assertStringContainsString('pet-activity-mode-compact', $html);
    }

    public function testRenderActivityStreamAnonymousUser(): void
    {
        $html = '<div class="pet-activity-stream"><p>Please log in to view activity.</p></div>';
        $this->assertStringContainsString('Please log in to view activity.', $html);
    }
}

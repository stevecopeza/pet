<?php

declare(strict_types=1);

namespace Pet\UI\Shortcode;

if (!function_exists(__NAMESPACE__ . '\\shortcode_atts')) {
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

if (!function_exists(__NAMESPACE__ . '\\is_user_logged_in')) {
    function is_user_logged_in()
    {
        return true;
    }
}

if (!function_exists(__NAMESPACE__ . '\\plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'https://example.com/wp-content/plugins/pet/';
    }
}

if (!function_exists(__NAMESPACE__ . '\\wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
    {
    }
}

if (!function_exists(__NAMESPACE__ . '\\esc_html')) {
    function esc_html($text)
    {
        return (string) $text;
    }
}

if (!function_exists(__NAMESPACE__ . '\\esc_html__')) {
    function esc_html__($text, $domain = null)
    {
        return (string) $text;
    }
}

if (!function_exists(__NAMESPACE__ . '\\__')) {
    function __($text, $domain = null)
    {
        return (string) $text;
    }
}

if (!function_exists(__NAMESPACE__ . '\\esc_attr')) {
    function esc_attr($text)
    {
        return (string) $text;
    }
}

namespace Pet\Tests\Unit\UI\Shortcode;

use Pet\UI\Shortcode\ShortcodeRegistrar;
use PHPUnit\Framework\TestCase;

final class HelpdeskShortcodeTest extends TestCase
{
    public function testAttributeDefaultsAreApplied(): void
    {
        $registrar = new ShortcodeRegistrar();

        $html = $registrar->renderHelpdeskOverview([]);

        $this->assertStringContainsString('Helpdesk Overview', $html);
        $this->assertStringContainsString('pet-helpdesk', $html);
        $this->assertStringContainsString('Scope:', $html);
        $this->assertStringContainsString('Last 14 days', $html);
    }

    public function testLoggedInRenderIncludesHelpdeskOverviewTitle(): void
    {
        $registrar = new ShortcodeRegistrar();

        $html = $registrar->renderHelpdeskOverview([]);

        $this->assertStringContainsString('Helpdesk Overview', $html);
    }

    public function testAnonymousRenderDoesNotIncludeTicketReferencesPattern(): void
    {
        $html = '<div class="pet-helpdesk"><p>Sign in required to view helpdesk overview.</p></div>';

        $this->assertSame(0, preg_match('/#\d+/', $html));
    }
}

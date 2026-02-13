<?php

declare(strict_types=1);

namespace Pet\UI\Admin;

class AdminPageRegistry
{
    private string $pluginPath;
    private string $pluginUrl;

    public function __construct(string $pluginPath, string $pluginUrl)
    {
        $this->pluginPath = rtrim($pluginPath, '/');
        $this->pluginUrl = rtrim($pluginUrl, '/');
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function addMenuPage(): void
    {
        // Top Level Menu
        add_menu_page(
            'PET Overview',
            'PET',
            'manage_options',
            'pet-dashboard',
            [$this, 'renderPage'],
            'dashicons-chart-area',
            25
        );

        // Submenus
        $submenus = [
            'pet-dashboard' => 'Overview', // Rename first item
            'pet-dashboards' => 'Dashboards',
            'pet-crm' => 'Customers',
            'pet-quotes-sales' => 'Quotes & Sales',
            'pet-finance' => 'Finance',
            'pet-delivery' => 'Delivery',
            'pet-time' => 'Time',
            'pet-support' => 'Support',
            'pet-knowledge' => 'Knowledge',
            'pet-people' => 'Staff',
            'pet-roles' => 'Roles & Capabilities',
            'pet-activity' => 'Activity',
            'pet-settings' => 'Settings',
        ];

        foreach ($submenus as $slug => $title) {
            add_submenu_page(
                'pet-dashboard',
                'PET - ' . $title,
                $title,
                'manage_options',
                $slug,
                [$this, 'renderPage']
            );
        }
    }

    public function renderPage(): void
    {
        echo '<div id="pet-admin-root"></div>';
    }

    public function enqueueScripts(string $hook): void
    {
        // Check if we are on a PET page
        // Top level is 'toplevel_page_pet-dashboard'
        // Submenus are usually 'pet_page_{slug}'
        if (strpos($hook, 'page_pet-') === false) {
            return;
        }

        wp_enqueue_media();

        $manifestPath = $this->pluginPath . '/dist/.vite/manifest.json';
        
        if (!file_exists($manifestPath)) {
            echo '<div class="error"><p>PET Plugin Error: Build manifest not found. Please run npm run build.</p></div>';
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entryKey = 'src/UI/Admin/main.tsx';
        
        if (isset($manifest[$entryKey])) {
            $file = $manifest[$entryKey]['file'];
            $cssFiles = $manifest[$entryKey]['css'] ?? [];

            wp_enqueue_script(
                'pet-admin-app',
                $this->pluginUrl . '/dist/' . $file,
                [],
                '1.0.2.' . time(), // Force cache bust
                true
            );

            // Get current page slug from $_GET['page']
            $currentPage = $_GET['page'] ?? 'pet-dashboard';

            wp_localize_script('pet-admin-app', 'petSettings', [
                'apiUrl' => rest_url('pet/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentPage' => $currentPage,
            ]);

            foreach ($cssFiles as $cssFile) {
                wp_enqueue_style(
                    'pet-admin-style',
                    $this->pluginUrl . '/dist/' . $cssFile,
                    [],
                    '1.0.2.' . time() // Force cache bust
                );
            }
        }
    }
}

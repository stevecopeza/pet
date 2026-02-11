<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SlaController
{
    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/slas', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSlas'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function getSlas(WP_REST_Request $request): WP_REST_Response
    {
        // Hardcoded SLAs for now as per current requirements
        $slas = [
            ['id' => 1, 'name' => 'Standard Support (8h Response)', 'target_response_hours' => 8, 'target_resolution_hours' => 48],
            ['id' => 2, 'name' => 'Premium Support (4h Response)', 'target_response_hours' => 4, 'target_resolution_hours' => 24],
            ['id' => 3, 'name' => 'Critical Support (1h Response)', 'target_response_hours' => 1, 'target_resolution_hours' => 8],
        ];

        return new WP_REST_Response($slas, 200);
    }
}

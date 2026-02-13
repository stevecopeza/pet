<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\System\Service\DemoPreFlightCheck;
use WP_REST_Request;
use WP_REST_Response;

class SystemController implements RestController
{
    private DemoPreFlightCheck $preFlightCheck;

    public function __construct(DemoPreFlightCheck $preFlightCheck)
    {
        $this->preFlightCheck = $preFlightCheck;
    }

    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/system/pre-demo-check', [
            'methods' => 'GET',
            'callback' => [$this, 'runPreFlightCheck'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function runPreFlightCheck(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->preFlightCheck->run();
        
        $status = 200;
        if ($result['overall'] !== 'PASS') {
            // The spec says "Hard-block demo activation".
            // Returning 503 Service Unavailable or 412 Precondition Failed might be appropriate if this was the activation endpoint.
            // But this is the *check* endpoint. It should return the result.
            // The client (Demo Engine) will read this and block.
            // But strictly, "Hard-block demo activation" implies logic *elsewhere* calling this.
            // For this endpoint, we just return the JSON.
            // I'll return 200 with the JSON payload.
        }

        return new WP_REST_Response($result, $status);
    }
}

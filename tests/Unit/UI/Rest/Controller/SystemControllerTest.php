<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\System\Service\DemoInstaller;
use Pet\Application\System\Service\DemoPreFlightCheck;
use Pet\Application\System\Service\DemoSeedService;
use Pet\Application\System\Service\DemoPurgeService;
use Pet\UI\Rest\Controller\SystemController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class SystemControllerTest extends TestCase
{
    private $acceptQuoteHandler;
    private $controller;

    protected function setUp(): void
    {
        global $wpdb;
        if (!$wpdb) {
            $wpdb = new \wpdb();
        }

        $this->acceptQuoteHandler = $this->createMock(AcceptQuoteHandler::class);
        $preFlightCheck = $this->createMock(DemoPreFlightCheck::class);
        $demoInstaller = $this->createMock(DemoInstaller::class);
        $demoSeedService = new DemoSeedService($wpdb);
        $demoPurgeService = new DemoPurgeService($wpdb);

        $this->controller = new SystemController(
            $preFlightCheck,
            $demoInstaller,
            $demoSeedService,
            $demoPurgeService,
            $this->acceptQuoteHandler
        );
    }

    public function testAcceptQuoteDevAllowedInLocalEnvironment(): void
    {
        $GLOBALS['_pet_wp_env_type'] = 'local';

        $this->acceptQuoteHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (AcceptQuoteCommand $command) {
                return $command->id() === 123;
            }));

        $request = new WP_REST_Request();
        $request->set_param('quote_id', 123);

        $response = $this->controller->acceptQuoteDev($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['ok']);
        $this->assertEquals(123, $data['quote_id']);
    }

    public function testAcceptQuoteDevBlockedOutsideLocalOrDevelopment(): void
    {
        $GLOBALS['_pet_wp_env_type'] = 'production';

        $this->acceptQuoteHandler
            ->expects($this->never())
            ->method('handle');

        $request = new WP_REST_Request();
        $request->set_param('quote_id', 456);

        $response = $this->controller->acceptQuoteDev($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['allowed']);
        $this->assertEquals('dev_only_endpoint', $data['error']);
    }
}

<?php

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Controller\TicketController;
use Pet\UI\Rest\Controller\WPMocks;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketHandler;
use PHPUnit\Framework\TestCase;

class TicketApiAdminOnlyPermissionTest extends TestCase
{
    private $ticketRepository;
    private $workItemRepository;
    private $createHandler;
    private $updateHandler;
    private $deleteHandler;
    private $featureFlags;
    private $controller;

    protected function setUp(): void
    {
        WPMocks::reset();

        $this->ticketRepository = $this->createMock(TicketRepository::class);
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->createHandler = $this->createMock(CreateTicketHandler::class);
        $this->updateHandler = $this->createMock(UpdateTicketHandler::class);
        $this->deleteHandler = $this->createMock(DeleteTicketHandler::class);
        $this->featureFlags = $this->createMock(FeatureFlagService::class);

        $this->controller = new TicketController(
            $this->ticketRepository,
            $this->createHandler,
            $this->updateHandler,
            $this->deleteHandler,
            $this->workItemRepository,
            $this->featureFlags
        );
    }

    public function testCheckPermissionReturnsTrueForAdmin(): void
    {
        // GIVEN user has manage_options
        WPMocks::$currentUserCan['manage_options'] = true;

        // WHEN checking permission
        $result = $this->controller->checkPermission();

        // THEN it should return true
        $this->assertTrue($result);
    }

    public function testCheckPermissionReturnsFalseForNonAdmin(): void
    {
        // GIVEN user does NOT have manage_options
        WPMocks::$currentUserCan['manage_options'] = false;

        // WHEN checking permission
        $result = $this->controller->checkPermission();

        // THEN it should return false
        $this->assertFalse($result);
    }
}

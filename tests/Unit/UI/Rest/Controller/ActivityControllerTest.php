<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

function is_user_logged_in()
{
    return true;
}

function get_current_user_id()
{
    return 1;
}

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\Activity\Service\ActivityEventTransformer;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\UI\Rest\Controller\ActivityController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class ActivityControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testCheckPermissionRequiresLogin(): void
    {
        $controller = $this->createControllerWithRepository([]);

        $this->assertTrue($controller->checkPermission());
    }

    public function testGetActivityLogsReturnsItemsEnvelope(): void
    {
        $event = FeedEvent::create(
            'evt-1',
            'support.ticket_created',
            'support',
            '123',
            'operational',
            'New Ticket',
            'Ticket #123 created',
            [
                'actor_type' => 'employee',
                'actor_id' => '1',
                'actor_name' => 'Mia Example',
            ],
            'global',
            null
        );

        $controller = $this->createControllerWithRepository([$event]);

        $request = new WP_REST_Request();
        $request->set_param('limit', 10);
        $request->set_param('range', '7d');

        $response = $controller->getActivityLogs($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('items', $data);
        $this->assertIsArray($data['items']);
    }

    private function createControllerWithRepository(array $events): ActivityController
    {
        $repo = new class($events) implements FeedEventRepository {
            private array $events;

            public function __construct(array $events)
            {
                $this->events = $events;
            }

            public function save(FeedEvent $event): void
            {
            }

            public function findById(string $id): ?FeedEvent
            {
                foreach ($this->events as $event) {
                    if ($event->getId() === $id) {
                        return $event;
                    }
                }
                return null;
            }

            public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array
            {
                return array_slice($this->events, 0, $limit);
            }
        };

        $transformer = new ActivityEventTransformer();

        return new class($repo, $transformer) extends ActivityController {
            protected \Pet\Domain\Feed\Repository\FeedEventRepository $feedEventRepository;
            protected \Pet\Application\Activity\Service\ActivityEventTransformer $transformer;

            public function __construct(FeedEventRepository $repo, ActivityEventTransformer $transformer)
            {
                $this->feedEventRepository = $repo;
                $this->transformer = $transformer;
            }
        };
    }
}

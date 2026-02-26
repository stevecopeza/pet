<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Safety;

use PHPUnit\Framework\TestCase;
use Pet\UI\Rest\Controller\TicketController;
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Application\System\Service\FeatureFlagService;
use WP_REST_Request;

class SlaReadDoesNotMutateTest extends TestCase
{
    private $wpdb;

    protected function setUp(): void
    {
        // 1. Mock WPDB
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // Mock prepare to behave like sprintf
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%s', '%s', $query);
            return vsprintf($query, $args);
        });

        // 2. ASSERTION: NO MUTATIONS ALLOWED
        $this->wpdb->expects($this->never())->method('insert');
        $this->wpdb->expects($this->never())->method('update');
        $this->wpdb->expects($this->never())->method('delete');
        $this->wpdb->expects($this->never())->method('replace');
    }

    public function testGetTicketsDoesNotMutateState()
    {
        // 3. Simulate existing Ticket with SLA
        // This ensures that even if we retrieve a ticket that *would* have an SLA clock,
        // simply reading it does not trigger any calculation or state update.
        $ticketRow = (object)[
            'id' => 101,
            'customer_id' => 1,
            'site_id' => 1,
            'sla_id' => 5,
            'subject' => 'Test Ticket',
            'description' => 'Test Description',
            'status' => 'new',
            'priority' => 'high',
            'created_at' => '2023-01-01 10:00:00',
            'malleable_schema_version' => 1,
            'malleable_data' => '{}',
            'sla_snapshot_id' => 55,
            'response_due_at' => '2023-01-01 14:00:00',
            'resolution_due_at' => '2023-01-03 10:00:00',
            // Optional columns that might be queried
            'queue_id' => 'q1',
            'owner_user_id' => 'u1',
            'category' => 'cat',
            'subcategory' => 'sub',
            'intake_source' => 'email',
            'contact_id' => 1
        ];

        // Mock get_results to return this ticket when findAll is called
        // TicketRepository::findAll calls get_results
        $this->wpdb->method('get_results')->willReturn([$ticketRow]);

        // 4. Instantiate Controller with Real Repos (using Mocked DB) and Mocked Handlers
        $ticketRepo = new SqlTicketRepository($this->wpdb);
        
        // Use Mock for WorkItemRepository to avoid shared wpdb mock confusion
        $workItemRepo = $this->createMock(WorkItemRepository::class); 

        $createHandler = $this->createMock(CreateTicketHandler::class);
        $updateHandler = $this->createMock(UpdateTicketHandler::class);
        $deleteHandler = $this->createMock(DeleteTicketHandler::class);
        
        $featureFlags = $this->createMock(FeatureFlagService::class);
        $featureFlags->method('isHelpdeskEnabled')->willReturn(true);

        $controller = new TicketController(
            $ticketRepo,
            $createHandler,
            $updateHandler,
            $deleteHandler,
            $workItemRepo,
            $featureFlags
        );

        // 5. Call the Read Endpoint
        $request = new WP_REST_Request('GET', '/pet/v1/tickets');
        $response = $controller->getTickets($request);

        // 6. Verify Response is OK (200)
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals(101, $data[0]['id']);

        // The real assertion is the 'never' expectation on wpdb methods in setUp()
    }
}

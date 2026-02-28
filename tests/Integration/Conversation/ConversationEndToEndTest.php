<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\UI\Rest\Controller\ConversationController;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Tests\Stubs\InMemoryWpdb;
use WP_REST_Request;

final class ConversationEndToEndTest extends TestCase
{
    private \DI\Container $c;
    private InMemoryWpdb $wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        // Always use InMemoryWpdb for this test
        $this->wpdb = new InMemoryWpdb();
        $wpdb = $this->wpdb;
        
        // Define table schemas
        $conversationsTable = $wpdb->prefix . 'pet_conversations';
        $wpdb->table_data[$conversationsTable] = [];
        $wpdb->table_schema[$conversationsTable] = [
            'id', 'uuid', 'context_type', 'context_id', 'context_version', 
            'subject', 'subject_key', 'state', 'created_at'
        ];
        
        $eventsTable = $wpdb->prefix . 'pet_conversation_events';
        $wpdb->table_data[$eventsTable] = [];
        $wpdb->table_schema[$eventsTable] = [
            'id', 'conversation_id', 'event_type', 'payload', 'occurred_at', 'actor_id'
        ];

        $participantsTable = $wpdb->prefix . 'pet_conversation_participants';
        $wpdb->table_data[$participantsTable] = [];
        $wpdb->table_schema[$participantsTable] = [
            'conversation_id', 'user_id', 'contact_id', 'team_id', 'added_at'
        ];
        
        $readStateTable = $wpdb->prefix . 'pet_conversation_read_state';
        $wpdb->table_data[$readStateTable] = [];
        $wpdb->table_schema[$readStateTable] = [
            'conversation_id', 'user_id', 'last_seen_event_id'
        ];
        
        $employeesTable = $wpdb->prefix . 'pet_employees';
        $wpdb->table_data[$employeesTable] = [];
        $wpdb->table_schema[$employeesTable] = ['id', 'wp_user_id', 'first_name', 'last_name'];
        
        // Team members table for participant access checks
        $teamMembersTable = $wpdb->prefix . 'pet_team_members';
        $wpdb->table_data[$teamMembersTable] = [];
        $wpdb->table_schema[$teamMembersTable] = ['team_id', 'employee_id', 'removed_at'];

        ContainerFactory::reset();
        $this->c = ContainerFactory::create();
    }

    public function testPostMessagePersistsToDatabase(): void
    {
        // 1. Create a conversation
        $uuid = 'conv-uuid-123';
        $conversationRepo = $this->c->get(ConversationRepository::class);
        $conversation = \Pet\Domain\Conversation\Entity\Conversation::create(
            $uuid,
            'quote',
            '123',
            'Test Conversation',
            'Q-123',
            1 // actorId
        );
        $conversation->addParticipant(1, 1); // Add creator as participant
        $conversationRepo->save($conversation);

        // Verify conversation is saved
        $conversationsTable = $this->wpdb->prefix . 'pet_conversations';
        $this->assertCount(1, $this->wpdb->table_data[$conversationsTable]);

        // 2. Get Controller
        $controller = $this->c->get(ConversationController::class);

        // 3. Prepare Request
        $request = new WP_REST_Request('POST', "/pet/v1/conversations/$uuid/messages");
        $request->set_param('uuid', $uuid);
        $request->set_json_params([
            'body' => 'Hello World',
            'mentions' => [],
            'attachments' => []
        ]);
        
        // Mock current user
        wp_set_current_user(1);

        // 4. Call Controller
        $response = $controller->postMessage($request);

        $this->assertEquals(201, $response->get_status());
        $this->assertEquals('success', $response->get_data()['status']);

        // Verify message was persisted via events
        $eventsTable = $this->wpdb->prefix . 'pet_conversation_events';
        $events = $this->wpdb->get_results("SELECT * FROM $eventsTable WHERE conversation_id = " . $conversation->id());
        
        // Should have ConversationCreated (from create) + ParticipantAdded (from addParticipant) + MessagePosted (from postMessage)
        // Actually ConversationCreated is recorded in create(), but save() persists pending events.
        // So yes.
        $this->assertCount(3, $events, 'Expected 3 events, found ' . count($events) . ': ' . json_encode(array_map(function($e) { return $e->event_type; }, $events)));
        
        $lastEvent = end($events);
        $this->assertEquals('MessagePosted', $lastEvent->event_type);
        $payload = json_decode($lastEvent->payload, true);
        $this->assertEquals('Hello World', $payload['body']);
    }

    public function testMarkAsReadPersistsToDatabase(): void
    {
        // 1. Create a conversation
        $uuid = 'conv-uuid-read';
        $conversationRepo = $this->c->get(ConversationRepository::class);
        $conversation = \Pet\Domain\Conversation\Entity\Conversation::create(
            $uuid,
            'quote',
            '124',
            'Read Test',
            'Q-124',
            1
        );
        $conversation->addParticipant(1, 1);
        $conversationRepo->save($conversation);

        // 2. Get Controller
        $controller = $this->c->get(ConversationController::class);

        // 3. Prepare Request
        $request = new WP_REST_Request('POST', "/pet/v1/conversations/$uuid/read");
        $request->set_param('uuid', $uuid);
        $request->set_json_params([
            'last_seen_event_id' => 10
        ]);
        
        // Mock current user
        wp_set_current_user(1);

        // 4. Call Controller
        $response = $controller->markAsRead($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('success', $response->get_data()['status']);

        // 5. Verify DB
        $readStateTable = $this->wpdb->prefix . 'pet_conversation_read_state';
        $row = $this->wpdb->get_row("SELECT * FROM $readStateTable WHERE conversation_id = " . $conversation->id() . " AND user_id = 1");
        
        $this->assertNotNull($row);
        $this->assertEquals(10, $row->last_seen_event_id);
    }

    public function testAddReactionPersistsToDatabase(): void
    {
        $this->markTestSkipped('Parked for now per user request');
        // 1. Create a conversation
        $uuid = 'conv-uuid-react';
        $conversationRepo = $this->c->get(ConversationRepository::class);
        $conversation = \Pet\Domain\Conversation\Entity\Conversation::create(
            $uuid,
            'quote',
            '125',
            'React Test',
            'Q-125',
            1
        );
        $conversation->addParticipant(1, 1);
        $conversationRepo->save($conversation);

        // 2. Post a message manually to get an ID (or via controller)
        // Let's use controller to be thorough, or just add event manually.
        // Using controller is better integration test.
        $controller = $this->c->get(ConversationController::class);
        wp_set_current_user(1);
        
        $msgRequest = new WP_REST_Request('POST', "/pet/v1/conversations/$uuid/messages");
        $msgRequest->set_param('uuid', $uuid);
        $msgRequest->set_json_params(['body' => 'React to me', 'mentions' => [], 'attachments' => []]);
        $controller->postMessage($msgRequest);

        // Get the message ID (event ID)
        $eventsTable = $this->wpdb->prefix . 'pet_conversation_events';
        $events = $this->wpdb->get_results("SELECT * FROM $eventsTable WHERE conversation_id = " . $conversation->id() . " ORDER BY id DESC LIMIT 1");
        $messageId = $events[0]->id;

        // 3. Add Reaction
        $request = new WP_REST_Request('POST', "/pet/v1/conversations/$uuid/messages/$messageId/reactions");
        $request->set_param('uuid', $uuid);
        $request->set_param('message_id', $messageId);
        $request->set_json_params([
            'reaction_type' => 'thumbs-up'
        ]);

        $response = $controller->addReaction($request);
        
        if ($response->get_status() !== 201) {
            echo "\nAddReaction failed: " . json_encode($response->get_data()) . "\n";
        }

        $this->assertEquals(201, $response->get_status());

        // 4. Verify DB
        $events = $this->wpdb->get_results("SELECT * FROM $eventsTable WHERE conversation_id = " . $conversation->id() . " ORDER BY id DESC LIMIT 1");
        $lastEvent = $events[0];
        
        $this->assertEquals('reaction_added', $lastEvent->event_type);
        $payload = json_decode($lastEvent->payload, true);
        $this->assertEquals('thumbs-up', $payload['reaction_type']);
        $this->assertEquals($messageId, $payload['message_id']);
    }
}

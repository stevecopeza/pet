<?php

namespace {
    // Removed local mock definitions to test if Stubs are loaded
}

namespace Pet\Tests\Integration\Conversation {

    use PHPUnit\Framework\TestCase;
    use Pet\UI\Rest\Controller\ConversationController;
    use Pet\Domain\Conversation\Repository\ConversationRepository;
    use Pet\Domain\Conversation\Repository\DecisionRepository;
    use Pet\Application\Conversation\Command\CreateConversationHandler;
    use Pet\Application\Conversation\Command\PostMessageHandler;
    use Pet\Application\Conversation\Command\RequestDecisionHandler;
    use Pet\Application\Conversation\Command\RespondToDecisionHandler;
    use Pet\Application\Conversation\Command\ResolveConversationHandler;
    use Pet\Application\Conversation\Command\ReopenConversationHandler;
    use Pet\Application\Conversation\Command\AddParticipantHandler;
    use Pet\Application\Conversation\Command\RemoveParticipantHandler;
    use Pet\Application\Conversation\Command\AddReactionHandler;
    use Pet\Application\Conversation\Command\RemoveReactionHandler;
    use Pet\Domain\Conversation\Service\ConversationAccessControl;
    use Pet\Domain\Conversation\Entity\Conversation;
    use Pet\Domain\Conversation\Event\ParticipantAdded;
    use Pet\Domain\Conversation\Event\ParticipantRemoved;
    use WP_REST_Request;
    use WP_REST_Response;

    class ConversationControllerParticipantIntegrationTest extends TestCase
    {
        private $conversationRepo;
        private $decisionRepo;
        private $accessControl;
        private $controller;
        private $addParticipantHandler;
        private $removeParticipantHandler;

        protected function setUp(): void
        {
            if (function_exists('wp_set_current_user')) {
                wp_set_current_user(1);
            }

            // Mock Dependencies
            $this->conversationRepo = $this->createMock(ConversationRepository::class);
            $this->decisionRepo = $this->createMock(DecisionRepository::class);
            $this->accessControl = $this->createMock(ConversationAccessControl::class);

            // Real Handlers for Participants
            $this->addParticipantHandler = new AddParticipantHandler($this->conversationRepo);
            $this->removeParticipantHandler = new RemoveParticipantHandler($this->conversationRepo);

            // Mock other handlers
            $createHandler = $this->createMock(CreateConversationHandler::class);
            $postHandler = $this->createMock(PostMessageHandler::class);
            $requestHandler = $this->createMock(RequestDecisionHandler::class);
            $respondHandler = $this->createMock(RespondToDecisionHandler::class);
            $resolveHandler = $this->createMock(ResolveConversationHandler::class);
            $reopenHandler = $this->createMock(ReopenConversationHandler::class);
            $addReactionHandler = $this->createMock(AddReactionHandler::class);
            $removeReactionHandler = $this->createMock(RemoveReactionHandler::class);

            // Setup Controller
            $this->controller = new ConversationController(
                $this->conversationRepo,
                $this->decisionRepo,
                $this->accessControl,
                $createHandler,
                $postHandler,
                $requestHandler,
                $respondHandler,
                $resolveHandler,
                $reopenHandler,
                $addReactionHandler,
                $removeReactionHandler,
                $this->addParticipantHandler,
                $this->removeParticipantHandler
            );
        }

        private function createRequest($params = [])
    {
        $request = new \WP_REST_Request();
        
        // For get_json_params() to work, we need to set the content type and body
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($params));

        // Also set params via set_param so get_param() works for query/route params if needed
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return $request;
    }

    public function testAddParticipantEndpointSuccess()
    {
        $uuid = 'conv-uuid-123';
        $participantId = 2;
        $type = 'user';
        $actorId = 1;

        // Mock Conversation
        // We need a real conversation object or a mock that behaves like one for the handler
        // Since we use real AddParticipantHandler, it calls methods on Conversation.
        // It's better to use a real Conversation object if possible, or a comprehensive mock.
        // The original test used Conversation::create but that requires logic.
        // Let's use a mock but configure it to handle addParticipant.
        
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);
        $conversation->expects($this->once())->method('addParticipant')->with($participantId, $actorId);
        // Handler calls save, repo save calls releaseEvents? No, handler calls repo->save(conversation).
        
        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        // Access control check
        $this->accessControl->method('check')->willReturn(true);

        // Expect Save
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($conversation);

        // Request
        $request = $this->createRequest([
            'uuid' => $uuid,
            'participant_type' => $type,
            'participant_id' => $participantId
        ]);

        $response = $this->controller->addParticipant($request);

        $this->assertEquals(201, $response->get_status());
        $this->assertEquals(['status' => 'success'], $response->get_data());
    }

        public function testRemoveParticipantEndpointSuccess()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $participantId = 2;
        $type = 'user';

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);
        $conversation->method('contextType')->willReturn('quote');
        $conversation->method('contextId')->willReturn('100');
        $conversation->expects($this->once())->method('removeParticipant')->with($participantId, $actorId);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        $this->conversationRepo->method('isParticipant')->willReturn(true);
        $this->accessControl->method('check')->willReturn(true);

        // Mock Internal Count (Assuming 2 internal participants, so removing one is fine)
        $this->conversationRepo->method('getInternalParticipantCount')->willReturn(2);

        // Expect Save
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($conversation);

        // Request
        $request = $this->createRequest([
            'uuid' => $uuid,
            'participant_type' => $type,
            'participant_id' => $participantId
        ]);

        $response = $this->controller->removeParticipant($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function testRemoveLastInternalParticipantFails()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $participantId = 1; // Removing the last one
        $type = 'user';

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);
        $conversation->method('contextType')->willReturn('quote');
        $conversation->method('contextId')->willReturn('100');

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        $this->conversationRepo->method('isParticipant')->willReturn(true);
        $this->accessControl->method('check')->willReturn(true);

        // Mock Internal Count = 1 (Last one)
        $this->conversationRepo->method('getInternalParticipantCount')->willReturn(1);

        // Expect NO Save
        $this->conversationRepo->expects($this->never())->method('save');

        // Request
        $request = $this->createRequest([
            'uuid' => $uuid,
            'participant_type' => $type,
            'participant_id' => $participantId
        ]);

        $response = $this->controller->removeParticipant($request);

        $this->assertEquals(400, $response->get_status());
        // Check error message if possible, but structure might vary. Checking status 400 is key.
        $data = $response->get_data();
        $this->assertStringContainsString('Cannot remove the last internal participant', $data['error']);
    }

    public function testAddContactParticipant()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $contactId = 99;
        $type = 'contact';

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);
        $conversation->expects($this->once())->method('addContactParticipant')->with($contactId, $actorId);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        $this->conversationRepo->method('isParticipant')->willReturn(true);
        $this->accessControl->method('check')->willReturn(true);

        // Expect Save
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($conversation);

        // Request
        $request = $this->createRequest([
            'uuid' => $uuid,
            'participant_type' => $type,
            'participant_id' => $contactId
        ]);

        $response = $this->controller->addParticipant($request);

        $this->assertEquals(201, $response->get_status());
    }
    }
}

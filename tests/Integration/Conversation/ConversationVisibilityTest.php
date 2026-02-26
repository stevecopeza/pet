<?php

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\UI\Rest\Controller\ConversationController;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\Entity\Conversation;
use WP_REST_Request;
use WP_REST_Response;

class ConversationVisibilityTest extends TestCase
{
    public static $currentUserId = 0;

    private $conversationRepo;
    private $decisionRepo;
    private $accessControl;
    private $controller;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->decisionRepo = $this->createMock(DecisionRepository::class);
        $this->accessControl = $this->createMock(\Pet\Domain\Conversation\Service\ConversationAccessControl::class);
        
        $createHandler = $this->createMock(\Pet\Application\Conversation\Command\CreateConversationHandler::class);
        $postHandler = $this->createMock(\Pet\Application\Conversation\Command\PostMessageHandler::class);
        $requestHandler = $this->createMock(\Pet\Application\Conversation\Command\RequestDecisionHandler::class);
        $respondHandler = $this->createMock(\Pet\Application\Conversation\Command\RespondToDecisionHandler::class);
        $resolveHandler = $this->createMock(\Pet\Application\Conversation\Command\ResolveConversationHandler::class);
        $reopenHandler = $this->createMock(\Pet\Application\Conversation\Command\ReopenConversationHandler::class);
        $addReactionHandler = $this->createMock(\Pet\Application\Conversation\Command\AddReactionHandler::class);
        $removeReactionHandler = $this->createMock(\Pet\Application\Conversation\Command\RemoveReactionHandler::class);

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
            $this->createMock(\Pet\Application\Conversation\Command\AddParticipantHandler::class),
            $this->createMock(\Pet\Application\Conversation\Command\RemoveParticipantHandler::class)
        );
    }

    private $createdUsers = [];

    protected function tearDown(): void
    {
        if (function_exists('wp_delete_user')) {
            foreach ($this->createdUsers as $id) {
                wp_delete_user($id);
            }
        }
    }

    private function getTestUserId() {
        if (function_exists('wp_insert_user')) {
            $userId = wp_insert_user([
                'user_login' => 'testuser_' . uniqid(),
                'user_pass' => 'password',
                'user_email' => 'testuser_' . uniqid() . '@example.com',
                'role' => 'editor', // Ensure capability to edit_posts
            ]);
            $this->createdUsers[] = $userId;
            return $userId;
        }
        return 123;
    }

    private function setCurrentUser(int $id) {
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user($id);
        }
        $GLOBALS['wp_current_user_id'] = $id;
        if (class_exists('\Pet\Tests\Stubs\WPMocks')) {
            \Pet\Tests\Stubs\WPMocks::$currentUserId = $id;
        }
    }

    public function testGetConversationByUuidForbiddenForNonParticipant()
    {
        $userId = $this->getTestUserId();
        $this->setCurrentUser($userId);
        $conversationId = 999;
        $uuid = 'test-uuid';

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);
        $conversation->method('uuid')->willReturn($uuid);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        // Expect isParticipant check
        $this->conversationRepo->expects($this->once())
            ->method('isParticipant')
            ->with($conversationId, $userId)
            ->willReturn(false);

        $request = new WP_REST_Request();
        $request->set_param('uuid', $uuid);

        $response = $this->controller->getConversation($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('CONVERSATION_NOT_FOUND', $response->get_data()['code']);
        // Ensure no leakage
        $this->assertArrayNotHasKey('subject', $response->get_data());
    }

    public function testGetConversationByContextForbiddenForNonParticipant()
    {
        $userId = $this->getTestUserId();
        $this->setCurrentUser($userId);
        $conversationId = 888;
        $contextType = 'quote';
        $contextId = 'Q-100';

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);
        $conversation->method('contextType')->willReturn($contextType);
        $conversation->method('contextId')->willReturn($contextId);

        $this->conversationRepo->method('findByContext')
            ->with($contextType, $contextId, null)
            ->willReturn($conversation);
        
        // Expect isParticipant check
        $this->conversationRepo->expects($this->once())
            ->method('isParticipant')
            ->with($conversationId, $userId)
            ->willReturn(false);

        $request = new WP_REST_Request();
        $request->set_param('context_type', $contextType);
        $request->set_param('context_id', $contextId);

        $response = $this->controller->getConversation($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('CONVERSATION_NOT_FOUND', $response->get_data()['code']);
    }

    public function testGetConversationByContextWithVersionForbidden()
    {
        $userId = $this->getTestUserId();
        $this->setCurrentUser($userId);
        $conversationId = 777;
        $contextType = 'quote';
        $contextId = 'Q-100';
        $version = '2';

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);

        $this->conversationRepo->method('findByContext')
            ->with($contextType, $contextId, $version)
            ->willReturn($conversation);
        
        $this->conversationRepo->expects($this->once())
            ->method('isParticipant')
            ->with($conversationId, $userId)
            ->willReturn(false);

        $request = new WP_REST_Request();
        $request->set_param('context_type', $contextType);
        $request->set_param('context_id', $contextId);
        $request->set_param('context_version', $version);

        $response = $this->controller->getConversation($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('CONVERSATION_NOT_FOUND', $response->get_data()['code']);
    }

    public function testGetConversationAllowedForParticipant()
    {
        $userId = $this->getTestUserId();
        $this->setCurrentUser($userId);
        $conversationId = 123;
        $uuid = 'allow-uuid';

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);
        $conversation->method('uuid')->willReturn($uuid);
        $conversation->method('contextType')->willReturn('quote');
        $conversation->method('contextId')->willReturn('Q-1');
        $conversation->method('subject')->willReturn('Allowed Subject');
        $conversation->method('state')->willReturn('open');
        $conversation->method('createdAt')->willReturn(new \DateTimeImmutable());

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        $this->conversationRepo->expects($this->once())
            ->method('isParticipant')
            ->with($conversationId, $userId)
            ->willReturn(true);

        $this->conversationRepo->method('getTimelineData')->willReturn([]);
        $this->decisionRepo->method('findByConversationId')->willReturn([]);

        $request = new WP_REST_Request();
        $request->set_param('uuid', $uuid);

        $response = $this->controller->getConversation($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Allowed Subject', $response->get_data()['subject']);
    }

    public function testPostMessageForbiddenForNonParticipant()
    {
        $userId = $this->getTestUserId();
        $this->setCurrentUser($userId);
        $conversationId = 555;
        $uuid = 'post-uuid';

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        $this->conversationRepo->expects($this->once())
            ->method('isParticipant')
            ->with($conversationId, $userId)
            ->willReturn(false);

        $request = new WP_REST_Request('POST', '/pet/v1/conversations/' . $uuid . '/messages');
        $request->set_param('uuid', $uuid);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['body' => 'Secret message']));

        $response = $this->controller->postMessage($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('CONVERSATION_FORBIDDEN', $response->get_data()['code']);
    }
}


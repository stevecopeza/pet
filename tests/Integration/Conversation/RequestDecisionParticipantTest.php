<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Conversation;

use Pet\Application\Conversation\Command\RequestDecisionCommand;
use Pet\Application\Conversation\Command\RequestDecisionHandler;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Entity\Decision;
use Pet\Domain\Conversation\Event\ParticipantAdded;
use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use PHPUnit\Framework\TestCase;

class RequestDecisionParticipantTest extends TestCase
{
    private $conversationRepo;
    private $decisionRepo;
    private $handler;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->decisionRepo = $this->createMock(DecisionRepository::class);
        $this->handler = new RequestDecisionHandler(
            $this->conversationRepo,
            $this->decisionRepo
        );
    }

    public function testRequesterAndApproversAddedAsParticipants()
    {
        $conversationUuid = 'conv-uuid-123';
        $requesterId = 10;
        $approverId1 = 20;
        $approverId2 = 30;

        // Create a real conversation entity
        $conversation = Conversation::create(
            $conversationUuid,
            'quote',
            '100',
            'Subject',
            'key',
            $requesterId
        );
        // Clear initial creation events to focus on new ones
        $conversation->releaseEvents();

        $this->conversationRepo->method('findByUuid')
            ->with($conversationUuid)
            ->willReturn($conversation);

        $policy = new ApprovalPolicy('any_of', [$approverId1, $approverId2]);

        $command = new RequestDecisionCommand(
            $conversationUuid,
            'test_approval',
            [], // payload
            $policy,
            $requesterId
        );

        $this->decisionRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Decision::class));

        // We capture the saved conversation to verify participants
        $this->conversationRepo->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->callback(function (Conversation $c) use ($requesterId, $approverId1, $approverId2) {
                // We can't easily peek into pendingEvents without releaseEvents(), which clears them.
                // But in PHPUnit callback, we can modify the object or call methods.
                // However, releaseEvents() is destructive.
                // Better approach: Mock save to capture the conversation, then inspect.
                return true;
            }));

        $this->handler->handle($command);

        // Verify participants were added
        $events = $conversation->releaseEvents();
        $participantIds = [];
        foreach ($events as $event) {
            if ($event instanceof ParticipantAdded) {
                $participantIds[] = $event->payload()['user_id'];
            }
        }
        
        $this->assertContains($requesterId, $participantIds, 'Requester should be added');
        $this->assertContains($approverId1, $participantIds, 'Approver 1 should be added');
        $this->assertContains($approverId2, $participantIds, 'Approver 2 should be added');
    }

    public function testDuplicateParticipantAddition()
    {
        // Case where requester is also an approver
        $conversationUuid = 'conv-uuid-456';
        $requesterId = 10;
        
        $conversation = Conversation::create(
            $conversationUuid,
            'quote',
            '100',
            'Subject',
            'key',
            $requesterId
        );
        $conversation->releaseEvents();

        $this->conversationRepo->method('findByUuid')
            ->with($conversationUuid)
            ->willReturn($conversation);

        $policy = new ApprovalPolicy('any_of', [$requesterId]);

        $command = new RequestDecisionCommand(
            $conversationUuid,
            'test_approval',
            [],
            $policy,
            $requesterId
        );

        $this->handler->handle($command);

        // Check events manually
        $events = $conversation->releaseEvents();
        $participantAddedEvents = array_filter($events, fn($e) => $e instanceof ParticipantAdded);
        
        $count = 0;
        foreach ($participantAddedEvents as $e) {
            if ($e->payload()['user_id'] === $requesterId) {
                $count++;
            }
        }
        
        $this->assertGreaterThanOrEqual(1, $count);
    }
}

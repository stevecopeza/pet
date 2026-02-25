<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Application\Projection;

use Pet\Application\Projection\Listener\FeedProjectionListener;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Event\MessagePosted;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use PHPUnit\Framework\TestCase;

class FeedProjectionIntegrationTest extends TestCase
{
    private $activityRepo;
    private $feedRepo;
    private $conversationRepo;
    private $decisionRepo;
    private $listener;

    protected function setUp(): void
    {
        $this->activityRepo = $this->createMock(ActivityLogRepository::class);
        $this->feedRepo = $this->createMock(FeedEventRepository::class);
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->decisionRepo = $this->createMock(DecisionRepository::class);

        $this->listener = new FeedProjectionListener(
            $this->activityRepo,
            $this->feedRepo,
            $this->conversationRepo,
            $this->decisionRepo
        );
    }

    public function testOnMessagePostedCreatesFeedEventWithCorrectMetadata(): void
    {
        // 1. Setup Data
        $uuid = 'convo-uuid-123';
        $conversation = new Conversation(
            null,
            $uuid,
            'ticket',
            '100',
            'Ticket Discussion',
            'ticket:100',
            'open',
            new \DateTimeImmutable()
        );

        $event = new MessagePosted(
            $uuid,
            'Hello World',
            [],
            [],
            1,
            null
        );

        // 2. Expect Repositories calls
        $this->conversationRepo->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn($conversation);

        $this->feedRepo->expects($this->once())
            ->method('findLatestBySource')
            ->willReturn(null);

        $this->feedRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (FeedEvent $feedEvent) use ($uuid) {
                $metadata = $feedEvent->getMetadata();
                return $feedEvent->getEventType() === 'conversation.message_posted'
                    && $feedEvent->getSourceEntityId() === $uuid
                    && $feedEvent->getSourceEngine() === 'conversation'
                    && $metadata['context_type'] === 'ticket'
                    && $metadata['context_id'] === '100'
                    && $metadata['subject_key'] === 'ticket:100';
            }));

        // 3. Execute
        $this->listener->onMessagePosted($event);
    }
}

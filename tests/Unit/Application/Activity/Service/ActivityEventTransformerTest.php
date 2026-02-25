<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Activity\Service;

use Pet\Application\Activity\Service\ActivityEventTransformer;
use Pet\Domain\Feed\Entity\FeedEvent;
use PHPUnit\Framework\TestCase;

class ActivityEventTransformerTest extends TestCase
{
    private ActivityEventTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new ActivityEventTransformer();
    }

    public function testTransformConversationMessagePosted(): void
    {
        $event = FeedEvent::create(
            'evt-1',
            'conversation.message_posted',
            'conversation',
            '123',
            'operational',
            'New Message',
            'User posted a message',
            [
                'context_type' => 'ticket',
                'context_id' => '456',
                'actor_type' => 'user',
                'actor_id' => '789',
                'actor_name' => 'John Doe',
            ],
            'global',
            null
        );

        $activity = $this->transformer->fromFeedEvent($event);

        $this->assertEquals('CONVERSATION_MESSAGE', $activity->eventType);
        $this->assertEquals('info', $activity->severity);
        $this->assertEquals('ticket', $activity->referenceType);
        $this->assertEquals('#456', $activity->referenceId);
        $this->assertEquals('John Doe', $activity->actorDisplayName);
    }

    public function testTransformDecisionRequested(): void
    {
        $event = FeedEvent::create(
            'evt-2',
            'conversation.decision_requested',
            'conversation',
            '124',
            'operational',
            'Decision Needed',
            'Please approve this',
            [
                'context_type' => 'project',
                'context_id' => '789',
            ],
            'global',
            null
        );

        $activity = $this->transformer->fromFeedEvent($event);

        $this->assertEquals('DECISION_REQUESTED', $activity->eventType);
        $this->assertEquals('attention', $activity->severity);
        $this->assertEquals('project', $activity->referenceType);
        $this->assertEquals('#789', $activity->referenceId);
    }

    public function testTransformDecisionResponded(): void
    {
        $event = FeedEvent::create(
            'evt-3',
            'conversation.decision_recorded',
            'conversation',
            '125',
            'operational',
            'Decision Made',
            'Approved',
            [
                'context_type' => 'knowledge_article',
                'context_id' => '999',
            ],
            'global',
            null
        );

        $activity = $this->transformer->fromFeedEvent($event);

        $this->assertEquals('DECISION_RESPONDED', $activity->eventType);
        $this->assertEquals('info', $activity->severity);
        $this->assertEquals('knowledge', $activity->referenceType);
        $this->assertEquals('#999', $activity->referenceId);
    }
}

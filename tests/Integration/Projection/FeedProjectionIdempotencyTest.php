<?php

declare(strict_types=1);

namespace Tests\Integration\Projection;

use Pet\Application\Projection\Listener\FeedProjectionListener;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use PHPUnit\Framework\TestCase;

class FeedProjectionIdempotencyTest extends TestCase
{
    public function testQuoteAcceptedIsIdempotent(): void
    {
        // Mocks
        $feedRepo = $this->createMock(FeedEventRepository::class);
        $activityRepo = $this->createMock(ActivityLogRepository::class);
        $convRepo = $this->createMock(ConversationRepository::class);
        $decisionRepo = $this->createMock(DecisionRepository::class);

        $listener = new FeedProjectionListener(
            $activityRepo,
            $feedRepo,
            $convRepo,
            $decisionRepo
        );

        // Quote Mock
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn(123);
        $quote->method('totalValue')->willReturn(1000.0);
        
        $event = new QuoteAccepted($quote);

        // Expectation:
        // 1. First call: findLatestBySource returns null -> save() called
        // 2. Second call: findLatestBySource returns object -> save() NOT called
        
        $feedRepo->expects($this->exactly(2))
            ->method('findLatestBySource')
            ->with('commercial', '123', 'commercial.quote_accepted')
            ->willReturnOnConsecutiveCalls(null, $this->createMock(FeedEvent::class));

        $feedRepo->expects($this->once())
            ->method('save');
            
        // Also log should only be called once
        $activityRepo->expects($this->once())
            ->method('save');

        // Execute twice
        $listener->onQuoteAccepted($event); // Should save
        $listener->onQuoteAccepted($event); // Should skip
    }
}

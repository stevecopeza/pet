<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Application\Conversation\Service\ActionGatingService;
use Pet\Infrastructure\Persistence\Repository\Conversation\SqlConversationRepository;
use Pet\Infrastructure\Persistence\Repository\Conversation\SqlDecisionRepository;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Entity\Decision;
use Pet\Application\Conversation\Exception\ActionGatedByDecisionException;

class AcceptQuoteGatingEnforcementTest extends TestCase
{
    private $wpdb;
    private $quoteRepo;
    private $conversationRepo;
    private $decisionRepo;
    private $eventBus;
    private $gatingService;
    private $handler;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%f', '%s', $query);
            $query = str_replace('%s', "'%s'", $query);
            return vsprintf($query, $args);
        });

        $this->quoteRepo = $this->createMock(SqlQuoteRepository::class);
        $this->conversationRepo = $this->createMock(SqlConversationRepository::class);
        $this->decisionRepo = $this->createMock(SqlDecisionRepository::class);
        $this->eventBus = new InMemoryEventBus();
        
        $this->gatingService = new ActionGatingService(
            $this->conversationRepo,
            $this->decisionRepo
        );

        $this->handler = new AcceptQuoteHandler(
            $this->quoteRepo,
            $this->eventBus,
            null,
            null,
            $this->gatingService
        );
    }

    private function createQuote(int $id): Quote
    {
        $item = new QuoteCatalogItem('Item 1', 2.0, 100.0, 50.0, null, null, [], 'product', 'SKU-TEST');
        $component = new CatalogComponent([$item], 'Component 1');
        $paymentSchedule = [
            new PaymentMilestone('Deposit', 200.0, null, false),
            new PaymentMilestone('Balance', 800.0, null, false)
        ];
        
        return new Quote(
            1, // customerId
            'Test Quote', // title
            'Description', // description
            QuoteState::fromString('sent'), // state
            1, // version
            1000.00, // totalValue
            500.00, // totalInternalCost
            'AUD', // currency
            null, // acceptedAt
            $id, // id
            new \DateTimeImmutable(), // createdAt
            null, // updatedAt
            null, // archivedAt
            [$component], // components
            [], // malleableData
            [], // costAdjustments
            $paymentSchedule // paymentSchedule
        );
    }

    public function testAcceptQuoteIsGated()
    {
        $quoteId = 123;
        $quote = $this->createQuote($quoteId);
        $this->quoteRepo->method('findById')->willReturn($quote);

        // Expectation: It should check for conversation
        // If gating is OFF (current state), this might not be called, or it might be called but not throw exception.
        // If gating is ON, it will call findByContext.
        
        // Scenario: No conversation exists.
        // If gating is ON, this should throw exception because decision is required but no conversation.
        $this->conversationRepo->method('findByContext')->willReturn(null);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("requires decisions: accept_quote_approval");

        $this->handler->handle(new AcceptQuoteCommand($quoteId));
    }
}

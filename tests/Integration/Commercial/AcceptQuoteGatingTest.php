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

class AcceptQuoteGatingTest extends TestCase
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

    public function testAcceptQuoteBlockedWhenNoConversation()
    {
        $quoteId = 123;
        $quote = $this->createQuote($quoteId);
        $this->quoteRepo->method('findById')->willReturn($quote);
        $this->conversationRepo->method('findByContext')->willReturn(null);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("no conversation found");

        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);
    }

    public function testAcceptQuoteBlockedIfDecisionPending()
    {
        $quoteId = 124;
        $quote = $this->createQuote($quoteId);
        $this->quoteRepo->method('findById')->willReturn($quote);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(10);
        $this->conversationRepo->method('findByContext')->willReturn($conversation);

        $decision = $this->createMock(Decision::class);
        $decision->method('decisionType')->willReturn('accept_quote_approval');
        $decision->method('state')->willReturn('pending');
        
        $this->decisionRepo->method('findByConversationId')->willReturn([$decision]);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("blocked by decision 'accept_quote_approval' in state 'pending'");

        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);
    }

    public function testAcceptQuoteBlockedWhenRequiredDecisionMissing()
    {
        $quoteId = 126;
        $quote = $this->createQuote($quoteId);
        $this->quoteRepo->method('findById')->willReturn($quote);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(10);
        $this->conversationRepo->method('findByContext')->willReturn($conversation);

        // Only send_quote_approval exists, but we need accept_quote_approval
        $decision = $this->createMock(Decision::class);
        $decision->method('decisionType')->willReturn('send_quote_approval');
        $decision->method('state')->willReturn('pending');
        
        $this->decisionRepo->method('findByConversationId')->willReturn([$decision]);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("requires decision 'accept_quote_approval', which is missing");

        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);
    }

    public function testAcceptQuoteAllowedWhenApproved()
    {
        $quoteId = 127;
        $quote = $this->createQuote($quoteId);
        $this->quoteRepo->method('findById')->willReturn($quote);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(10);
        $this->conversationRepo->method('findByContext')->willReturn($conversation);

        $decision = $this->createMock(Decision::class);
        $decision->method('decisionType')->willReturn('accept_quote_approval');
        $decision->method('state')->willReturn('approved');
        $decision->method('requestedAt')->willReturn(new \DateTimeImmutable());
        
        $this->decisionRepo->method('findByConversationId')->willReturn([$decision]);

        // Should NOT throw exception
        $command = new AcceptQuoteCommand($quoteId);
        $this->handler->handle($command);
        
        $this->assertTrue(true);
    }
}

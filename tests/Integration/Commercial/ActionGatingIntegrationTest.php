<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\SendQuoteHandler;
use Pet\Application\Commercial\Command\SendQuoteCommand;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Application\Conversation\Service\ActionGatingService;
use Pet\Infrastructure\Persistence\Repository\Conversation\SqlConversationRepository;
use Pet\Infrastructure\Persistence\Repository\Conversation\SqlDecisionRepository;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Entity\Decision;
use Pet\Application\Conversation\Exception\ActionGatedByDecisionException;

use Pet\Application\System\Service\TransactionManager;

class ActionGatingIntegrationTest extends TestCase
{
    private $quoteRepo;
    private $conversationRepo;
    private $decisionRepo;
    private $eventBus;
    private $gatingService;
    private $acceptHandler;
    private $sendHandler;

    protected function setUp(): void
    {
        $this->quoteRepo = $this->createMock(SqlQuoteRepository::class);
        $this->conversationRepo = $this->createMock(SqlConversationRepository::class);
        $this->decisionRepo = $this->createMock(SqlDecisionRepository::class);
        $this->eventBus = new InMemoryEventBus();

        $this->gatingService = new ActionGatingService(
            $this->conversationRepo,
            $this->decisionRepo
        );

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $this->acceptHandler = new AcceptQuoteHandler(
            $transactionManager,
            $this->quoteRepo,
            $this->eventBus,
            null,
            null,
            $this->gatingService
        );

        $this->sendHandler = new SendQuoteHandler(
            $transactionManager,
            $this->quoteRepo,
            $this->gatingService
        );
    }

    private function createQuote(int $id, int $version, ?QuoteState $state = null): Quote
    {
        $item = new QuoteCatalogItem(
            'Test Item',
            1.0,
            1000.0,
            500.0,
            null,
            null,
            [],
            'product',
            'SKU-TEST'
        );
        $component = new CatalogComponent([$item], 'Test Component');
        $milestone = new PaymentMilestone('Deposit', 1000.0);

        return new Quote(
            1,
            'Test Quote',
            'Desc',
            $state ?? QuoteState::fromString('draft'),
            $version,
            1000.0,
            500.0,
            'AUD',
            null,
            $id,
            new \DateTimeImmutable(),
            null,
            null,
            [$component],
            [],
            [],
            [$milestone]
        );
    }

    private function createConversation(int $id, string $contextId, ?string $version): Conversation
    {
        return new Conversation(
            $id, 'uuid-'.$id, 'quote', $contextId, 'Subject', 'key', 'open',
            new \DateTimeImmutable(), $version
        );
    }

    private function createDecision(int $id, int $conversationId, string $type, string $state): Decision
    {
        $d = $this->createMock(Decision::class);
        $d->method('id')->willReturn($id);
        $d->method('decisionType')->willReturn($type);
        $d->method('state')->willReturn($state);
        $d->method('requestedAt')->willReturn(new \DateTimeImmutable());
        return $d;
    }

    public function testVersionIsolation()
    {
        // Scenario 1: v1 Send (Approved)
        $quoteV1 = $this->createQuote(1, 1);
        $this->quoteRepo->method('findById')->willReturn($quoteV1);
        
        $convV1 = $this->createConversation(10, '1', '1');
        $decisionApproved = $this->createDecision(100, 10, 'send_quote_approval', 'approved');

        $this->conversationRepo->expects($this->once())
            ->method('findByContext')
            ->with('quote', '1', '1')
            ->willReturn($convV1);

        $this->decisionRepo->method('findByConversationId')
            ->with(10)
            ->willReturn([$decisionApproved]);

        $this->sendHandler->handle(new SendQuoteCommand(1));
    }

    public function testVersionIsolationBlocked()
    {
        // Scenario 2: v2 Send (Pending)
        $quoteV2 = $this->createQuote(1, 2);
        
        // Reset quote repo mock or create new test class instance (PHPUnit does separate instance per test)
        // Since setUp is called per test, mocks are fresh.
        $this->quoteRepo->method('findById')->willReturn($quoteV2);
        
        $convV2 = $this->createConversation(20, '1', '2');
        $decisionPending = $this->createDecision(200, 20, 'send_quote_approval', 'pending');

        $this->conversationRepo->expects($this->once())
            ->method('findByContext')
            ->with('quote', '1', '2')
            ->willReturn($convV2);

        $this->decisionRepo->method('findByConversationId')
            ->with(20)
            ->willReturn([$decisionPending]);

        try {
            $this->sendHandler->handle(new SendQuoteCommand(1));
            $this->fail('Expected ActionGatedByDecisionException was not thrown');
        } catch (ActionGatedByDecisionException $e) {
            $this->assertEquals('ACTION_GATED_BY_DECISION', $e->getErrorCode());
            $this->assertEquals('send_quote', $e->getAction());
            $this->assertEquals(['200'], $e->getDecisionIds());
        }
    }

    public function testAcceptQuoteIsGated()
    {
        $quote = $this->createQuote(1, 1, QuoteState::sent());
        $this->quoteRepo->method('findById')->willReturn($quote);

        // Since accept_quote IS NOW in REQUIRED_DECISIONS, 
        // ActionGatingService::check queries conversation
        $this->conversationRepo->expects($this->once())
            ->method('findByContext')
            ->willReturn(null);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("no conversation found");

        $this->acceptHandler->handle(new AcceptQuoteCommand(1));
    }

    public function testMissingConversationBlocksRequiredAction()
    {
        $quote = $this->createQuote(1, 1);
        $this->quoteRepo->method('findById')->willReturn($quote);

        $this->conversationRepo->method('findByContext')
            ->willReturn(null);

        $this->expectException(ActionGatedByDecisionException::class);
        $this->expectExceptionMessage("no conversation found");

        $this->sendHandler->handle(new SendQuoteCommand(1));
    }

    public function testIrrelevantDecisionDoesNotBlock()
    {
        $quote = $this->createQuote(1, 1);
        $this->quoteRepo->method('findById')->willReturn($quote);
        
        $conv = $this->createConversation(30, '1', '1');
        $decisionRelevant = $this->createDecision(301, 30, 'send_quote_approval', 'approved');
        $decisionIrrelevant = $this->createDecision(302, 30, 'other_approval', 'pending');

        $this->conversationRepo->expects($this->once())
            ->method('findByContext')
            ->with('quote', '1', '1')
            ->willReturn($conv);

        $this->decisionRepo->method('findByConversationId')
            ->with(30)
            ->willReturn([$decisionRelevant, $decisionIrrelevant]);

        $this->sendHandler->handle(new SendQuoteCommand(1));
    }
}

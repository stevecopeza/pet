<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Application\Commercial\Command\AddCostAdjustmentHandler;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Application\Commercial\Command\AddQuoteSectionHandler;
use Pet\Application\Commercial\Command\ArchiveQuoteHandler;
use Pet\Application\Commercial\Command\CreateQuoteBlockCommand;
use Pet\Application\Commercial\Command\CreateQuoteBlockHandler;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Application\Commercial\Command\RemoveComponentHandler;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler;
use Pet\Application\Commercial\Command\SendQuoteHandler;
use Pet\Application\Commercial\Command\SetPaymentScheduleHandler;
use Pet\Application\Commercial\Command\UpdateQuoteSectionHandler;
use Pet\Application\Commercial\Command\CloneQuoteSectionHandler;
use Pet\Application\Commercial\Command\DeleteQuoteSectionHandler;
use Pet\Application\Commercial\Command\DeleteQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteHandler;
use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\UI\Rest\Controller\QuoteController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class QuoteControllerBlocksTest extends TestCase
{
    public function testAddBlockToSectionCreatesBlockAndReturnsUpdatedQuote(): void
    {
        $quote = new Quote(
            10,
            'Test',
            null,
            QuoteState::draft(),
            1,
            0.0,
            0.0,
            'USD',
            null,
            10
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(10)
            ->willReturn($quote);

        $section = new QuoteSection(
            10,
            'Section A',
            1000,
            true,
            false,
            false,
            1
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->method('findByQuoteId')
            ->with(10)
            ->willReturn([$section]);

        $existingBlock = new QuoteBlock(
            1000,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            null,
            0.0,
            0.0,
            true,
            1,
            [],
            5
        );

        $blocks = [$existingBlock];

        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $quoteBlockRepository
            ->method('findByQuoteId')
            ->willReturnCallback(function (int $quoteId) use (&$blocks) {
                return $blocks;
            });

        $quoteBlockRepository
            ->method('insert')
            ->willReturnCallback(function (QuoteBlock $block, int $quoteId) use (&$blocks) {
                $newBlock = new QuoteBlock(
                    $block->position(),
                    $block->type(),
                    $block->componentId(),
                    $block->sellValue(),
                    $block->internalCost(),
                    $block->isPriced(),
                    $block->sectionId(),
                    $block->payload(),
                    6
                );

                $blocks[] = $newBlock;

                return $newBlock;
            });

        $createHandler = $this->createMock(CreateQuoteHandler::class);
        $updateHandler = $this->createMock(UpdateQuoteHandler::class);
        $addLineHandler = $this->createMock(AddQuoteLineHandler::class);
        $archiveHandler = $this->createMock(ArchiveQuoteHandler::class);
        $addComponentHandler = $this->createMock(AddComponentHandler::class);
        $removeComponentHandler = $this->createMock(RemoveComponentHandler::class);
        $sendHandler = $this->createMock(SendQuoteHandler::class);
        $acceptHandler = $this->createMock(AcceptQuoteHandler::class);
        $addAdjustmentHandler = $this->createMock(AddCostAdjustmentHandler::class);
        $removeAdjustmentHandler = $this->createMock(RemoveCostAdjustmentHandler::class);
        $setScheduleHandler = $this->createMock(SetPaymentScheduleHandler::class);

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $updateSectionHandler = new UpdateQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository);
        $cloneSectionHandler = new CloneQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $deleteSectionHandler = new DeleteQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $addSectionHandler = $this->createMock(AddQuoteSectionHandler::class);

        $createBlockHandler = new CreateQuoteBlockHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);

        $deleteBlockHandler = new DeleteQuoteBlockHandler($transactionManager, $quoteRepository, $quoteBlockRepository);

        $controller = new QuoteController(
            $quoteRepository,
            $createHandler,
            $updateHandler,
            $addLineHandler,
            $archiveHandler,
            $addComponentHandler,
            $removeComponentHandler,
            $sendHandler,
            $acceptHandler,
            $addAdjustmentHandler,
            $removeAdjustmentHandler,
            $setScheduleHandler,
            $quoteSectionRepository,
            $addSectionHandler,
            $updateSectionHandler,
            $cloneSectionHandler,
            $deleteSectionHandler,
            $quoteBlockRepository,
            $createBlockHandler,
            new UpdateQuoteBlockHandler($transactionManager, $quoteRepository, $quoteBlockRepository),
            $deleteBlockHandler
        );

        $request = new WP_REST_Request();
        $request->set_param('id', 10);
        $request->set_param('sectionId', 1);
        $request->set_json_params([
            'type' => QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            'payload' => ['foo' => 'bar'],
        ]);

        $response = $controller->addBlockToSection($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('blocks', $data);
        $this->assertCount(2, $data['blocks']);
        $this->assertSame(1, $data['blocks'][0]['sectionId']);
        $this->assertSame(1, $data['blocks'][1]['sectionId']);
    }

    public function testAddBlockWithNullSectionIdCreatesRootLevelBlock(): void
    {
        $quote = new Quote(
            20,
            'Test',
            null,
            QuoteState::draft(),
            1,
            0.0,
            0.0,
            'USD',
            null,
            20
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(20)
            ->willReturn($quote);

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->method('findByQuoteId')
            ->with(20)
            ->willReturn([]);

        $existingBlock = new QuoteBlock(
            1000,
            QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE,
            null,
            0.0,
            0.0,
            true,
            null,
            [],
            1
        );

        $rootAdjustment = new QuoteBlock(
            2000,
            QuoteBlock::TYPE_PRICE_ADJUSTMENT,
            null,
            0.0,
            0.0,
            true,
            null,
            [],
            2
        );

        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $quoteBlockRepository
            ->method('findByQuoteId')
            ->with(20)
            ->willReturn([$existingBlock, $rootAdjustment]);

        $quoteBlockRepository
            ->method('insert')
            ->willReturn($rootAdjustment);

        $createHandler = $this->createMock(CreateQuoteHandler::class);
        $updateHandler = $this->createMock(UpdateQuoteHandler::class);
        $addLineHandler = $this->createMock(AddQuoteLineHandler::class);
        $archiveHandler = $this->createMock(ArchiveQuoteHandler::class);
        $addComponentHandler = $this->createMock(AddComponentHandler::class);
        $removeComponentHandler = $this->createMock(RemoveComponentHandler::class);
        $sendHandler = $this->createMock(SendQuoteHandler::class);
        $acceptHandler = $this->createMock(AcceptQuoteHandler::class);
        $addAdjustmentHandler = $this->createMock(AddCostAdjustmentHandler::class);
        $removeAdjustmentHandler = $this->createMock(RemoveCostAdjustmentHandler::class);
        $setScheduleHandler = $this->createMock(SetPaymentScheduleHandler::class);

        $transactionManager = $this->createMock(TransactionManager::class);
        $transactionManager->method('transactional')->willReturnCallback(function ($callable) {
            return $callable();
        });

        $updateSectionHandler = new UpdateQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository);
        $cloneSectionHandler = new CloneQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $deleteSectionHandler = new DeleteQuoteSectionHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $addSectionHandler = $this->createMock(AddQuoteSectionHandler::class);

        $createBlockHandler = new CreateQuoteBlockHandler($transactionManager, $quoteRepository, $quoteSectionRepository, $quoteBlockRepository);

        $updateBlockHandler = new UpdateQuoteBlockHandler($transactionManager, $quoteRepository, $quoteBlockRepository);
        $deleteBlockHandler = new DeleteQuoteBlockHandler($transactionManager, $quoteRepository, $quoteBlockRepository);

        $controller = new QuoteController(
            $quoteRepository,
            $createHandler,
            $updateHandler,
            $addLineHandler,
            $archiveHandler,
            $addComponentHandler,
            $removeComponentHandler,
            $sendHandler,
            $acceptHandler,
            $addAdjustmentHandler,
            $removeAdjustmentHandler,
            $setScheduleHandler,
            $quoteSectionRepository,
            $addSectionHandler,
            $updateSectionHandler,
            $cloneSectionHandler,
            $deleteSectionHandler,
            $quoteBlockRepository,
            $createBlockHandler,
            $updateBlockHandler,
            $deleteBlockHandler
        );

        $request = new WP_REST_Request();
        $request->set_param('id', 20);
        $request->set_param('sectionId', 'null');
        $request->set_json_params([
            'type' => QuoteBlock::TYPE_PRICE_ADJUSTMENT,
            'payload' => ['amount' => 100],
        ]);

        $response = $controller->addBlockToSection($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('blocks', $data);
        $this->assertCount(2, $data['blocks']);
        $this->assertNull($data['blocks'][0]['sectionId']);
        $this->assertNull($data['blocks'][1]['sectionId']);
    }
}

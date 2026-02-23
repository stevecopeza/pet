<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

require_once __DIR__ . '/../../../../Stubs/WP_REST_Classes.php';

use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Application\Commercial\Command\AddCostAdjustmentHandler;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Application\Commercial\Command\AddQuoteSectionCommand;
use Pet\Application\Commercial\Command\AddQuoteSectionHandler;
use Pet\Application\Commercial\Command\ArchiveQuoteHandler;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Application\Commercial\Command\RemoveComponentHandler;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler;
use Pet\Application\Commercial\Command\SendQuoteHandler;
use Pet\Application\Commercial\Command\SetPaymentScheduleHandler;
use Pet\Application\Commercial\Command\UpdateQuoteSectionHandler;
use Pet\Application\Commercial\Command\CloneQuoteSectionHandler;
use Pet\Application\Commercial\Command\DeleteQuoteSectionHandler;
use Pet\Application\Commercial\Command\CreateQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteBlockHandler;
use Pet\Application\Commercial\Command\DeleteQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\UI\Rest\Controller\QuoteController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class QuoteControllerTest extends TestCase
{
    public function testAddSectionCreatesSectionAndReturnsUpdatedQuote(): void
    {
        $quote = new Quote(
            2,
            'Test',
            null,
            QuoteState::draft(),
            1,
            0.0,
            0.0,
            'USD',
            null,
            31
        );

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository
            ->method('findById')
            ->with(31)
            ->willReturn($quote);

        $existingSection = new QuoteSection(
            31,
            'Existing',
            1000,
            true,
            false,
            false,
            1
        );

        $newSection = new QuoteSection(
            31,
            'New Section',
            2000,
            true,
            false,
            false,
            2
        );

        $quoteSectionRepository = $this->createMock(QuoteSectionRepository::class);
        $quoteSectionRepository
            ->method('findByQuoteId')
            ->with(31)
            ->willReturn([$existingSection, $newSection]);

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
        $updateSectionHandler = new UpdateQuoteSectionHandler($quoteRepository, $quoteSectionRepository);
        $quoteBlockRepository = $this->createMock(QuoteBlockRepository::class);
        $cloneSectionHandler = new CloneQuoteSectionHandler($quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $deleteSectionHandler = new DeleteQuoteSectionHandler($quoteRepository, $quoteSectionRepository, $quoteBlockRepository);

        $addSectionHandler = $this->createMock(AddQuoteSectionHandler::class);
        $addSectionHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (AddQuoteSectionCommand $command) {
                return $command->quoteId() === 31 && $command->name() === 'New Section';
            }))
            ->willReturn($newSection);

        $quoteBlockRepository
            ->method('findByQuoteId')
            ->with(31)
            ->willReturn([]);

        $createBlockHandler = new CreateQuoteBlockHandler($quoteRepository, $quoteSectionRepository, $quoteBlockRepository);
        $updateBlockHandler = new UpdateQuoteBlockHandler($quoteRepository, $quoteBlockRepository);
        $deleteBlockHandler = new DeleteQuoteBlockHandler($quoteRepository, $quoteBlockRepository);

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
        $request->set_param('id', 31);
        $request->set_json_params(['name' => 'New Section']);

        $response = $controller->addSection($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('sections', $data);
        $this->assertCount(2, $data['sections']);
        $this->assertSame('Existing', $data['sections'][0]['name']);
        $this->assertSame('New Section', $data['sections'][1]['name']);
        $this->assertArrayHasKey('blocks', $data);
    }
}

<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Event\EventBus;

class AcceptQuoteHandler
{
    private QuoteRepository $quoteRepository;
    private EventBus $eventBus;

    public function __construct(QuoteRepository $quoteRepository, EventBus $eventBus)
    {
        $this->quoteRepository = $quoteRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(AcceptQuoteCommand $command): void
    {
        error_log("AcceptQuoteHandler: Handling quote " . $command->id());
        
        $quote = $this->quoteRepository->findById($command->id());

        if (!$quote) {
            error_log("AcceptQuoteHandler: Quote not found " . $command->id());
            throw new \RuntimeException('Quote not found');
        }

        try {
            $quote->accept();
            error_log("AcceptQuoteHandler: Quote accepted. State: " . $quote->state()->toString());
            
            $this->quoteRepository->save($quote);
            error_log("AcceptQuoteHandler: Quote saved.");
            
            $this->eventBus->dispatch(new QuoteAccepted($quote));
            error_log("AcceptQuoteHandler: Event dispatched.");
        } catch (\Exception $e) {
            error_log("AcceptQuoteHandler: Error accepting quote: " . $e->getMessage());
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class UpdateQuoteHandler
{
    private QuoteRepository $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(UpdateQuoteCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->id());

        if (!$quote) {
            throw new \RuntimeException('Quote not found');
        }

        $quote->update(
            $command->customerId(),
            $command->totalValue(),
            $command->currency(),
            $command->acceptedAt(),
            $command->malleableData()
        );

        $this->quoteRepository->save($quote);
    }
}

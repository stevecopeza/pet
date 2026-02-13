<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class RemoveComponentHandler
{
    private QuoteRepository $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(RemoveComponentCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $quote->removeComponent($command->componentId());
        $this->quoteRepository->save($quote);
    }
}

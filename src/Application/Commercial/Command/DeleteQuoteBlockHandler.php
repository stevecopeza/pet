<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;

final class DeleteQuoteBlockHandler
{
    private QuoteRepository $quoteRepository;
    private QuoteBlockRepository $quoteBlockRepository;

    public function __construct(
        QuoteRepository $quoteRepository,
        QuoteBlockRepository $quoteBlockRepository
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteBlockRepository = $quoteBlockRepository;
    }

    public function handle(DeleteQuoteBlockCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if ($quote === null) {
            throw new \DomainException('Quote not found');
        }

        $blocks = $this->quoteBlockRepository->findByQuoteId($command->quoteId());

        $exists = false;

        foreach ($blocks as $block) {
            if ($block->id() === $command->blockId()) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            throw new \DomainException('Block not found');
        }

        $this->quoteBlockRepository->delete($command->blockId());
    }
}


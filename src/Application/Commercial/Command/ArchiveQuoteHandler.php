<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;

class ArchiveQuoteHandler
{
    private QuoteRepository $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(ArchiveQuoteCommand $command): void
    {
        $this->quoteRepository->delete($command->id());
    }
}

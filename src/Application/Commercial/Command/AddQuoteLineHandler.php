<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Entity\QuoteLine;
use Pet\Domain\Commercial\Repository\QuoteRepository;

class AddQuoteLineHandler
{
    private QuoteRepository $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(AddQuoteLineCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $line = new QuoteLine(
            $command->description(),
            $command->quantity(),
            $command->unitPrice(),
            $command->lineGroupType()
        );

        $quote->addLine($line);

        $this->quoteRepository->save($quote);
    }
}

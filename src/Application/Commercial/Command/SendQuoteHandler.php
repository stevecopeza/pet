<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Application\Conversation\Service\ActionGatingService;

class SendQuoteHandler
{
    private QuoteRepository $quoteRepository;
    private ?ActionGatingService $gatingService;

    public function __construct(
        QuoteRepository $quoteRepository,
        ?ActionGatingService $gatingService = null
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->gatingService = $gatingService;
    }

    public function handle(SendQuoteCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        if ($this->gatingService) {
            // Check if sending is allowed
            // Context Type: 'quote', Context ID: quote ID
            // Action: 'send_quote'
            $this->gatingService->check('quote', (string)$quote->id(), 'send_quote', $quote->version());
        }

        $quote->send();
        $this->quoteRepository->save($quote);
    }
}

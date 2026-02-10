<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Identity\Repository\CustomerRepository;

class CreateQuoteHandler
{
    private QuoteRepository $quoteRepository;
    private CustomerRepository $customerRepository;

    public function __construct(
        QuoteRepository $quoteRepository,
        CustomerRepository $customerRepository
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->customerRepository = $customerRepository;
    }

    public function handle(CreateQuoteCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $quote = new Quote(
            $command->customerId(),
            QuoteState::draft()
        );

        $this->quoteRepository->save($quote);
    }
}

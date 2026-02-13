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

    public function handle(CreateQuoteCommand $command): int
    {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $quote = new Quote(
            $command->customerId(),
            $command->title(),
            $command->description(),
            QuoteState::draft(),
            1,
            0.00, // Initial totalValue
            0.00, // totalInternalCost
            $command->currency(),
            $command->acceptedAt(),
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [],
            $command->malleableData()
        );

        $this->quoteRepository->save($quote);
        
        return $quote->id();
    }
}

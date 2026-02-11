<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Repository\CustomerRepository;

class UpdateCustomerHandler
{
    private CustomerRepository $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function handle(UpdateCustomerCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->id());

        if (!$customer) {
            throw new \RuntimeException('Customer not found');
        }

        $customer->update(
            $command->name(),
            $command->contactEmail(),
            $command->legalName(),
            $command->status(),
            $command->malleableData()
        );

        $this->customerRepository->save($customer);
    }
}

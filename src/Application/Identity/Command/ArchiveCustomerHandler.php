<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Repository\CustomerRepository;

class ArchiveCustomerHandler
{
    private CustomerRepository $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function handle(ArchiveCustomerCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->id());

        if (!$customer) {
            throw new \RuntimeException('Customer not found');
        }

        $customer->archive();

        $this->customerRepository->save($customer);
    }
}

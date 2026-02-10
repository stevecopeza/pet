<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;

class CreateCustomerHandler
{
    private CustomerRepository $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function handle(CreateCustomerCommand $command): void
    {
        $customer = new Customer(
            $command->name(),
            $command->contactEmail()
        );

        $this->customerRepository->save($customer);
    }
}

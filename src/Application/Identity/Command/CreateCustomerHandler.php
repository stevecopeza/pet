<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;

class CreateCustomerHandler
{
    private CustomerRepository $customerRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(
        CustomerRepository $customerRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->customerRepository = $customerRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateCustomerCommand $command): void
    {
        // Handle Malleable Data
        $activeSchema = $this->schemaRepository->findActiveByEntityType('customer');
        $malleableSchemaId = null;
        $malleableData = $command->malleableData();

        if ($activeSchema) {
            $malleableSchemaId = $activeSchema->id();
            // Validate data against schema
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            if (!empty($errors)) {
                throw new \InvalidArgumentException("Invalid customer data: " . implode(', ', $errors));
            }
        }

        $customer = new Customer(
            $command->name(),
            $command->contactEmail(),
            null,
            $command->legalName(),
            $command->status(),
            $malleableSchemaId,
            $malleableData
        );

        $this->customerRepository->save($customer);
    }
}

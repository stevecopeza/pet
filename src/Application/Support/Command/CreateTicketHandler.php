<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Event\TicketCreated;
use InvalidArgumentException;

class CreateTicketHandler
{
    private TicketRepository $ticketRepository;
    private CustomerRepository $customerRepository;
    private EventBus $eventBus;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(
        TicketRepository $ticketRepository,
        CustomerRepository $customerRepository,
        EventBus $eventBus,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->customerRepository = $customerRepository;
        $this->eventBus = $eventBus;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateTicketCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $activeSchema = $this->schemaRepository->findActiveByEntityType('ticket');
        $malleableData = $command->malleableData();
        $schemaVersion = null;

        if ($activeSchema) {
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $ticket = new Ticket(
            $command->customerId(),
            $command->subject(),
            $command->description(),
            'new',
            $command->priority(),
            $command->siteId(),
            $command->slaId(),
            null,
            $schemaVersion,
            $malleableData
        );

        $this->ticketRepository->save($ticket);

        // Dispatch event
        $this->eventBus->dispatch(new TicketCreated($ticket));
    }
}

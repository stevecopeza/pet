<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Support\Event\TicketCreated;

class CreateTicketHandler
{
    private TicketRepository $ticketRepository;
    private CustomerRepository $customerRepository;
    private EventBus $eventBus;

    public function __construct(
        TicketRepository $ticketRepository,
        CustomerRepository $customerRepository,
        EventBus $eventBus
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->customerRepository = $customerRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(CreateTicketCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $ticket = new Ticket(
            $command->customerId(),
            $command->subject(),
            $command->description(),
            'new',
            $command->priority()
        );

        $this->ticketRepository->save($ticket);

        // Dispatch event
        $this->eventBus->dispatch(new TicketCreated($ticket));
    }
}

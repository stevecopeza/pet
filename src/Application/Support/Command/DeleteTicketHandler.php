<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

use Pet\Domain\Support\Repository\TicketRepository;

class DeleteTicketHandler
{
    private TicketRepository $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function handle(DeleteTicketCommand $command): void
    {
        $this->ticketRepository->delete($command->id());
    }
}

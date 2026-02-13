<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Repository;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Entity\SlaClockState;

interface SlaClockStateRepository
{
    public function findByTicketIdForUpdate(int $ticketId): ?SlaClockState;
    public function initialize(Ticket $ticket): SlaClockState;
    public function save(SlaClockState $state): void;
}

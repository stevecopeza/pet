<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Event\EventBus;
use Pet\Application\System\Service\TouchedTracker;

final class SubmitTimeEntryHandler
{
    public function __construct(
        private TimeEntryRepository $timeEntryRepository,
        private EventBus $eventBus,
        private TouchedTracker $touched
    ) {
    }

    public function handle(SubmitTimeEntryCommand $command): void
    {
        $entry = $this->timeEntryRepository->findById($command->timeEntryId());
        if (!$entry) {
            throw new \DomainException('Time entry not found');
        }
        $entry->submit();
        $this->timeEntryRepository->save($entry);

        foreach ($entry->releaseEvents() as $event) {
            $this->eventBus->dispatch($event);
        }

        $this->touched->mark('time_entry', (int)$entry->id(), $entry->employeeId());
    }
}

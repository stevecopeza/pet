<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Repository\ContactRepository;
use RuntimeException;

class ArchiveContactHandler
{
    private ContactRepository $contactRepository;

    public function __construct(ContactRepository $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    public function handle(ArchiveContactCommand $command): void
    {
        $contact = $this->contactRepository->findById($command->id);
        if (!$contact) {
            throw new RuntimeException("Contact not found with ID: {$command->id}");
        }

        $contact->archive();
        $this->contactRepository->save($contact);
    }
}

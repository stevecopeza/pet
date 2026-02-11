<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Entity\ContactAffiliation;
use Pet\Domain\Identity\Repository\ContactRepository;
use RuntimeException;

class UpdateContactHandler
{
    private ContactRepository $contactRepository;

    public function __construct(ContactRepository $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    public function handle(UpdateContactCommand $command): void
    {
        $contact = $this->contactRepository->findById($command->id);
        if (!$contact) {
            throw new RuntimeException("Contact not found with ID: {$command->id}");
        }

        $affiliations = [];
        foreach ($command->affiliations as $affData) {
            $affiliations[] = new ContactAffiliation(
                (int) $affData['customerId'],
                isset($affData['siteId']) ? (int) $affData['siteId'] : null,
                $affData['role'] ?? null,
                (bool) ($affData['isPrimary'] ?? false)
            );
        }

        $contact->update(
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->phone,
            $affiliations,
            $command->malleableData
        );

        $this->contactRepository->save($contact);
    }
}

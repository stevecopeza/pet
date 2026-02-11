<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Entity\ContactAffiliation;
use Pet\Domain\Identity\Repository\ContactRepository;

class CreateContactHandler
{
    private ContactRepository $contactRepository;

    public function __construct(ContactRepository $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    public function handle(CreateContactCommand $command): void
    {
        $affiliations = [];
        foreach ($command->affiliations as $affData) {
            $affiliations[] = new ContactAffiliation(
                (int) $affData['customerId'],
                isset($affData['siteId']) ? (int) $affData['siteId'] : null,
                $affData['role'] ?? null,
                (bool) ($affData['isPrimary'] ?? false)
            );
        }

        $contact = new Contact(
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->phone,
            $affiliations,
            null,
            null,
            $command->malleableData
        );

        $this->contactRepository->save($contact);
    }
}

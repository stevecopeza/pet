<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\PersonCertification;
use Pet\Domain\Work\Repository\PersonCertificationRepository;

class AssignCertificationToPersonHandler
{
    private PersonCertificationRepository $personCertificationRepository;

    public function __construct(PersonCertificationRepository $personCertificationRepository)
    {
        $this->personCertificationRepository = $personCertificationRepository;
    }

    public function handle(AssignCertificationToPersonCommand $command): void
    {
        $personCertification = new PersonCertification(
            $command->employeeId(),
            $command->certificationId(),
            $command->obtainedDate(),
            $command->expiryDate(),
            $command->evidenceUrl()
        );

        $this->personCertificationRepository->save($personCertification);
    }
}

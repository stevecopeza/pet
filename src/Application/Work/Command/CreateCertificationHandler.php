<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\Certification;
use Pet\Domain\Work\Repository\CertificationRepository;

class CreateCertificationHandler
{
    private CertificationRepository $certificationRepository;

    public function __construct(CertificationRepository $certificationRepository)
    {
        $this->certificationRepository = $certificationRepository;
    }

    public function handle(CreateCertificationCommand $command): void
    {
        $certification = new Certification(
            $command->name(),
            $command->issuingBody(),
            $command->expiryMonths()
        );

        $this->certificationRepository->save($certification);
    }
}

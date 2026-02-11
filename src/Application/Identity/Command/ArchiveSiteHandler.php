<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Repository\SiteRepository;

class ArchiveSiteHandler
{
    private SiteRepository $siteRepository;

    public function __construct(SiteRepository $siteRepository)
    {
        $this->siteRepository = $siteRepository;
    }

    public function handle(ArchiveSiteCommand $command): void
    {
        $site = $this->siteRepository->findById($command->id());
        if (!$site) {
            throw new \RuntimeException("Site not found");
        }

        $this->siteRepository->delete($site->id());
    }
}

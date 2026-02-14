<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\CapacityOverrideRepository;

final class SetCapacityOverrideHandler
{
    public function __construct(private CapacityOverrideRepository $repo)
    {
    }

    public function handle(SetCapacityOverrideCommand $c): void
    {
        $pct = max(0, min(100, $c->capacityPct()));
        $this->repo->setOverride($c->employeeId(), $c->date(), $pct, $c->reason());
    }
}


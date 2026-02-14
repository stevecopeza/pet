<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Cron;

use Pet\Application\Integration\Service\OutboxDispatcherService;

final class OutboxDispatchJob
{
    public function __construct(private OutboxDispatcherService $dispatcher)
    {
    }

    public function run(): void
    {
        $this->dispatcher->dispatchQuickBooks();
    }
}


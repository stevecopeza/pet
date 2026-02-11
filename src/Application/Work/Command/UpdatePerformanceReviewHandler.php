<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\PerformanceReviewRepository;

class UpdatePerformanceReviewHandler
{
    private $repository;

    public function __construct(PerformanceReviewRepository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(UpdatePerformanceReviewCommand $command): void
    {
        $review = $this->repository->findById($command->id());

        if (!$review) {
            throw new \InvalidArgumentException('Performance review not found.');
        }

        $review->updateContent($command->content());

        if ($command->status()) {
            if ($command->status() === 'submitted') {
                $review->submit();
            } elseif ($command->status() === 'completed') {
                $review->finalize();
            }
        }

        $this->repository->save($review);
    }
}

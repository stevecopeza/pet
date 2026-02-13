<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Entity\PaymentMilestone;

class SetPaymentScheduleHandler
{
    private QuoteRepository $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(SetPaymentScheduleCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());

        if (!$quote) {
            throw new \RuntimeException("Quote not found: " . $command->quoteId());
        }

        $milestones = [];
        foreach ($command->milestones() as $data) {
            $milestones[] = new PaymentMilestone(
                $data['title'],
                (float) $data['amount'],
                !empty($data['dueDate']) ? new \DateTimeImmutable($data['dueDate']) : null
            );
        }

        $quote->setPaymentSchedule($milestones);
        $this->quoteRepository->save($quote);
    }
}

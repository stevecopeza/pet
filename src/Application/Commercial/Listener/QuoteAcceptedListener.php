<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\ContractCreated;
use Pet\Domain\Commercial\Event\BaselineCreated;
use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\Entity\Baseline;
use Pet\Domain\Commercial\Repository\ContractRepository;
use Pet\Domain\Commercial\Repository\BaselineRepository;
use Pet\Domain\Commercial\ValueObject\ContractStatus;
use Pet\Domain\Event\EventBus;

class QuoteAcceptedListener
{
    private ContractRepository $contractRepository;
    private BaselineRepository $baselineRepository;
    private EventBus $eventBus;

    public function __construct(
        ContractRepository $contractRepository,
        BaselineRepository $baselineRepository,
        EventBus $eventBus
    ) {
        $this->contractRepository = $contractRepository;
        $this->baselineRepository = $baselineRepository;
        $this->eventBus = $eventBus;
    }

    public function __invoke(QuoteAccepted $event): void
    {
        $quote = $event->quote();

        // Create Contract
        $contract = new Contract(
            (int)$quote->id(),
            $quote->customerId(),
            ContractStatus::active(),
            $quote->totalValue(),
            $quote->currency() ?? 'USD',
            $quote->acceptedAt() ?? new \DateTimeImmutable()
        );
        
        $this->contractRepository->save($contract);
        $this->eventBus->dispatch(new ContractCreated($contract));
        
        if ($contract->id()) {
            $baseline = new Baseline(
                $contract->id(),
                $quote->totalValue(),
                $quote->totalInternalCost(),
                $quote->components()
            );
            
            $this->baselineRepository->save($baseline);
            $this->eventBus->dispatch(new BaselineCreated($baseline));
        }
    }
}

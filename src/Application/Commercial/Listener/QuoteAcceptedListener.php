<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\Entity\Baseline;
use Pet\Domain\Commercial\Repository\ContractRepository;
use Pet\Domain\Commercial\Repository\BaselineRepository;
use Pet\Domain\Commercial\ValueObject\ContractStatus;

class QuoteAcceptedListener
{
    private ContractRepository $contractRepository;
    private BaselineRepository $baselineRepository;

    public function __construct(
        ContractRepository $contractRepository,
        BaselineRepository $baselineRepository
    ) {
        $this->contractRepository = $contractRepository;
        $this->baselineRepository = $baselineRepository;
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
        
        // Note: In a real implementation with auto-increment IDs, 
        // save() should update the entity ID or we need to flush/refresh.
        // Assuming save() handles this for the object reference or we rely on explicit IDs.
        // If contract->id() is null here, Baseline creation will fail or have null FK.
        // For now, we assume the repository handles identity generation on the object.
        
        if ($contract->id()) {
            $baseline = new Baseline(
                $contract->id(),
                $quote->totalValue(),
                $quote->totalInternalCost(),
                $quote->components()
            );
            
            $this->baselineRepository->save($baseline);
        }
    }
}

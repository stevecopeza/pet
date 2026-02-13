<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;

class AddComponentHandler
{
    private QuoteRepository $quoteRepository;
    private CatalogItemRepository $catalogItemRepository;

    public function __construct(
        QuoteRepository $quoteRepository,
        CatalogItemRepository $catalogItemRepository
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->catalogItemRepository = $catalogItemRepository;
    }

    public function handle(AddComponentCommand $command): void
    {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $type = $command->type();
        $data = $command->data();
        $description = $data['description'] ?? '';
        $section = $data['section'] ?? 'General';

        if ($type === 'catalog') {
            $items = [];
            foreach ($data['items'] ?? [] as $itemData) {
                $catalogItemId = isset($itemData['catalog_item_id']) ? (int) $itemData['catalog_item_id'] : null;
                $wbsSnapshot = [];
                
                if ($catalogItemId) {
                    $catalogItem = $this->catalogItemRepository->findById($catalogItemId);
                    if ($catalogItem) {
                        $wbsSnapshot = $catalogItem->wbsTemplate();
                    }
                }
                
                // Override wbs_snapshot if provided in command data (e.g. from UI)
                if (isset($itemData['wbs_snapshot']) && is_array($itemData['wbs_snapshot'])) {
                    $wbsSnapshot = $itemData['wbs_snapshot'];
                }

                $items[] = new QuoteCatalogItem(
                    $itemData['description'],
                    (float) $itemData['quantity'],
                    (float) $itemData['unit_sell_price'],
                    (float) ($itemData['unit_internal_cost'] ?? 0.0),
                    null, // id
                    $catalogItemId,
                    $wbsSnapshot
                );
            }
            $component = new CatalogComponent($items, $description, null, $section);

        } elseif ($type === 'implementation') {
            $milestones = [];
            foreach ($data['milestones'] ?? [] as $mData) {
                $tasks = [];
                foreach ($mData['tasks'] ?? [] as $tData) {
                    $tasks[] = new QuoteTask(
                        $tData['description'],
                        (float) $tData['duration_hours'],
                        (int) $tData['complexity'],
                        (float) ($tData['internal_cost'] ?? 0.0),
                        (float) $tData['sell_rate']
                    );
                }
                $milestones[] = new QuoteMilestone($mData['description'], $tasks);
            }
            $component = new ImplementationComponent($milestones, $description, null, $section);

        } elseif ($type === 'recurring') {
            $component = new RecurringServiceComponent(
                $data['service_name'],
                $data['sla_snapshot'] ?? [],
                $data['cadence'],
                (int) $data['term_months'],
                $data['renewal_model'],
                (float) $data['sell_price_per_period'],
                (float) ($data['internal_cost_per_period'] ?? 0.0),
                $description,
                null,
                $section
            );

        } else {
            throw new \InvalidArgumentException("Invalid component type: {$type}");
        }

        $quote->addComponent($component);
        $this->quoteRepository->save($quote);
    }
}

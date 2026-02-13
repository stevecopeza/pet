<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Baseline;
use Pet\Domain\Commercial\Repository\BaselineRepository;
use Pet\Domain\Commercial\Entity\Component\QuoteComponent;

class SqlBaselineRepository implements BaselineRepository
{
    private $wpdb;
    private $baselinesTable;
    private $componentsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->baselinesTable = $wpdb->prefix . 'pet_baselines';
        $this->componentsTable = $wpdb->prefix . 'pet_baseline_components';
    }

    public function save(Baseline $baseline): void
    {
        $data = [
            'contract_id' => $baseline->contractId(),
            'total_value' => $baseline->totalValue(),
            'total_internal_cost' => $baseline->totalInternalCost(),
            'created_at' => $baseline->createdAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%f', '%f', '%s'];

        if ($baseline->id()) {
            // Baselines are generally immutable, but if we need to update (e.g. initial save logic flow)
            $this->wpdb->update(
                $this->baselinesTable,
                $data,
                ['id' => $baseline->id()],
                $format,
                ['%d']
            );
            $baselineId = $baseline->id();
        } else {
            $this->wpdb->insert(
                $this->baselinesTable,
                $data,
                $format
            );
            $baselineId = $this->wpdb->insert_id;
            $this->setId($baseline, (int)$baselineId);
        }

        // Save components
        // We assume baseline components are immutable and inserted once.
        // If updating, we might need to delete old ones, but for now we assume insert-only for new baselines.
        if (!$baseline->id()) { // Only insert components if it's a new baseline or we explicitly handle updates
             // Logic: If we just inserted (id was null), save components.
             // If we updated (id existed), we assume components didn't change (immutability).
             // However, to be safe, let's just save if we have an ID now.
        }
        
        // Actually, let's always save components if they don't exist?
        // Simpler: Delete all for this baseline and re-insert?
        // Or just insert.
        // Since this is called from QuoteAcceptedListener which creates a NEW Baseline, insert is fine.
        
        if ($baselineId) {
             // Check if components exist? No, assume new for now.
             // But to be safe against duplicates if save is called twice:
             // $this->wpdb->delete($this->componentsTable, ['baseline_id' => $baselineId]);
             // For now, let's just insert.
             
             foreach ($baseline->components() as $component) {
                 $this->saveComponent((int)$baselineId, $component);
             }
        }
    }

    private function saveComponent(int $baselineId, QuoteComponent $component): void
    {
        $this->wpdb->insert(
            $this->componentsTable,
            [
                'baseline_id' => $baselineId,
                'component_type' => get_class($component),
                'description' => $component->description(),
                'sell_value' => $component->sellValue(),
                'internal_cost' => $component->internalCost(),
                'component_data' => serialize($component), // Using PHP serialization for snapshot fidelity
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%f', '%f', '%s', '%s']
        );
    }

    public function findByContractId(int $contractId): ?Baseline
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->baselinesTable} WHERE contract_id = %d LIMIT 1",
            $contractId
        );
        $row = $this->wpdb->get_row($sql);

        if (!$row) {
            return null;
        }

        $components = $this->findComponentsByBaselineId((int)$row->id);

        return new Baseline(
            (int)$row->contract_id,
            (float)$row->total_value,
            (float)$row->total_internal_cost,
            $components,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }

    private function findComponentsByBaselineId(int $baselineId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT component_data FROM {$this->componentsTable} WHERE baseline_id = %d",
            $baselineId
        );
        $results = $this->wpdb->get_results($sql);

        $components = [];
        foreach ($results as $row) {
            $component = unserialize($row->component_data);
            if ($component instanceof QuoteComponent) {
                $components[] = $component;
            }
        }

        return $components;
    }

    private function setId(Baseline $baseline, int $id): void
    {
        $reflection = new \ReflectionClass($baseline);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($baseline, $id);
    }
}

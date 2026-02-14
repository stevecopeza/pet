<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Service;

use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\Domain\Finance\Repository\BillingExportRepository;

final class OutboxDispatcherService
{
    public function __construct(
        private SqlOutboxRepository $outbox,
        private BillingExportRepository $exports
    ) {
    }

    public function dispatchQuickBooks(): void
    {
        $due = $this->outbox->findDue('quickbooks', 25);
        foreach ($due as $row) {
            $outboxId = (int)$row['id'];
            $eventId = (int)$row['event_id'];
            try {
                $exportId = $eventId; // Using event_id to carry export id until full event linkage exists
                $export = $this->exports->findById($exportId);
                if (!$export) {
                    throw new \RuntimeException('Export not found for outbox row ' . $outboxId);
                }
                $items = $this->exports->findItems($exportId);
                $payload = $this->buildEnvelope($exportId, $items);
                $this->simulateQuickBooksSend($payload);
                $this->outbox->markSent($outboxId);
                $this->exports->setStatus($exportId, 'processed');
            } catch (\Throwable $e) {
                $attempt = ((int)$row['attempt_count']) + 1;
                if ($attempt >= 6) {
                    $this->outbox->markDead($outboxId, $e->getMessage());
                    $exportId = (int)$row['event_id'];
                    if ($exportId > 0) {
                        $this->exports->setStatus($exportId, 'failed');
                    }
                    continue;
                }
                $backoff = $this->backoffAt($attempt);
                $this->outbox->markFailed($outboxId, $attempt, $backoff, $e->getMessage());
            }
        }
    }

    private function buildEnvelope(int $exportId, array $items): array
    {
        $total = 0.0;
        $lines = [];
        foreach ($items as $i) {
            $lines[] = [
                'source_type' => $i->sourceType(),
                'source_id' => $i->sourceId(),
                'description' => $i->description(),
                'quantity' => round($i->quantity(), 2),
                'unit_price' => round($i->unitPrice(), 2),
                'amount' => round($i->amount(), 2),
                'qb_item_ref' => $i->qbItemRef() ?? 'GEN-SERVICE',
            ];
            $total += $i->amount();
        }
        $total = round($total, 2);
        return [
            'export_id' => $exportId,
            'schema_version' => 1,
            'lines' => $lines,
            'total_amount' => $total,
        ];
    }

    private function simulateQuickBooksSend(array $payload): void
    {
        if (empty($payload['lines'])) {
            throw new \RuntimeException('No line items to send');
        }
        // Simulate success
    }

    private function backoffAt(int $attempt): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $map = [
            1 => '+1 minutes',
            2 => '+5 minutes',
            3 => '+30 minutes',
            4 => '+2 hours',
            5 => '+6 hours',
            6 => '+24 hours',
        ];
        $spec = $map[$attempt] ?? '+60 minutes';
        return $now->modify($spec);
    }
}


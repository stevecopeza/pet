<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Domain\Finance\Entity\BillingExport;

final class BillingExportStateMachineTest extends TestCase
{
    public function testAllowsDraftToQueued(): void
    {
        $export = BillingExport::draft('u', 1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10);
        $export->queue();
        $this->assertSame('queued', $export->status());
    }

    public function testBlocksDraftToSentDirectly(): void
    {
        $export = BillingExport::draft('u', 1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10);
        $this->expectException(DomainException::class);
        $export->markSent();
    }

    public function testBlocksQueuedToDraft(): void
    {
        $export = BillingExport::draft('u', 1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10);
        $export->queue();
        $this->expectException(DomainException::class);
        // no method to go back to draft; simulate by trying invalid operation
        $export->confirm(); // confirm requires sent, so should throw
    }

    public function testBlocksAnyTransitionFromConfirmed(): void
    {
        $export = BillingExport::draft('u', 1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'), 10);
        $export->queue();
        $export->markSent();
        $export->confirm();
        $this->assertSame('confirmed', $export->status());
        $this->expectException(DomainException::class);
        $export->markFailed();
    }
}

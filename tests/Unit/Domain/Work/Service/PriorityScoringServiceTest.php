<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Service;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Work\Entity\WorkItem;
use DateTimeImmutable;

class PriorityScoringServiceTest extends TestCase
{
    private DateTimeImmutable $now;
    private PriorityScoringService $service;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2024-01-01 12:00:00');
        $this->service = new PriorityScoringService($this->now);
    }

    private function createWorkItem(
        ?int $slaMinutes = null,
        ?DateTimeImmutable $due = null,
        int $escalation = 0,
        ?DateTimeImmutable $start = null,
        string $status = 'active',
        float $revenue = 0.0,
        int $clientTier = 1,
        float $managerOverride = 0.0
    ): WorkItem {
        $workItem = WorkItem::create(
            'id-123',
            'ticket',
            'src-1',
            'dept-1',
            0.0,
            $status,
            $this->now
        );

        if ($slaMinutes !== null) {
            $workItem->updateSlaState('snap-1', $slaMinutes);
        }
        
        if ($due !== null || $start !== null) {
            $workItem->updateScheduling($start, $due);
        }

        if ($escalation > 0) {
            $workItem->escalate($escalation);
        }

        if ($revenue > 0.0 || $clientTier !== 1) {
            $workItem->updateCommercialInfo($revenue, $clientTier);
        }

        if ($managerOverride !== 0.0) {
            $workItem->setManagerPriorityOverride($managerOverride);
        }

        return $workItem;
    }

    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    public function testCalculateScoreBreachedSla()
    {
        $workItem = $this->createWorkItem(slaMinutes: -10);
        $score = $this->service->calculate($workItem);
        // Max SLA (500)
        $this->assertEquals(500.0, $score);
    }

    public function testCalculateScoreCriticalSla()
    {
        $workItem = $this->createWorkItem(slaMinutes: 30);
        $score = $this->service->calculate($workItem);
        // < 60 mins (400)
        $this->assertEquals(400.0, $score);
    }

    public function testCalculateScoreOverdueDeadline()
    {
        $due = $this->now->modify('-1 hour');
        $workItem = $this->createWorkItem(due: $due);
        $score = $this->service->calculate($workItem);
        // Overdue (250)
        $this->assertEquals(250.0, $score);
    }

    public function testCalculateScoreEscalated()
    {
        $workItem = $this->createWorkItem(escalation: 2);
        $score = $this->service->calculate($workItem);
        // Level 2 * 50 = 100
        $this->assertEquals(100.0, $score);
    }

    public function testCalculateScoreWaiting()
    {
        $workItem = $this->createWorkItem(status: 'waiting');
        $score = $this->service->calculate($workItem);
        // Waiting Penalty (-400)
        $this->assertEquals(-400.0, $score);
    }

    public function testCalculateScoreCombined()
    {
        // SLA Critical (400) + Escalatied Level 1 (50) + Waiting (-400) = 50
        $workItem = $this->createWorkItem(
            slaMinutes: 30,
            escalation: 1,
            status: 'waiting'
        );
        $score = $this->service->calculate($workItem);
        $this->assertEquals(50.0, $score);
    }
    
    public function testCalculateScoreScheduleStartPassed()
    {
        $start = $this->now->modify('-10 minutes');
        $workItem = $this->createWorkItem(start: $start);
        $score = $this->service->calculate($workItem);
        // Should have started (100)
        $this->assertEquals(100.0, $score);
    }

    public function testCalculateScoreCommercialTier1HighRevenue()
    {
        // Tier 1 (0) + Revenue > 10000 (10) = 10
        $workItem = $this->createWorkItem(revenue: 15000.0, clientTier: 1);
        $score = $this->service->calculate($workItem);
        $this->assertEquals(10.0, $score);
    }

    public function testCalculateScoreCommercialTier2()
    {
        // Tier 2 (25)
        $workItem = $this->createWorkItem(clientTier: 2);
        $score = $this->service->calculate($workItem);
        $this->assertEquals(25.0, $score);
    }

    public function testCalculateScoreManagerOverride()
    {
        // Override (999) -> Clamped to 300
        $workItem = $this->createWorkItem(managerOverride: 999.0);
        $score = $this->service->calculate($workItem);
        $this->assertEquals(300.0, $score);
    }

    public function testCalculateScoreManagerOverrideNormal()
    {
        // Override (150) -> 150
        $workItem = $this->createWorkItem(managerOverride: 150.0);
        $score = $this->service->calculate($workItem);
        $this->assertEquals(150.0, $score);
    }

    public function testCalculateScoreManagerOverrideNegative()
    {
        // Override (-100)
        $workItem = $this->createWorkItem(managerOverride: -100.0);
        $score = $this->service->calculate($workItem);
        $this->assertEquals(-100.0, $score);
    }
}

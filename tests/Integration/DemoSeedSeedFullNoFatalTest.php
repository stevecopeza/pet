<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\Application\System\Service\DemoSeedService;

final class DemoSeedSeedFullNoFatalTest extends TestCase
{
    private \DI\Container $c;

    protected function setUp(): void
    {
        $this->c = ContainerFactory::create();
    }

    public function testSeedFullReturnsSummaryWithoutFatal(): void
    {
        $seedRunId = $this->uuid();
        /** @var DemoSeedService $seed */
        $seed = $this->c->get(DemoSeedService::class);
        $summary = $seed->seedFull($seedRunId, 'demo_full');
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('calendar', $summary);
        global $wpdb;
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_calendars");
        $this->assertGreaterThanOrEqual(1, $count);
    }

    private function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

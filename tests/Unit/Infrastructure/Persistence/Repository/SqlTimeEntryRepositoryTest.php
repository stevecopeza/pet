<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Repository\SqlTimeEntryRepository;
use Pet\Domain\Time\Entity\TimeEntry;

class SqlTimeEntryRepositoryTest extends TestCase
{
    public function testSumBillableHours()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlTimeEntryRepository($wpdb);

        $wpdb->expects($this->once())
             ->method('get_var')
             ->with($this->stringContains('SELECT SUM(duration_minutes)'))
             ->willReturn('120'); // 120 minutes = 2 hours

        $hours = $repo->sumBillableHours();
        $this->assertEquals(2.0, $hours);
    }
}

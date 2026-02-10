<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\ValueObject\QuoteState;

class SqlQuoteRepositoryTest extends TestCase
{
    public function testSumRevenue()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlQuoteRepository($wpdb);

        $start = new \DateTimeImmutable('2023-01-01');
        $end = new \DateTimeImmutable('2023-01-31');

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 $this->stringContains('SELECT SUM(l.total)'),
                 QuoteState::ACCEPTED,
                 $start->format('Y-m-d H:i:s'),
                 $end->format('Y-m-d H:i:s')
             )
             ->willReturn('SELECT SUM(l.total) ...');

        $wpdb->expects($this->once())
             ->method('get_var')
             ->willReturn('5000.00');

        $revenue = $repo->sumRevenue($start, $end);
        $this->assertEquals(5000.00, $revenue);
    }

    public function testCountPending()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlQuoteRepository($wpdb);

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 $this->stringContains('SELECT COUNT(*)'),
                 QuoteState::DRAFT
             )
             ->willReturn('SELECT COUNT(*) ...');

        $wpdb->expects($this->once())
             ->method('get_var')
             ->willReturn('3');

        $count = $repo->countPending();
        $this->assertEquals(3, $count);
    }
}

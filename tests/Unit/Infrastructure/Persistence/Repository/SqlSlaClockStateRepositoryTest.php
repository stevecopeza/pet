<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Infrastructure\Persistence\Repository\SqlSlaClockStateRepository;
use PHPUnit\Framework\TestCase;

class SqlSlaClockStateRepositoryTest extends TestCase
{
    // Note: This requires a real DB or comprehensive WPDB mock.
    // For this environment, we'll verify the class structure and method existence
    // rather than full integration testing which might fail without a running DB.
    
    public function testRepositoryImplementsInterface(): void
    {
        $wpdb = $this->getMockBuilder(\stdClass::class)->getMock();
        $repo = new SqlSlaClockStateRepository($wpdb);
        $this->assertInstanceOf(\Pet\Domain\Support\Repository\SlaClockStateRepository::class, $repo);
    }
    
    public function testInitializeReturnsNewEntity(): void
    {
        $wpdb = $this->getMockBuilder(\stdClass::class)->getMock();
        $repo = new SqlSlaClockStateRepository($wpdb);
        $ticket = $this->createMock(\Pet\Domain\Support\Entity\Ticket::class);
        $ticket->method('id')->willReturn(123);
        
        $state = $repo->initialize($ticket);
        
        $this->assertInstanceOf(SlaClockState::class, $state);
        $this->assertEquals(123, $state->getTicketId());
        $this->assertEquals('none', $state->getLastEventDispatched());
    }
}

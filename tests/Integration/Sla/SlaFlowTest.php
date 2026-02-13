<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Sla;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Sla\Service\SlaClockService;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use Pet\Infrastructure\Persistence\Repository\SqlSlaRepository;
use Pet\Infrastructure\Persistence\Repository\SqlCalendarRepository;

class SlaFlowTest extends TestCase
{
    private $wpdb;
    private $slaRepo;
    private $clockService;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // Mock prepare
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            // Simple vsprintf implementation for mocking
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%s', '%s', $query);
            return vsprintf($query, $args);
        });

        // Mock CalendarRepo just for SlaRepo constructor
        $calendarRepo = $this->createMock(SqlCalendarRepository::class);
        $this->slaRepo = new SqlSlaRepository($this->wpdb, $calendarRepo);
        
        $this->clockService = new SlaClockService(new BusinessTimeCalculator());
    }

    public function testSaveSnapshotPersistsCorrectData()
    {
        // Setup Snapshot
        $calendarSnapshot = [
            'timezone' => 'UTC',
            'holidays' => [],
            'working_windows' => []
        ];
        
        $snapshot = new SlaSnapshot(
            null, // Ad-hoc (null project)
            101, // original ID
            1, // version
            'Gold Support',
            60,
            240,
            $calendarSnapshot
        );

        // Expect Insert
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_contract_sla_snapshots',
                $this->callback(function ($data) {
                    return $data['project_id'] === null &&
                           $data['sla_original_id'] === 101 &&
                           $data['response_target_minutes'] === 60 &&
                           $data['calendar_snapshot'] !== '';
                })
            )
            ->will($this->returnCallback(function() {
                $this->wpdb->insert_id = 555;
                return 1;
            }));

        $id = $this->slaRepo->saveSnapshot($snapshot);
        
        $this->assertEquals(555, $id);
    }

    public function testClockServiceCalculatesDueDates()
    {
        // Raw Windows format (Flat list)
        $windows = [
            ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '17:00'],
            ['day_of_week' => 'tuesday', 'start_time' => '09:00', 'end_time' => '17:00'],
        ];

        $calendarSnapshot = [
            'timezone' => 'UTC',
            'holidays' => [],
            'working_windows' => $windows
        ];

        $snapshot = new SlaSnapshot(
            null, 1, 1, 'Test', 60, 240, $calendarSnapshot
        );

        // Monday 10:00 AM
        $start = new \DateTimeImmutable('2023-10-23 10:00:00');
        
        // Add 60 mins -> 11:00 AM
        $due = $this->clockService->calculateDueDate($start, 60, $snapshot);
        
        $this->assertEquals('2023-10-23 11:00:00', $due->format('Y-m-d H:i:s'));
        
        // Add 480 mins (8 hours) -> 10am to 5pm (7h) + 1h next day -> Tuesday 10:00 AM?
        // 10am to 5pm is 7 hours (420 mins). Remaining 60 mins.
        // Next day starts 9am. +60 mins = 10am.
        // So 8 hours (480 mins) from Mon 10am should be Tue 10am.
        
        $longDue = $this->clockService->calculateDueDate($start, 480, $snapshot);
        
        // Need to ensure BusinessTimeCalculator logic handles day rollover correctly
        // My implementation had logic for this.
        
        // Note: My mock windows only had Monday and Tuesday.
        $this->assertEquals('2023-10-24 10:00:00', $longDue->format('Y-m-d H:i:s'));
    }
}

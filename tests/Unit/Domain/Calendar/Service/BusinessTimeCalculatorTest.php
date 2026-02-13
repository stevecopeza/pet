<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Calendar\Service;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;

class BusinessTimeCalculatorTest extends TestCase
{
    private BusinessTimeCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new BusinessTimeCalculator();
    }

    private function getTestSnapshot(): array
    {
        return [
            'uuid' => 'test-cal',
            'name' => 'Test Calendar',
            'timezone' => 'UTC',
            'is_default' => true,
            'working_windows' => [
                ['day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'working', 'rate_multiplier' => 1.0],
                ['day_of_week' => 'Tuesday', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'working', 'rate_multiplier' => 1.0],
                ['day_of_week' => 'Wednesday', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'working', 'rate_multiplier' => 1.0],
                ['day_of_week' => 'Thursday', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'working', 'rate_multiplier' => 1.0],
                ['day_of_week' => 'Friday', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'working', 'rate_multiplier' => 1.0],
            ],
            'holidays' => [
                ['name' => 'Christmas', 'date' => '2023-12-25', 'is_recurring' => false],
                ['name' => 'New Year', 'date' => '01-01', 'is_recurring' => true],
            ]
        ];
    }

    public function testCalculateBusinessMinutesSameDay(): void
    {
        $start = new \DateTimeImmutable('2023-06-05 10:00:00'); // Monday
        $end = new \DateTimeImmutable('2023-06-05 12:00:00'); // Monday
        
        $minutes = $this->calculator->calculateBusinessMinutes($start, $end, $this->getTestSnapshot());
        
        $this->assertEquals(120, $minutes);
    }

    public function testCalculateBusinessMinutesOverNight(): void
    {
        $start = new \DateTimeImmutable('2023-06-05 16:00:00'); // Monday
        $end = new \DateTimeImmutable('2023-06-06 10:00:00'); // Tuesday
        
        // Mon 16-17 (60) + Tue 09-10 (60) = 120
        $minutes = $this->calculator->calculateBusinessMinutes($start, $end, $this->getTestSnapshot());
        
        $this->assertEquals(120, $minutes);
    }

    public function testCalculateBusinessMinutesWithHoliday(): void
    {
        // 2023-12-25 is Monday (Holiday)
        $start = new \DateTimeImmutable('2023-12-22 16:00:00'); // Friday
        $end = new \DateTimeImmutable('2023-12-26 10:00:00'); // Tuesday
        
        // Fri 16-17 (60) + Mon (0 - Holiday) + Tue 09-10 (60) = 120
        $minutes = $this->calculator->calculateBusinessMinutes($start, $end, $this->getTestSnapshot());
        
        $this->assertEquals(120, $minutes);
    }
    
    public function testAddBusinessMinutesSameDay(): void
    {
        $start = new \DateTimeImmutable('2023-06-05 10:00:00'); // Monday
        $minutesToAdd = 120;
        
        $expected = new \DateTimeImmutable('2023-06-05 12:00:00');
        $actual = $this->calculator->addBusinessMinutes($start, $minutesToAdd, $this->getTestSnapshot());
        
        $this->assertEquals($expected->format('Y-m-d H:i'), $actual->format('Y-m-d H:i'));
    }

    public function testAddBusinessMinutesOverNight(): void
    {
        $start = new \DateTimeImmutable('2023-06-05 16:00:00'); // Monday
        $minutesToAdd = 120; // 60 today, 60 tomorrow
        
        $expected = new \DateTimeImmutable('2023-06-06 10:00:00'); // Tuesday
        $actual = $this->calculator->addBusinessMinutes($start, $minutesToAdd, $this->getTestSnapshot());
        
        $this->assertEquals($expected->format('Y-m-d H:i'), $actual->format('Y-m-d H:i'));
    }

    public function testAddBusinessMinutesWithHoliday(): void
    {
        // 2023-12-25 is Monday (Holiday)
        $start = new \DateTimeImmutable('2023-12-22 16:00:00'); // Friday
        $minutesToAdd = 120; // 60 Fri, Skip Sat/Sun, Skip Mon(Holiday), 60 Tue
        
        $expected = new \DateTimeImmutable('2023-12-26 10:00:00'); // Tuesday
        $actual = $this->calculator->addBusinessMinutes($start, $minutesToAdd, $this->getTestSnapshot());
        
        $this->assertEquals($expected->format('Y-m-d H:i'), $actual->format('Y-m-d H:i'));
    }
}

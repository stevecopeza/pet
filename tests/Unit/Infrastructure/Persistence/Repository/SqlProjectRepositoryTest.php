<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Repository;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Repository\SqlProjectRepository;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\ValueObject\ProjectState;

class SqlProjectRepositoryTest extends TestCase
{
    public function testSaveInsert()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlProjectRepository($wpdb);
        
        $project = new Project(1, 'Test Project', 100.0);
        
        $wpdb->expects($this->once())
             ->method('insert')
             ->with(
                 $this->stringContains('pet_projects'),
                 $this->callback(function($data) {
                     return $data['name'] === 'Test Project' && $data['customer_id'] === 1;
                 })
             );
             
        $repo->save($project);
    }

    public function testCountActive()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlProjectRepository($wpdb);

        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 $this->stringContains('SELECT COUNT(*)'),
                 ProjectState::ACTIVE
             )
             ->willReturn('SELECT COUNT(*) ...');

        $wpdb->expects($this->once())
             ->method('get_var')
             ->willReturn('5');

        $count = $repo->countActive();
        $this->assertEquals(5, $count);
    }

    public function testSumSoldHours()
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $repo = new SqlProjectRepository($wpdb);

        $wpdb->expects($this->once())
             ->method('get_var')
             ->with($this->stringContains('SELECT SUM(sold_hours)'))
             ->willReturn('150.5');

        $sum = $repo->sumSoldHours();
        $this->assertEquals(150.5, $sum);
    }
}

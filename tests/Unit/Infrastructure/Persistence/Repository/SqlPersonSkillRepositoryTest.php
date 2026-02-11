<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repository;

use Pet\Infrastructure\Persistence\Repository\SqlPersonSkillRepository;
use PHPUnit\Framework\TestCase;

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

class SqlPersonSkillRepositoryTest extends TestCase
{
    public function testGetAverageProficiencyBySkillExecutesCorrectQuery(): void
    {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        
        $expectedSql = "
            SELECT 
                s.name as skill_name, 
                AVG(ps.manager_rating) as avg_rating 
            FROM wp_pet_person_skills ps
            JOIN wp_pet_skills s ON s.id = ps.skill_id
            WHERE ps.manager_rating IS NOT NULL
            GROUP BY ps.skill_id 
            ORDER BY avg_rating DESC
            LIMIT 10
        ";

        $wpdb->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function ($sql) {
                return strpos($sql, 'AVG(ps.manager_rating)') !== false
                    && strpos($sql, 'JOIN wp_pet_skills') !== false;
            }), ARRAY_A)
            ->willReturn([
                ['skill_name' => 'PHP', 'avg_rating' => '4.5'],
                ['skill_name' => 'React', 'avg_rating' => '3.8'],
            ]);

        $repository = new SqlPersonSkillRepository($wpdb);
        $result = $repository->getAverageProficiencyBySkill();

        $this->assertCount(2, $result);
        $this->assertEquals('PHP', $result[0]['skill_name']);
    }
}

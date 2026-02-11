<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Team;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Team\Entity\Team;
use Pet\Infrastructure\Persistence\Repository\SqlTeamRepository;

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

class SqlTeamRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        if (!class_exists('wpdb')) {
             eval("class wpdb { 
                public \$prefix = 'wp_'; 
                public \$insert_id = 0;
                public function prepare(\$query, ...\$args) {}
                public function get_row(\$query, \$output = OBJECT, \$y = 0) {}
                public function get_results(\$query, \$output = OBJECT) {}
                public function get_var(\$query, \$x = 0, \$y = 0) {}
                public function get_col(\$query, \$x = 0) {}
                public function insert(\$table, \$data, \$format = null) {}
                public function update(\$table, \$data, \$where, \$format = null, \$where_format = null) {}
                public function replace(\$table, \$data, \$format = null) {}
                public function delete(\$table, \$where, \$where_format = null) {}
                public function query(\$query) {}
             }");
        }

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlTeamRepository($this->wpdb);
    }

    public function testSaveNewTeamWithMembers()
    {
        $team = new Team('Engineering', null, null, null, null, 'active', null, null, 1, null, [101, 102]);

        // 1. Insert Team
        $this->wpdb->expects($this->exactly(3)) // 1 for team, 2 for members
            ->method('insert')
            ->withConsecutive(
                [
                    'wp_pet_teams',
                    $this->callback(function ($data) {
                        return $data['name'] === 'Engineering';
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_team_members',
                    $this->callback(function ($data) {
                        return $data['employee_id'] === 101 && $data['role'] === 'member';
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_team_members',
                    $this->callback(function ($data) {
                        return $data['employee_id'] === 102 && $data['role'] === 'member';
                    }),
                    $this->anything()
                ]
            );

        // 2. Mock insert_id for the team
        $this->wpdb->insert_id = 50;

        // 3. Mock get_col for existing members (return empty)
        $this->wpdb->method('get_col')->willReturn([]);

        // 4. Mock get_var for checking member existence (return null/false so it inserts)
        $this->wpdb->method('get_var')->willReturn(null);

        // Mock prepare to return a string
        $this->wpdb->method('prepare')->willReturnArgument(0);

        $this->repository->save($team);
    }

    public function testSaveUpdateTeamMembers()
    {
        // Existing team ID 50
        // Existing members: [101]
        // New members: [102]
        // Result: Remove 101, Add 102
        
        $team = new Team('Engineering', 50, null, null, null, 'active', null, null, 1, null, [102]);

        // Mock prepare to return a string
        $this->wpdb->method('prepare')->willReturnArgument(0);

        // 1. Update Team
        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_teams',
                $this->anything(),
                ['id' => 50],
                $this->anything(),
                $this->anything()
            );

        // 2. get_col returns [101]
        $this->wpdb->method('get_col')->willReturn([101]);

        // 3. get_var returns null for new member 102
        $this->wpdb->method('get_var')->willReturn(null);

        // 4. Expect insert for 102
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_team_members',
                $this->callback(function ($data) {
                    return $data['employee_id'] === 102;
                }),
                $this->anything()
            );

        // 5. Expect query for removing 101 (soft delete)
        $this->wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('UPDATE wp_pet_team_members SET removed_at'));

        $this->repository->save($team);
    }
}

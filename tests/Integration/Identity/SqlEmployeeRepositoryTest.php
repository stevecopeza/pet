<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Identity;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository;

class SqlEmployeeRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlEmployeeRepository($this->wpdb);
    }

    public function testSaveNewEmployeeWithTeams()
    {
        $employee = new Employee(1, 'Jane', 'Doe', 'jane@example.com', null, 'active', null, null, null, [], [50, 51]);

        // Mock prepare to return a string
        $this->wpdb->method('prepare')->willReturnArgument(0);

        // 1. Insert Employee
        $this->wpdb->expects($this->exactly(3)) // 1 for employee, 2 for teams
            ->method('insert')
            ->withConsecutive(
                [
                    'wp_pet_employees',
                    $this->callback(function ($data) {
                        return $data['email'] === 'jane@example.com';
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_team_members',
                    $this->callback(function ($data) {
                        return $data['team_id'] === 50 && $data['role'] === 'member';
                    }),
                    $this->anything()
                ],
                [
                    'wp_pet_team_members',
                    $this->callback(function ($data) {
                        return $data['team_id'] === 51 && $data['role'] === 'member';
                    }),
                    $this->anything()
                ]
            );

        // 2. Mock insert_id for the employee
        $this->wpdb->insert_id = 101;

        // 3. Mock get_col for existing teams (return empty)
        $this->wpdb->method('get_col')->willReturn([]);

        // 4. Mock get_var for checking team membership existence (return null/false)
        $this->wpdb->method('get_var')->willReturn(null);

        $this->repository->save($employee);
    }

    public function testSaveUpdateEmployeeTeams()
    {
        // Existing employee ID 101
        // Existing teams: [50]
        // New teams: [51]
        // Result: Remove 50, Add 51
        
        $employee = new Employee(1, 'Jane', 'Doe', 'jane@example.com', 101, 'active', null, null, null, [], [51]);

        // Mock prepare to return a string
        $this->wpdb->method('prepare')->willReturnArgument(0);

        // 1. Update Employee
        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_employees',
                $this->anything(),
                ['id' => 101],
                $this->anything(),
                $this->anything()
            );

        // 2. get_col returns [50]
        $this->wpdb->method('get_col')->willReturn([50]);

        // 3. get_var returns null for new team 51
        $this->wpdb->method('get_var')->willReturn(null);

        // 4. Expect insert for 51
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_team_members',
                $this->callback(function ($data) {
                    return $data['team_id'] === 51;
                }),
                $this->anything()
            );

        // 5. Expect query for removing 50
        $this->wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('UPDATE wp_pet_team_members SET removed_at'));

        $this->repository->save($employee);
    }
}

<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Infrastructure\Persistence\Repository;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Knowledge\Entity\Article;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Infrastructure\Persistence\Repository\SqlProjectRepository;
use Pet\Infrastructure\Persistence\Repository\SqlArticleRepository;
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository;
use PHPUnit\Framework\TestCase;

class MalleableEntitiesRepositoryTest extends TestCase
{
    private $wpdb;
    private $projectRepo;
    private $articleRepo;
    private $ticketRepo;
    private $employeeRepo;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        $this->projectRepo = new SqlProjectRepository($this->wpdb);
        $this->articleRepo = new SqlArticleRepository($this->wpdb);
        $this->ticketRepo = new SqlTicketRepository($this->wpdb);
        $this->employeeRepo = new SqlEmployeeRepository($this->wpdb);
    }

    public function testSaveProjectWithMalleableData(): void
    {
        $malleableData = ['custom_field' => 'value'];
        $project = new Project(
            1, 'Test Project', 100.0, null, null, 0.0, null, null, null, 1, $malleableData
        );

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_projects',
                $this->callback(function ($data) use ($malleableData) {
                    return $data['malleable_schema_version'] === 1 &&
                           $data['malleable_data'] === json_encode($malleableData);
                }),
                $this->anything()
            );

        $this->projectRepo->save($project);
    }

    public function testSaveArticleWithMalleableData(): void
    {
        $malleableData = ['tags' => ['a', 'b']];
        $article = new Article(
            'Title', 'Content', 'general', 'draft', null, 1, $malleableData
        );

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_articles',
                $this->callback(function ($data) use ($malleableData) {
                    return $data['malleable_schema_version'] === 1 &&
                           $data['malleable_data'] === json_encode($malleableData);
                }),
                $this->anything()
            );

        $this->articleRepo->save($article);
    }

    public function testSaveTicketWithMalleableData(): void
    {
        $malleableData = ['urgency' => 'critical'];
        $ticket = new Ticket(
            1, 'Subject', 'Desc', 'new', 'medium', null, 1, null, 1, $malleableData
        );

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_tickets',
                $this->callback(function ($data) use ($malleableData) {
                    return $data['malleable_schema_version'] === 1 &&
                           $data['malleable_data'] === json_encode($malleableData);
                }),
                $this->anything()
            );

        $this->ticketRepo->save($ticket);
    }

    public function testSaveEmployeeWithMalleableData(): void
    {
        $malleableData = ['skills' => ['php', 'js']];
        $employee = new Employee(
            1, 'John', 'Doe', 'john@example.com', null, 'active', null, null, 1, $malleableData
        );

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_employees',
                $this->callback(function ($data) use ($malleableData) {
                    return $data['malleable_schema_version'] === 1 &&
                           $data['malleable_data'] === json_encode($malleableData);
                }),
                $this->anything()
            );

        $this->employeeRepo->save($employee);
    }
}

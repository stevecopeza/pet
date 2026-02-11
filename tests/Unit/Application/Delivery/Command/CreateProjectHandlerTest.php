<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Delivery\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Event\EventBus;

class CreateProjectHandlerTest extends TestCase
{
    public function testHandleCreatesAndSavesProject()
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $customerRepository = $this->createMock(CustomerRepository::class);
        $schemaRepository = $this->createMock(SchemaDefinitionRepository::class);
        $schemaValidator = $this->createMock(SchemaValidator::class);
        $eventBus = $this->createMock(EventBus::class);

        $customer = $this->createMock(Customer::class);
        $customerRepository->method('findById')->willReturn($customer);

        // Mock schema repository to return null for active schema (simplest case)
        $schemaRepository->method('findActiveByEntityType')->willReturn(null);

        $projectRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Project $project) {
                return $project->name() === 'New Project'
                    && $project->soldHours() === 100.0;
            }));

        $handler = new CreateProjectHandler(
            $projectRepository,
            $customerRepository,
            $schemaRepository,
            $schemaValidator,
            $eventBus
        );

        $command = new CreateProjectCommand(1, 'New Project', 100.0);
        $handler->handle($command);
    }

    public function testHandleThrowsExceptionIfCustomerNotFound()
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $customerRepository = $this->createMock(CustomerRepository::class);
        $schemaRepository = $this->createMock(SchemaDefinitionRepository::class);
        $schemaValidator = $this->createMock(SchemaValidator::class);
        $eventBus = $this->createMock(EventBus::class);

        $customerRepository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);

        $handler = new CreateProjectHandler(
            $projectRepository,
            $customerRepository,
            $schemaRepository,
            $schemaValidator,
            $eventBus
        );

        $command = new CreateProjectCommand(1, 'New Project', 100.0);
        $handler->handle($command);
    }
}

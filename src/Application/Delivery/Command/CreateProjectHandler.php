<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Event\EventBus;

class CreateProjectHandler
{
    private ProjectRepository $projectRepository;
    private CustomerRepository $customerRepository;
    private EventBus $eventBus;

    public function __construct(
        ProjectRepository $projectRepository,
        CustomerRepository $customerRepository,
        EventBus $eventBus
    ) {
        $this->projectRepository = $projectRepository;
        $this->customerRepository = $customerRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(CreateProjectCommand $command): void
    {
        $customer = $this->customerRepository->findById($command->customerId());
        if (!$customer) {
            throw new \DomainException("Customer not found: {$command->customerId()}");
        }

        $project = new Project(
            $command->customerId(),
            $command->name(),
            $command->soldHours(),
            $command->sourceQuoteId()
        );

        $this->projectRepository->save($project);

        // Dispatch domain event if needed (e.g. ProjectCreated)
        // $this->eventBus->dispatch(new ProjectCreated($project));
    }
}

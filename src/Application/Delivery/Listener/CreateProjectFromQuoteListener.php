<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;

class CreateProjectFromQuoteListener
{
    private CreateProjectHandler $createProjectHandler;

    public function __construct(CreateProjectHandler $createProjectHandler)
    {
        $this->createProjectHandler = $createProjectHandler;
    }

    public function __invoke(QuoteAccepted $event): void
    {
        $quote = $event->quote();
        
        $soldHours = 0.0;
        $implementationValue = 0.0;
        $hasImplementation = false;
        $projectTasks = [];

        foreach ($quote->components() as $component) {
            if ($component instanceof ImplementationComponent) {
                $hasImplementation = true;
                $implementationValue += $component->sellValue();
                foreach ($component->milestones() as $milestone) {
                    foreach ($milestone->tasks() as $task) {
                        $soldHours += $task->durationHours();
                        $projectTasks[] = new \Pet\Domain\Delivery\Entity\Task(
                            $task->title(),
                            $task->durationHours()
                        );
                    }
                }
            } elseif ($component instanceof CatalogComponent) {
                // Treat Catalog Components as Implementation for now (simplification for Project Plan)
                $hasImplementation = true;
                $implementationValue += $component->sellValue();
                foreach ($component->items() as $item) {
                    $wbsSnapshot = $item->wbsSnapshot();
                    
                    if (!empty($wbsSnapshot)) {
                        // Use WBS Template Snapshot
                        foreach ($wbsSnapshot as $wbsTask) {
                            // Scale hours by quantity? 
                            // Usually WBS template is "per unit".
                            // If I sell 2 "Websites", I expect 2x the tasks?
                            // Or is the WBS template for the total?
                            // Typically, a catalog item is a unit.
                            // If quantity is > 1, we should probably multiply hours.
                            
                            $taskHours = (float)($wbsTask['hours'] ?? 0);
                            $totalHours = $taskHours * $item->quantity();
                            
                            $soldHours += $totalHours;
                            $projectTasks[] = new \Pet\Domain\Delivery\Entity\Task(
                                ($wbsTask['description'] ?? 'Task') . ' (' . $item->description() . ')',
                                $totalHours
                            );
                        }
                    } else {
                        // Fallback: Assume quantity is hours for the sake of project planning if it's a service
                        // This is a heuristic: if we are selling "Development", quantity 10 usually means 10 hours.
                        $hours = $item->quantity();
                        $soldHours += $hours;
                        $projectTasks[] = new \Pet\Domain\Delivery\Entity\Task(
                            $item->description(),
                            $hours
                        );
                    }
                }
            }
        }

        if ($hasImplementation) {
            $command = new CreateProjectCommand(
                $quote->customerId(),
                'Project for Quote #' . $quote->id(), // Default name
                $soldHours,
                $quote->id(),
                $implementationValue,
                null, // startDate
                null, // endDate
                [], // malleableData
                $projectTasks
            );

            $this->createProjectHandler->handle($command);
        }
    }
}

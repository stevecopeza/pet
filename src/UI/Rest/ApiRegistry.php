<?php

declare(strict_types=1);

namespace Pet\UI\Rest;

use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Psr\Container\ContainerInterface;

class ApiRegistry
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $controllers = [
            \Pet\UI\Rest\Controller\DashboardController::class,
            \Pet\UI\Rest\Controller\ProjectController::class,
            \Pet\UI\Rest\Controller\QuoteController::class,
            \Pet\UI\Rest\Controller\TimeEntryController::class,
            \Pet\UI\Rest\Controller\CustomerController::class,
            \Pet\UI\Rest\Controller\EmployeeController::class,
            \Pet\UI\Rest\Controller\TicketController::class,
            \Pet\UI\Rest\Controller\ArticleController::class,
            \Pet\UI\Rest\Controller\ActivityController::class,
            \Pet\UI\Rest\Controller\SettingsController::class,
        ];

        foreach ($controllers as $controllerClass) {
            /** @var \Pet\UI\Rest\Controller\RestController $controller */
            $controller = $this->container->get($controllerClass);
            $controller->registerRoutes();
        }
    }
}

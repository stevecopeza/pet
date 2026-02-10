<?php

declare(strict_types=1);

namespace Pet\Infrastructure\DependencyInjection;

use DI\Container;
use DI\ContainerBuilder;

class ContainerFactory
{
    public static function create(): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAnnotations(false);

        // Load definitions
        $builder->addDefinitions(self::getDefinitions());

        return $builder->build();
    }

    private static function getDefinitions(): array
    {
        return [
            // Infrastructure
            \Pet\Domain\Event\EventBus::class => \DI\autowire(\Pet\Infrastructure\Event\InMemoryEventBus::class),
            
            \Pet\Infrastructure\Persistence\Migration\MigrationRunner::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Migration\MigrationRunner($wpdb);
            },

            // Repositories
            \Pet\Domain\Identity\Repository\EmployeeRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository($wpdb);
            },
            \Pet\Domain\Identity\Repository\CustomerRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCustomerRepository($wpdb);
            },
            \Pet\Domain\Delivery\Repository\ProjectRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlProjectRepository($wpdb);
            },

            \Pet\Domain\Commercial\Repository\QuoteRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository($wpdb);
            },
            
            \Pet\Domain\Time\Repository\TimeEntryRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTimeEntryRepository($wpdb);
            },
            
            \Pet\Domain\Support\Repository\TicketRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTicketRepository($wpdb);
            },

            \Pet\Domain\Knowledge\Repository\ArticleRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlArticleRepository($wpdb);
            },

            \Pet\Domain\Activity\Repository\ActivityLogRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlActivityLogRepository($wpdb);
            },

            \Pet\Domain\Configuration\Repository\SettingRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSettingRepository($wpdb);
            },

            // Application Handlers
            \Pet\Application\Delivery\Command\CreateProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\CreateProjectHandler::class),
            \Pet\Application\Delivery\Command\AddTaskHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\AddTaskHandler::class),
            
            \Pet\Application\Commercial\Command\CreateQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateQuoteHandler::class),
            \Pet\Application\Commercial\Command\AddQuoteLineHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddQuoteLineHandler::class),
            
            \Pet\Application\Time\Command\LogTimeHandler::class => \DI\autowire(\Pet\Application\Time\Command\LogTimeHandler::class),
            \Pet\Application\Identity\Command\CreateCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateCustomerHandler::class),
            \Pet\Application\Identity\Command\CreateEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateEmployeeHandler::class),
            \Pet\Application\Support\Command\CreateTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\CreateTicketHandler::class),
            \Pet\Application\Knowledge\Command\CreateArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\CreateArticleHandler::class),

            \Pet\UI\Rest\Controller\ProjectController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\QuoteController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TimeEntryController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CustomerController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TicketController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ArticleController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ActivityController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SettingsController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\DashboardController::class => \DI\autowire(),
        ];
    }
}

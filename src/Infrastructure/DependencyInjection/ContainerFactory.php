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
            \Pet\Domain\Identity\Repository\ContactRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlContactRepository($wpdb);
            },
            \Pet\Domain\Identity\Repository\SiteRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSiteRepository($wpdb);
            },
            \Pet\Domain\Delivery\Repository\ProjectRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlProjectRepository($wpdb);
            },

            \Pet\Domain\Commercial\Repository\QuoteRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\LeadRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlLeadRepository($wpdb);
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

            \Pet\Domain\Configuration\Repository\SchemaDefinitionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSchemaDefinitionRepository($wpdb);
            },

            \Pet\Domain\Team\Repository\TeamRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTeamRepository($wpdb);
            },

            // Work Domain Repositories
            \Pet\Domain\Work\Repository\RoleRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRoleRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\SkillRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSkillRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\CapabilityRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCapabilityRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\ProficiencyLevelRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlProficiencyLevelRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\CertificationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCertificationRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\AssignmentRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlAssignmentRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonSkillRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonSkillRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonCertificationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonCertificationRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\KpiDefinitionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlKpiDefinitionRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\RoleKpiRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRoleKpiRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonKpiRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonKpiRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PerformanceReviewRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPerformanceReviewRepository($wpdb);
            },

            // Application Handlers
            \Pet\Application\Delivery\Command\CreateProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\CreateProjectHandler::class),
            \Pet\Application\Delivery\Command\AddTaskHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\AddTaskHandler::class),
            \Pet\Application\Delivery\Command\UpdateProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\UpdateProjectHandler::class),
            \Pet\Application\Delivery\Command\ArchiveProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\ArchiveProjectHandler::class),
            
            \Pet\Application\Commercial\Command\CreateQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateQuoteHandler::class),
            \Pet\Application\Commercial\Command\UpdateQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateQuoteHandler::class),
            \Pet\Application\Commercial\Command\AddQuoteLineHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddQuoteLineHandler::class),
            \Pet\Application\Commercial\Command\ArchiveQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ArchiveQuoteHandler::class),
            \Pet\Application\Commercial\Command\CreateLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateLeadHandler::class),
            \Pet\Application\Commercial\Command\UpdateLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateLeadHandler::class),
            \Pet\Application\Commercial\Command\DeleteLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\DeleteLeadHandler::class),

            \Pet\Application\Team\Command\CreateTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\CreateTeamHandler::class),
            \Pet\Application\Team\Command\UpdateTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\UpdateTeamHandler::class),
            \Pet\Application\Team\Command\ArchiveTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\ArchiveTeamHandler::class),
            
            \Pet\Application\Time\Command\LogTimeHandler::class => \DI\autowire(\Pet\Application\Time\Command\LogTimeHandler::class),
            \Pet\Application\Identity\Command\CreateEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateEmployeeHandler::class),
            \Pet\Application\Identity\Command\UpdateEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateEmployeeHandler::class),
            \Pet\Application\Identity\Command\ArchiveEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveEmployeeHandler::class),

            // Work Handlers
            \Pet\Application\Work\Command\CreateRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateRoleHandler::class),
            \Pet\Application\Work\Command\PublishRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\PublishRoleHandler::class),
            \Pet\Application\Work\Command\UpdateRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdateRoleHandler::class),
            \Pet\Application\Work\Command\AssignRoleToPersonHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignRoleToPersonHandler::class),
            \Pet\Application\Work\Command\EndAssignmentHandler::class => \DI\autowire(\Pet\Application\Work\Command\EndAssignmentHandler::class),
            \Pet\Application\Work\Command\CreateSkillHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateSkillHandler::class),
            \Pet\Application\Work\Command\RateEmployeeSkillHandler::class => \DI\autowire(\Pet\Application\Work\Command\RateEmployeeSkillHandler::class),
            \Pet\Application\Work\Command\CreateCertificationHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateCertificationHandler::class),
            \Pet\Application\Work\Command\AssignCertificationToPersonHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignCertificationToPersonHandler::class),
            \Pet\Application\Work\Command\CreateKpiDefinitionHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateKpiDefinitionHandler::class),
            \Pet\Application\Work\Command\AssignKpiToRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignKpiToRoleHandler::class),
            \Pet\Application\Work\Command\GeneratePersonKpisHandler::class => \DI\autowire(\Pet\Application\Work\Command\GeneratePersonKpisHandler::class),
            \Pet\Application\Work\Command\UpdatePersonKpiHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdatePersonKpiHandler::class),
            \Pet\Application\Work\Command\CreatePerformanceReviewHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreatePerformanceReviewHandler::class),
            \Pet\Application\Work\Command\UpdatePerformanceReviewHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdatePerformanceReviewHandler::class),

            \Pet\Application\Identity\Command\CreateCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateCustomerHandler::class),
            \Pet\Application\Identity\Command\UpdateCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateCustomerHandler::class),
            \Pet\Application\Identity\Command\ArchiveCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveCustomerHandler::class),
            \Pet\Application\Identity\Command\CreateContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateContactHandler::class),
            \Pet\Application\Identity\Command\UpdateContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateContactHandler::class),
            \Pet\Application\Identity\Command\ArchiveContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveContactHandler::class),
            \Pet\Application\Support\Command\CreateTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\CreateTicketHandler::class),
            \Pet\Application\Support\Command\UpdateTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\UpdateTicketHandler::class),
            \Pet\Application\Support\Command\DeleteTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\DeleteTicketHandler::class),
            \Pet\Application\Knowledge\Command\CreateArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\CreateArticleHandler::class),
            \Pet\Application\Knowledge\Command\UpdateArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\UpdateArticleHandler::class),
            \Pet\Application\Knowledge\Command\ArchiveArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\ArchiveArticleHandler::class),

            \Pet\UI\Rest\Controller\ProjectController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\QuoteController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\LeadController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TimeEntryController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CustomerController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ContactController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SiteController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TicketController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ArticleController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ActivityController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SettingsController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SchemaController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\DashboardController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SlaController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\RoleController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\AssignmentController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SkillController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CapabilityController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeSkillController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CertificationController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeCertificationController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\KpiDefinitionController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\RoleKpiController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\PersonKpiController::class => \DI\autowire(),
        ];
    }
}

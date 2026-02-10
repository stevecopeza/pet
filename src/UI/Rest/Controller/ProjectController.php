<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Delivery\Command\AddTaskCommand;
use Pet\Application\Delivery\Command\AddTaskHandler;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ProjectController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'projects';

    private ProjectRepository $projectRepository;
    private CreateProjectHandler $createProjectHandler;
    private AddTaskHandler $addTaskHandler;

    public function __construct(
        ProjectRepository $projectRepository,
        CreateProjectHandler $createProjectHandler,
        AddTaskHandler $addTaskHandler
    ) {
        $this->projectRepository = $projectRepository;
        $this->createProjectHandler = $createProjectHandler;
        $this->addTaskHandler = $addTaskHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getProjects'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createProject'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/tasks', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addTask'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getProjects(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        
        if ($customerId) {
            $projects = $this->projectRepository->findByCustomerId((int) $customerId);
        } else {
            $projects = $this->projectRepository->findAll();
        }

        $data = array_map(function ($project) {
            return [
                'id' => $project->id(),
                'name' => $project->name(),
                'customerId' => $project->customerId(),
                'soldHours' => $project->soldHours(),
                'tasks' => array_map(function ($task) {
                    return [
                        'id' => $task->id(),
                        'name' => $task->name(),
                        'estimatedHours' => $task->estimatedHours(),
                        'completed' => $task->isCompleted(),
                    ];
                }, $project->tasks()),
            ];
        }, $projects);

        return new WP_REST_Response($data, 200);
    }

    public function createProject(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateProjectCommand(
                (int) $params['customerId'],
                $params['name'],
                (float) $params['soldHours'],
                isset($params['sourceQuoteId']) ? (int) $params['sourceQuoteId'] : null
            );

            $this->createProjectHandler->handle($command);

            return new WP_REST_Response(['message' => 'Project created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addTask(WP_REST_Request $request): WP_REST_Response
    {
        $projectId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddTaskCommand(
                $projectId,
                $params['name'],
                (float) $params['estimatedHours']
            );

            $this->addTaskHandler->handle($command);

            return new WP_REST_Response(['message' => 'Task added'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}

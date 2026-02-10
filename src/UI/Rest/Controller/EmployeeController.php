<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Application\Identity\Command\CreateEmployeeCommand;
use Pet\Application\Identity\Command\CreateEmployeeHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EmployeeController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'employees';

    private EmployeeRepository $employeeRepository;
    private CreateEmployeeHandler $createEmployeeHandler;

    public function __construct(
        EmployeeRepository $employeeRepository,
        CreateEmployeeHandler $createEmployeeHandler
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->createEmployeeHandler = $createEmployeeHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEmployees'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEmployee'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getEmployees(WP_REST_Request $request): WP_REST_Response
    {
        $employees = $this->employeeRepository->findAll();

        $data = array_map(function ($employee) {
            return [
                'id' => $employee->id(),
                'wpUserId' => $employee->wpUserId(),
                'firstName' => $employee->firstName(),
                'lastName' => $employee->lastName(),
                'email' => $employee->email(),
                'createdAt' => $employee->createdAt()->format('Y-m-d H:i:s'),
                'archivedAt' => $employee->archivedAt() ? $employee->archivedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $employees);

        return new WP_REST_Response($data, 200);
    }

    public function createEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['wpUserId']) || empty($params['firstName']) || empty($params['lastName']) || empty($params['email'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new CreateEmployeeCommand(
                (int) $params['wpUserId'],
                $params['firstName'],
                $params['lastName'],
                $params['email']
            );

            $this->createEmployeeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Employee created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}

<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class TimeEntryController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'time-entries';

    private TimeEntryRepository $timeEntryRepository;
    private LogTimeHandler $logTimeHandler;

    public function __construct(
        TimeEntryRepository $timeEntryRepository,
        LogTimeHandler $logTimeHandler
    ) {
        $this->timeEntryRepository = $timeEntryRepository;
        $this->logTimeHandler = $logTimeHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTimeEntries'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'logTime'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTimeEntries(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = $request->get_param('employee_id');
        $taskId = $request->get_param('task_id');
        
        if ($employeeId) {
            $entries = $this->timeEntryRepository->findByEmployeeId((int) $employeeId);
        } elseif ($taskId) {
            $entries = $this->timeEntryRepository->findByTaskId((int) $taskId);
        } else {
            $entries = $this->timeEntryRepository->findAll();
        }

        $data = array_map(function ($entry) {
            return [
                'id' => $entry->id(),
                'employeeId' => $entry->employeeId(),
                'taskId' => $entry->taskId(),
                'start' => $entry->start()->format('Y-m-d H:i:s'),
                'end' => $entry->end()->format('Y-m-d H:i:s'),
                'duration' => $entry->durationMinutes(),
                'description' => $entry->description(),
                'billable' => $entry->isBillable(),
                'status' => $entry->status(),
            ];
        }, $entries);

        return new WP_REST_Response($data, 200);
    }

    public function logTime(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new LogTimeCommand(
                (int) $params['employeeId'],
                (int) $params['taskId'],
                new \DateTimeImmutable($params['start']),
                new \DateTimeImmutable($params['end']),
                (bool) $params['isBillable'],
                $params['description']
            );

            $this->logTimeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Time logged'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}

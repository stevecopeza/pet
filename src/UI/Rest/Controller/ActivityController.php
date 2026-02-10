<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Activity\Repository\ActivityLogRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ActivityController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'activity';

    private ActivityLogRepository $activityLogRepository;

    public function __construct(ActivityLogRepository $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getActivityLogs'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getActivityLogs(WP_REST_Request $request): WP_REST_Response
    {
        $limit = $request->get_param('limit') ? (int) $request->get_param('limit') : 50;
        $entityType = $request->get_param('entity_type');
        $entityId = $request->get_param('entity_id');

        if ($entityType && $entityId) {
            $logs = $this->activityLogRepository->findByRelatedEntity($entityType, (int) $entityId);
        } else {
            $logs = $this->activityLogRepository->findAll($limit);
        }

        $data = array_map(function ($log) {
            return [
                'id' => $log->id(),
                'type' => $log->type(),
                'description' => $log->description(),
                'userId' => $log->userId(),
                'relatedEntityType' => $log->relatedEntityType(),
                'relatedEntityId' => $log->relatedEntityId(),
                'createdAt' => $log->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $logs);

        return new WP_REST_Response($data, 200);
    }
}

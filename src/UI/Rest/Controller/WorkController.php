<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WorkController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'work';

    public function __construct(
        private WorkItemRepository $workItemRepository,
        private AdvisorySignalRepository $signalRepository
    ) {}

    public function registerRoutes(): void
    {
        // My Work Items
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/my-items', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyWorkItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Department Queue
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/department-queue/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getDepartmentQueue'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in();
    }

    public function getMyWorkItems(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (string)get_current_user_id();
        $items = $this->workItemRepository->findByAssignedUser($userId);
        return new WP_REST_Response($this->mapItems($items));
    }

    public function getDepartmentQueue(WP_REST_Request $request): WP_REST_Response
    {
        $departmentId = $request->get_param('id');
        $items = $this->workItemRepository->findByDepartmentUnassigned($departmentId);
        return new WP_REST_Response($this->mapItems($items));
    }

    private function mapItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $ids = array_map(fn($item) => $item->getId(), $items);
        $allSignals = $this->signalRepository->findByWorkItemIds($ids);
        
        // Group signals by work item id
        $signalsByItem = [];
        foreach ($allSignals as $signal) {
            $signalsByItem[$signal->getWorkItemId()][] = [
                'type' => $signal->getSignalType(),
                'severity' => $signal->getSeverity(),
                'message' => $signal->getMessage(),
                'createdAt' => $signal->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return array_map(function ($item) use ($signalsByItem) {
            return [
                'id' => $item->getId(),
                'sourceType' => $item->getSourceType(),
                'sourceId' => $item->getSourceId(),
                'priorityScore' => $item->getPriorityScore(),
                'slaTimeRemainingMinutes' => $item->getSlaTimeRemainingMinutes(),
                'scheduledDueUtc' => $item->getScheduledDueUtc()?->format('Y-m-d H:i:s'),
                'status' => $item->getStatus(),
                'departmentId' => $item->getDepartmentId(),
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'signals' => $signalsByItem[$item->getId()] ?? [],
            ];
        }, $items);
    }
}

<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use WP_REST_Request;
use WP_REST_Response;

class DashboardController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'dashboard';

    private $projectRepository;
    private $quoteRepository;
    private $activityLogRepository;

    public function __construct(
        ProjectRepository $projectRepository,
        QuoteRepository $quoteRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->projectRepository = $projectRepository;
        $this->quoteRepository = $quoteRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            'methods' => 'GET',
            'callback' => [$this, 'getDashboardData'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getDashboardData(WP_REST_Request $request): WP_REST_Response
    {
        $activeProjects = $this->projectRepository->countActive();
        $pendingQuotes = $this->quoteRepository->countPending();
        $activities = $this->activityLogRepository->findAll(5); // Get last 5 activities

        $recentActivity = array_map(function ($log) {
            return [
                'id' => $log->id(),
                'type' => $log->type(),
                'message' => $log->description(),
                'time' => $this->timeElapsedString($log->createdAt()),
            ];
        }, $activities);

        $data = [
            'overview' => [
                'activeProjects' => $activeProjects,
                'pendingQuotes' => $pendingQuotes,
                'utilizationRate' => 85, // Mocked for now
                'revenueThisMonth' => 0, // Mocked for now
            ],
            'recentActivity' => $recentActivity,
        ];

        return new WP_REST_Response($data, 200);
    }

    private function timeElapsedString(\DateTimeImmutable $datetime, $full = false) {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($datetime);

        // Calculate weeks manually if needed, but for simplicity let's stick to days
        // If days > 7, we can show weeks, but standard DateInterval usage is safer without custom properties.
        
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

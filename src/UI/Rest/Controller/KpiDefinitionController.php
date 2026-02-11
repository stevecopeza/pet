<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\CreateKpiDefinitionCommand;
use Pet\Application\Work\Command\CreateKpiDefinitionHandler;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class KpiDefinitionController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'kpi-definitions';

    private KpiDefinitionRepository $kpiDefinitionRepository;
    private CreateKpiDefinitionHandler $createKpiDefinitionHandler;

    public function __construct(
        KpiDefinitionRepository $kpiDefinitionRepository,
        CreateKpiDefinitionHandler $createKpiDefinitionHandler
    ) {
        $this->kpiDefinitionRepository = $kpiDefinitionRepository;
        $this->createKpiDefinitionHandler = $createKpiDefinitionHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getKpiDefinitions'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createKpiDefinition'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getKpiDefinitions(WP_REST_Request $request): WP_REST_Response
    {
        $definitions = $this->kpiDefinitionRepository->findAll();
        
        $data = array_map(function ($def) {
            return [
                'id' => $def->id(),
                'name' => $def->name(),
                'description' => $def->description(),
                'default_frequency' => $def->defaultFrequency(),
                'unit' => $def->unit(),
                'created_at' => $def->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $definitions);

        return new WP_REST_Response($data, 200);
    }

    public function createKpiDefinition(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['description'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new CreateKpiDefinitionCommand(
            $params['name'],
            $params['description'],
            $params['default_frequency'] ?? 'monthly',
            $params['unit'] ?? '%'
        );

        try {
            $this->createKpiDefinitionHandler->handle($command);
            return new WP_REST_Response(['message' => 'KPI Definition created successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}

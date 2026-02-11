<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\CreateCertificationCommand;
use Pet\Application\Work\Command\CreateCertificationHandler;
use Pet\Domain\Work\Repository\CertificationRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CertificationController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'certifications';

    private CertificationRepository $certificationRepository;
    private CreateCertificationHandler $createCertificationHandler;

    public function __construct(
        CertificationRepository $certificationRepository,
        CreateCertificationHandler $createCertificationHandler
    ) {
        $this->certificationRepository = $certificationRepository;
        $this->createCertificationHandler = $createCertificationHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCertifications'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createCertification'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getCertifications(WP_REST_Request $request): WP_REST_Response
    {
        $certifications = $this->certificationRepository->findAll();
        
        $data = array_map(function ($cert) {
            return [
                'id' => $cert->id(),
                'name' => $cert->name(),
                'issuing_body' => $cert->issuingBody(),
                'expiry_months' => $cert->expiryMonths(),
                'status' => $cert->status(),
                'created_at' => $cert->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $certifications);

        return new WP_REST_Response($data, 200);
    }

    public function createCertification(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['issuing_body'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new CreateCertificationCommand(
            $params['name'],
            $params['issuing_body'],
            (int)($params['expiry_months'] ?? 0)
        );

        try {
            $this->createCertificationHandler->handle($command);
            return new WP_REST_Response(['message' => 'Certification created successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}

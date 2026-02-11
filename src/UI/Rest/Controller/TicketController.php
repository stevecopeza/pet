<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\UpdateTicketCommand;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketCommand;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Domain\Support\Repository\TicketRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class TicketController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'tickets';

    private TicketRepository $ticketRepository;
    private CreateTicketHandler $createTicketHandler;
    private UpdateTicketHandler $updateTicketHandler;
    private DeleteTicketHandler $deleteTicketHandler;

    public function __construct(
        TicketRepository $ticketRepository,
        CreateTicketHandler $createTicketHandler,
        UpdateTicketHandler $updateTicketHandler,
        DeleteTicketHandler $deleteTicketHandler
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->createTicketHandler = $createTicketHandler;
        $this->updateTicketHandler = $updateTicketHandler;
        $this->deleteTicketHandler = $deleteTicketHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTickets'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTickets(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        
        if ($customerId) {
            $tickets = $this->ticketRepository->findByCustomerId((int) $customerId);
        } else {
            $tickets = $this->ticketRepository->findAll();
        }

        $data = array_map(function ($ticket) {
            return [
                'id' => $ticket->id(),
                'customerId' => $ticket->customerId(),
                'siteId' => $ticket->siteId(),
                'slaId' => $ticket->slaId(),
                'subject' => $ticket->subject(),
                'description' => $ticket->description(),
                'status' => $ticket->status(),
                'priority' => $ticket->priority(),
                'malleableData' => $ticket->malleableData(),
                'createdAt' => $ticket->createdAt()->format('Y-m-d H:i:s'),
                'openedAt' => $ticket->openedAt() ? $ticket->openedAt()->format('Y-m-d H:i:s') : null,
                'closedAt' => $ticket->closedAt() ? $ticket->closedAt()->format('Y-m-d H:i:s') : null,
                'resolvedAt' => $ticket->resolvedAt() ? $ticket->resolvedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $tickets);

        return new WP_REST_Response($data, 200);
    }

    public function createTicket(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateTicketCommand(
                (int) $params['customerId'],
                isset($params['siteId']) ? (int) $params['siteId'] : null,
                isset($params['slaId']) ? (int) $params['slaId'] : null,
                $params['subject'],
                $params['description'],
                $params['priority'] ?? 'medium',
                $params['malleableData'] ?? []
            );

            $this->createTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function updateTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateTicketCommand(
                $id,
                isset($params['siteId']) ? (int) $params['siteId'] : null,
                isset($params['slaId']) ? (int) $params['slaId'] : null,
                $params['subject'],
                $params['description'],
                $params['priority'],
                $params['status'],
                $params['malleableData'] ?? []
            );

            $this->updateTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function deleteTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new DeleteTicketCommand($id);
            $this->deleteTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket deleted'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}

<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\AddQuoteLineCommand;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Application\Commercial\Command\CreateQuoteCommand;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class QuoteController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'quotes';

    private QuoteRepository $quoteRepository;
    private CreateQuoteHandler $createQuoteHandler;
    private AddQuoteLineHandler $addQuoteLineHandler;

    public function __construct(
        QuoteRepository $quoteRepository,
        CreateQuoteHandler $createQuoteHandler,
        AddQuoteLineHandler $addQuoteLineHandler
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->createQuoteHandler = $createQuoteHandler;
        $this->addQuoteLineHandler = $addQuoteLineHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuotes'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/lines', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addLine'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getQuotes(WP_REST_Request $request): WP_REST_Response
    {
        // For now findAll, potentially filter by customer
        $quotes = $this->quoteRepository->findAll();

        $data = array_map(function ($quote) {
            return $this->serializeQuote($quote);
        }, $quotes);

        return new WP_REST_Response($data, 200);
    }

    public function getQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $quote = $this->quoteRepository->findById($id);

        if (!$quote) {
            return new WP_REST_Response(['error' => 'Quote not found'], 404);
        }

        return new WP_REST_Response($this->serializeQuote($quote), 200);
    }

    private function serializeQuote($quote): array
    {
        return [
            'id' => $quote->id(),
            'customerId' => $quote->customerId(),
            'state' => $quote->state()->toString(),
            'version' => $quote->version(),
            'lines' => array_map(function ($line) {
                return [
                    'id' => $line->id(),
                    'description' => $line->description(),
                    'quantity' => $line->quantity(),
                    'unitPrice' => $line->unitPrice(),
                    'total' => $line->total(),
                    'group' => $line->lineGroupType(),
                ];
            }, $quote->lines()),
        ];
    }

    public function createQuote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateQuoteCommand(
                (int) $params['customerId']
            );

            $this->createQuoteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Quote created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addLine(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddQuoteLineCommand(
                $quoteId,
                $params['description'],
                (float) $params['quantity'],
                (float) $params['unitPrice'],
                $params['lineGroupType']
            );

            $this->addQuoteLineHandler->handle($command);

            return new WP_REST_Response(['message' => 'Line added'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}

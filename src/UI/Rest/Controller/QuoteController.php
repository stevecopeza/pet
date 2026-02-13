<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\AddQuoteLineCommand;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Application\Commercial\Command\CreateQuoteCommand;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Application\Commercial\Command\UpdateQuoteCommand;
use Pet\Application\Commercial\Command\UpdateQuoteHandler;
use Pet\Application\Commercial\Command\ArchiveQuoteCommand;
use Pet\Application\Commercial\Command\ArchiveQuoteHandler;
use Pet\Application\Commercial\Command\AddComponentCommand;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Application\Commercial\Command\RemoveComponentCommand;
use Pet\Application\Commercial\Command\RemoveComponentHandler;
use Pet\Application\Commercial\Command\SendQuoteCommand;
use Pet\Application\Commercial\Command\SendQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AddCostAdjustmentCommand;
use Pet\Application\Commercial\Command\AddCostAdjustmentHandler;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentCommand;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler;
use Pet\Application\Commercial\Command\SetPaymentScheduleCommand;
use Pet\Application\Commercial\Command\SetPaymentScheduleHandler;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\CostAdjustment;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use DateTimeImmutable;

class QuoteController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'quotes';

    private QuoteRepository $quoteRepository;
    private CreateQuoteHandler $createQuoteHandler;
    private UpdateQuoteHandler $updateQuoteHandler;
    private AddQuoteLineHandler $addQuoteLineHandler;
    private ArchiveQuoteHandler $archiveQuoteHandler;
    private AddComponentHandler $addComponentHandler;
    private RemoveComponentHandler $removeComponentHandler;
    private SendQuoteHandler $sendQuoteHandler;
    private AcceptQuoteHandler $acceptQuoteHandler;
    private AddCostAdjustmentHandler $addCostAdjustmentHandler;
    private RemoveCostAdjustmentHandler $removeCostAdjustmentHandler;
    private SetPaymentScheduleHandler $setPaymentScheduleHandler;

    public function __construct(
        QuoteRepository $quoteRepository,
        CreateQuoteHandler $createQuoteHandler,
        UpdateQuoteHandler $updateQuoteHandler,
        AddQuoteLineHandler $addQuoteLineHandler,
        ArchiveQuoteHandler $archiveQuoteHandler,
        AddComponentHandler $addComponentHandler,
        RemoveComponentHandler $removeComponentHandler,
        SendQuoteHandler $sendQuoteHandler,
        AcceptQuoteHandler $acceptQuoteHandler,
        AddCostAdjustmentHandler $addCostAdjustmentHandler,
        RemoveCostAdjustmentHandler $removeCostAdjustmentHandler,
        SetPaymentScheduleHandler $setPaymentScheduleHandler
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->createQuoteHandler = $createQuoteHandler;
        $this->updateQuoteHandler = $updateQuoteHandler;
        $this->addQuoteLineHandler = $addQuoteLineHandler;
        $this->archiveQuoteHandler = $archiveQuoteHandler;
        $this->addComponentHandler = $addComponentHandler;
        $this->removeComponentHandler = $removeComponentHandler;
        $this->sendQuoteHandler = $sendQuoteHandler;
        $this->acceptQuoteHandler = $acceptQuoteHandler;
        $this->addCostAdjustmentHandler = $addCostAdjustmentHandler;
        $this->removeCostAdjustmentHandler = $removeCostAdjustmentHandler;
        $this->setPaymentScheduleHandler = $setPaymentScheduleHandler;
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
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveQuote'],
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

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/components', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addComponent'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/components/(?P<componentId>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeComponent'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/send', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sendQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/accept', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'acceptQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/adjustments', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addCostAdjustment'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/adjustments/(?P<adjustmentId>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeCostAdjustment'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/payment-schedule', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setPaymentSchedule'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/session', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSession'],
                'permission_callback' => '__return_true', // Public, but we check login inside or relies on cookie
            ],
        ]);
    }

    public function getSession(): WP_REST_Response
    {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['code' => 'unauthorized'], 401);
        }
        return new WP_REST_Response([
            'nonce' => wp_create_nonce('wp_rest'),
            'user_id' => get_current_user_id()
        ], 200);
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
            'title' => $quote->title(),
            'description' => $quote->description(),
            'state' => $quote->state()->toString(),
            'version' => $quote->version(),
            'totalValue' => $quote->totalValue(),
            'totalInternalCost' => $quote->totalInternalCost(),
            'adjustedTotalInternalCost' => $quote->adjustedTotalInternalCost(),
            'margin' => $quote->margin(),
            'currency' => $quote->currency(),
            'acceptedAt' => $quote->acceptedAt() ? $quote->acceptedAt()->format(\DateTimeImmutable::ATOM) : null,
            'malleableData' => $quote->malleableData(),
            'components' => array_map(function ($component) {
                $data = [
                    'id' => $component->id(),
                    'type' => $component->type(),
                    'section' => $component->section(),
                    'description' => $component->description(),
                    'sellValue' => $component->sellValue(),
                    'internalCost' => $component->internalCost(),
                ];

                if ($component instanceof CatalogComponent) {
                    $data['items'] = array_map(function ($item) {
                        return [
                            'description' => $item->description(),
                            'quantity' => $item->quantity(),
                            'unitSellPrice' => $item->unitSellPrice(),
                            'sellValue' => $item->sellValue(),
                        ];
                    }, $component->items());
                }

                return $data;
            }, $quote->components()),
            'costAdjustments' => array_map(function ($adjustment) {
                return [
                    'id' => $adjustment->id(),
                    'description' => $adjustment->description(),
                    'amount' => $adjustment->amount(),
                    'reason' => $adjustment->reason(),
                    'approvedBy' => $adjustment->approvedBy(),
                    'appliedAt' => $adjustment->appliedAt()->format(DateTimeImmutable::ATOM),
                ];
            }, $quote->costAdjustments()),
            'paymentSchedule' => array_map(function ($milestone) {
                return [
                    'id' => $milestone->id(),
                    'title' => $milestone->title(),
                    'amount' => $milestone->amount(),
                    'dueDate' => $milestone->dueDate() ? $milestone->dueDate()->format(DateTimeImmutable::ATOM) : null,
                    'isPaid' => $milestone->isPaid(),
                ];
            }, $quote->paymentSchedule()),
        ];
    }

    public function createQuote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateQuoteCommand(
                (int) $params['customerId'],
                (string) ($params['title'] ?? ''),
                $params['description'] ?? null,
                (string) ($params['currency'] ?? 'USD'),
                !empty($params['acceptedAt']) ? new \DateTimeImmutable($params['acceptedAt']) : null,
                $params['malleableData'] ?? []
            );

            $quoteId = $this->createQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($quoteId);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function updateQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateQuoteCommand(
                $id,
                (int) $params['customerId'],
                (string) ($params['currency'] ?? 'USD'),
                !empty($params['acceptedAt']) ? new \DateTimeImmutable($params['acceptedAt']) : null,
                $params['malleableData'] ?? []
            );

            $this->updateQuoteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Quote updated'], 200);
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

    public function addComponent(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddComponentCommand(
                $id,
                $params['type'],
                $params['data']
            );

            $this->addComponentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeComponent(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $componentId = (int) $request->get_param('componentId');

        try {
            $command = new RemoveComponentCommand($id, $componentId);
            $this->removeComponentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function sendQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            $command = new SendQuoteCommand($id);
            $this->sendQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function acceptQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            $command = new AcceptQuoteCommand($id);
            $this->acceptQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function archiveQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveQuoteCommand($id);
            $this->archiveQuoteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Quote archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addCostAdjustment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddCostAdjustmentCommand(
                $id,
                $params['description'],
                (float) $params['amount'],
                $params['reason'],
                $params['approvedBy']
            );
            $this->addCostAdjustmentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeCostAdjustment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $adjustmentId = (int) $request->get_param('adjustmentId');

        try {
            $command = new RemoveCostAdjustmentCommand($id, $adjustmentId);
            $this->removeCostAdjustmentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function setPaymentSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new SetPaymentScheduleCommand($id, $params['milestones']);
            $this->setPaymentScheduleHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}

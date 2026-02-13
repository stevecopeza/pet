<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\EventRegistry;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Domain\Support\Service\SlaAutomationService;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Entity\Component\QuoteComponent;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Identity\Entity\Customer;

class PreFlightCheckTest extends TestCase
{
    private InMemoryEventBus $eventBus;
    private array $capturedEvents = [];

    protected function setUp(): void
    {
        $this->eventBus = new InMemoryEventBus();
        $this->capturedEvents = [];

        // Subscribe to all relevant events
        $listener = function ($event) {
            $this->capturedEvents[get_class($event)] = $event;
        };

        $this->eventBus->subscribe(QuoteAccepted::class, $listener);
        $this->eventBus->subscribe(ProjectCreated::class, $listener);
        $this->eventBus->subscribe(TicketCreated::class, $listener);
        $this->eventBus->subscribe(TicketWarningEvent::class, $listener);
        $this->eventBus->subscribe(TicketBreachedEvent::class, $listener);
    }

    public function testEventRegistryAudit()
    {
        $this->verifyQuoteAccepted();
        $this->verifyProjectCreated();
        $this->verifyTicketCreated();
        $this->verifySlaEvents();
    }

    private function verifyQuoteAccepted()
    {
        // Setup dependencies
        $quoteRepo = $this->createMock(QuoteRepository::class);
        
        $quoteId = 1;
        $customerId = 1;
        $paymentSchedule = [new PaymentMilestone('Deposit', 100.0, null, false, 1)];
        // Create a valid catalog item with SKU and Role ID (required for service type)
        $component = new CatalogComponent([
            new QuoteCatalogItem(
                'Item', 
                1.0, 
                100.0, 
                50.0, 
                null, 
                null, 
                [], 
                'service', 
                'SKU-TEST', 
                1
            )
        ], 'Component');

        $quote = new Quote(
            $customerId,
            'Quote Title',
            'Quote Description',
            QuoteState::sent(),
            1,
            100.0,
            50.0,
            'USD',
            null,
            $quoteId,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
            [$component],
            [],
            [],
            $paymentSchedule
        );

        $quoteRepo->method('findById')->willReturn($quote);

        $handler = new AcceptQuoteHandler($quoteRepo, $this->eventBus);
        $command = new AcceptQuoteCommand($quoteId, 123, 'John Doe');

        $handler->handle($command);

        $this->assertArrayHasKey(QuoteAccepted::class, $this->capturedEvents, 'QuoteAccepted event not dispatched');
    }

    private function verifyProjectCreated()
    {
        $projectRepo = $this->createMock(ProjectRepository::class);
        $customerRepo = $this->createMock(CustomerRepository::class);
        $schemaRepo = $this->createMock(SchemaDefinitionRepository::class);
        $schemaValidator = $this->createMock(SchemaValidator::class);

        // Mock customer
        $customer = new Customer('Acme', 'email@acme.com', 1);
        $customerRepo->method('findById')->willReturn($customer);

        $handler = new CreateProjectHandler(
            $projectRepo,
            $customerRepo,
            $schemaRepo,
            $schemaValidator,
            $this->eventBus
        );

        $command = new CreateProjectCommand(
            1, // customerId
            'New Project',
            100.0, // soldHours
            null, // sourceQuoteId
            1000.0, // soldValue
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            [] // tasks
        );

        $handler->handle($command);

        $this->assertArrayHasKey(ProjectCreated::class, $this->capturedEvents, 'ProjectCreated event not dispatched');
    }

    private function verifyTicketCreated()
    {
        $ticketRepo = $this->createMock(TicketRepository::class);
        $customerRepo = $this->createMock(CustomerRepository::class);
        $schemaRepo = $this->createMock(SchemaDefinitionRepository::class);
        $schemaValidator = $this->createMock(SchemaValidator::class);

        $customer = new Customer('Acme', 'email@acme.com', 1);
        $customerRepo->method('findById')->willReturn($customer);

        $handler = new CreateTicketHandler(
            $ticketRepo,
            $customerRepo,
            $this->eventBus,
            $schemaRepo,
            $schemaValidator
        );

        $command = new CreateTicketCommand(
            1, // customerId
            1, // siteId
            1, // slaId
            'Issue', // subject
            'Description', // description
            'high' // priority
        );

        $handler->handle($command);

        $this->assertArrayHasKey(TicketCreated::class, $this->capturedEvents, 'TicketCreated event not dispatched');
    }

    private function verifySlaEvents()
    {
        $ticketRepo = $this->createMock(TicketRepository::class);
        $clockStateRepo = $this->createMock(SlaClockStateRepository::class);

        // Setup Ticket for Warning
        // Current time is now. Warning is < 1 hour to breach.
        // Let's set resolutionDueAt to 30 mins from now.
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dueSoon = $now->modify('+30 minutes');
        
        $ticketWarning = new Ticket(
            1, 'SLA Warning', 'Desc', 'new', 'high', 1, 1, 
            101, // id
            null, [], null, null, null, null, null, 
            null, // responseDue
            $dueSoon // resolutionDue
        );
        
        // Setup Ticket for Breach
        // ResolutionDueAt in the past
        $duePast = $now->modify('-10 minutes');
        $ticketBreach = new Ticket(
            1, 'SLA Breach', 'Desc', 'new', 'high', 1, 1, 
            102, // id
            null, [], null, null, null, null, null, 
            null, // responseDue
            $duePast // resolutionDue
        );

        $ticketRepo->method('findActive')->willReturn([$ticketWarning, $ticketBreach]);

        // Mock ClockStateRepo to return null (first run) or existing state
        // initialize() should return a new SlaClockState
        $clockStateRepo->method('initialize')->willReturnCallback(function ($ticket) {
            return new SlaClockState($ticket->id());
        });
        
        $clockStateRepo->method('findByTicketIdForUpdate')->willReturn(null);

        $service = new SlaAutomationService(
            $ticketRepo,
            $clockStateRepo,
            $this->eventBus
        );

        // Run automation
        $service->runSlaCheck();

        $this->assertArrayHasKey(TicketWarningEvent::class, $this->capturedEvents, 'TicketWarningEvent not dispatched');
        $this->assertArrayHasKey(TicketBreachedEvent::class, $this->capturedEvents, 'TicketBreachedEvent not dispatched');
    }
}

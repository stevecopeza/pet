<?php

namespace Pet\Tests\Integration;

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Event\EventBus;
use Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use Pet\Tests\Stubs\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

class AcceptQuoteLockingTest extends TestCase
{
    private $wpdb;
    private $quoteRepo;
    private $transactionManager;
    private $eventBus;
    private $handler;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        
        // Initialize tables
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quotes'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_components'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_milestones'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_tasks'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_payment_schedule'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_onceoff_phases'] = [];
        $this->wpdb->table_data[$this->wpdb->prefix . 'pet_quote_onceoff_units'] = [];
        
        $this->quoteRepo = new SqlQuoteRepository($this->wpdb);
        $this->transactionManager = new SqlTransaction($this->wpdb);
        
        $this->eventBus = $this->createMock(EventBus::class);
        
        $this->handler = new AcceptQuoteHandler(
            $this->transactionManager,
            $this->quoteRepo,
            $this->eventBus
        );
    }

    public function testAcceptQuoteUsesLocking(): void
    {
        // 1. Setup a quote in DB
        $quote = new Quote(
            1, // customerId
            'Test Quote',
            'Description',
            QuoteState::draft(),
            1, // version
            100.0,
            50.0,
            'USD',
            null,
            123 // ID
        );
        // We need to bypass the fact that we can't save a quote without components easily via repo if we are strict,
        // but SqlQuoteRepository implementation of saveComponents deletes and inserts.
        // Let's just insert the raw row into InMemoryWpdb to avoid complex object setup
        
        $this->wpdb->insert($this->wpdb->prefix . 'pet_quotes', [
            'id' => 123,
            'customer_id' => 1,
            'title' => 'Test Quote',
            'state' => 'sent',
            'version' => 1,
            'total_value' => 100.00,
            'total_internal_cost' => 50.00,
            'currency' => 'USD',
            'created_at' => '2023-01-01 12:00:00',
            'updated_at' => null,
            'archived_at' => null,
            'malleable_data' => '{}'
        ]);

        // Add a component
        $this->wpdb->insert($this->wpdb->prefix . 'pet_quote_components', [
            'id' => 1,
            'quote_id' => 123,
            'type' => 'implementation',
            'section' => 'A',
            'description' => 'Test Component'
        ]);

        // Add a milestone
        $this->wpdb->insert($this->wpdb->prefix . 'pet_quote_milestones', [
            'id' => 1,
            'component_id' => 1,
            'title' => 'Test Milestone',
            'description' => 'Test Description'
        ]);

        // Add a task
        $this->wpdb->insert($this->wpdb->prefix . 'pet_quote_tasks', [
            'id' => 1,
            'milestone_id' => 1,
            'title' => 'Test Task',
            'description' => 'Test Description',
            'duration_hours' => 5.0,
            'role_id' => 1,
            'base_internal_rate' => 50.0,
            'sell_rate' => 100.0
        ]);

        // Add a payment schedule
        $this->wpdb->insert($this->wpdb->prefix . 'pet_quote_payment_schedule', [
            'id' => 1,
            'quote_id' => 123,
            'title' => 'Initial Payment',
            'amount' => 100.0,
            'due_date' => '2023-01-01 12:00:00',
            'paid_flag' => 0
        ]);

        // 2. Expect EventBus dispatch
        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(QuoteAccepted::class));

        // 3. Handle Command
        $this->handler->handle(new AcceptQuoteCommand(123));

        // 4. Verify SQL queries for locking
        $queries = $this->wpdb->query_log;
        
        // Look for SELECT ... FOR UPDATE
        $foundLock = false;
        foreach ($queries as $q) {
            if (strpos($q, 'SELECT * FROM wp_pet_quotes WHERE id = 123 LIMIT 1 FOR UPDATE') !== false) {
                $foundLock = true;
                break;
            }
        }
        
        $this->assertTrue($foundLock, 'AcceptQuoteHandler should lock the quote row using FOR UPDATE');
        
        // 5. Verify transaction usage
        $this->assertContains('START TRANSACTION', $this->wpdb->transactionStatus);
        $this->assertContains('COMMIT', $this->wpdb->transactionStatus);
    }
}

<?php

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Conversation\Command\CreateConversationHandler;
use Pet\Application\Conversation\Command\CreateConversationCommand;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Application\Conversation\Command\AddParticipantHandler;
use Pet\Application\Conversation\Command\AddParticipantCommand;
use Pet\Application\Conversation\Command\RemoveParticipantHandler;
use Pet\Application\Conversation\Command\RemoveParticipantCommand;

class ParticipantManagementTest extends TestCase
{
    private $conversationRepo;
    private $employeeRepo;
    private $contactRepo;
    private $teamRepo;
    private $quoteRepo;
    private $transactionManager;
    private $createHandler;
    private $addHandler;
    private $removeHandler;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->employeeRepo = $this->createMock(EmployeeRepository::class);
        $this->contactRepo = $this->createMock(ContactRepository::class);
        $this->teamRepo = $this->createMock(TeamRepository::class);
        $this->quoteRepo = $this->createMock(QuoteRepository::class);
        $this->transactionManager = $this->createMock(TransactionManager::class);
        $this->transactionManager->method('transactional')->willReturnCallback(function ($fn) {
            return $fn();
        });

        $this->createHandler = new CreateConversationHandler(
            $this->transactionManager,
            $this->conversationRepo,
            $this->employeeRepo,
            $this->contactRepo,
            $this->teamRepo,
            $this->quoteRepo
        );
        
        $this->addHandler = new AddParticipantHandler($this->transactionManager, $this->conversationRepo);
        $this->removeHandler = new RemoveParticipantHandler($this->transactionManager, $this->conversationRepo);

        // Mock wp_generate_uuid4 if needed, but since it's a WP function, 
        // we might need to rely on the existing mock or define it if running in isolation.
        if (!function_exists('wp_generate_uuid4')) {
            function wp_generate_uuid4() {
                return 'test-uuid-' . uniqid();
            }
        }
    }

    public function testSmartSeedingForQuoteParticipants()
    {
        $quoteId = 123;
        $customerId = 456;
        $actorId = 1;
        
        // Mock Quote
        $quote = $this->createMock(Quote::class);
        $quote->method('customerId')->willReturn($customerId);
        $quote->method('id')->willReturn($quoteId);

        $this->quoteRepo->expects($this->once())
            ->method('findById')
            ->with($quoteId)
            ->willReturn($quote);

        // Mock Contacts
        $contact1 = $this->createMock(Contact::class);
        $contact1->method('id')->willReturn(101);
        
        $contact2 = $this->createMock(Contact::class);
        $contact2->method('id')->willReturn(102);

        $this->contactRepo->expects($this->once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$contact1, $contact2]);

        // Capture the saved conversation to verify participants
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function(Conversation $conversation) use ($actorId) {
                // Inspect pending events to verify participants were added
                // Since we are intercepting the save call, the events are still pending (unless save calls releaseEvents, which it does, but we are in the mock)
                
                // We can use reflection to peek at pendingEvents without consuming them if we want to be safe,
                // or just consume them since this is a mock and real save won't happen.
                $events = $conversation->releaseEvents();
                
                // Expect: 
                // 1. ParticipantAdded (Creator)
                // 2. ContactParticipantAdded (Contact 101)
                // 3. ContactParticipantAdded (Contact 102)
                
                $hasCreator = false;
                $hasContact1 = false;
                $hasContact2 = false;

                foreach ($events as $event) {
                    if ($event instanceof \Pet\Domain\Conversation\Event\ParticipantAdded) {
                        $payload = $event->payload();
                        if ($payload['user_id'] === $actorId) $hasCreator = true;
                    }
                    if ($event instanceof \Pet\Domain\Conversation\Event\ContactParticipantAdded) {
                        $payload = $event->payload();
                        if ($payload['contact_id'] === 101) $hasContact1 = true;
                        if ($payload['contact_id'] === 102) $hasContact2 = true;
                    }
                }

                return $hasCreator && $hasContact1 && $hasContact2;
            }));

        $command = new CreateConversationCommand(
            'quote',
            (string)$quoteId,
            'Test Subject',
            'quote:123',
            $actorId
        );

        $this->createHandler->handle($command);
    }

    public function testAddParticipant()
    {
        $conversationUuid = 'uuid-123';
        $actorId = 1;
        $participantId = 2;
        
        $conversation = $this->createMock(Conversation::class);
        $conversation->expects($this->once())
            ->method('addParticipant')
            ->with($participantId, $actorId);
            
        $this->conversationRepo->expects($this->once())
            ->method('findByUuid')
            ->with($conversationUuid)
            ->willReturn($conversation);
            
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($conversation);
            
        $command = new AddParticipantCommand($conversationUuid, 'user', $participantId, $actorId);
        $this->addHandler->handle($command);
    }

    public function testRemoveParticipantEnforcesLastInternalCoverage()
    {
        $conversationUuid = 'uuid-123';
        $actorId = 1;
        $participantId = 2;
        $conversationId = 100;
        
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);
        
        $this->conversationRepo->expects($this->once())
            ->method('findByUuid')
            ->with($conversationUuid)
            ->willReturn($conversation);
            
        // Mock that there is only 1 internal participant left
        $this->conversationRepo->expects($this->once())
            ->method('getInternalParticipantCount')
            ->with($conversationId)
            ->willReturn(1);
            
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot remove the last internal participant from the conversation.');
        
        $command = new RemoveParticipantCommand($conversationUuid, 'user', $participantId, $actorId);
        $this->removeHandler->handle($command);
    }
    
    public function testRemoveParticipantSuccess()
    {
        $conversationUuid = 'uuid-123';
        $actorId = 1;
        $participantId = 2;
        $conversationId = 100;
        
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn($conversationId);
        $conversation->expects($this->once())
            ->method('removeParticipant')
            ->with($participantId, $actorId);
        
        $this->conversationRepo->expects($this->once())
            ->method('findByUuid')
            ->with($conversationUuid)
            ->willReturn($conversation);
            
        // Mock that there are 2 internal participants left
        $this->conversationRepo->expects($this->once())
            ->method('getInternalParticipantCount')
            ->with($conversationId)
            ->willReturn(2);
            
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($conversation);
        
        $command = new RemoveParticipantCommand($conversationUuid, 'user', $participantId, $actorId);
        $this->removeHandler->handle($command);
    }
}

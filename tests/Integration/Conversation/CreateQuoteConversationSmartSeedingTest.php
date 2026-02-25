<?php

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\Application\Conversation\Command\CreateConversationCommand;
use Pet\Application\Conversation\Command\CreateConversationHandler;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Entity\Employee;

class CreateQuoteConversationSmartSeedingTest extends TestCase
{
    private $conversationRepo;
    private $employeeRepo;
    private $contactRepo;
    private $teamRepo;
    private $quoteRepo;
    private $handler;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->employeeRepo = $this->createMock(EmployeeRepository::class);
        $this->contactRepo = $this->createMock(ContactRepository::class);
        $this->teamRepo = $this->createMock(TeamRepository::class);
        $this->quoteRepo = $this->createMock(QuoteRepository::class);

        // We need to verify that repositories are injected correctly
        $this->handler = new CreateConversationHandler(
            $this->conversationRepo,
            $this->employeeRepo,
            $this->contactRepo,
            $this->teamRepo,
            $this->quoteRepo
        );
        
        // Mock wp_generate_uuid4 if needed, but it's a WP function.
        // Assuming it exists or we might need to mock it if running in pure PHPUnit without WP load.
        // The previous test passed so WP functions are likely available or mocked in bootstrap.
        if (!function_exists('wp_generate_uuid4')) {
             // Mocking global functions is tricky without namespaced functions or specific tools.
             // We rely on the environment having these or the handler using them.
             // In the previous test, we didn't mock it because the handler generates it.
             // But wait, the handler calls wp_generate_uuid4().
             // If this test runs in isolation without WP, it might fail.
             // But the previous integration test ran fine, so WP environment is likely loaded.
        }
    }

    public function testSmartSeedingAutoAddsCustomerContacts()
    {
        $quoteId = 100;
        $customerId = 200;
        $actorId = 1;
        $uuid = 'generated-uuid';
        $contactId = 300;

        // Mock Quote
        $quote = $this->createMock(Quote::class);
        $quote->method('customerId')->willReturn($customerId);
        $quote->method('id')->willReturn($quoteId);

        $this->quoteRepo->method('findById')->with($quoteId)->willReturn($quote);

        // Mock Contacts
        $contact = $this->createMock(Contact::class);
        $contact->method('id')->willReturn($contactId);
        
        $this->contactRepo->method('findByCustomerId')->with($customerId)->willReturn([$contact]);

        // Mock Conversation Creation and Participant Addition
        // The handler creates a NEW Conversation instance using Conversation::create.
        // Since Conversation::create is a static method returning a new instance, we cannot mock the instance created inside the handler easily
        // UNLESS we mock the repository save method to capture the saved conversation and assert on it.
        // OR we can't easily mock the addParticipant calls on the *created* conversation because it's a real object.
        // BUT, Conversation::create returns a real Conversation object.
        // So we can check the events recorded on that object if we capture it in save().
        
        // We will capture the conversation passed to save()
        $capturedConversation = null;
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->will($this->returnCallback(function($conversation) use (&$capturedConversation) {
                $capturedConversation = $conversation;
                return;
            }));

        $command = new CreateConversationCommand(
            'quote',
            (string)$quoteId,
            'Subject',
            'subject_key',
            $actorId
        );

        $resultUuid = $this->handler->handle($command);
        
        $this->assertNotNull($capturedConversation);
        
        if ($capturedConversation) {
            $events = $capturedConversation->releaseEvents();
            
            $participantAddedCount = 0;
            $contactParticipantAddedCount = 0;
            
            foreach ($events as $event) {
                $classname = get_class($event);
                if (strpos($classname, 'ParticipantAdded') !== false && strpos($classname, 'ContactParticipantAdded') === false && strpos($classname, 'TeamParticipantAdded') === false) {
                    $participantAddedCount++;
                }
                if (strpos($classname, 'ContactParticipantAdded') !== false) {
                    $contactParticipantAddedCount++;
                }
            }
            
            // 1. Creator added (ParticipantAdded)
            $this->assertEquals(1, $participantAddedCount, 'Creator should be added as participant');
            
            // 2. Contact added (ContactParticipantAdded)
            $this->assertEquals(1, $contactParticipantAddedCount, 'Quote contact should be auto-added');
        }
    }
}

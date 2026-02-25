<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Conversation;

use Pet\Application\Conversation\Command\CreateConversationCommand;
use Pet\Application\Conversation\Command\CreateConversationHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Event\ContactParticipantAdded;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use PHPUnit\Framework\TestCase;

class CreateQuoteConversationTest extends TestCase
{
    private $conversationRepo;
    private $quoteRepo;
    private $contactRepo;
    private $employeeRepo;
    private $teamRepo;
    private $handler;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->quoteRepo = $this->createMock(QuoteRepository::class);
        $this->contactRepo = $this->createMock(ContactRepository::class);
        $this->employeeRepo = $this->createMock(EmployeeRepository::class);
        $this->teamRepo = $this->createMock(TeamRepository::class);

        $this->handler = new CreateConversationHandler(
            $this->conversationRepo,
            $this->employeeRepo,
            $this->contactRepo,
            $this->teamRepo,
            $this->quoteRepo
        );
    }

    public function testCustomerContactsAreSeededForQuoteConversation()
    {
        $quoteId = 123;
        $customerId = 555;
        $creatorId = 10;
        $contactId1 = 1001;
        $contactId2 = 1002;

        $command = new CreateConversationCommand(
            'quote',
            (string)$quoteId,
            'Quote Discussion',
            'quote_discussion',
            $creatorId
        );

        // Mock Conversation Repository: findByContext returns null (new conversation)
        $this->conversationRepo->method('findByContext')->willReturn(null);

        // Mock Quote Repository: findById returns a quote with customerId
        $quote = $this->createMock(Quote::class);
        $quote->method('customerId')->willReturn($customerId);
        $this->quoteRepo->method('findById')->with($quoteId)->willReturn($quote);

        // Mock Contact Repository: findByCustomerId returns contacts
        $contact1 = $this->createMock(Contact::class);
        $contact1->method('id')->willReturn($contactId1);
        
        $contact2 = $this->createMock(Contact::class);
        $contact2->method('id')->willReturn($contactId2);

        $this->contactRepo->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$contact1, $contact2]);

        // Mock Conversation Save: Capture and verify events
        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Conversation $c) use ($contactId1, $contactId2) {
                $events = $c->releaseEvents();
                $contactParticipantIds = [];
                foreach ($events as $event) {
                    if ($event instanceof ContactParticipantAdded) {
                        $contactParticipantIds[] = $event->payload()['contact_id'];
                    }
                }

                return in_array($contactId1, $contactParticipantIds)
                    && in_array($contactId2, $contactParticipantIds);
            }));

        $this->handler->handle($command);
    }

    public function testNonQuoteConversationDoesNotSeedContacts()
    {
        $ticketId = 789;
        $creatorId = 10;

        $command = new CreateConversationCommand(
            'ticket',
            (string)$ticketId,
            'Ticket Discussion',
            'ticket_discussion',
            $creatorId
        );

        $this->conversationRepo->method('findByContext')->willReturn(null);

        // Ensure Quote/Contact repos are NOT called
        $this->quoteRepo->expects($this->never())->method('findById');
        $this->contactRepo->expects($this->never())->method('findByCustomerId');

        $this->conversationRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Conversation $c) {
                $events = $c->releaseEvents();
                foreach ($events as $event) {
                    if ($event instanceof ContactParticipantAdded) {
                        return false; // Should not have contact participants
                    }
                }
                return true;
            }));

        $this->handler->handle($command);
    }
}

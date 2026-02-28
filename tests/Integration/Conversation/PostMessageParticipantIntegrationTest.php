<?php

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Conversation\Command\PostMessageCommand;
use Pet\Application\Conversation\Command\PostMessageHandler;
use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Team\Entity\Team;

class PostMessageParticipantIntegrationTest extends TestCase
{
    private $conversationRepo;
    private $employeeRepo;
    private $contactRepo;
    private $teamRepo;
    private $transactionManager;
    private $handler;

    protected function setUp(): void
    {
        $this->conversationRepo = $this->createMock(ConversationRepository::class);
        $this->employeeRepo = $this->createMock(EmployeeRepository::class);
        $this->contactRepo = $this->createMock(ContactRepository::class);
        $this->teamRepo = $this->createMock(TeamRepository::class);
        $this->transactionManager = $this->createMock(TransactionManager::class);
        $this->transactionManager->method('transactional')->willReturnCallback(function ($fn) {
            return $fn();
        });

        $this->handler = new PostMessageHandler(
            $this->transactionManager,
            $this->conversationRepo,
            $this->employeeRepo,
            $this->contactRepo,
            $this->teamRepo
        );
    }

    public function testPosterIsAutoAddedAsParticipant()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $body = 'Hello world';

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);
        
        // Expect poster to be added
        $conversation->expects($this->once())
            ->method('addParticipant')
            ->with($actorId, $actorId);
            
        $conversation->expects($this->once())
            ->method('postMessage')
            ->with($body, [], [], $actorId, null);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        $this->conversationRepo->expects($this->once())->method('save')->with($conversation);

        $command = new PostMessageCommand(
            $uuid,
            $body,
            [],
            [],
            $actorId
        );

        $this->handler->handle($command);
    }

    public function testUserMentionAutoAddsParticipant()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $mentionedUserId = 2;
        $body = 'Hello @user';
        $mentions = [['type' => 'user', 'id' => $mentionedUserId]];

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);

        // Expect poster added (1st call) AND mentioned user added (2nd call)
        $conversation->expects($this->exactly(2))
            ->method('addParticipant')
            ->withConsecutive(
                [$actorId, $actorId],
                [$mentionedUserId, $actorId]
            );

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        // Mock Employee Repo to find user
        $employee = $this->createMock(Employee::class);
        $this->employeeRepo->method('findByWpUserId')->with($mentionedUserId)->willReturn($employee);

        $this->conversationRepo->expects($this->once())->method('save')->with($conversation);

        $command = new PostMessageCommand(
            $uuid,
            $body,
            $mentions,
            [],
            $actorId
        );

        $this->handler->handle($command);
    }

    public function testContactMentionAutoAddsParticipant()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $contactId = 99;
        $body = 'Hello @contact';
        $mentions = [['type' => 'contact', 'id' => $contactId]];

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);

        // Expect poster added
        $conversation->expects($this->once())
            ->method('addParticipant')
            ->with($actorId, $actorId);
            
        // Expect contact added
        $conversation->expects($this->once())
            ->method('addContactParticipant')
            ->with($contactId, $actorId);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        // Mock Contact Repo
        $contact = $this->createMock(Contact::class);
        $this->contactRepo->method('findById')->with($contactId)->willReturn($contact);

        $this->conversationRepo->expects($this->once())->method('save')->with($conversation);

        $command = new PostMessageCommand(
            $uuid,
            $body,
            $mentions,
            [],
            $actorId
        );

        $this->handler->handle($command);
    }

    public function testTeamMentionAutoAddsParticipant()
    {
        $uuid = 'conv-uuid-123';
        $actorId = 1;
        $teamId = 55;
        $body = 'Hello @team';
        $mentions = [['type' => 'team', 'id' => $teamId]];

        // Mock Conversation
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('id')->willReturn(1);

        // Expect poster added
        $conversation->expects($this->once())
            ->method('addParticipant')
            ->with($actorId, $actorId);
            
        // Expect team added
        $conversation->expects($this->once())
            ->method('addTeamParticipant')
            ->with($teamId, $actorId);

        $this->conversationRepo->method('findByUuid')->with($uuid)->willReturn($conversation);
        
        // Mock Team Repo
        $team = $this->createMock(Team::class);
        $this->teamRepo->method('find')->with($teamId)->willReturn($team);

        $this->conversationRepo->expects($this->once())->method('save')->with($conversation);

        $command = new PostMessageCommand(
            $uuid,
            $body,
            $mentions,
            [],
            $actorId
        );

        $this->handler->handle($command);
    }
}

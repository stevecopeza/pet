<?php

namespace Pet\Tests\Unit\Domain\Conversation\Service {

    use PHPUnit\Framework\TestCase;
    use Pet\Domain\Conversation\Service\ConversationAccessControl;
    use Pet\Domain\Delivery\Repository\ProjectRepository;
    use Pet\Domain\Identity\Repository\EmployeeRepository;
    use Pet\Domain\Support\Repository\TicketRepository;
    use Pet\Domain\Commercial\Repository\QuoteRepository;
    use Pet\Domain\Identity\Repository\ContactRepository;
    use Pet\Domain\Support\Entity\Ticket;
    use Pet\Domain\Delivery\Entity\Project;
    use Pet\Domain\Commercial\Entity\Quote;
    use Pet\Domain\Identity\Entity\Contact;
    use Pet\Domain\Identity\Entity\Employee;

    class ConversationAccessControlTest extends TestCase
    {
        private $ticketRepository;
        private $projectRepository;
        private $employeeRepository;
        private $quoteRepository;
        private $contactRepository;
        private $service;

        protected function setUp(): void
        {
            $this->ticketRepository = $this->createMock(TicketRepository::class);
            $this->projectRepository = $this->createMock(ProjectRepository::class);
            $this->employeeRepository = $this->createMock(EmployeeRepository::class);
            $this->quoteRepository = $this->createMock(QuoteRepository::class);
            $this->contactRepository = $this->createMock(ContactRepository::class);

            $this->service = new ConversationAccessControl(
                $this->ticketRepository,
                $this->projectRepository,
                $this->employeeRepository,
                $this->quoteRepository,
                $this->contactRepository
            );
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['wp_tests_user_can_return']);
            unset($GLOBALS['wp_tests_get_userdata_return']);
        }

        public function testAdminCanAccessAnything()
        {
            $GLOBALS['wp_tests_user_can_return'] = true;
            // Mock findById to return something so we don't fail on null check
            $this->ticketRepository->method('findById')->willReturn($this->createMock(Ticket::class));
            $this->quoteRepository->method('findById')->willReturn($this->createMock(Quote::class));
            $this->projectRepository->method('findById')->willReturn($this->createMock(Project::class));
            
            $this->assertTrue($this->service->check('ticket', '1', 1));
            $this->assertTrue($this->service->check('quote', '1', 1));
            $this->assertTrue($this->service->check('project', '1', 1));
        }

        public function testTicketOwnerCanAccess()
        {
            $GLOBALS['wp_tests_user_can_return'] = false;
            $ticketId = 1;
            $userId = 123;
            
            $ticket = $this->createMock(Ticket::class);
            $ticket->method('ownerUserId')->willReturn((string)$userId);
            
            $this->ticketRepository->method('findById')->with($ticketId)->willReturn($ticket);
            
            $this->assertTrue($this->service->check('ticket', (string)$ticketId, $userId));
        }

        public function testTicketNonOwnerCannotAccess()
        {
            $GLOBALS['wp_tests_user_can_return'] = false;
            $ticketId = 1;
            $userId = 123;
            $ownerId = 456;
            
            $ticket = $this->createMock(Ticket::class);
            $ticket->method('ownerUserId')->willReturn((string)$ownerId);
            
            $this->ticketRepository->method('findById')->with($ticketId)->willReturn($ticket);
            
            $this->assertFalse($this->service->check('ticket', (string)$ticketId, $userId));
        }

        public function testProjectEmployeeCanAccess()
        {
            $GLOBALS['wp_tests_user_can_return'] = false;
            $projectId = 1;
            $userId = 123;
            
            $project = $this->createMock(Project::class);
            $this->projectRepository->method('findById')->with($projectId)->willReturn($project);
            
            $this->employeeRepository->method('findByWpUserId')->with($userId)->willReturn($this->createMock(Employee::class));
            
            $this->assertTrue($this->service->check('project', (string)$projectId, $userId));
        }

        public function testKnowledgeEmployeeCanAccess()
        {
            $GLOBALS['wp_tests_user_can_return'] = false;
            $articleId = 1;
            $userId = 123;
            
            $this->employeeRepository->method('findByWpUserId')->with($userId)->willReturn($this->createMock(Employee::class));
            
            $this->assertTrue($this->service->check('knowledge_article', (string)$articleId, $userId));
        }

        public function testQuoteAccessIsDeniedForNonAdmin()
        {
            $GLOBALS['wp_tests_user_can_return'] = false;
            $quoteId = 1;
            $userId = 123;
            
            $this->assertFalse($this->service->check('quote', (string)$quoteId, $userId));
        }
    }
}

namespace Pet\Domain\Conversation\Service {
    if (!function_exists('Pet\Domain\Conversation\Service\user_can')) {
        function user_can($user, $capability) {
            return $GLOBALS['wp_tests_user_can_return'] ?? false;
        }
    }

    if (!function_exists('Pet\Domain\Conversation\Service\get_userdata')) {
        function get_userdata($userId) {
            return $GLOBALS['wp_tests_get_userdata_return'] ?? false;
        }
    }
}

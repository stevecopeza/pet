<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class ReopenConversationHandler
{
    private ConversationRepository $conversationRepository;

    public function __construct(ConversationRepository $conversationRepository)
    {
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(ReopenConversationCommand $command): void
    {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $conversation->reopen($command->actorId());
        $this->conversationRepository->save($conversation);
    }
}

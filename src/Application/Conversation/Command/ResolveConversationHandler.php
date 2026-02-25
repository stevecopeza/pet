<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class ResolveConversationHandler
{
    private ConversationRepository $conversationRepository;

    public function __construct(ConversationRepository $conversationRepository)
    {
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(ResolveConversationCommand $command): void
    {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $conversation->resolve($command->actorId());
        $this->conversationRepository->save($conversation);
    }
}

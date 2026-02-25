<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class AddParticipantHandler
{
    private ConversationRepository $conversationRepository;

    public function __construct(ConversationRepository $conversationRepository)
    {
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(AddParticipantCommand $command): void
    {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());

        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        switch ($command->participantType()) {
            case 'user':
                $conversation->addParticipant($command->participantId(), $command->actorId());
                break;
            case 'contact':
                $conversation->addContactParticipant($command->participantId(), $command->actorId());
                break;
            case 'team':
                $conversation->addTeamParticipant($command->participantId(), $command->actorId());
                break;
            default:
                throw new \InvalidArgumentException('Invalid participant type: ' . $command->participantType());
        }

        $this->conversationRepository->save($conversation);
    }
}

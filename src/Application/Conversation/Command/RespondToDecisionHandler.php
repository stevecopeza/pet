<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;

class RespondToDecisionHandler
{
    private DecisionRepository $decisionRepository;
    private ConversationRepository $conversationRepository;

    public function __construct(
        DecisionRepository $decisionRepository,
        ConversationRepository $conversationRepository
    ) {
        $this->decisionRepository = $decisionRepository;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(RespondToDecisionCommand $command): void
    {
        // Use FOR UPDATE via repository method
        $decision = $this->decisionRepository->findByUuidForUpdate($command->decisionUuid());
        
        if (!$decision) {
            throw new \RuntimeException('Decision not found');
        }

        $decision->respond(
            $command->responderId(),
            $command->response(),
            $command->comment()
        );

        $this->decisionRepository->save($decision);

        // Add responder as participant
        $conversation = $this->conversationRepository->findById($decision->conversationId());
        if ($conversation) {
            $conversation->addParticipant($command->responderId(), $command->responderId());
            $this->conversationRepository->save($conversation);
        }
    }
}

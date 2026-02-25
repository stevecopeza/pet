<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Domain\Conversation\Entity\Decision;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;

class RequestDecisionHandler
{
    private ConversationRepository $conversationRepository;
    private DecisionRepository $decisionRepository;

    public function __construct(
        ConversationRepository $conversationRepository,
        DecisionRepository $decisionRepository
    ) {
        $this->conversationRepository = $conversationRepository;
        $this->decisionRepository = $decisionRepository;
    }

    public function handle(RequestDecisionCommand $command): string
    {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $uuid = wp_generate_uuid4();

        $decision = Decision::request(
            $uuid,
            $command->conversationUuid(),
            (int)$conversation->id(),
            $command->decisionType(),
            $command->payload(),
            $command->policy(),
            $command->requesterId()
        );

        $this->decisionRepository->save($decision);

        // Auto-add requester as participant
        $conversation->addParticipant($command->requesterId(), $command->requesterId());
        $this->conversationRepository->save($conversation);
        
        // Auto-add approvers as participants?
        // Spec: "Approvers are auto-added as participants."
        foreach ($command->policy()->eligibleUserIds() as $userId) {
            $conversation->addParticipant($userId, $command->requesterId());
        }
        $this->conversationRepository->save($conversation);

        return $uuid;
    }
}

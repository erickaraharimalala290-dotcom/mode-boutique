<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\MessageReplyRepository;
use App\Repository\ReturnRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnreadMessagesExtension extends AbstractExtension
{
    public function __construct(
        private MessageReplyRepository $messageReplyRepository,
        private ReturnRequestRepository $returnRequestRepository,
        private Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadMessagesCount']),
            new TwigFunction('admin_pending_return_requests_count', [$this, 'getPendingReturnRequestsCount']),
        ];
    }

    /**
     * Compter les réponses non lues envoyées par l'administration au client connecté.
     */
    public function getUnreadMessagesCount(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        $unreadReplies = $this->messageReplyRepository
            ->findUnreadRepliesForUser($user);

        $count = 0;

        foreach ($unreadReplies as $reply) {
            $sender = $reply->getSender();

            // Prendre en compte les réponses de l'administrateur et du gestionnaire.
            if (
                $sender !== null &&
                (
                    in_array('ROLE_ADMIN', $sender->getRoles(), true) ||
                    in_array('ROLE_MANAGER', $sender->getRoles(), true)
                )
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Compter les demandes de retour en attente pour la navigation admin.
     */
    public function getPendingReturnRequestsCount(): int
    {
        // Éviter d'afficher le nombre de demandes aux utilisateurs non autorisés.
        if (
            !$this->security->isGranted('ROLE_ADMIN') &&
            !$this->security->isGranted('ROLE_MANAGER')
        ) {
            return 0;
        }

        return $this->returnRequestRepository->countPendingRequests();
    }
}

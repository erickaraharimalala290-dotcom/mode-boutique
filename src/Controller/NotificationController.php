<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ContactMessageRepository;
use App\Repository\MessageReplyRepository;
use App\Repository\ReturnRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route(
        '/notifications/counts',
        name: 'app_notifications_counts',
        methods: ['GET']
    )]
    public function counts(
        ContactMessageRepository $contactMessageRepository,
        MessageReplyRepository $messageReplyRepository,
        ReturnRequestRepository $returnRequestRepository
    ): JsonResponse {
        $user = $this->getUser();

        // Vérifier que l'utilisateur est connecté.
        if (!$user instanceof User) {
            return $this->json(
                ['error' => 'Utilisateur non connecté.'],
                401
            );
        }

        // Préparer les compteurs selon le type de compte.
        if ($this->isGranted('ROLE_MANAGER')) {
            return $this->json([
                // Messages envoyés par les clients et non encore lus.
                'messages' => $contactMessageRepository->count([
                    'isRead' => false,
                ]),

                // Demandes de retour en attente de traitement.
                'returns' => $returnRequestRepository->countPendingRequests(),
            ])->setPrivate()->setMaxAge(0);
        }

        // Récupérer les réponses non lues des conversations du client.
        $unreadReplies = $messageReplyRepository
            ->findUnreadRepliesForUser($user);

        $unreadCount = 0;

        foreach ($unreadReplies as $reply) {
            $sender = $reply->getSender();

            // Compter uniquement les réponses de l'administration.
            if (
                $sender !== null &&
                (
                    in_array('ROLE_ADMIN', $sender->getRoles(), true) ||
                    in_array('ROLE_MANAGER', $sender->getRoles(), true)
                )
            ) {
                $unreadCount++;
            }
        }

        return $this->json([
            'messages' => $unreadCount,
        ])->setPrivate()->setMaxAge(0);
    }
}

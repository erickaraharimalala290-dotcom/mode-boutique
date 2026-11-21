<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Entity\MessageReply;
use App\Entity\User;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/messages', name: 'admin_messages_')]
#[IsGranted('ROLE_MANAGER')]
class MessageCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ContactMessageRepository $contactMessageRepository
    ): Response {
        // Récupérer les messages du plus récent au plus ancien.
        $contactMessages = $contactMessageRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        $messages = [];

        $stats = [
            'total' => count($contactMessages),
            'unread' => 0,
            'read' => 0,
        ];

        foreach ($contactMessages as $contactMessage) {
            $isRead = $contactMessage->isRead();

            if ($isRead) {
                $stats['read']++;
            } else {
                $stats['unread']++;
            }

            $messages[] = [
                'id' => $contactMessage->getId(),
                'name' => $contactMessage->getName(),
                'email' => $contactMessage->getEmail(),
                'profilePicture' => $contactMessage->getUser()?->getProfilePicture(),
                'subject' => match ($contactMessage->getSubject()) {
                    'delivery' => 'Livraison',
                    'order' => 'Commande',
                    'other' => 'Autre',
                    default => $contactMessage->getSubject() ?? 'Sans sujet',
                },
                'date' => $contactMessage->getCreatedAt()->format('d/m/Y'),
                'status' => $isRead ? 'Lu' : 'Non lu',
                'isRead' => $isRead,
                'message' => $contactMessage->getMessage(),
                'replies' => $contactMessage->getMessageReplies(),
            ];
        }

        return $this->render('admin/message/index.html.twig', [
            'messages' => $messages,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/reply', name: 'reply', methods: ['POST'])]
    public function reply(
        ContactMessage $contactMessage,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'reply_' . $contactMessage->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Action non autorisée.'
            );
        }

        // Vérifier que l'administrateur est bien connecté.
        $admin = $this->getUser();

        if (!$admin instanceof User) {
            throw $this->createAccessDeniedException(
                'Administrateur non autorisé.'
            );
        }

        // Récupérer et nettoyer la réponse.
        $content = trim((string) $request->request->get('content'));

        if ($content === '') {
            $this->addFlash(
                'error',
                'Veuillez saisir votre réponse avant de l\'envoyer.'
            );

            return $this->redirectToRoute('admin_messages_index');
        }

        // CrÃ©er une nouvelle rÃ©ponse dans la conversation.
        $messageReply = new MessageReply();
        $messageReply->setContactMessage($contactMessage);
        $messageReply->setSender($admin);
        $messageReply->setContent($content);

        $contactMessage->addMessageReply($messageReply);
        $contactMessage->setIsRead(true);

        $entityManager->persist($messageReply);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Votre réponse a été enregistrée avec succès.'
        );

        return $this->redirectToRoute('admin_messages_index');
    }

    #[Route('/{id}/toggle-read', name: 'toggle_read', methods: ['POST'])]
    public function toggleRead(
        ContactMessage $contactMessage,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'toggle_read_' . $contactMessage->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Action non autorisée.'
            );
        }

        // Inverser le statut du message.
        $contactMessage->setIsRead(!$contactMessage->isRead());

        $entityManager->flush();

        $this->addFlash(
            'success',
            $contactMessage->isRead()
                ? 'Le message a été marqué comme lu.'
                : 'Le message a été marqué comme non lu.'
        );

        return $this->redirectToRoute('admin_messages_index');
    }
}


<?php

namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsletterController extends AbstractController
{
    #[Route('/newsletter', name: 'app_newsletter', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriberRepository $newsletterRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('newsletter', $request->request->get('_token'))) {
            $this->addFlash('error', 'Une erreur est survenue. Veuillez réessayer.');

            return $this->redirectToRoute('home');
        }

        // Récupération et validation de l'adresse e-mail
        $email = trim((string) $request->request->get('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Veuillez saisir une adresse e-mail valide.');

            return $this->redirectToRoute('home');
        }

        // Vérification de l'existence de l'abonné
        $subscriber = $newsletterRepository->findOneByEmail($email);

        if ($subscriber !== null) {
            if ($subscriber->isActive()) {
                $this->addFlash('info', 'Cette adresse e-mail est déjà inscrite à notre newsletter.');

                return $this->redirectToRoute('home');
            }

            // Réactivation d'un ancien abonnement
            $subscriber->setIsActive(true);
            $subscriber->setSubscribedAt(new \DateTimeImmutable());

            $this->addFlash('success', 'Votre inscription à la newsletter a été réactivée.');
        } else {
            // Création d'un nouvel abonnement
            $subscriber = new NewsletterSubscriber();
            $subscriber->setEmail($email);
            $subscriber->setSubscribedAt(new \DateTimeImmutable());
            $subscriber->setIsActive(true);

            $entityManager->persist($subscriber);

            $this->addFlash('success', 'Merci ! Votre inscription à la newsletter a bien été prise en compte.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('home');
    }
}
<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReviewController extends AbstractController
{
    // Enregistrer un avis sur un produit.
    #[Route('/product/{slug}/reviews', name: 'app_review_add', methods: ['POST'])]
    public function add(
        string $slug,
        Request $request,
        ProductRepository $productRepository,
        ReviewRepository $reviewRepository,
        EntityManagerInterface $entityManager
    ): Response {

        // Récupérer le produit actif.
        $product = $productRepository->findOneBy([
            'slug' => $slug,
            'isActive' => true,
        ]);

        if (!$product) {
            throw $this->createNotFoundException(
                'Le produit demandé n’existe pas ou n’est pas publié.'
            );
        }

        // Vérifier que le client est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                'Connectez-vous pour laisser un avis.'
            );

            return $this->redirectToRoute('app_login');
        }

        // Vérifier le token CSRF.
        if (!$this->isCsrfTokenValid(
            'review_add_' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash(
                'error',
                'Le formulaire a expiré. Veuillez réessayer.'
            );

            return $this->redirectToRoute(
                'app_product_detail',
                ['slug' => $slug]
            );
        }

        // Vérifier si le client a déjà laissé un avis.
        $existingReview = $reviewRepository->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);

        if ($existingReview) {
            $this->addFlash(
                'error',
                'Vous avez déjà laissé un avis sur ce produit.'
            );

            return $this->redirectToRoute(
                'app_product_detail',
                ['slug' => $slug]
            );
        }

        // Récupérer les données du formulaire.
        $rating = $request->request->getInt('rating');
        $comment = trim(
            (string) $request->request->get('comment', '')
        );

        // Vérifier la note.
        if ($rating < 1 || $rating > 5) {
            $this->addFlash(
                'error',
                'Veuillez choisir une note entre 1 et 5 étoiles.'
            );

            return $this->redirectToRoute(
                'app_product_detail',
                ['slug' => $slug]
            );
        }

        // Vérifier le commentaire.
        if ($comment === '') {
            $this->addFlash(
                'error',
                'Veuillez écrire un commentaire.'
            );

            return $this->redirectToRoute(
                'app_product_detail',
                ['slug' => $slug]
            );
        }

        // Créer et enregistrer le nouvel avis.
        $review = new Review();

        $review
            ->setUser($user)
            ->setProduct($product)
            ->setRating($rating)
            ->setComment($comment);

        $entityManager->persist($review);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Votre avis a été envoyé avec succès.'
        );

        return $this->redirectToRoute(
            'app_product_detail',
            ['slug' => $slug]
        );
    }
}

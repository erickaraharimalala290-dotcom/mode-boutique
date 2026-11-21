<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/avis', name: 'admin_reviews_')]
#[IsGranted('ROLE_MANAGER')]
class ReviewCrudController extends AbstractController
{
    // Afficher tous les avis enregistrés.
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ReviewRepository $reviewRepository
    ): Response {

        // Récupérer les avis du plus récent au plus ancien.
        $reviews = $reviewRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        // Calculer les statistiques des avis.
        $totalReviews = count($reviews);
        $totalRatings = 0;
        $fiveStars = 0;
        $lowRatings = 0;

        foreach ($reviews as $review) {
            $rating = $review->getRating() ?? 0;

            $totalRatings += $rating;

            if ($rating === 5) {
                $fiveStars++;
            }

            if ($rating <= 2) {
                $lowRatings++;
            }
        }

        // Calculer la note moyenne.
        $averageRating = $totalReviews > 0
            ? round($totalRatings / $totalReviews, 1)
            : 0;

        return $this->render('admin/review/index.html.twig', [
            'reviews' => $reviews,
            'stats' => [
                'total' => $totalReviews,
                'average' => $averageRating,
                'fiveStars' => $fiveStars,
                'lowRatings' => $lowRatings,
            ],
        ]);
    }

    // Supprimer un avis.
    #[Route(
        '/{id}/supprimer',
        name: 'delete',
        methods: ['POST']
    )]
    public function delete(
        Review $review,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        // Vérifier le token CSRF.
        if (!$this->isCsrfTokenValid(
            'delete_review_' . $review->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash(
                'error',
                'La requête a expiré. Veuillez réessayer.'
            );

            return $this->redirectToRoute('admin_reviews_index');
        }

        // Supprimer l'avis de la base de données.
        $entityManager->remove($review);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Lâ€™avis a été supprimé avec succès.'
        );

        return $this->redirectToRoute('admin_reviews_index');
    }
}


<?php

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/favoris', name: 'admin_favorites_')]
#[IsGranted('ROLE_MANAGER')]
class FavoriteCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        // Récupérer les produits et compter leurs favoris.
        $results = $productRepository
            ->createQueryBuilder('p')
            ->select('p AS product, COUNT(f.id) AS favoritesCount')
            ->innerJoin('p.favorites', 'f')
            ->groupBy('p.id')
            ->orderBy('favoritesCount', 'DESC')
            ->getQuery()
            ->getResult();

        // Préparer les données pour le tableau.
        $favorites = [];
        $totalFavorites = 0;
        $topFavorites = 0;

        foreach ($results as $result) {
            $count = (int) $result['favoritesCount'];

            $favorites[] = [
                'product' => $result['product'],
                'count' => $count,
            ];

            $totalFavorites += $count;
            $topFavorites = max($topFavorites, $count);
        }

        return $this->render('admin/favorite/index.html.twig', [
            'favorites' => $favorites,
            'stats' => [
                'products' => count($favorites),
                'total' => $totalFavorites,
                'top' => $topFavorites,
            ],
        ]);
    }
}


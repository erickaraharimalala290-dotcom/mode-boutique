<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\ProductPromotionCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        ProductPromotionCalculator $promotionCalculator
    ): Response {
        // Récupérer les filtres envoyés dans l'URL.
        $genre = $request->query->get('genre');
        $type = $request->query->get('type');
        $sousCategorie = $request->query->get('sousCategorie');

        // Valeurs autorisées.
        $genresAutorises = ['femme', 'homme'];

        $typesAutorises = [
            'vetements',
            'chaussures',
            'accessoires',
        ];

        $sousCategoriesAutorisees = [
            'robes',
            'pantalons',
            'sweats',
            'tops',
            'chemises',
            't-shirts',
            'sneakers',
            'talons',
            'sandales',
            'chaussures-habillees',
            'sacs',
            'bijoux',
            'ceintures',
            'montres',
        ];

        // Vérifier les paramètres reçus.
        if (!in_array($genre, $genresAutorises, true)) {
            $genre = null;
        }

        if (!in_array($type, $typesAutorises, true)) {
            $type = null;
        }

        if (!in_array($sousCategorie, $sousCategoriesAutorisees, true)) {
            $sousCategorie = null;
        }

        // Récupérer les produits publiés.
        $products = $productRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC']
        );

        // Calculer les prix promotionnels.
        $productPricing = [];

        foreach ($products as $product) {
            $productPricing[$product->getId()] =
                $promotionCalculator->calculate($product);
        }

        return $this->render('pages/shop/index.html.twig', [
            'products' => $products,
            'currentGenre' => $genre,
            'currentType' => $type,
            'currentSubcategory' => $sousCategorie,
            'productPricing' => $productPricing,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ProductPromotionCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ProductPromotionCalculator $promotionCalculator
    ): Response {
        // Catégories principales : Femme et Homme
        $categories = $categoryRepository->createQueryBuilder('c')
            ->where('c.slug IN (:slugs)')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.parent IS NULL')
            ->setParameter('slugs', ['femme', 'homme'])
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Les 4 produits actifs les plus vendus.
        $featuredProducts = $productRepository->findBestSellingProducts(4);

        // Récupérer les identifiants des produits populaires.
        $featuredProductIds = array_map(
            fn ($product) => $product->getId(),
            $featuredProducts
        );

        // Récupérer la couleur du variant le plus vendu pour chaque produit.
        $bestVariantColors = $productRepository->findBestSellingVariantColors(
            $featuredProductIds
        );

        // Déterminer l'image à afficher pour chaque produit populaire.
        $featuredProductImages = [];

        foreach ($featuredProducts as $product) {
            $productId = $product->getId();
            $selectedImage = null;

            $bestColor = $bestVariantColors[$productId] ?? null;

            if ($bestColor !== null) {
                $matchingImages = [];

                foreach ($product->getImages() as $productImage) {
                    if (
                        $productImage->getColor() !== null
                        && mb_strtolower(trim($productImage->getColor()))
                            === mb_strtolower(trim($bestColor))
                    ) {
                        $matchingImages[] = $productImage;
                    }
                }

                // Privilégier l'image principale, puis l'ordre d'affichage.
                usort($matchingImages, function ($a, $b) {
                    if ($a->isPrimary() !== $b->isPrimary()) {
                        return $b->isPrimary() <=> $a->isPrimary();
                    }

                    return $a->getSortOrder() <=> $b->getSortOrder();
                });

                if (!empty($matchingImages)) {
                    $selectedImage = $matchingImages[0]->getImagePath();
                }
            }

            // Utiliser l'image principale du produit si nécessaire.
            $featuredProductImages[$productId] = $selectedImage
                ?: $product->getMainImage();
        }

        // Les 4 produits publiés ajoutés le plus récemment.
        $latestProducts = $productRepository->findBy(
            [
                'isActive' => true,
            ],
            [
                'createdAt' => 'DESC',
            ],
            4
        );

        // Calculer les prix promotionnels des produits affichés.
        $productPricing = [];

        foreach (array_merge($featuredProducts, $latestProducts) as $product) {
            $productId = $product->getId();

            if (!isset($productPricing[$productId])) {
                $productPricing[$productId] = $promotionCalculator->calculate($product);
            }
        }

        return $this->render('pages/home/index.html.twig', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'featuredProductImages' => $featuredProductImages,
            'latestProducts' => $latestProducts,
            'productPricing' => $productPricing,
        ]);
    }
}

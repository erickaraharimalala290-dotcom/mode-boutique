<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\ProductPromotionCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PromotionController extends AbstractController
{
    #[Route('/promotions', name: 'app_promotions', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        ProductPromotionCalculator $promotionCalculator
    ): Response {
        // Récupérer les produits actifs.
        $products = $productRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC']
        );

        $promotedProducts = [];
        $productPricing = [];

        // Garder uniquement les produits ayant une promotion active.
        foreach ($products as $product) {
            $pricing = $promotionCalculator->calculate($product);

            if ($pricing['hasPromotion'] ?? false) {
                $promotedProducts[] = $product;
                $productPricing[$product->getId()] = $pricing;
            }
        }

        return $this->render('pages/promotion/index.html.twig', [
            'promotedProducts' => $promotedProducts,
            'productPricing' => $productPricing,
        ]);
    }
}

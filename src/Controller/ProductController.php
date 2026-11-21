<?php

namespace App\Controller;

use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Service\ProductPromotionCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/product', name: 'app_product')]
    public function index(ProductRepository $productRepository): Response
    {
        // Récupérer le dernier produit actif.
        $product = $productRepository->findOneBy(
            ['isActive' => true],
            ['createdAt' => 'DESC']
        );

        if (!$product) {
            throw $this->createNotFoundException(
                'Aucun produit publié n’est disponible.'
            );
        }

        return $this->redirectToRoute('app_product_detail', [
            'slug' => $product->getSlug(),
        ]);
    }

    #[Route('/product/{slug}', name: 'app_product_detail')]
    public function show(
        string $slug,
        ProductRepository $productRepository,
        ReviewRepository $reviewRepository,
        ProductPromotionCalculator $promotionCalculator
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

        // Calculer le meilleur prix promotionnel applicable.
        $pricing = $promotionCalculator->calculate($product);

        // Préparer le chemin public des images.
        $getImageUrl = static function (?string $image): ?string {
            if (!$image) {
                return null;
            }

            if (
                str_starts_with($image, 'http://')
                || str_starts_with($image, 'https://')
                || str_starts_with($image, '/')
            ) {
                return $image;
            }

            if (
                str_starts_with($image, 'uploads/')
                || str_starts_with($image, 'images/')
            ) {
                return '/' . $image;
            }

            return '/images/products/' . $image;
        };

        // Récupérer l'image principale.
        $mainImage = $getImageUrl($product->getMainImage());

        // Récupérer les images supplémentaires et les classer par couleur.
        $images = [];
        $colorImages = [];

        $productImages = $product->getImages()->toArray();

        usort(
            $productImages,
            static fn(ProductImage $a, ProductImage $b): int =>
            $a->getSortOrder() <=> $b->getSortOrder()
        );

        foreach ($productImages as $productImage) {
            $imageUrl = $getImageUrl($productImage->getImagePath());

            if (!$imageUrl) {
                continue;
            }

            $images[] = [
                'path' => $imageUrl,
                'color' => $productImage->getColor(),
                'alt' => $productImage->getAltText() ?: $product->getName(),
                'primary' => $productImage->isPrimary(),
            ];

            $color = $productImage->getColor();

            if ($color && !isset($colorImages[$color])) {
                $colorImages[$color] = $imageUrl;
            }

            if ($productImage->isPrimary()) {
                $mainImage = $imageUrl;
            }
        }

        // Préparer les couleurs, les tailles et les pointures.
        $colors = [];
        $sizes = [];
        $shoeSizes = [];
        $variants = [];

        foreach ($product->getVariants() as $variant) {
            // Ignorer les variantes désactivées.
            if (!$variant->isActive()) {
                continue;
            }

            $readValue = static function (
                object $variant,
                array $methods
            ): ?string {
                foreach ($methods as $method) {
                    if (!method_exists($variant, $method)) {
                        continue;
                    }

                    $value = $variant->$method();

                    if (is_scalar($value) && $value !== '') {
                        return (string) $value;
                    }

                    if (is_object($value) && method_exists($value, 'getName')) {
                        return (string) $value->getName();
                    }
                }

                return null;
            };

            $color = $readValue($variant, ['getColor']);
            $size = $readValue($variant, ['getSize', 'getTaille']);
            $shoeSize = $readValue(
                $variant,
                ['getShoeSize', 'getPointure']
            );

            // La taille ou la pointure de la variante.
            $selectedSize = $shoeSize ?? $size;

            if ($color !== null) {
                $colors[] = $color;
            }

            if ($size !== null) {
                $sizes[] = $size;
            }

            if ($shoeSize !== null) {
                $shoeSizes[] = $shoeSize;
            }

            // Envoyer les informations de stock au JavaScript.
            $variants[] = [
                'id' => $variant->getId(),
                'color' => $color,
                'size' => $selectedSize,
                'stockQuantity' => $variant->getStockQuantity(),
                'lowStockThreshold' => $variant->getLowStockThreshold(),
            ];
        }


        // Supprimer les valeurs répétées.
        $colors = array_values(array_unique($colors));
        $sizes = array_values(array_unique($sizes));
        $shoeSizes = array_values(array_unique($shoeSizes));

        // Récupérer le slug de la catégorie du produit.
        $categorySlug = $product->getCategory()?->getSlug();


        // Vérifier si le produit appartient à une catégorie de chaussures.
        $isShoe = preg_match(
            '/^(chaussure|sneakers|talons|botte|sandale|escarpins)(-|$)/',
            $categorySlug ?? ''
        ) === 1;


        // Déterminer le type de variante selon la catégorie du produit.
        if ($isShoe) {
            $variantType = 'shoe_size';

            // Les pointures sont stockées dans le champ "size".
            $sizes = $shoeSizes ?: $sizes;
        } elseif (!empty($sizes)) {
            $variantType = 'size';
        } else {
            $variantType = 'none';
        }


        // Déterminer le badge du produit.
        $badge = null;

        if ($pricing['hasPromotion'] || $product->getOldPrice() !== null) {
            $badge = 'Promotion';
        } elseif (
            $product->getCreatedAt() !== null
            && $product->getCreatedAt() >= new \DateTimeImmutable('-30 days')
        ) {
            $badge = 'Nouveau';
        }

        // Préparer les données à envoyer au template.
        $productData = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'brand' => $product->getBrand(),
            'category' => $product->getCategory()
                ? $product->getCategory()->getName()
                : 'Sans catégorie',
            'price' => number_format(
                $pricing['price'],
                0,
                ',',
                ' '
            ) . ' Ar',
            'oldPrice' => $pricing['oldPrice'] !== null
                ? number_format(
                    $pricing['oldPrice'],
                    0,
                    ',',
                    ' '
                ) . ' Ar'
                : null,
            'discountBadge' => $pricing['discountBadge'],
            'badge' => $badge,
            'image' => $mainImage,
            'images' => $images,
            'colorImages' => $colorImages,
            'description' => $product->getDescription(),
            'colors' => $colors,
            'sizes' => $sizes,
            'variantType' => $variantType,
            'variants' => $variants,
        ];

        // Récupérer les avis du produit.
        $reviews = $reviewRepository->findBy(
            ['product' => $product],
            ['createdAt' => 'DESC']
        );

        // Calculer le nombre et la moyenne des avis.
        $reviewsCount = count($reviews);

        $averageRating = $reviewsCount > 0
            ? array_sum(array_map(
                static fn($review) => $review->getRating() ?? 0,
                $reviews
            )) / $reviewsCount
            : 0;

        return $this->render('pages/product/index.html.twig', [
            'product' => $productData,
            'slug' => $product->getSlug(),

            // Avis du produit.
            'reviews' => $reviews,
            'reviewsCount' => $reviewsCount,
            'averageRating' => $averageRating,
        ]);
    }
}

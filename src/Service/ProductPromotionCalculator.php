<?php

namespace App\Service;

use App\Entity\Product;

class ProductPromotionCalculator
{
    /**
     * Calculer le meilleur prix promotionnel d'un produit.
     *
     * Les promotions ne sont jamais cumulées.
     * La réduction la plus avantageuse est sélectionnée.
     */
    public function calculate(Product $product, ?float $basePrice = null): array
    {
        $now = new \DateTimeImmutable();

        $originalPrice = $basePrice ?? (float) $product->getPrice();
        $bestDiscount = 0.0;

        foreach ($product->getPromotions() as $promotion) {
            // Ignorer les promotions désactivées.
            if (!$promotion->isActive()) {
                continue;
            }

            // Vérifier les dates de validité.
            $today = $now->format('Y-m-d');

            if (
                $promotion->getStartsAt() !== null
                && $promotion->getStartsAt()->format('Y-m-d') > $today
            ) {
                continue;
            }

            if (
                $promotion->getEndsAt() === null
                || $promotion->getEndsAt()->format('Y-m-d') < $today
            ) {
                continue;
            }

            $discountValue = (float) $promotion->getDiscountValue();

            // Calculer la réduction selon son type.
            if ($promotion->getDiscountType() === 'percentage') {
                $discount = $originalPrice * ($discountValue / 100);
            } elseif ($promotion->getDiscountType() === 'fixed') {
                $discount = $discountValue;
            } else {
                continue;
            }

            // Une réduction ne peut pas dépasser le prix du produit.
            $discount = min($discount, $originalPrice);

            // Garder uniquement la meilleure réduction.
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
            }
        }

        // Une promotion valide a été trouvée.
        if ($bestDiscount > 0) {
            $discountedPrice = max(0, $originalPrice - $bestDiscount);

            $discountPercentage = $originalPrice > 0
                ? (int) round(($bestDiscount / $originalPrice) * 100)
                : 0;

            return [
                'price' => $discountedPrice,
                'oldPrice' => $originalPrice,
                'discountBadge' => '-' . $discountPercentage . ' %',
                'hasPromotion' => true,
            ];
        }

        // Conserver l'ancien prix manuel si aucune promotion n'est active.
        $manualOldPrice = $product->getOldPrice();

        return [
            'price' => $originalPrice,
            'oldPrice' => $basePrice === null && $manualOldPrice !== null
                ? (float) $manualOldPrice
                : null,
            'discountBadge' => null,
            'hasPromotion' => false,
        ];
    }
}

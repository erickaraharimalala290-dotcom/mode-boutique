<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/stock', name: 'admin_stock_')]
#[IsGranted('ROLE_MANAGER')]
class StockCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ProductVariantRepository $variantRepository
    ): Response {
        // Récupération des variantes actives depuis la base de données.
        $variants = $variantRepository->findAllForStock();

        // Initialisation des statistiques.
        $stats = [
            'products' => 0,
            'units' => 0,
            'low' => 0,
            'out' => 0,
        ];

        // Comptage des produits distincts.
        $products = [];

        foreach ($variants as $variant) {
            $product = $variant->getProduct();
            $quantity = $variant->getStockQuantity();
            $threshold = $variant->getLowStockThreshold();

            $products[$product->getId()] = true;
            $stats['units'] += $quantity;

            if ($quantity === 0) {
                $stats['out']++;
            } elseif ($quantity <= $threshold) {
                $stats['low']++;
            }
        }

        $stats['products'] = count($products);

        return $this->render('admin/stock/index.html.twig', [
            'variants' => $variants,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/reapprovisionner', name: 'reapprovisionner', methods: ['GET', 'POST'])]
    public function reapprovisionner(
        ProductVariant $variant,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            // Vérification du jeton CSRF.
            if (!$this->isCsrfTokenValid(
                'reapprovisionner' . $variant->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
            }

            // Récupération de la quantité à ajouter.
            $quantityToAdd = filter_var(
                $request->request->get('quantity'),
                FILTER_VALIDATE_INT
            );

            if ($quantityToAdd === false || $quantityToAdd <= 0) {
                $this->addFlash('error', 'Veuillez saisir une quantité supérieure à zéro.');

                return $this->redirectToRoute('admin_stock_reapprovisionner', [
                    'id' => $variant->getId(),
                ]);
            }

            // Ajout de la nouvelle quantité au stock actuel.
            $newQuantity = $variant->getStockQuantity() + $quantityToAdd;
            $variant->setStockQuantity($newQuantity);

            $entityManager->flush();

            $this->addFlash('success', 'Le stock a été réapprovisionné avec succès.');

            return $this->redirectToRoute('admin_stock_index');
        }

        return $this->render('admin/stock/reapprovisionner.html.twig', [
            'variant' => $variant,
        ]);
    }
}


<?php

namespace App\Controller\Admin;

use App\Entity\Promotion;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/promotions', name: 'admin_promotions_')]
#[IsGranted('ROLE_MANAGER')]
class PromotionCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PromotionRepository $promotionRepository): Response
    {
        $promotions = $promotionRepository->findAll();

        $today = new \DateTimeImmutable('today');

        $stats = [
            'total' => count($promotions),
            'active' => 0,
            'expired' => 0,
            'uses' => 0,
        ];

        $promotionList = [];

        foreach ($promotions as $promotion) {
            $usage = $promotion->getCustomerOrders()->count();
            $limit = $promotion->getUsageLimit();

            if ($promotion->getEndsAt() < $today) {
                $status = 'ExpirÃ©e';
            } elseif (!$promotion->isActive()) {
                $status = 'Inactive';
            } elseif (
                $promotion->getStartsAt() !== null
                && $promotion->getStartsAt() > $today
            ) {
                $status = 'Ã€ venir';
            } else {
                $status = 'Active';
            }

            if ($status === 'Active') {
                $stats['active']++;
            }

            if ($status === 'ExpirÃ©e') {
                $stats['expired']++;
            }

            $stats['uses'] += $usage;

            $promotionList[] = [
                'id' => $promotion->getId(),
                'name' => $promotion->getName(),
                'code' => $promotion->getCode(),
                'type' => $promotion->getDiscountType() === 'percentage'
                    ? 'Pourcentage'
                    : 'Montant fixe',
                'discount' => $promotion->getDiscountType() === 'percentage'
                    ? $promotion->getDiscountValue() . ' %'
                    : number_format((float) $promotion->getDiscountValue(), 0, ',', ' ') . ' Ar',
                'start' => $promotion->getStartsAt()?->format('d/m/Y') ?? 'â€”',
                'end' => $promotion->getEndsAt()->format('d/m/Y'),
                'usage' => $usage,
                'limit' => $limit,
                'status' => $status,
                'usagePercent' => $limit !== null && $limit > 0
                    ? min(100, ($usage / $limit) * 100)
                    : 0,
            ];
        }

        return $this->render('admin/promotion/index.html.twig', [
            'promotions' => $promotionList,
            'stats' => $stats,
        ]);
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        PromotionRepository $promotionRepository,
        ProductRepository $productRepository
    ): Response {
        $values = [
            'name' => '',
            'code' => '',
            'discount_type' => 'percentage',
            'discount_value' => '',
            'start_date' => '',
            'end_date' => '',
            'usage_limit' => '',
            'status' => 'active',
        ];

        $fieldErrors = [];
        $globalErrors = [];

        $selectedProductIds = [];

        if ($request->isMethod('POST')) {
            foreach ($values as $key => $default) {
                $values[$key] = trim((string) $request->request->get($key, $default));
            }

            if (!$this->isCsrfTokenValid(
                'create_promotion',
                $request->request->get('_token')
            )) {
                $globalErrors[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $name = $values['name'];
            $code = strtoupper($values['code']);
            $discountType = $values['discount_type'];
            $discountValue = $values['discount_value'];
            $startDate = $values['start_date'];
            $endDate = $values['end_date'];
            $usageLimit = $values['usage_limit'];

            // RÃ©cupÃ©ration des produits sÃ©lectionnÃ©s
            $selectedProducts = $this->getSelectedProducts(
                $request,
                $productRepository,
                $fieldErrors,
                $selectedProductIds
            );

            // Validation du nom
            if ($name === '') {
                $fieldErrors['name'][] = 'Le nom de la promotion est obligatoire.';
            } elseif (mb_strlen($name) > 150) {
                $fieldErrors['name'][] = 'Le nom ne doit pas dépasser 150 caractères.';
            }

            // Validation du code promotionnel
            if ($code === '') {
                $fieldErrors['code'][] = 'Le code promotionnel est obligatoire.';
            } elseif (mb_strlen($code) > 50) {
                $fieldErrors['code'][] = 'Le code ne doit pas dépasser 50 caractères.';
            } elseif ($promotionRepository->findOneBy(['code' => $code]) !== null) {
                $fieldErrors['code'][] = 'Ce code promotionnel existe déjà.';
            }

            // Validation du type de réduction
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                $fieldErrors['discount_type'][] = 'Veuillez sélectionner un type de réduction valide.';
            }

            // Validation de la valeur de réduction
            if ($discountValue === '') {
                $fieldErrors['discount_value'][] = 'La valeur de la réduction est obligatoire.';
            } elseif (!is_numeric($discountValue) || (float) $discountValue <= 0) {
                $fieldErrors['discount_value'][] = 'La valeur doit être un nombre supérieur à zéro.';
            } elseif (
                $discountType === 'percentage'
                && (float) $discountValue > 100
            ) {
                $fieldErrors['discount_value'][] = 'Une réduction en pourcentage ne peut pas dépasser 100 %.';
            }

            // Validation de la date de début
            $start = null;

            if ($startDate === '') {
                $fieldErrors['start_date'][] = 'La date de début est obligatoire.';
            } else {
                try {
                    $start = new \DateTimeImmutable($startDate);

                    if ($start->format('Y-m-d') !== $startDate) {
                        $fieldErrors['start_date'][] = 'La date de début est invalide.';
                        $start = null;
                    }
                } catch (\Exception) {
                    $fieldErrors['start_date'][] = 'Veuillez saisir une date de début valide.';
                }
            }

            // Validation de la date de fin
            $end = null;

            if ($endDate === '') {
                $fieldErrors['end_date'][] = 'La date de fin est obligatoire.';
            } else {
                try {
                    $end = new \DateTimeImmutable($endDate);

                    if ($end->format('Y-m-d') !== $endDate) {
                        $fieldErrors['end_date'][] = 'La date de fin est invalide.';
                        $end = null;
                    }
                } catch (\Exception) {
                    $fieldErrors['end_date'][] = 'Veuillez saisir une date de fin valide.';
                }
            }

            if ($start !== null && $end !== null && $end < $start) {
                $fieldErrors['end_date'][] = 'La date de fin doit être postérieure ou égale à la date de début.';
            }

            // Validation de la limite d'utilisation
            if (
                $usageLimit !== ''
                && (!ctype_digit($usageLimit) || (int) $usageLimit < 1)
            ) {
                $fieldErrors['usage_limit'][] = 'La limite doit être un nombre entier supérieur à zéro.';
            }

            // Validation du statut
            if (!in_array($values['status'], ['active', 'inactive'], true)) {
                $fieldErrors['status'][] = 'Veuillez sélectionner un statut valide.';
            }

            // Enregistrement si aucune erreur
            if (empty($fieldErrors) && empty($globalErrors)) {
                $promotion = new Promotion();

                $promotion
                    ->setName($name)
                    ->setCode($code)
                    ->setDiscountType($discountType)
                    ->setDiscountValue(number_format((float) $discountValue, 2, '.', ''))
                    ->setStartsAt($start)
                    ->setEndsAt($end)
                    ->setUsageLimit($usageLimit !== '' ? (int) $usageLimit : null)
                    ->setIsActive($values['status'] === 'active');

                foreach ($selectedProducts as $product) {
                    $promotion->addProduct($product);
                }

                $entityManager->persist($promotion);
                $entityManager->flush();

                $this->addFlash('success', 'La promotion a été créée avec succès.');

                return $this->redirectToRoute('admin_promotions_index');
            }
        }

        return $this->render('admin/promotion/new.html.twig', [
            'values' => $values,
            'fieldErrors' => $fieldErrors,
            'globalErrors' => $globalErrors,
            'products' => $productRepository->findBy(
                ['isActive' => true],
                ['name' => 'ASC']
            ),
            'selectedProductIds' => $selectedProductIds,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Promotion $promotion,
        Request $request,
        EntityManagerInterface $entityManager,
        PromotionRepository $promotionRepository,
        ProductRepository $productRepository
    ): Response {
        $values = [
            'name' => $promotion->getName() ?? '',
            'code' => $promotion->getCode() ?? '',
            'discount_type' => $promotion->getDiscountType() ?? 'percentage',
            'discount_value' => $promotion->getDiscountValue() ?? '',
            'start_date' => $promotion->getStartsAt()?->format('Y-m-d') ?? '',
            'end_date' => $promotion->getEndsAt()?->format('Y-m-d') ?? '',
            'usage_limit' => $promotion->getUsageLimit() !== null
                ? (string) $promotion->getUsageLimit()
                : '',
            'status' => $promotion->isActive() ? 'active' : 'inactive',
        ];

        $fieldErrors = [];
        $globalErrors = [];

        $selectedProductIds = [];

        // Produits déjà associés à la promotion
        foreach ($promotion->getProducts() as $product) {
            $selectedProductIds[] = $product->getId();
        }

        if ($request->isMethod('POST')) {
            foreach ($values as $key => $default) {
                $values[$key] = trim((string) $request->request->get($key, $default));
            }

            if (!$this->isCsrfTokenValid(
                'edit_promotion_' . $promotion->getId(),
                $request->request->get('_token')
            )) {
                $globalErrors[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            $name = $values['name'];
            $code = strtoupper($values['code']);
            $discountType = $values['discount_type'];
            $discountValue = $values['discount_value'];
            $startDate = $values['start_date'];
            $endDate = $values['end_date'];
            $usageLimit = $values['usage_limit'];

            // Récupération des produits sélectionnés
            $selectedProducts = $this->getSelectedProducts(
                $request,
                $productRepository,
                $fieldErrors,
                $selectedProductIds
            );

            // Validation du nom
            if ($name === '') {
                $fieldErrors['name'][] = 'Le nom de la promotion est obligatoire.';
            } elseif (mb_strlen($name) > 150) {
                $fieldErrors['name'][] = 'Le nom ne doit pas dépasser 150 caractères.';
            }

            // Validation du code promotionnel
            if ($code === '') {
                $fieldErrors['code'][] = 'Le code promotionnel est obligatoire.';
            } elseif (mb_strlen($code) > 50) {
                $fieldErrors['code'][] = 'Le code ne doit pas dépasser 50 caractères.';
            } else {
                $existingPromotion = $promotionRepository->findOneBy(['code' => $code]);

                if (
                    $existingPromotion !== null
                    && $existingPromotion->getId() !== $promotion->getId()
                ) {
                    $fieldErrors['code'][] = 'Ce code promotionnel existe déjà.';
                }
            }

            // Validation du type de réduction
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                $fieldErrors['discount_type'][] = 'Veuillez sélectionner un type de réduction valide.';
            }

            // Validation de la valeur
            if ($discountValue === '') {
                $fieldErrors['discount_value'][] = 'La valeur de la réduction est obligatoire.';
            } elseif (!is_numeric($discountValue) || (float) $discountValue <= 0) {
                $fieldErrors['discount_value'][] = 'La valeur doit être un nombre supérieur à zéro.';
            } elseif (
                $discountType === 'percentage'
                && (float) $discountValue > 100
            ) {
                $fieldErrors['discount_value'][] = 'Une réduction en pourcentage ne peut pas dépasser 100 %.';
            }

            // Validation de la date de début
            $start = null;

            if ($startDate === '') {
                $fieldErrors['start_date'][] = 'La date de début est obligatoire.';
            } else {
                try {
                    $start = new \DateTimeImmutable($startDate);

                    if ($start->format('Y-m-d') !== $startDate) {
                        $fieldErrors['start_date'][] = 'La date de début est invalide.';
                        $start = null;
                    }
                } catch (\Exception) {
                    $fieldErrors['start_date'][] = 'Veuillez saisir une date de début valide.';
                }
            }

            // Validation de la date de fin
            $end = null;

            if ($endDate === '') {
                $fieldErrors['end_date'][] = 'La date de fin est obligatoire.';
            } else {
                try {
                    $end = new \DateTimeImmutable($endDate);

                    if ($end->format('Y-m-d') !== $endDate) {
                        $fieldErrors['end_date'][] = 'La date de fin est invalide.';
                        $end = null;
                    }
                } catch (\Exception) {
                    $fieldErrors['end_date'][] = 'Veuillez saisir une date de fin valide.';
                }
            }

            if ($start !== null && $end !== null && $end < $start) {
                $fieldErrors['end_date'][] = 'La date de fin doit être postérieure ou égale à la date de début.';
            }

            // Validation de la limite d'utilisation
            if (
                $usageLimit !== ''
                && (!ctype_digit($usageLimit) || (int) $usageLimit < 1)
            ) {
                $fieldErrors['usage_limit'][] = 'La limite doit être un nombre entier supérieur à zéro.';
            }

            // Validation du statut
            if (!in_array($values['status'], ['active', 'inactive'], true)) {
                $fieldErrors['status'][] = 'Veuillez sélectionner un statut valide.';
            }

            // Mise à jour de la promotion
            if (empty($fieldErrors) && empty($globalErrors)) {
                $promotion
                    ->setName($name)
                    ->setCode($code)
                    ->setDiscountType($discountType)
                    ->setDiscountValue(number_format((float) $discountValue, 2, '.', ''))
                    ->setStartsAt($start)
                    ->setEndsAt($end)
                    ->setUsageLimit($usageLimit !== '' ? (int) $usageLimit : null)
                    ->setIsActive($values['status'] === 'active');

                // Remplacement des produits associés
                foreach ($promotion->getProducts()->toArray() as $oldProduct) {
                    $promotion->removeProduct($oldProduct);
                }

                foreach ($selectedProducts as $product) {
                    $promotion->addProduct($product);
                }

                $entityManager->flush();

                $this->addFlash('success', 'La promotion a été modifiée avec succès.');

                return $this->redirectToRoute('admin_promotions_index');
            }
        }

        return $this->render('admin/promotion/edit.html.twig', [
            'promotion' => $promotion,
            'values' => $values,
            'fieldErrors' => $fieldErrors,
            'globalErrors' => $globalErrors,
            'products' => $productRepository->findBy(
                ['isActive' => true],
                ['name' => 'ASC']
            ),
            'selectedProductIds' => $selectedProductIds,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Promotion $promotion,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete_promotion_' . $promotion->getId(),
            $request->request->get('_token')
        )) {
            $this->addFlash('error', 'La demande de suppression est invalide.');

            return $this->redirectToRoute('admin_promotions_index');
        }

        if ($promotion->getCustomerOrders()->count() > 0) {
            $this->addFlash(
                'error',
                'Impossible de supprimer cette promotion car elle est déjà associée à une ou plusieurs commandes.'
            );

            return $this->redirectToRoute('admin_promotions_index');
        }

        $entityManager->remove($promotion);
        $entityManager->flush();

        $this->addFlash('success', 'La promotion a été supprimée avec succès.');

        return $this->redirectToRoute('admin_promotions_index');
    }

    /**
     * Récupère et valide les produits sélectionnés dans le formulaire.
     *
     * @return array<\App\Entity\Product>
     */
    private function getSelectedProducts(
        Request $request,
        ProductRepository $productRepository,
        array &$fieldErrors,
        array &$selectedProductIds
    ): array {
        $submittedData = $request->request->all();
        $submittedIds = $submittedData['products'] ?? [];

        if (!is_array($submittedIds)) {
            $submittedIds = [];
        }

        $submittedIds = array_values(array_unique(array_filter(
            $submittedIds,
            static fn ($id): bool => is_string($id) || is_int($id)
        )));

        $validIds = [];

        foreach ($submittedIds as $id) {
            if (ctype_digit((string) $id) && (int) $id > 0) {
                $validIds[] = (int) $id;
            }
        }

        $validIds = array_values(array_unique($validIds));
        $selectedProductIds = $validIds;

        if (empty($validIds)) {
            $fieldErrors['products'][] = 'Veuillez sélectionner au moins un produit.';

            return [];
        }

        $products = $productRepository->findBy([
            'id' => $validIds,
            'isActive' => true,
        ]);

        if (count($products) !== count($validIds)) {
            $fieldErrors['products'][] = 'Un ou plusieurs produits sélectionnés ne sont pas disponibles.';
        }

        return $products;
    }
}


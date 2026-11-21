<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use App\Entity\ReturnRequest;
use App\Repository\ReturnRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des demandes de retour dans l'espace d'administration.
 */
#[Route('/administration/retours', name: 'admin_return_requests_')]
#[IsGranted('ROLE_MANAGER')]
class ReturnRequestCrudController extends AbstractController
{
    /**
     * Affiche la liste des demandes et les statistiques.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ReturnRequestRepository $returnRequestRepository
    ): Response {

        $returnRequests = $returnRequestRepository->findBy(
            [],
            ['requestedAt' => 'DESC']
        );

        $stats = [
            'total' => count($returnRequests),
            'pending' => 0,
            'approved' => 0,
            'received' => 0,
            'rejected' => 0,
            'completed' => 0,
        ];

        foreach ($returnRequests as $returnRequest) {
            $status = $returnRequest->getStatus();

            if (array_key_exists($status, $stats)) {
                $stats[$status]++;
            }
        }

        return $this->render('admin/return_request/index.html.twig', [
            'returnRequests' => $returnRequests,
            'stats' => $stats,
        ]);
    }

    /**
     * Met à jour le statut et la note administrative.
     */
    #[Route(
        '/{id}/modifier',
        name: 'update',
        methods: ['POST']
    )]
    public function update(
        ReturnRequest $returnRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'update_return_request_' . $returnRequest->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        $currentStatus = $returnRequest->getStatus();
        $newStatus = (string) $request->request->get('status');

        $adminNote = trim(
            (string) $request->request->get('adminNote')
        );

        // Définir les transitions autorisées.
        $allowedTransitions = [
            ReturnRequest::STATUS_PENDING => [
                ReturnRequest::STATUS_APPROVED,
                ReturnRequest::STATUS_REJECTED,
            ],

            ReturnRequest::STATUS_APPROVED => [
                ReturnRequest::STATUS_RECEIVED,
            ],

            ReturnRequest::STATUS_RECEIVED => [
                ReturnRequest::STATUS_COMPLETED,
            ],

            ReturnRequest::STATUS_REJECTED => [],

            ReturnRequest::STATUS_COMPLETED => [],
        ];

        if (!array_key_exists($newStatus, $allowedTransitions)) {
            $this->addFlash(
                'error',
                'Le statut sélectionné est invalide.'
            );

            return $this->redirectToRoute(
                'admin_return_requests_index'
            );
        }

        $sameStatus = $currentStatus === $newStatus;

        if (
            !$sameStatus
            && !in_array(
                $newStatus,
                $allowedTransitions[$currentStatus] ?? [],
                true
            )
        ) {
            $this->addFlash(
                'error',
                'Cette transition de statut n’est pas autorisée.'
            );

            return $this->redirectToRoute(
                'admin_return_requests_index'
            );
        }

        // Préparer les modifications sans enregistrer immédiatement.
        if (!$sameStatus) {

            // Vérifier le traitement d'un échange.
            if (
                $newStatus === ReturnRequest::STATUS_COMPLETED
                && $returnRequest->getType() === 'exchange'
            ) {
                $replacementVariant = $returnRequest->getReplacementVariant();

                if (
                    !$replacementVariant
                    || !$replacementVariant->isActive()
                    || !$replacementVariant->getProduct()->isActive()
                ) {
                    $this->addFlash(
                        'error',
                        'La variante de remplacement n’est plus disponible.'
                    );

                    return $this->redirectToRoute(
                        'admin_return_requests_index'
                    );
                }

                $quantity = $returnRequest->getQuantity();

                // Vérifier la disponibilité du stock.
                if (
                    $quantity <= 0
                    || $replacementVariant->getStockQuantity() < $quantity
                ) {
                    $this->addFlash(
                        'error',
                        'Le stock disponible est insuffisant pour finaliser cet échange.'
                    );

                    return $this->redirectToRoute(
                        'admin_return_requests_index'
                    );
                }

                // Calculer le montant total à régler pour l'échange.
                $priceDifference = (float) $returnRequest->getPriceDifference();
                $exchangeDeliveryFee = (float) $returnRequest->getExchangeDeliveryFee();

                $exchangeTotal = $priceDifference + $exchangeDeliveryFee;

                // Vérifier le paiement si un montant est dû.
                if ($exchangeTotal > 0) {

                    $payment = $entityManager
                        ->getRepository(Payment::class)
                        ->findOneBy([
                            'returnRequest' => $returnRequest,
                        ]);

                    if (
                        !$payment
                        || $payment->getStatus() !== 'paid'
                        || (float) $payment->getAmount() < $exchangeTotal
                    ) {
                        $this->addFlash(
                            'error',
                            'Le paiement complet de la différence de prix et des frais de livraison doit être réglé avant de finaliser cet échange.'
                        );

                        return $this->redirectToRoute(
                            'admin_return_requests_index'
                        );
                    }
                }

                // Déduire le stock une seule fois lors de la finalisation.
                $replacementVariant->setStockQuantity(
                    $replacementVariant->getStockQuantity() - $quantity
                );

                $replacementVariant->setUpdatedAt(
                    new \DateTimeImmutable()
                );
            }

            $returnRequest->setStatus($newStatus);

            if ($newStatus === ReturnRequest::STATUS_RECEIVED) {
                $returnRequest->setReceivedAt(
                    new \DateTimeImmutable()
                );
            }

            if (in_array(
                $newStatus,
                [
                    ReturnRequest::STATUS_REJECTED,
                    ReturnRequest::STATUS_COMPLETED,
                ],
                true
            )) {
                $returnRequest->setProcessedAt(
                    new \DateTimeImmutable()
                );
            }
        }

        // Enregistrer la note administrative.
        $returnRequest->setAdminNote(
            $adminNote !== '' ? $adminNote : null
        );

        $entityManager->flush();

        $this->addFlash(
            'success',
            'La demande de retour a été mise à jour.'
        );

        return $this->redirectToRoute(
            'admin_return_requests_index'
        );
    }
}

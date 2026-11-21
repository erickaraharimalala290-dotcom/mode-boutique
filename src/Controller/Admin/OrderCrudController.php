<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\DBAL\LockMode;
use App\Repository\CustomerOrderRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/administration/commandes', name: 'admin_orders_')]
#[IsGranted('ROLE_MANAGER')]
class OrderCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CustomerOrderRepository $customerOrderRepository
    ): Response {
        // Récupérer toutes les commandes, de la plus récente à la plus ancienne.
        $customerOrders = $customerOrderRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        $statusLabels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
        ];

        $paymentLabels = [
            'cash_on_delivery' => 'Paiement à la livraison',
            'mobile_money_demo' => 'Mobile Money',
        ];

        $orders = [];

        $stats = [
            'total' => count($customerOrders),
            'pending' => 0,
            'processing' => 0,
            'revenue' => 0,
        ];

        foreach ($customerOrders as $order) {
            $status = strtolower($order->getStatus());

            // Récupérer le client associé à la commande.
            $user = $order->getUser();

            $client = $user instanceof User
                ? $user->getEmail()
                : 'Client inconnu';

            // Récupérer le premier paiement de la commande.
            $payment = $order->getPayments()->first() ?: null;

            $paymentStatus = $payment
                ? strtolower($payment->getStatus())
                : 'pending';

            $paymentLabel = $payment
                ? ($paymentLabels[$payment->getMethod()] ?? $payment->getMethod())
                : 'Non renseigné';

            if ($paymentStatus === 'paid') {
                $paymentLabel .= ' — Payé';
                $stats['revenue'] += (float) $payment->getAmount();
            } else {
                $paymentLabel .= ' — En attente';
            }

            // Préparer les données à afficher dans le tableau.
            $orders[] = [
                'reference' => $order->getOrderNumber(),
                'client' => $client,
                'email' => $client,
                'date' => $order->getCreatedAt()->format('d/m/Y'),
                'total' => (float) $order->getTotalAmount(),
                'payment' => $paymentLabel,
                'status' => $statusLabels[$status] ?? ucfirst($status),
                'orderNumber' => $order->getOrderNumber(),
            ];

            // Calculer les statistiques.
            if ($status === 'pending') {
                $stats['pending']++;
            }

            if ($status === 'processing') {
                $stats['processing']++;
            }
        }

        return $this->render('admin/order/index.html.twig', [
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }



    #[Route('/{orderNumber}', name: 'show', methods: ['GET'])]
    public function show(
        string $orderNumber,
        CustomerOrderRepository $customerOrderRepository
    ): Response {
        // Rechercher la commande à partir de sa référence.
        $order = $customerOrderRepository->findOneBy([
            'orderNumber' => $orderNumber,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Récupérer les paiements associés à la commande.
        $payments = $order->getPayments();

        $payment = $payments->first() ?: null;

        // Calculer le sous-total des produits.
        $subtotal = 0;

        foreach ($order->getOrderItems() as $item) {
            $subtotal += (float) $item->getUnitPrice()
                * $item->getQuantity();
        }

        // Calculer les frais de livraison.
        $total = (float) $order->getTotalAmount();

        $deliveryFee = max(0, $total - $subtotal);

        // Calculer le montant réellement encaissé.
        $paidAmount = 0;

        foreach ($payments as $currentPayment) {
            if (
                strtolower((string) $currentPayment->getStatus()) === 'paid'
            ) {
                $paidAmount += (float) $currentPayment->getAmount();
            }
        }

        // Calculer le solde restant.
        $remainingAmount = max(0, $total - $paidAmount);

        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
            'payment' => $payment,
            'payments' => $payments,
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'paidAmount' => $paidAmount,
            'remainingAmount' => $remainingAmount,
        ]);
    }



    #[Route(
        '/{orderNumber}/statut',
        name: 'update_status',
        methods: ['POST']
    )]
    public function updateStatus(
        string $orderNumber,
        Request $request,
        CustomerOrderRepository $customerOrderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Rechercher la commande.
        $order = $customerOrderRepository->findOneBy([
            'orderNumber' => $orderNumber,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'update_order_status_' . $orderNumber,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Définir les statuts autorisés.
        $allowedStatuses = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'cancelled',
        ];

        $newStatus = $request->request->get('status');

        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->addFlash('error', 'Le statut sélectionné est invalide.');

            return $this->redirectToRoute('admin_orders_show', [
                'orderNumber' => $orderNumber,
            ]);
        }

        try {
            // Démarrer une transaction pour sécuriser les opérations.
            $entityManager->beginTransaction();

            // Verrouiller la commande pendant le traitement.
            $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);

            // Recharger la commande avec son statut actuel.
            $entityManager->refresh($order);

            // Empêcher toute modification après l'annulation.
            if ($order->getStatus() === 'cancelled') {
                $entityManager->rollback();

                $this->addFlash(
                    'error',
                    'Cette commande est déjà annulée. Son statut ne peut plus être modifié.'
                );

                return $this->redirectToRoute('admin_orders_show', [
                    'orderNumber' => $orderNumber,
                ]);
            }

            // Restaurer le stock uniquement lors de l'annulation.
            if ($newStatus === 'cancelled') {
                foreach ($order->getOrderItems() as $orderItem) {
                    $variant = $orderItem->getVariant();

                    if (!$variant) {
                        throw new \RuntimeException(
                            'Une variante est introuvable pour un article de cette commande.'
                        );
                    }

                    // Ajouter la quantité annulée au stock de la variante.
                    $variant->setStockQuantity(
                        $variant->getStockQuantity()
                            + $orderItem->getQuantity()
                    );
                }
            }

            // Mettre à jour le statut de la commande.
            $order->setStatus($newStatus);

            // Enregistrer le stock et le statut dans la mÃªme transaction.
            $entityManager->flush();
            $entityManager->commit();

            $this->addFlash(
                'success',
                $newStatus === 'cancelled'
                    ? 'La commande a été annulée et le stock a été restauré.'
                    : 'Le statut de la commande a été mis à jour.'
            );
        } catch (\Throwable $exception) {
            // Annuler toutes les modifications en cas d'erreur.
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            throw $exception;
        }

        return $this->redirectToRoute('admin_orders_show', [
            'orderNumber' => $orderNumber,
        ]);
    }


    #[Route(
        '/{orderNumber}/paiements/{paymentId}/confirmer-cash',
        name: 'confirm_cash_payment',
        methods: ['POST']
    )]
    public function confirmCashPayment(
        string $orderNumber,
        int $paymentId,
        Request $request,
        CustomerOrderRepository $customerOrderRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Rechercher la commande.
        $order = $customerOrderRepository->findOneBy([
            'orderNumber' => $orderNumber,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Rechercher le paiement.
        $payment = $paymentRepository->find($paymentId);

        if (
            !$payment
            || $payment->getCustomerOrder()?->getId() !== $order->getId()
        ) {
            throw $this->createNotFoundException(
                'Ce paiement est introuvable pour cette commande.'
            );
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'confirm_cash_balance_' . $paymentId,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Autoriser uniquement la confirmation d'un paiement cash en attente.
        if (
            $payment->getMethod() !== 'cash_balance'
            || $payment->getStatus() !== 'pending'
        ) {
            $this->addFlash(
                'error',
                'Ce paiement ne peut pas être confirmée.'
            );

            return $this->redirectToRoute('admin_orders_show', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Le rglement en espèces est réservé à la livraison à domicile.
        if ($order->getDelivery()?->getDeliveryMethod() !== 'home_delivery') {
            $this->addFlash(
                'error',
                'Le paiement en espèces est réservé à la livraison à domicile.'
            );

            return $this->redirectToRoute('admin_orders_show', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Vérifier que l'acompte initial a bien été payé.
        $paidAmount = 0;
        $depositPaid = false;

        foreach ($order->getPayments() as $currentPayment) {
            if (strtolower((string) $currentPayment->getStatus()) === 'paid') {
                $paidAmount += (float) $currentPayment->getAmount();

                if (
                    $currentPayment->getMethod() === 'mobile_money_demo'
                    && !str_starts_with(
                        (string) $currentPayment->getTransactionReference(),
                        'BALANCE-'
                    )
                ) {
                    $depositPaid = true;
                }
            }
        }

        if (!$depositPaid) {
            $this->addFlash(
                'error',
                'L\'acompte doit être payé avant la confirmation du solde.'
            );

            return $this->redirectToRoute('admin_orders_show', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Vérifier le montant exact du solde.
        $remainingAmount = max(
            0,
            (float) $order->getTotalAmount() - $paidAmount
        );

        if (
            $remainingAmount <= 0
            || abs($remainingAmount - (float) $payment->getAmount()) > 0.01
        ) {
            $this->addFlash(
                'error',
                'Le montant de ce paiement ne correspond pas au solde restant.'
            );

            return $this->redirectToRoute('admin_orders_show', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Confirmer le paiement après réception effective des espèces.
        $payment->setStatus('paid');
        $payment->setPaidAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash(
            'success',
            'Le paiement en espèces a été confirmé avec succès.'
        );

        return $this->redirectToRoute('admin_orders_show', [
            'orderNumber' => $orderNumber,
        ]);
    }
}


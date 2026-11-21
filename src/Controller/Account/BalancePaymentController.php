<?php

namespace App\Controller\Account;

use App\Entity\CustomerOrder;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account/orders', name: 'app_account_balance_')]
class BalancePaymentController extends AbstractController
{
    /**
     * Effectuer le paiement du solde par Mobile Money.
     */
    #[Route(
        '/{orderNumber}/solde/mobile-money',
        name: 'mobile',
        methods: ['POST']
    )]
    public function payMobileMoney(
        string $orderNumber,
        Request $request,
        CustomerOrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement une commande appartenant au client connecté.
        $order = $orderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'pay_balance_' . $orderNumber,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Vérifier que la livraison autorise le paiement Mobile Money.
        $deliveryMethod = $order->getDelivery()?->getDeliveryMethod();

        if (!in_array(
            $deliveryMethod,
            ['home_delivery', 'taxi_brousse'],
            true
        )) {
            $this->addFlash(
                'error',
                'Le mode de livraison de cette commande est invalide.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Vérifier les paiements déjà effectués.
        $paymentState = $this->getPaymentState($order);

        if (!$paymentState['depositPaid']) {
            $this->addFlash(
                'error',
                'Veuillez d’abord régler et confirmer votre acompte.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($order->getStatus() === 'cancelled') {
            $this->addFlash(
                'error',
                'Cette commande a été annulée. Aucun paiement supplémentaire n’est autorisé.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($paymentState['remainingAmount'] <= 0) {
            $this->addFlash(
                'error',
                'Cette commande est déjà entièrement réglée.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Empêcher le paiement Mobile Money si un règlement cash est en attente.
        if ($paymentState['cashBalancePending']) {
            $this->addFlash(
                'error',
                'Un règlement en espèces est déjà en attente de confirmation. Veuillez contacter notre équipe.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Préparer le paiement du solde restant.
        $payment = new Payment();

        $payment->setCustomerOrder($order);
        $payment->setMethod('mobile_money_balance');
        $payment->setStatus('paid');
        $payment->setAmount(
            number_format(
                $paymentState['remainingAmount'],
                2,
                '.',
                ''
            )
        );

        $payment->setPaidAt(new \DateTimeImmutable());

        // Générer une référence pour la transaction.
        $operator = $paymentState['operator'];

        $payment->setTransactionReference(
            'BALANCE-'
            . $operator
            . '-'
            . date('YmdHis')
            . '-'
            . strtoupper(bin2hex(random_bytes(3)))
        );

        // Ajouter un nouvel enregistrement sans modifier l'acompte.
        $order->addPayment($payment);

        $entityManager->persist($payment);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Le paiement du solde a été enregistré avec succès.'
        );

        return $this->redirectToRoute('app_account_order_detail', [
            'orderNumber' => $orderNumber,
        ]);
    }

    /**
     * Enregistrer une demande de règlement en espèces.
     */
    #[Route(
        '/{orderNumber}/solde/especes',
        name: 'cash',
        methods: ['POST']
    )]
    public function requestCashPayment(
        string $orderNumber,
        Request $request,
        CustomerOrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement une commande appartenant au client connecté.
        $order = $orderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'pay_balance_cash_' . $orderNumber,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Le paiement en espèces est réservé à la livraison à domicile.
        if ($order->getDelivery()?->getDeliveryMethod() !== 'home_delivery') {
            $this->addFlash(
                'error',
                'Le paiement en espèces est disponible uniquement pour la livraison à domicile.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Vérifier les paiements déjà effectués.
        $paymentState = $this->getPaymentState($order);

        if (!$paymentState['depositPaid']) {
            $this->addFlash(
                'error',
                'Veuillez d’abord régler et confirmer votre acompte.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($order->getStatus() === 'cancelled') {
            $this->addFlash(
                'error',
                'Cette commande a été annulée. Aucun paiement supplémentaire n’est autorisé.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($paymentState['remainingAmount'] <= 0) {
            $this->addFlash(
                'error',
                'Cette commande est déjà entièrement réglée.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($paymentState['cashBalancePending']) {
            $this->addFlash(
                'error',
                'Une demande de règlement en espèces est déjà enregistrée.'
            );

            return $this->redirectToRoute('app_account_order_detail', [
                'orderNumber' => $orderNumber,
            ]);
        }

        // Créer un paiement en espèces en attente de réception.
        $payment = new Payment();

        $payment->setCustomerOrder($order);
        $payment->setMethod('cash_balance');
        $payment->setStatus('pending');

        $payment->setAmount(
            number_format(
                $paymentState['remainingAmount'],
                2,
                '.',
                ''
            )
        );

        $payment->setTransactionReference(
            'BALANCE-CASH-'
            . date('YmdHis')
            . '-'
            . strtoupper(bin2hex(random_bytes(3)))
        );

        // Enregistrer le paiement sans le considérer comme déjà encaissé.
        $order->addPayment($payment);

        $entityManager->persist($payment);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Votre demande de règlement en espèces est enregistrée. Le paiement sera confirmé après réception du montant.'
        );

        return $this->redirectToRoute('app_account_order_detail', [
            'orderNumber' => $orderNumber,
        ]);
    }

    /**
     * Calculer les paiements confirmés et le solde restant.
     */
    private function getPaymentState(CustomerOrder $order): array
    {
        $depositPaid = false;
        $paidAmount = 0;
        $cashBalancePending = false;
        $operator = 'MOBILE';

        foreach ($order->getPayments() as $payment) {
            $reference = (string) $payment->getTransactionReference();
            $status = strtolower((string) $payment->getStatus());

            $isBalancePayment = str_starts_with(
                $reference,
                'BALANCE-'
            );

            // Identifier l'acompte initial confirmé.
            if (
                !$isBalancePayment
                && $payment->getMethod() === 'mobile_money_demo'
                && $status === 'paid'
            ) {
                $depositPaid = true;

                // Récupérer l'opérateur utilisé pour l'acompte.
                if (preg_match(
                    '/^(?:SIM|DEMO)-([A-Z0-9_]+)/',
                    $reference,
                    $matches
                )) {
                    $operator = $matches[1];
                }
            }

            // Comptabiliser uniquement les paiements confirmés.
            if ($status === 'paid') {
                $paidAmount += (float) $payment->getAmount();
            }

            // Identifier une demande de paiement cash non encore confirmée.
            if (
                $isBalancePayment
                && $payment->getMethod() === 'cash_balance'
                && $status === 'pending'
            ) {
                $cashBalancePending = true;
            }
        }

        $total = (float) $order->getTotalAmount();

        return [
            'depositPaid' => $depositPaid,
            'paidAmount' => $paidAmount,
            'remainingAmount' => max(0, $total - $paidAmount),
            'cashBalancePending' => $cashBalancePending,
            'operator' => $operator,
        ];
    }
}

<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderConfirmationController extends AbstractController
{
    #[Route(
        '/commande/confirmation',
        name: 'app_order_confirmation',
        methods: ['GET']
    )]
    public function index(
        Request $request,
        CustomerOrderRepository $customerOrderRepository
    ): Response {
        // Vérifier que le client est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer le numéro de la dernière commande enregistrée.
        $orderNumber = $request->getSession()->get('last_order_number');

        if (!$orderNumber) {
            $this->addFlash(
                'error',
                'Aucune commande récente n’a été trouvée.'
            );

            return $this->redirectToRoute('app_shop');
        }

        // Récupérer uniquement une commande appartenant au client connecté.
        $order = $customerOrderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
        ]);

        if (!$order) {
            $this->addFlash(
                'error',
                'La commande demandée est introuvable.'
            );

            return $this->redirectToRoute('app_shop');
        }

        // Calculer le sous-total à partir des lignes de commande.
        $subtotal = 0;

        foreach ($order->getOrderItems() as $item) {
            $subtotal += (float) $item->getUnitPrice()
                * $item->getQuantity();
        }

        // Déduire les frais de livraison du montant total enregistré.
        $deliveryFee = max(
            0,
            (float) $order->getTotalAmount() - $subtotal
        );

        // Récupérer le paiement associé à la commande.
        $payment = $order->getPayments()->first();

        if ($payment === false) {
            $payment = null;
        }

        // Préparer le libellé du mode de paiement.
        $paymentMethodLabel = match ($payment?->getMethod()) {
            'cash_on_delivery' => 'Paiement à la livraison',
            'mobile_money_demo' => 'Mobile Money — acompte de 50 %',
            default => 'Mode de paiement non renseigné',
        };

        return $this->render(
            'pages/order_confirmation/index.html.twig',
            [
                'order' => $order,
                'subtotal' => $subtotal,
                'deliveryFee' => $deliveryFee,
                'payment' => $payment,
                'paymentMethodLabel' => $paymentMethodLabel,
            ]
        );
    }

    #[Route(
        '/commande/{orderNumber}/paiement/simulation',
        name: 'app_order_payment_simulate',
        methods: ['POST']
    )]
    public function simulatePayment(
        string $orderNumber,
        Request $request,
        CustomerOrderRepository $customerOrderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que le client est connecté.
        $user = $this->getUser();

        // instanceof pour vérifier le type de l'objet / si l'utilisateu est connécté
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement une commande appartenant au client connecté.
        $order = $customerOrderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Cette commande est introuvable.'
            );
        }

        // Vérifier le jeton CSRF.
        // isCsrfTokenValid pour vérifier la sécurité du formulaire
        if (!$this->isCsrfTokenValid(
            'simulate_payment_' . $orderNumber,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Récupérer le paiement de la commande.
        $payment = $order->getPayments()->first();

        if (!$payment instanceof Payment) {
            $this->addFlash(
                'error',
                'Aucun paiement n’est associé à cette commande.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Autoriser cette simulation uniquement pour Mobile Money.
        if ($payment->getMethod() !== 'mobile_money_demo') {
            $this->addFlash(
                'error',
                'Cette commande ne nécessite pas de simulation Mobile Money.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Empêcher de payer deux fois le même acompte.
        if ($payment->getStatus() === 'paid') {
            $this->addFlash(
                'error',
                'Cet acompte a déjà été confirmé.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Vérifier que le montant de l'acompte est valide.
        if ((float) $payment->getAmount() <= 0) {
            $this->addFlash(
                'error',
                'Le montant de l’acompte est invalide.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Récupérer le résultat choisi dans la simulation.
        $simulationResult = $request->request->get('simulationResult');

        // Récupérer le nom de l'opérateur sélectionné lors du checkout.
        $operator = 'MOBILE';

        $storedReference = (string) $payment->getTransactionReference();

        if (preg_match('/^(?:DEMO|SIM)-([A-Z0-9_]+)/', $storedReference, $matches)) {
            $operator = $matches[1];
        }

        if ($simulationResult === 'success') {
            // Confirmer le paiement simulé.
            $payment->setStatus('paid');
            $payment->setPaidAt(new \DateTimeImmutable());

            // Générer une référence de transaction de démonstration.
            $payment->setTransactionReference(
                'SIM-'
                . $operator
                . '-'
                . date('YmdHis')
                . '-'
                . strtoupper(bin2hex(random_bytes(3)))
            );

            $this->addFlash(
                'success',
                'Simulation réussie ! Votre acompte a été enregistré.'
            );
        } elseif ($simulationResult === 'failure') {
            // Enregistrer l'échec de la simulation.
            $payment->setStatus('failed');
            $payment->setPaidAt(null);

            // Conserver l'opérateur pour permettre une nouvelle tentative.
            $payment->setTransactionReference('DEMO-' . $operator);

            $this->addFlash(
                'error',
                'La simulation du paiement a échoué. Vous pouvez réessayer.'
            );
        } else {
            $this->addFlash(
                'error',
                'Le résultat de la simulation est invalide.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Enregistrer le résultat du paiement.
        $entityManager->flush();

        // Conserver la commande pour afficher sa confirmation.
        $request->getSession()->set(
            'last_order_number',
            $order->getOrderNumber()
        );

        return $this->redirectToRoute('app_order_confirmation');
    }
}

<?php

namespace App\Controller\Account;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account/payments', name: 'app_account_payment_')]
class PaymentReceiptController extends AbstractController
{
    #[Route('/{paymentId}/receipt.pdf', name: 'receipt', methods: ['GET'])]
    public function receipt(
        int $paymentId,
        PaymentRepository $paymentRepository
    ): Response {
        // Vérifier que l'utilisateur est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException(
                'Vous devez être connecté pour accéder à ce reçu.'
            );
        }

        // Récupérer le paiement.
        $payment = $paymentRepository->find($paymentId);

        if (!$payment instanceof Payment) {
            throw $this->createNotFoundException(
                'Paiement introuvable.'
            );
        }

        $order = $payment->getCustomerOrder();

        // Vérifier que la commande appartient à l'utilisateur connecté.
        if (!$order || $order->getUser() !== $user) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas accéder à ce reçu.'
            );
        }

        // Vérifier que le paiement est confirmé.
        if (
            strtolower((string) $payment->getStatus()) !== 'paid'
            || !$payment->getPaidAt()
        ) {
            throw $this->createAccessDeniedException(
                'Ce paiement ne possède pas encore de reçu.'
            );
        }

        // Récupérer la demande d'échange associée au paiement, si elle existe.
        $returnRequest = $payment->getReturnRequest();

        // Vérifier que la demande d'échange appartient à la commande concernée.
        if ($returnRequest) {
            $returnOrder = $returnRequest->getOrderItem()?->getCustomerOrder();

            if (
                $returnOrder !== $order
                || $returnRequest->getType() !== 'exchange'
            ) {
                throw $this->createAccessDeniedException(
                    'Ce reçu ne correspond pas à cette demande d’échange.'
                );
            }
        }

        // Configurer la génération du PDF.
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        // Générer le contenu HTML du reçu.
        $html = $this->renderView(
            'pages/account/payment_receipt_pdf.html.twig',
            [
                'payment' => $payment,
                'order' => $order,
                'user' => $user,
                'address' => $order->getAddress(),
                'returnRequest' => $returnRequest,
            ]
        );

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Préparer le téléchargement du reçu.
        $filename = $returnRequest
            ? 'recu-echange-' . $payment->getId() . '.pdf'
            : 'recu-paiement-' . $payment->getId() . '.pdf';

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}

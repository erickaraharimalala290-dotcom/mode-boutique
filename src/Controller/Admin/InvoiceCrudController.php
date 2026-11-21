<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/factures', name: 'admin_invoices_')]
#[IsGranted('ROLE_MANAGER')]
class InvoiceCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        InvoiceRepository $invoiceRepository
    ): Response {
        // Récupérer les factures de la plus récente à la plus ancienne.
        $invoices = $invoiceRepository->findBy(
            [],
            ['issuedAt' => 'DESC']
        );

        $invoiceRows = [];

        $stats = [
            'total' => count($invoices),
            'amount' => 0,
        ];

        foreach ($invoices as $invoice) {
            $order = $invoice->getCustomerOrder();
            $user = $order?->getUser();

            $totalAmount = $order
                ? (float) $order->getTotalAmount()
                : 0;

            $invoiceRows[] = [
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'orderNumber' => $order?->getOrderNumber() ?? 'â€”',
                'client' => $user?->getEmail() ?? 'Client inconnu',
                'issuedAt' => $invoice->getIssuedAt(),
                'total' => $totalAmount,
            ];

            $stats['amount'] += $totalAmount;
        }

        return $this->render('admin/invoice/index.html.twig', [
            'invoices' => $invoiceRows,
            'stats' => $stats,
        ]);
    }

    #[Route('/{invoiceNumber}', name: 'show', methods: ['GET'])]
    public function show(
        string $invoiceNumber,
        InvoiceRepository $invoiceRepository
    ): Response {
        $invoice = $this->findInvoice(
            $invoiceNumber,
            $invoiceRepository
        );

        $details = $this->getInvoiceDetails($invoice);

        return $this->render(
            'admin/invoice/show.html.twig',
            ['invoice' => $invoice] + $details
        );
    }

    #[Route('/{invoiceNumber}/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(
        string $invoiceNumber,
        InvoiceRepository $invoiceRepository
    ): Response {
        $invoice = $this->findInvoice(
            $invoiceNumber,
            $invoiceRepository
        );

        $details = $this->getInvoiceDetails($invoice);

        // Configurer la génération du document PDF.
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');

        // Ne pas autoriser le chargement de ressources distantes.
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);

        // Générer le HTML destiné au document PDF.
        $html = $this->renderView(
            'admin/invoice/pdf.html.twig',
            ['invoice' => $invoice] + $details
        );

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Préparer le téléchargement du fichier.
        $fileName = 'facture-'
            . ($invoice->getInvoiceNumber() ?? $invoice->getId())
            . '.pdf';

        $response = new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf']
        );

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $fileName
            )
        );

        return $response;
    }

    /**
     * Rechercher une facture à partir de son numéro.
     */
    private function findInvoice(
        string $invoiceNumber,
        InvoiceRepository $invoiceRepository
    ): Invoice {
        $invoice = $invoiceRepository->findOneBy([
            'invoiceNumber' => $invoiceNumber,
        ]);

        if (!$invoice) {
            throw $this->createNotFoundException(
                'Cette facture est introuvable.'
            );
        }

        return $invoice;
    }

    /**
     * Préparer les données financières de la facture.
     */
    private function getInvoiceDetails(Invoice $invoice): array
    {
        $order = $invoice->getCustomerOrder();

        if (!$order) {
            throw $this->createNotFoundException(
                'La commande associée à cette facture est introuvable.'
            );
        }

        // Calculer le sous-total des produits.
        $subtotal = 0;

        foreach ($order->getOrderItems() as $item) {
            $subtotal += (float) $item->getUnitPrice()
                * (int) $item->getQuantity();
        }

        // Calculer les frais de livraison.
        $total = (float) $order->getTotalAmount();
        $deliveryFee = max(0, $total - $subtotal);

        // Comptabiliser uniquement les paiements confirmÃ©s.
        $paidAmount = 0;

        foreach ($order->getPayments() as $payment) {
            if (strtolower((string) $payment->getStatus()) === 'paid') {
                $paidAmount += (float) $payment->getAmount();
            }
        }

        // Calculer le solde restant.
        $remainingAmount = max(0, $total - $paidAmount);

        return [
            'order' => $order,
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'total' => $total,
            'paidAmount' => $paidAmount,
            'remainingAmount' => $remainingAmount,
        ];
    }
}


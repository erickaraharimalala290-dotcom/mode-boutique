<?php

namespace App\Controller\Admin;

use App\Entity\Delivery;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des livraisons dans l'espace d'administration.
 */
#[Route('/administration/livraisons', name: 'admin_deliveries_')]
#[IsGranted('ROLE_MANAGER')]
class DeliveryCrudController extends AbstractController
{
    /**
     * Affiche la liste des livraisons et les statistiques.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        DeliveryRepository $deliveryRepository
    ): Response {

        // Récupérer les livraisons de la plus récente à la plus ancienne.
        $deliveries = $deliveryRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        // Initialiser les statistiques de livraison.
        $stats = [
            'total' => count($deliveries),
            'pending' => 0,
            'inProgress' => 0,
            'delivered' => 0,
            'cancelled' => 0,
            'postponed' => 0,
            'fees' => 0,
        ];

        // Préparer les informations destinées au tableau Twig.
        $deliveryRows = [];

        foreach ($deliveries as $delivery) {

            // Récupérer la commande et l'adresse associées à la livraison.
            $order = $delivery->getCustomerOrder();
            $address = $order->getAddress();

            // Récupérer le statut propre à la livraison.
            $status = $delivery->getStatus();

            // Vérifier que le statut de livraison est autorisé.
            $allowedStatuses = [
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'postponed',
            ];

            // Utiliser le statut de la commande comme solution de secours
            // si le statut de livraison est vide ou invalide.
            if (!in_array($status, $allowedStatuses, true)) {
                $status = $order->getStatus() ?? 'pending';
            }

            // Calculer les statistiques selon le statut de la livraison.
            if (in_array($status, ['pending', 'confirmed'], true)) {
                $stats['pending']++;
            } elseif (in_array($status, ['processing', 'shipped'], true)) {
                $stats['inProgress']++;
            } elseif ($status === 'delivered') {
                $stats['delivered']++;
            } elseif ($status === 'cancelled') {
                $stats['cancelled']++;
            } elseif ($status === 'postponed') {
                $stats['postponed']++;
            }

            // Calculer le sous-total des articles de la commande.
            $subtotal = 0;

            foreach ($order->getOrderItems() as $item) {
                $subtotal += (float) $item->getUnitPrice()
                    * $item->getQuantity();
            }

            // Déduire les frais de livraison du montant total.
            $fees = max(
                0,
                (float) $order->getTotalAmount() - $subtotal
            );

            // Ajouter les frais aux statistiques.
            $stats['fees'] += $fees;

            // Construire l'adresse complète du destinataire.
            $addressText = 'Adresse non renseignée';

            if ($address) {
                $addressParts = array_filter([
                    $address->getAddressLine1(),
                    $address->getAddressLine2(),
                    $address->getCity(),
                    $address->getPostalCode(),
                    $address->getCountry(),
                ]);

                $addressText = implode(', ', $addressParts);
            }

            // Déterminer le libellé du mode de livraison.
            $deliveryMethodLabel = match ($delivery->getDeliveryMethod()) {
                'home_delivery' => 'Livraison à domicile',
                'taxi_brousse' => 'Envoi en agence',
                default => 'Non défini',
            };

            // Préparer les données de chaque livraison pour le template.
            $deliveryRows[] = [
                'delivery' => $delivery,
                'order' => $order,
                'deliveryMethod' => $deliveryMethodLabel,

                // Utiliser le numéro de suivi ou l'identifiant de livraison.
                'reference' => $delivery->getTrackingNumber()
                    ?: 'LIV-' . $delivery->getId(),

                'customer' => $address?->getRecipientName() ?? 'Client',
                'phone' => $address?->getPhone() ?? '',
                'address' => $addressText,

                // Afficher la date d'expédition ou la date de création.
                'date' => $delivery->getShippedAt()
                    ?? $delivery->getCreatedAt(),

                'fees' => $fees,

                // Transmettre le statut réel de la livraison à Twig.
                'status' => $status,

                // Transmettre la raison de l'échec ou du report.
                'failureReason' => $delivery->getFailureReason(),

                // Transmettre la date de l'échec ou du report.
                'failedAt' => $delivery->getFailedAt(),
            ];
        }

        // Envoyer les données au template Twig.
        return $this->render('admin/delivery/index.html.twig', [
            'deliveries' => $deliveries,
            'deliveryRows' => $deliveryRows,
            'stats' => $stats,
        ]);
    }

    /**
     * Modifie les informations d'une livraison et son statut.
     */
    #[Route(
        '/{id}/modifier',
        name: 'update',
        methods: ['POST']
    )]
    public function update(
        Delivery $delivery,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        // Vérifier le jeton CSRF pour sécuriser le formulaire.
        if (!$this->isCsrfTokenValid(
            'update_delivery_' . $delivery->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Récupérer les informations envoyées par le formulaire.
        $carrier = trim((string) $request->request->get('carrier'));

        $trackingNumber = trim(
            (string) $request->request->get('trackingNumber')
        );

        $status = $request->request->get('status');

        $failureReason = $request->request->get('failureReason');

        // Définir les statuts autorisés pour une livraison.
        $allowedStatuses = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'cancelled',
            'postponed',
        ];

        // Vérifier que le statut envoyé est valide.
        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash(
                'error',
                'Le statut sélectionné est invalide.'
            );

            return $this->redirectToRoute('admin_deliveries_index');
        }

        // Définir les raisons autorisées en cas d'annulation.
        $allowedFailureReasons = [
            'Client absent',
            'Client injoignable',
            'Adresse incorrecte',
        ];

        // Définir les raisons autorisées en cas de report.
        $allowedPostponementReasons = [
            'Livreur non présenté',
            'Problème du transporteur',
        ];

        // Une raison est obligatoire pour annuler une livraison.
        if (
            $status === 'cancelled'
            && !in_array($failureReason, $allowedFailureReasons, true)
        ) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner la raison de l’annulation.'
            );

            return $this->redirectToRoute('admin_deliveries_index');
        }

        // Une raison est également obligatoire pour reporter une livraison.
        if (
            $status === 'postponed'
            && !in_array($failureReason, $allowedPostponementReasons, true)
        ) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner la raison du report.'
            );

            return $this->redirectToRoute('admin_deliveries_index');
        }

        // Récupérer le statut précédent avant la modification.
        $previousStatus = $delivery->getStatus();

        // Mettre à jour le transporteur.
        $delivery->setCarrier(
            $carrier !== '' ? $carrier : null
        );

        // Mettre à jour le numéro de suivi.
        $delivery->setTrackingNumber(
            $trackingNumber !== '' ? $trackingNumber : null
        );

        // Mettre à jour le statut propre à la livraison.
        $delivery->setStatus($status);

        // Récupérer la commande associée à cette livraison.
        $order = $delivery->getCustomerOrder();

        /*
         * Gestion d'une livraison annulée ou reportée.
         * Dans ces deux cas, la commande conserve son statut actuel.
         */
        if (in_array($status, ['cancelled', 'postponed'], true)) {

            // Enregistrer la raison de l'annulation ou du report.
            $delivery->setFailureReason($failureReason);

            // Enregistrer la date de l'événement.
            // Si le statut n'a pas changé, conserver la date précédente.
            if (
                $previousStatus !== $status
                || $delivery->getFailedAt() === null
            ) {
                $delivery->setFailedAt(new \DateTimeImmutable());
            }

            // Une livraison non effectuée ne doit pas conserver
            // une date de réception.
            $delivery->setDeliveredAt(null);

        } else {

            /*
             * Pour les statuts normaux, synchroniser le statut
             * de la livraison avec celui de la commande.
             */
            $order->setStatus($status);

            // Effacer les anciennes informations d'échec ou de report
            // lorsque la livraison reprend son déroulement normal.
            $delivery->setFailureReason(null);
            $delivery->setFailedAt(null);

            // Enregistrer automatiquement la date d'expédition.
            if (
                $status === 'shipped'
                && $delivery->getShippedAt() === null
            ) {
                $delivery->setShippedAt(new \DateTimeImmutable());
            }

            // Enregistrer la date de livraison lors de la réception du colis.
            if ($status === 'delivered') {

                // Ajouter une date d'expédition si elle n'existe pas.
                if ($delivery->getShippedAt() === null) {
                    $delivery->setShippedAt(new \DateTimeImmutable());
                }

                // Enregistrer la date de réception uniquement la première fois.
                if ($delivery->getDeliveredAt() === null) {
                    $delivery->setDeliveredAt(new \DateTimeImmutable());
                }

            } else {
                // Effacer la date de réception si le statut change.
                $delivery->setDeliveredAt(null);
            }
        }

        // Enregistrer toutes les modifications dans la base de données.
        $entityManager->flush();

        // Afficher un message adapté à l'action effectuée.
        if ($status === 'cancelled') {
            $this->addFlash(
                'success',
                'La livraison a été annulée. Le statut de la commande a été conservé.'
            );
        } elseif ($status === 'postponed') {
            $this->addFlash(
                'success',
                'La livraison a été reportée. Le statut de la commande a été conservé.'
            );
        } else {
            $this->addFlash(
                'success',
                'Les informations de livraison ont été mises à jour.'
            );
        }

        // Revenir à la liste des livraisons.
        return $this->redirectToRoute('admin_deliveries_index');
    }
}

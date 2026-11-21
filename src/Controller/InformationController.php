<?php

namespace App\Controller;

use App\Repository\StoreSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InformationController extends AbstractController
{
    #[Route('/informations/livraison', name: 'app_delivery_info', methods: ['GET'])]
    public function delivery(StoreSettingRepository $repository): Response
    {
        $settings = $repository->findOneBy([], ['id' => 'ASC']);

        $shippingFee = $settings?->getShippingFee() ?? 0;
        $threshold = $settings?->getFreeShippingThreshold();
        $currency = $settings?->getCurrency() ?? 'MGA';
        $deliveryDelay = $settings?->getDeliveryDelay();

        $sections = [
            [
                'title' => 'Modes de livraison',
                'text' => 'ModeBoutique propose deux modes de réception de commande : la livraison à domicile et l’envoi en agence.',
            ],
            [
                'title' => 'Frais de livraison',
                'text' => 'Les frais de livraison actuellement configurés sont de '
                    . number_format($shippingFee, 0, ',', ' ') . ' ' . $currency . '.',
            ],
            [
                'title' => 'Délai de livraison',
                'text' => $deliveryDelay ?: 'Le délai de livraison sera communiqué lors de la confirmation de votre commande.',
            ],
        ];

        if ($threshold !== null) {
            $sections[] = [
                'title' => 'Livraison gratuite',
                'text' => 'La livraison est offerte à partir de '
                    . number_format($threshold, 0, ',', ' ') . ' ' . $currency . ', selon les conditions de la boutique.',
            ];
        }

        return $this->render('pages/information/show.html.twig', [
            'title' => 'Livraison',
            'intro' => 'Retrouvez les informations concernant la réception de vos commandes ModeBoutique.',
            'sections' => $sections,
        ]);
    }


    #[Route('/informations/retours', name: 'app_return_info', methods: ['GET'])]
    public function returns(): Response
    {
        return $this->render('pages/information/show.html.twig', [
            'title' => 'Politique de retour',
            'intro' => 'Vous disposez de 7 jours après réception de votre commande pour adresser une demande de retour à notre service client, sous réserve des conditions ci-dessous.',
            'sections' => [
                [
                    'title' => 'Délai de retour',
                    'text' => 'Toute demande de retour doit être effectuée dans un délai de 7 jours à compter de la réception de la commande.',
                ],
                [
                    'title' => 'État de l’article',
                    'text' => 'L’article doit être non porté, non utilisé et conservé dans son état d’origine, avec ses accessoires et son emballage si applicable.',
                ],
                [
                    'title' => 'Comment effectuer une demande ?',
                    'text' => 'Avant tout retour, veuillez contacter notre service client en indiquant votre numéro de commande, le produit concerné et le motif de votre demande.',
                ],
                [
                    'title' => 'Livraison et retour',
                    'text' => 'Que vous soyez à Antananarivo, en périphérie ou en province, notre équipe vous communiquera les modalités de retour adaptées à votre zone.',
                ],
                [
                    'title' => 'Échange et accompagnement',
                    'text' => 'Un échange peut être proposé après examen de votre demande, sous réserve de disponibilité du produit souhaité. Notre service client vous accompagnera dans les démarches.',
                ],
            ],
        ]);
    }


    #[Route('/informations/paiement', name: 'app_payment_info', methods: ['GET'])]
    public function payment(): Response
    {
        return $this->render('pages/information/show.html.twig', [
            'title' => 'Modes de paiement',
            'intro' => 'Découvrez les modalités de paiement proposées pour vos commandes ModeBoutique.',
            'sections' => [
                [
                    'title' => 'Acompte obligatoire de 50 %',
                    'text' => 'Chaque commande nécessite le paiement d’un acompte de 50 % du montant total par Mobile Money.',
                ],
                [
                    'title' => 'Paiement du solde à domicile',
                    'text' => 'Pour une livraison à domicile, le solde restant peut être réglé par Mobile Money ou en espèces auprès du livreur.',
                ],
                [
                    'title' => 'Paiement du solde en agence',
                    'text' => 'Pour un envoi en agence, le solde restant doit être réglé par Mobile Money avant le retrait de la commande.',
                ],
                [
                    'title' => 'Opérateurs Mobile Money',
                    'text' => 'Les moyens proposés sont MVola, Orange Money et Airtel Money.',
                ],
            ],
        ]);
    }
}

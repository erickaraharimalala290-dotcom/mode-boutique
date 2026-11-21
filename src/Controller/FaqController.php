<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FaqController extends AbstractController
{
    #[Route('/faq', name: 'app_faq', methods: ['GET'])]
    public function index(): Response
    {
        $faqCategories = [
            [
                'title' => 'Commandes',
                'questions' => [
                    [
                        'question' => 'Comment passer une commande sur ModeBoutique ?',
                        'answer' => 'Parcourez notre boutique, choisissez les articles qui vous intéressent, sélectionnez les options disponibles et ajoutez-les au panier. Vous pourrez ensuite valider votre commande en renseignant votre adresse et les informations de livraison.',
                    ],
                    [
                        'question' => 'Comment connaître le statut de ma commande ?',
                        'answer' => 'Connectez-vous à votre compte client et consultez la rubrique « Mes commandes ». Vous pourrez y retrouver vos commandes et leur statut : en attente, confirmée, en préparation, expédiée, livrée ou annulée.',
                    ],
                    [
                        'question' => 'Puis-je retrouver les détails de ma commande ?',
                        'answer' => 'Oui. Les détails de vos commandes sont accessibles depuis votre espace client, dans la rubrique « Mes commandes ». Vous pouvez notamment consulter les articles commandés et le montant total.',
                    ],
                ],
            ],
            [
                'title' => 'Livraison',
                'questions' => [
                    [
                        'question' => 'Quels sont les modes de livraison disponibles ?',
                        'answer' => 'ModeBoutique propose la livraison à domicile ainsi que l’envoi en agence.',
                    ],
                    [
                        'question' => 'Comment suivre ma livraison ?',
                        'answer' => 'Lorsque les informations de suivi sont renseignées, vous pouvez retrouver le transporteur, le numéro de suivi et les dates d’expédition ou de livraison dans les informations de votre commande.',
                    ],
                    [
                        'question' => 'Quel est le délai de livraison ?',
                        'answer' => 'Le délai dépend du mode de livraison et de la destination. Les informations disponibles sont communiquées lors de la confirmation de votre commande.',
                    ],
                ],
            ],
            [
                'title' => 'Paiement',
                'questions' => [
                    [
                        'question' => 'Quel montant dois-je payer pour confirmer ma commande ?',
                        'answer' => 'Un acompte obligatoire de 50 % du montant total de la commande doit être réglé par Mobile Money.',
                    ],
                    [
                        'question' => 'Quels opérateurs Mobile Money sont proposés ?',
                        'answer' => 'Les moyens de paiement prévus sont MVola, Orange Money et Airtel Money.',
                    ],
                    [
                        'question' => 'Comment régler le solde restant ?',
                        'answer' => 'Pour une livraison à domicile, le solde peut être réglé par Mobile Money ou en espèces auprès du livreur. Pour un envoi en agence, le solde doit être réglé par Mobile Money avant le retrait.',
                    ],
                    
                ],
            ],
            [
                'title' => 'Factures et assistance',
                'questions' => [
                    [
                        'question' => 'Où puis-je retrouver ma facture ?',
                        'answer' => 'Vous pouvez consulter les informations de votre commande et accéder à la facture depuis votre espace client, lorsque celle-ci est disponible.',
                    ],
                    [
                        'question' => 'Comment contacter le service client ?',
                        'answer' => 'Vous pouvez nous envoyer un message depuis la page Contact. Si vous êtes connecté à votre compte, vos coordonnées seront automatiquement préremplies.',
                    ],
                    [
                        'question' => 'Comment demander un retour ?',
                        'answer' => 'Nous vous invitons à contacter notre service client en indiquant votre numéro de commande, le produit concerné et le motif de votre demande.',
                    ],
                ],
            ],
        ];

        return $this->render('pages/faq/index.html.twig', [
            'faqCategories' => $faqCategories,
        ]);
    }
}

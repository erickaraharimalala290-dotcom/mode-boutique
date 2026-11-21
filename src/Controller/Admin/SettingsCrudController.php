<?php

namespace App\Controller\Admin;

use App\Entity\StoreSetting;
use App\Repository\StoreSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/parametres', name: 'admin_settings_')]
#[IsGranted('ROLE_ADMIN')]
class SettingsCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        StoreSettingRepository $storeSettingRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupérer les paramètres existants.
        $settings = $storeSettingRepository->findOneBy([], ['id' => 'ASC']);

        // Créer les paramètres par défaut lors de la première visite.
        if (!$settings) {
            $settings = (new StoreSetting())
                ->setStoreName('ModeBoutique')
                ->setDescription('La mode à portée de clic.')
                ->setContactEmail('contact@modeboutique.test')
                ->setAddress('Antananarivo, Madagascar')
                ->setCurrency('MGA')
                ->setShippingFee(0)
                ->setDeliveryDelay('2 à 5 jours ouvrables');

            $entityManager->persist($settings);
            $entityManager->flush();
        }

        if ($request->isMethod('POST')) {
            // Vérifier le jeton CSRF.
            if (!$this->isCsrfTokenValid(
                'save_store_settings',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
            }

            $name = trim((string) $request->request->get('store_name', ''));
            $description = trim((string) $request->request->get('store_description', ''));
            $email = trim((string) $request->request->get('store_email', ''));
            $phone = trim((string) $request->request->get('store_phone', ''));
            $address = trim((string) $request->request->get('store_address', ''));
            $currency = (string) $request->request->get('store_currency', 'MGA');
            $deliveryDelay = trim((string) $request->request->get('delivery_delay', ''));

            $shippingFeeInput = trim((string) $request->request->get('shipping_fee', ''));
            $thresholdInput = trim((string) $request->request->get('free_shipping_threshold', ''));

            // Valider les informations obligatoires.
            if ($name === '' || $email === '') {
                $this->addFlash('error', 'Le nom de la boutique et l’adresse e-mail sont obligatoires.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Veuillez saisir une adresse e-mail valide.');
            } elseif (!in_array($currency, ['MGA', 'EUR', 'USD'], true)) {
                $this->addFlash('error', 'La devise sélectionnée est invalide.');
            } else {
                // Valider les frais de livraison.
                $shippingFee = $shippingFeeInput === ''
                    ? 0
                    : filter_var($shippingFeeInput, FILTER_VALIDATE_INT);

                $freeShippingThreshold = $thresholdInput === ''
                    ? null
                    : filter_var($thresholdInput, FILTER_VALIDATE_INT);

                if (
                    $shippingFee === false ||
                    $shippingFee < 0 ||
                    ($freeShippingThreshold !== null &&
                        ($freeShippingThreshold === false || $freeShippingThreshold < 0))
                ) {
                    $this->addFlash('error', 'Les montants de livraison doivent être des nombres entiers positifs.');
                } else {
                    // Enregistrer les paramètres.
                    $settings
                        ->setStoreName($name)
                        ->setDescription($description !== '' ? $description : null)
                        ->setContactEmail($email)
                        ->setPhone($phone !== '' ? $phone : null)
                        ->setAddress($address !== '' ? $address : null)
                        ->setCurrency($currency)
                        ->setDeliveryDelay($deliveryDelay !== '' ? $deliveryDelay : null)
                        ->setShippingFee($shippingFee)
                        ->setFreeShippingThreshold($freeShippingThreshold);

                    $entityManager->flush();

                    $this->addFlash('success', 'Les paramètres de la boutique ont été enregistrés avec succès.');

                    return $this->redirectToRoute('admin_settings_index');
                }
            }
        }

        return $this->render('admin/settings/index.html.twig', [
            'settings' => [
                'name' => $settings->getStoreName(),
                'description' => $settings->getDescription(),
                'email' => $settings->getContactEmail(),
                'phone' => $settings->getPhone(),
                'address' => $settings->getAddress(),
                'currency' => $settings->getCurrency(),
                'freeShippingThreshold' => $settings->getFreeShippingThreshold(),
                'shippingFee' => $settings->getShippingFee(),
                'deliveryDelay' => $settings->getDeliveryDelay(),
            ],
        ]);
    }
}

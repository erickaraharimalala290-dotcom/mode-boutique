<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\CustomerOrder;
use App\Entity\Delivery;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Invoice;
use App\Repository\AddressRepository;
use App\Repository\CartRepository;
use App\Service\ProductPromotionCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CartRepository $cartRepository,
        AddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
        ProductPromotionCalculator $promotionCalculator
    ): Response {
        $user = $this->getUser();

        // La commande nécessite un compte client.
        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                'Veuillez vous connecter pour finaliser votre commande.'
            );

            return $this->redirectToRoute('app_login');
        }

        // Récupérer le panier actif du client.
        $cart = $cartRepository->findOneBy([
            'user' => $user,
            'status' => 'active',
        ]);

        $items = $cart ? $cart->getItems()->toArray() : [];

        if (!$cart || count($items) === 0) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('app_cart');
        }

        // Recalculer les prix promotionnels avant la commande.
        $subtotal = 0;
        $pricesUpdated = false;

        foreach ($items as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();

            if (!$product) {
                continue;
            }

            // Récupérer le prix de base de la variante ou du produit.
            $basePrice = $variant?->getPriceOverride() ?? $product->getPrice();

            if ($basePrice === null) {
                continue;
            }

            // Calculer la meilleure promotion applicable.
            $pricing = $promotionCalculator->calculate(
                $product,
                (float) $basePrice
            );

            $newUnitPrice = number_format(
                $pricing['price'],
                2,
                '.',
                ''
            );

            // Actualiser le prix enregistré dans le panier.
            if ((float) $item->getUnitPrice() !== (float) $newUnitPrice) {
                $item->setUnitPrice($newUnitPrice);
                $item->setUpdatedAt(new \DateTimeImmutable());

                $pricesUpdated = true;
            }

            $subtotal += (float) $newUnitPrice * $item->getQuantity();
        }

        // Enregistrer les prix actualisés.
        if ($pricesUpdated) {
            $entityManager->flush();
        }

        // Récupérer les adresses du client.
        $addresses = $addressRepository->findBy(
            ['user' => $user],
            ['isDefault' => 'DESC', 'createdAt' => 'DESC']
        );

        // Utiliser l'adresse par défaut pour le calcul initial.
        $deliveryCity = count($addresses) > 0
            ? $addresses[0]->getCity()
            : '';

        // Calculer les frais selon la zone de livraison.
        $deliveryFee = $this->calculateDeliveryFee($deliveryCity);
        $total = $subtotal + $deliveryFee;

        // Initialiser les erreurs et les données du formulaire.
        $checkoutErrors = [];
        $formData = [];
        $selectedAddress = null;

        if ($request->isMethod('POST')) {
            $formData = $request->request->all();

            // Vérifier le jeton CSRF.
            if (!$this->isCsrfTokenValid(
                'checkout',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException(
                    'Jeton de sécurité invalide.'
                );
            }

            // Récupérer les données du formulaire.
            $paymentMethod = $request->request->get('paymentMethod');
            $paymentOperator = $request->request->get('paymentOperator');
            $deliveryMethod = $request->request->get('deliveryMethod');

            $addressId = $request->request->get('addressId');
            $useNewAddress = $request->request->get('useNewAddress') === '1';

            $recipientName = trim(
                (string) $request->request->get('recipientName')
            );

            $phone = trim(
                (string) $request->request->get('phone')
            );

            $addressLine1 = trim(
                (string) $request->request->get('address')
            );

            $city = trim(
                (string) $request->request->get('city')
            );

            $country = trim(
                (string) $request->request->get('country', 'Madagascar')
            );

            // Vérifier les informations de livraison.
            if (!$useNewAddress && count($addresses) > 0) {
                $selectedAddress = $addressId
                    ? $addressRepository->findOneBy([
                        'id' => $addressId,
                        'user' => $user,
                    ])
                    : null;

                if (!$selectedAddress) {
                    $checkoutErrors['addressId'] =
                        'Veuillez sélectionner une adresse valide.';
                } else {
                    // Utiliser la ville de l'adresse sélectionnée.
                    $deliveryCity = $selectedAddress->getCity();
                }
            } else {
                // Vérifier les informations d'une nouvelle adresse.
                if ($recipientName === '') {
                    $checkoutErrors['recipientName'] =
                        'Veuillez renseigner le nom du destinataire.';
                }

                if ($phone === '') {
                    $checkoutErrors['phone'] =
                        'Veuillez renseigner le numéro de téléphone.';
                } elseif (!preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
                    $checkoutErrors['phone'] =
                        'Veuillez saisir un numéro de téléphone valide.';
                }

                if ($addressLine1 === '') {
                    $checkoutErrors['address'] =
                        'Veuillez renseigner votre adresse complète.';
                }

                if ($city === '') {
                    $checkoutErrors['city'] =
                        'Veuillez renseigner votre ville.';
                }

                if ($country === '') {
                    $checkoutErrors['country'] =
                        'Veuillez renseigner votre pays.';
                }

                // Utiliser la ville saisie pour calculer les frais.
                $deliveryCity = $city;
            }

            // Recalculer les frais selon l'adresse choisie.
            $deliveryFee = $this->calculateDeliveryFee($deliveryCity);
            $total = $subtotal + $deliveryFee;

            // Vérifier le mode de livraison choisi.
            if (!in_array(
                $deliveryMethod,
                ['home_delivery', 'taxi_brousse'],
                true
            )) {
                $checkoutErrors['deliveryMethod'] =
                    'Veuillez choisir un mode de livraison.';
            }

            // Calculer uniquement les frais de livraison sans créer la commande.
            if ($request->request->get('checkoutAction') === 'calculate_delivery_fee') {
                if (count($checkoutErrors) > 0) {
                    return $this->render('pages/checkout/index.html.twig', [
                        'cart' => $cart,
                        'items' => $items,
                        'addresses' => $addresses,
                        'subtotal' => $subtotal,
                        'deliveryFee' => $deliveryFee,
                        'total' => $total,
                        'user' => $user,
                        'checkoutErrors' => $checkoutErrors,
                        'formData' => $formData,
                    ]);
                }

                return $this->render('pages/checkout/index.html.twig', [
                    'cart' => $cart,
                    'items' => $items,
                    'addresses' => $addresses,
                    'subtotal' => $subtotal,
                    'deliveryFee' => $deliveryFee,
                    'total' => $total,
                    'user' => $user,
                    'checkoutErrors' => [],
                    'formData' => $formData,
                ]);
            }

            // Vérifier que l'acompte Mobile Money est obligatoire.
            if ($paymentMethod !== 'mobile_money_demo') {
                $checkoutErrors['paymentMethod'] =
                    'Le paiement de l’acompte par Mobile Money est obligatoire.';
            }


            // Vérifier l'opérateur Mobile Money.
            $allowedOperators = [
                'mvola',
                'orange_money',
                'airtel_money',
            ];

            if (!in_array($paymentOperator, $allowedOperators, true)) {
                $checkoutErrors['paymentOperator'] =
                    'Veuillez choisir un opérateur Mobile Money.';
            }

            // Récupérer le numéro associé à l'adresse de livraison choisie.
            $paymentPhone = $selectedAddress instanceof Address
                ? (string) $selectedAddress->getPhone()
                : $phone;

            // Normaliser le numéro pour accepter les formats locaux et internationaux.
            $phoneDigits = preg_replace('/\D+/', '', $paymentPhone) ?? '';

            if (str_starts_with($phoneDigits, '261')) {
                $phoneDigits = '0' . substr($phoneDigits, 3);
            } elseif (
                strlen($phoneDigits) === 9
                && str_starts_with($phoneDigits, '3')
            ) {
                $phoneDigits = '0' . $phoneDigits;
            }

            // Vérifier le format du numéro malgache.
            if (!preg_match('/^03\d{8}$/', $phoneDigits)) {
                $checkoutErrors['phone'] =
                    'Veuillez saisir un numéro Mobile Money malgache valide.';
            } else {
                // Définir les préfixes autorisés pour chaque opérateur.
                $operatorPrefixes = [
                    'mvola' => ['034', '038'],
                    'orange_money' => ['032', '037'],
                    'airtel_money' => ['033', '035'],
                ];

                $phonePrefix = substr($phoneDigits, 0, 3);

                // Vérifier la correspondance entre le numéro et l'opérateur.
                if (
                    isset($operatorPrefixes[$paymentOperator])
                    && !in_array(
                        $phonePrefix,
                        $operatorPrefixes[$paymentOperator],
                        true
                    )
                ) {
                    $checkoutErrors['phone'] =
                        'Le numéro saisi ne correspond pas à l’opérateur Mobile Money sélectionné.';
                }
            }


            // Vérifier l'acceptation des conditions.
            if ($request->request->get('acceptTerms') !== '1') {
                $checkoutErrors['acceptTerms'] =
                    'Vous devez accepter les conditions de commande.';
            }

            // Réafficher le formulaire en cas d'erreurs.
            if (count($checkoutErrors) > 0) {
                return $this->render('pages/checkout/index.html.twig', [
                    'cart' => $cart,
                    'items' => $items,
                    'addresses' => $addresses,
                    'subtotal' => $subtotal,
                    'deliveryFee' => $deliveryFee,
                    'total' => $total,
                    'user' => $user,
                    'checkoutErrors' => $checkoutErrors,
                    'formData' => $formData,
                ]);
            }

            // Choisir une adresse enregistrée ou créer une nouvelle adresse.
            if (!$useNewAddress && count($addresses) > 0) {
                $address = $selectedAddress;
            } else {
                $address = new Address();

                $address->setRecipientName($recipientName);
                $address->setPhone($phone);
                $address->setAddressLine1($addressLine1);

                $address->setAddressLine2(
                    trim((string) $request->request->get('addressLine2')) ?: null
                );

                $address->setCity($city);
                $address->setCountry($country);

                $address->setPostalCode(
                    trim((string) $request->request->get('postalCode')) ?: null
                );

                $address->setUser($user);

                $entityManager->persist($address);
            }

            // Vérifier les produits et les stocks avant la commande.
            foreach ($items as $item) {
                $product = $item->getProduct();
                $variant = $item->getVariant();

                if (!$product || !$product->isActive()) {
                    $this->addFlash(
                        'error',
                        'Un produit de votre panier n’est plus disponible.'
                    );

                    return $this->redirectToRoute('app_cart');
                }

                if (
                    !$variant
                    || !$variant->isActive()
                    || $variant->getStockQuantity() < $item->getQuantity()
                ) {
                    $this->addFlash(
                        'error',
                        'Le stock d’un produit de votre panier est insuffisant.'
                    );

                    return $this->redirectToRoute('app_cart');
                }
            }

            // Créer la commande avec les frais de livraison calculés.
            $order = new CustomerOrder();

            $order->setOrderNumber(
                'MB-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)))
            );

            $order->setStatus('pending');

            $order->setTotalAmount(
                number_format($total, 2, '.', '')
            );

            $order->setUser($user);
            $order->setAddress($address);

            $entityManager->persist($order);

            // Créer automatiquement la facture de la commande.
            $invoice = new Invoice();

            $invoice->setInvoiceNumber(
                'FAC-' . $order->getOrderNumber()
            );

            $order->setInvoice($invoice);

            $entityManager->persist($invoice);

            // Créer les lignes de commande et diminuer le stock.
            foreach ($items as $item) {
                $product = $item->getProduct();
                $variant = $item->getVariant();

                $orderItem = new OrderItem();

                $orderItem->setCustomerOrder($order);
                $orderItem->setProduct($product);
                $orderItem->setVariant($variant);
                $orderItem->setQuantity($item->getQuantity());
                $orderItem->setUnitPrice($item->getUnitPrice());

                $entityManager->persist($orderItem);

                // Déduire la quantité commandée du stock disponible.
                $variant->setStockQuantity(
                    $variant->getStockQuantity() - $item->getQuantity()
                );
            }

            // Enregistrer le paiement.
            $payment = new Payment();

            $payment->setCustomerOrder($order);
            $payment->setMethod($paymentMethod);
            $payment->setStatus('pending');

            // Calculer l'acompte obligatoire de 50 %.
            $paymentAmount = $total / 2;

            $payment->setAmount(
                number_format($paymentAmount, 2, '.', '')
            );

            // Enregistrer l'opérateur choisi.
            $payment->setTransactionReference(
                'DEMO-' . strtoupper($paymentOperator)
            );

            $order->addPayment($payment);
            $entityManager->persist($payment);

            // Créer les informations de livraison.
            $delivery = new Delivery();

            $delivery->setDeliveryMethod($deliveryMethod);

            $order->setDelivery($delivery);

            $entityManager->persist($delivery);

            // Marquer le panier comme commandé.
            $cart->setStatus('ordered');
            $cart->setUpdatedAt(new \DateTimeImmutable());

            // Enregistrer toutes les opérations.
            $entityManager->flush();

            // Conserver le numéro de commande pour la confirmation.
            $request->getSession()->set(
                'last_order_number',
                $order->getOrderNumber()
            );

            $this->addFlash(
                'success',
                'Votre commande a été enregistrée avec succès.'
            );

            return $this->redirectToRoute('app_order_confirmation');
        }

        // Afficher le formulaire de commande.
        return $this->render('pages/checkout/index.html.twig', [
            'cart' => $cart,
            'items' => $items,
            'addresses' => $addresses,
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'total' => $total,
            'user' => $user,
            'checkoutErrors' => $checkoutErrors,
            'formData' => $formData,
        ]);
    }

    /**
     * Calcule les frais de livraison selon la zone géographique.
     */
    private function calculateDeliveryFee(string $city): int
    {
        // Normaliser le nom de la ville pour faciliter la comparaison.
        $city = strtolower(trim($city));

        $city = strtr($city, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);

        // Zone 1 : Antananarivo centre.
        $zone1 = [
            'antananarivo',
            'antananarivo renivohitra',
            'tana',
            'tananarive',
            'analakely',
            'ankorondrano',
            'anosy',
            'isotry',
            'behoririka',
            '67 ha',
            'ambodivona',
            'ambatonakanga',
            'ambodihady',
        ];

        if (in_array($city, $zone1, true)) {
            return 5000;
        }

        // Zone 2 : périphérie d'Antananarivo.
        $zone2 = [
            'ambohidratrimo',
            'alasora',
            'ankadikely ilafy',
            'itaosy',
            'ivato',
            'talatamaty',
            'sabotsy namehana',
            'fenoarivo',
            'tanjombato',
            'andoharanofotsy',
            'ampitatafika',
            'ambohibao',
            'ambohijanaka',
            'anjoma',
            'anjepy',
            'ankadivoribe',
            'anosizato',
            'tsarasaotra',
        ];

        if (in_array($city, $zone2, true)) {
            return 8000;
        }

        // Zone 3 : principales villes des provinces.
        $zone3 = [
            'toamasina',
            'tamatave',
            'mahajanga',
            'majunga',
            'fianarantsoa',
            'toliara',
            'tulear',
            'antsiranana',
            'diego suarez',
            'antseranana',
            'antsirabe',
        ];

        if (in_array($city, $zone3, true)) {
            return 20000;
        }

        // Zone 4 : autres villes et zones éloignées.
        return 25000;
    }
}

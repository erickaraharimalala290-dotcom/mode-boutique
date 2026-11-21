<?php

namespace App\Controller\Account;

use App\Entity\User;
use App\Entity\ContactMessage;
use App\Entity\MessageReply;
use App\Entity\ReturnRequest;
use App\Repository\OrderItemRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\CartRepository;
use App\Repository\CustomerOrderRepository;
use App\Repository\MessageReplyRepository;
use App\Repository\ReturnRequestRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountController extends AbstractController
{

    #[Route('/mon_compte', name: 'app_account')]
    public function index(
        CustomerOrderRepository $customerOrderRepository,
        FavoriteRepository $favoriteRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les commandes du client connecté.
        $orders = $customerOrderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Récupérer les cinq dernières commandes.
        $latestOrders = array_slice($orders, 0, 5);

        // Compter les commandes.
        $totalOrders = count($orders);

        // Compter les favoris du client.
        $favoritesCount = $favoriteRepository->count([
            'user' => $user,
        ]);

        // Calculer le montant réellement payé.
        $totalPurchases = $customerOrderRepository->createQueryBuilder('o')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->innerJoin('o.payments', 'p')
            ->andWhere('o.user = :user')
            ->andWhere('LOWER(p.status) = :paid')
            ->andWhere('o.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('paid', 'paid')
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('pages/account/index.html.twig', [
            'totalOrders' => $totalOrders,
            'favoritesCount' => $favoritesCount,
            'totalPurchases' => (float) $totalPurchases,
            'latestOrders' => $latestOrders,
        ]);
    }


    #[Route('/account/profile', name: 'app_account_profile')]
    public function profile(): Response
    {
        return $this->render('pages/account/profile.html.twig');
    }


    #[Route('/account/profile/edit', name: 'app_account_profile_edit', methods: ['GET', 'POST'])]
    #[Route('/administration/profil/modifier', name: 'admin_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $isAdminArea = $this->isGranted('ROLE_MANAGER');

        if ($request->isMethod('POST')) {

            $firstname = trim((string) $request->request->get('firstName', ''));
            $lastname = trim((string) $request->request->get('lastName', ''));
            $email = trim((string) $request->request->get('email', ''));

            /** @var UploadedFile|null $photo */
            $photo = $request->files->get('profilePicture');

            $error = null;

            // Vérification des informations personnelles
            if (
                mb_strlen($firstname) < 2 ||
                mb_strlen($firstname) > 100 ||
                mb_strlen($lastname) < 2 ||
                mb_strlen($lastname) > 100 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                mb_strlen($email) > 180
            ) {
                $error = 'Veuillez vérifier les informations saisies.';
            }

            // Vérifier si l'adresse e-mail est déjà utilisée
            if ($error === null) {
                $existingUser = $entityManager
                    ->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (
                    $existingUser !== null &&
                    $existingUser->getId() !== $user->getId()
                ) {
                    $error = 'Cette adresse e-mail est déjà utilisée.';
                }
            }

            // Vérifier le fichier photo
            if ($error === null && $photo !== null) {
                if (!$photo->isValid()) {
                    $error = 'Le téléversement de la photo a échoué.';
                } elseif ($photo->getSize() > 5 * 1024 * 1024) {
                    $error = 'La photo ne doit pas dépasser 5 Mo.';
                } else {
                    $imageInfo = @getimagesize($photo->getPathname());
                    $mimeType = $imageInfo['mime'] ?? '';

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];

                    if (
                        $imageInfo === false ||
                        !isset($allowedTypes[$mimeType])
                    ) {
                        $error = 'Choisissez une véritable image JPG, PNG ou WebP.';
                    }
                }
            }

            if ($error !== null) {
                $this->addFlash('error', $error);
            } else {
                // Enregistrer la nouvelle photo si une photo a été choisie
                if ($photo !== null) {
                    $uploadDirectory = $this->getParameter('kernel.project_dir')
                        . '/public/uploads/profile';

                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true) &&
                        !is_dir($uploadDirectory)
                    ) {
                        throw new \RuntimeException(
                            'Impossible de créer le dossier des photos de profil.'
                        );
                    }

                    $extension = $allowedTypes[$mimeType];
                    $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;

                    $oldFilename = $user->getProfilePicture();

                    $photo->move($uploadDirectory, $newFilename);

                    $user->setProfilePicture($newFilename);

                    // Supprimer l'ancienne photo après le remplacement
                    if ($oldFilename !== null) {
                        $oldPath = $uploadDirectory . '/' . basename($oldFilename);

                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }

                $user->setFirstname($firstname);
                $user->setLastname($lastname);
                $user->setEmail($email);
                $user->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();

                $this->addFlash('success', 'Votre profil a été mis à jour.');

                return $this->redirectToRoute(
                    $isAdminArea ? 'admin_profile_edit' : 'app_account_profile_edit'
                );
            }
        }

        return $this->render(
            $isAdminArea
                ? 'admin/profile/edit.html.twig'
                : 'pages/account/profile_edit.html.twig',
            [
                'user' => $user,
            ]
        );
    }



    #[Route('/account/security', name: 'app_account_security', methods: ['GET', 'POST'])]
    #[Route('/administration/profil/securite', name: 'admin_profile_security', methods: ['GET', 'POST'])]
    public function security(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $isAdminArea = $this->isGranted('ROLE_MANAGER');

        if ($request->isMethod('POST')) {
            $currentPassword = (string) $request->request->get('currentPassword', '');
            $newPassword = (string) $request->request->get('newPassword', '');
            $confirmPassword = (string) $request->request->get('confirmPassword', '');
            $csrfToken = (string) $request->request->get('_csrf_token', '');

            if (!$this->isCsrfTokenValid('change_password', $csrfToken)) {
                $this->addFlash('error', 'La requête a expiré. Veuillez réessayer.');
            } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Votre mot de passe actuel est incorrect.');
            } elseif (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les deux nouveaux mots de passe ne correspondent pas.');
            } elseif ($currentPassword === $newPassword) {
                $this->addFlash('error', 'Le nouveau mot de passe doit être différent de l’ancien.');
            } else {
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);

                $user->setPassword($hashedPassword);
                $user->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');

                return $this->redirectToRoute(
                    $isAdminArea ? 'admin_profile_security' : 'app_account_security'
                );
            }
        }

        return $this->render(
            $isAdminArea
                ? 'admin/profile/security.html.twig'
                : 'pages/account/security.html.twig'
        );
    }



    #[Route('/account/orders', name: 'app_account_orders')]
    public function orders(
        CustomerOrderRepository $customerOrderRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement les commandes du client connecté.
        $orders = $customerOrderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Préparer les libellés et les styles des statuts.
        $statusLabels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
        ];

        $statusClasses = [
            'pending' => 'bg-amber-50 text-amber-800',
            'confirmed' => 'bg-blue-50 text-blue-700',
            'processing' => 'bg-orange-50 text-orange-700',
            'shipped' => 'bg-indigo-50 text-indigo-700',
            'delivered' => 'bg-green-50 text-green-700',
            'cancelled' => 'bg-red-50 text-red-700',
        ];

        $ordersData = [];
        $processingCount = 0;
        $deliveredCount = 0;

        foreach ($orders as $order) {
            $status = strtolower($order->getStatus());

            $ordersData[] = [
                'order' => $order,
                'statusLabel' => $statusLabels[$status] ?? ucfirst($status),
                'statusClass' => $statusClasses[$status] ?? 'bg-gray-100 text-gray-700',
            ];

            if (in_array($status, ['pending', 'confirmed', 'processing', 'shipped'], true)) {
                $processingCount++;
            }

            if ($status === 'delivered') {
                $deliveredCount++;
            }
        }

        return $this->render('pages/account/orders.html.twig', [
            'ordersData' => $ordersData,
            'totalOrders' => count($orders),
            'processingCount' => $processingCount,
            'deliveredCount' => $deliveredCount,
        ]);
    }



    #[Route(
        '/account/orders/{orderNumber}',
        name: 'app_account_order_detail',
        methods: ['GET']
    )]
    public function orderDetail(
        string $orderNumber,
        CustomerOrderRepository $customerOrderRepository,
        ReturnRequestRepository $returnRequestRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

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

        // Calculer le sous-total des articles.
        $subtotal = 0;

        foreach ($order->getOrderItems() as $item) {
            $subtotal += (float) $item->getUnitPrice()
                * $item->getQuantity();
        }

        $total = (float) $order->getTotalAmount();

        // Calculer les frais de livraison.
        $deliveryFee = max(0, $total - $subtotal);

        // Récupérer le mode de livraison.
        $deliveryMethod = $order->getDelivery()?->getDeliveryMethod();

        // Identifier le paiement initial et calculer les montants encaissés.
        $payment = null;
        $paidAmount = 0;
        $depositPaid = false;

        foreach ($order->getPayments() as $currentPayment) {

            // Ne pas intégrer les paiements liés aux demandes d'échange.
            if ($currentPayment->getReturnRequest() !== null) {
                continue;
            }

            $reference = (string) $currentPayment->getTransactionReference();

            $isBalancePayment = str_starts_with(
                $reference,
                'BALANCE-'
            );

            // Identifier le paiement initial.
            if (!$isBalancePayment && $payment === null) {
                $payment = $currentPayment;
            }

            // Comptabiliser uniquement les paiements confirmés.
            if (strtolower((string) $currentPayment->getStatus()) === 'paid') {
                $paidAmount += (float) $currentPayment->getAmount();

                if (
                    !$isBalancePayment
                    && $currentPayment->getMethod() === 'mobile_money_demo'
                ) {
                    $depositPaid = true;
                }
            }
        }

        // Calculer le solde restant de la commande.
        $remainingAmount = max(0, $total - $paidAmount);

        // Vérifier si le client peut régler le solde.
        $canPayBalance = $depositPaid
            && $remainingAmount > 0
            && $order->getStatus() !== 'cancelled';

        // Le paiement en espèces est réservé à la livraison à domicile.
        $canPayCash = $deliveryMethod === 'home_delivery';

        // Préparer le libellé du paiement initial.
        $paymentLabels = [
            'mobile_money_demo' => 'Mobile Money — acompte de 50 %',
        ];

        $paymentLabel = $payment
            ? ($paymentLabels[$payment->getMethod()] ?? $payment->getMethod())
            : 'Non renseigné';

        // Vérifier si la commande est éligible à une demande de retour.
        $deliveredAt = $order->getDelivery()?->getDeliveredAt();

        $canRequestReturn = $order->getStatus() === 'delivered'
            && $deliveredAt !== null
            && $deliveredAt >= new \DateTimeImmutable('-7 days');

        // Récupérer les demandes de retour et leurs paiements.
        $returnRequestsByItem = [];
        $exchangePaymentsByRequest = [];

        $paymentRepository = $entityManager->getRepository(
            \App\Entity\Payment::class
        );

        foreach ($order->getOrderItems() as $item) {
            $returnRequests = $returnRequestRepository->findBy(
                ['orderItem' => $item],
                ['requestedAt' => 'DESC']
            );

            $returnRequestsByItem[$item->getId()] = $returnRequests;

            foreach ($returnRequests as $returnRequest) {
                $exchangePaymentsByRequest[$returnRequest->getId()] =
                    $paymentRepository->findOneBy([
                        'returnRequest' => $returnRequest,
                    ]);
            }
        }

        return $this->render('pages/account/order_detail.html.twig', [
            'order' => $order,
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'payment' => $payment,
            'paymentLabel' => $paymentLabel,
            'paidAmount' => $paidAmount,
            'remainingAmount' => $remainingAmount,
            'canPayBalance' => $canPayBalance,
            'canPayCash' => $canPayCash,
            'canRequestReturn' => $canRequestReturn,
            'returnRequestsByItem' => $returnRequestsByItem,
            'exchangePaymentsByRequest' => $exchangePaymentsByRequest,
        ]);
    }


    #[Route(
        '/account/orders/article/{id}/retour',
        name: 'app_account_return_request',
        methods: ['GET', 'POST']
    )]
    public function requestReturn(
        int $id,
        Request $request,
        OrderItemRepository $orderItemRepository,
        ReturnRequestRepository $returnRequestRepository,
        ProductVariantRepository $productVariantRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'article commandé.
        $orderItem = $orderItemRepository->find($id);

        if (!$orderItem) {
            throw $this->createNotFoundException(
                'Cet article commandé est introuvable.'
            );
        }

        // Vérifier que la commande appartient au client connecté.
        $order = $orderItem->getCustomerOrder();

        if ($order->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier le délai de retour de 7 jours après la livraison.
        $deliveredAt = $order->getDelivery()?->getDeliveredAt();

        if (
            $order->getStatus() !== 'delivered'
            || $deliveredAt === null
            || $deliveredAt < new \DateTimeImmutable('-7 days')
        ) {
            $this->addFlash(
                'error',
                'Le délai de demande d’échange est dépassé ou la commande n’est pas encore livrée.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        // Vérifier si une demande existe déjà pour cet article.
        $existingRequests = $returnRequestRepository->findBy([
            'orderItem' => $orderItem,
        ]);

        if (count($existingRequests) > 0) {
            $this->addFlash(
                'error',
                'Une demande d’échange existe déjà pour cet article.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        // Récupérer les variantes disponibles pour un remplacement.
        $replacementVariants = $productVariantRepository
            ->findAvailableForReplacement();

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token', '');

            $type = (string) $request->request->get('type', '');
            $reason = trim((string) $request->request->get('reason', ''));
            $description = trim(
                (string) $request->request->get('description', '')
            );
            $quantity = (int) $request->request->get('quantity', 0);

            $replacementVariantId = filter_var(
                $request->request->get('replacementVariant'),
                FILTER_VALIDATE_INT
            );

            if (!$this->isCsrfTokenValid(
                'return_request_' . $orderItem->getId(),
                $csrfToken
            )) {
                $this->addFlash(
                    'error',
                    'La requête a expiré. Veuillez réessayer.'
                );
            } elseif ($type !== 'exchange') {
                $this->addFlash(
                    'error',
                    'Seules les demandes d’échange sont autorisées.'
                );
            } elseif ($reason === '' || mb_strlen($reason) > 100) {
                $this->addFlash(
                    'error',
                    'Veuillez préciser le motif de votre demande.'
                );
            } elseif (
                $quantity < 1
                || $quantity > $orderItem->getQuantity()
            ) {
                $this->addFlash(
                    'error',
                    'La quantité demandée est invalide.'
                );
            } elseif (
                $replacementVariantId === false
                || $replacementVariantId < 1
            ) {
                $this->addFlash(
                    'error',
                    'Veuillez choisir un produit de remplacement.'
                );
            } else {
                $replacementVariant = $productVariantRepository
                    ->find($replacementVariantId);

                if (
                    !$replacementVariant
                    || !$replacementVariant->isActive()
                    || $replacementVariant->getStockQuantity() < $quantity
                    || !$replacementVariant->getProduct()->isActive()
                ) {
                    $this->addFlash(
                        'error',
                        'Cette variante n’est plus disponible en quantité suffisante. Veuillez en choisir une autre.'
                    );
                } elseif (
                    $orderItem->getVariant() !== null
                    && $orderItem->getProduct()->getId()
                    === $replacementVariant->getProduct()->getId()
                    && $orderItem->getVariant()->getColor()
                    === $replacementVariant->getColor()
                    && $orderItem->getVariant()->getSize()
                    === $replacementVariant->getSize()
                ) {
                    $this->addFlash(
                        'error',
                        'Vous avez déjà commandé cette même variante. Veuillez choisir une autre taille ou couleur.'
                    );
                } else {
                    $replacementProduct = $replacementVariant->getProduct();

                    // Récupérer le prix de la variante ou celui du produit.
                    $replacementPrice = (float) (
                        $replacementVariant->getPriceOverride()
                        ?? $replacementProduct->getPrice()
                    );

                    $originalPrice = (float) $orderItem->getUnitPrice();

                    // Calculer la différence totale selon la quantité demandée.
                    $priceDifferenceAmount = round(
                        ($replacementPrice - $originalPrice) * $quantity,
                        2
                    );

                    // Refuser un remplacement moins cher que l'article original.
                    if ($priceDifferenceAmount < 0) {
                        $this->addFlash(
                            'error',
                            'Le produit de remplacement doit avoir un prix égal ou supérieur à celui de l’article original.'
                        );
                    } else {
                        $replacementUnitPrice = number_format(
                            $replacementPrice,
                            2,
                            '.',
                            ''
                        );

                        $priceDifference = number_format(
                            $priceDifferenceAmount,
                            2,
                            '.',
                            ''
                        );

                        // Calculer les frais de livraison du nouvel échange.
                        $exchangeDeliveryFee = 0.00;

                        // Les changements de taille ou de couleur sont à la charge du client.
                        if (in_array($reason, [
                            'Taille incorrecte',
                            'Couleur différente',
                        ], true)) {
                            // Calculer le sous-total des articles de la commande originale.
                            $orderSubtotal = 0;

                            foreach ($order->getOrderItems() as $item) {
                                $orderSubtotal +=
                                    (float) $item->getUnitPrice()
                                    * $item->getQuantity();
                            }

                            // Récupérer les frais de livraison déjà appliqués à la commande.
                            $exchangeDeliveryFee = max(
                                0,
                                (float) $order->getTotalAmount() - $orderSubtotal
                            );
                        }

                        // Créer la demande d'échange.
                        $returnRequest = new ReturnRequest();

                        $returnRequest->setRequestNumber(
                            'RET-' . strtoupper(bin2hex(random_bytes(5)))
                        );

                        $returnRequest->setType('exchange');
                        $returnRequest->setReason($reason);
                        $returnRequest->setDescription(
                            $description !== '' ? $description : null
                        );
                        $returnRequest->setQuantity($quantity);
                        $returnRequest->setStatus(
                            ReturnRequest::STATUS_PENDING
                        );
                        $returnRequest->setOrderItem($orderItem);

                        // Enregistrer les informations du remplacement.
                        $returnRequest->setReplacementProduct(
                            $replacementProduct
                        );
                        $returnRequest->setReplacementVariant(
                            $replacementVariant
                        );
                        $returnRequest->setReplacementUnitPrice(
                            $replacementUnitPrice
                        );
                        $returnRequest->setPriceDifference(
                            $priceDifference
                        );

                        // Enregistrer les frais de livraison de l'échange.
                        $returnRequest->setExchangeDeliveryFee(
                            number_format($exchangeDeliveryFee, 2, '.', '')
                        );

                        $entityManager->persist($returnRequest);
                        $entityManager->flush();

                        $this->addFlash(
                            'success',
                            'Votre demande d’échange a été envoyée avec succès.'
                        );

                        return $this->redirectToRoute(
                            'app_account_order_detail',
                            ['orderNumber' => $order->getOrderNumber()]
                        );
                    }
                }
            }
        }

        return $this->render('pages/account/return_request.html.twig', [
            'orderItem' => $orderItem,
            'replacementVariants' => $replacementVariants,
        ]);
    }


    #[Route(
        '/account/retours/{id}/payer-difference',
        name: 'app_account_exchange_payment',
        methods: ['POST']
    )]
    public function payExchangeDifference(
        int $id,
        Request $request,
        ReturnRequestRepository $returnRequestRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer la demande d'échange.
        $returnRequest = $returnRequestRepository->find($id);

        if (!$returnRequest) {
            throw $this->createNotFoundException(
                "Cette demande d'échange est introuvable."
            );
        }

        $orderItem = $returnRequest->getOrderItem();
        $order = $orderItem->getCustomerOrder();

        // Vérifier que la commande appartient au client connecté.
        if ($order->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que la demande est un échange accepté.
        if (
            $returnRequest->getType() !== 'exchange'
            || $returnRequest->getStatus() !== ReturnRequest::STATUS_APPROVED
        ) {
            $this->addFlash(
                'error',
                'Cette demande ne permet pas actuellement un paiement.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        // Récupérer les montants de l'échange.
        $priceDifference = (float) $returnRequest->getPriceDifference();
        $exchangeDeliveryFee = (float) $returnRequest->getExchangeDeliveryFee();

        // Calculer le montant total à payer.
        $totalAmount = round(
            $priceDifference + $exchangeDeliveryFee,
            2
        );

        // Vérifier si un paiement est réellement nécessaire.
        if ($totalAmount <= 0) {
            $this->addFlash(
                'info',
                'Aucun paiement supplémentaire n’est nécessaire pour cet échange.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        // Vérifier que la variante de remplacement est toujours disponible.
        $replacementVariant = $returnRequest->getReplacementVariant();

        if (
            !$replacementVariant
            || !$replacementVariant->isActive()
            || !$replacementVariant->getProduct()->isActive()
            || $replacementVariant->getStockQuantity() < $returnRequest->getQuantity()
        ) {
            $this->addFlash(
                'error',
                'La variante de remplacement n’est plus disponible en quantité suffisante.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        $action = (string) $request->request->get('action', 'start');

        // Vérifier le jeton CSRF selon l'action demandée.
        $csrfTokenId = $action === 'confirm'
            ? 'confirm_exchange_payment_' . $id
            : 'start_exchange_payment_' . $id;

        $csrfToken = (string) $request->request->get('_token', '');

        if (
            !in_array($action, ['start', 'confirm'], true)
            || !$this->isCsrfTokenValid($csrfTokenId, $csrfToken)
        ) {
            $this->addFlash(
                'error',
                'La requête a expiré. Veuillez réessayer.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        $paymentRepository = $entityManager->getRepository(
            \App\Entity\Payment::class
        );

        // Récupérer le paiement déjà associé à cette demande.
        $payment = $paymentRepository->findOneBy([
            'returnRequest' => $returnRequest,
        ]);

        // Confirmer la simulation du paiement.
        if ($action === 'confirm') {
            if (!$payment || $payment->getStatus() !== 'pending') {
                $this->addFlash(
                    'error',
                    'Ce paiement ne peut pas être confirmé.'
                );

                return $this->redirectToRoute(
                    'app_account_order_detail',
                    ['orderNumber' => $order->getOrderNumber()]
                );
            }

            // Enregistrer le montant total payé.
            $payment->setAmount(
                number_format($totalAmount, 2, '.', '')
            );

            $payment->setStatus('paid');
            $payment->setPaidAt(new \DateTimeImmutable());

            $payment->setTransactionReference(
                'EXCHANGE-' . $returnRequest->getRequestNumber()
                    . '-' . strtoupper(bin2hex(random_bytes(4)))
            );

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le paiement de votre échange, y compris les frais de livraison, a été effectué avec succès.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        // Créer un paiement séparé ou réutiliser celui qui existe.
        if (!$payment) {
            $payment = new \App\Entity\Payment();

            $payment->setCustomerOrder($order);
            $payment->setReturnRequest($returnRequest);
            $payment->setMethod('mobile_money_demo');
            $payment->setStatus('pending');

            $payment->setAmount(
                number_format($totalAmount, 2, '.', '')
            );

            $entityManager->persist($payment);
        } elseif ($payment->getStatus() !== 'paid') {
            // Réinitialiser un éventuel paiement non confirmé.
            $payment->setStatus('pending');
            $payment->setMethod('mobile_money_demo');

            $payment->setAmount(
                number_format($totalAmount, 2, '.', '')
            );

            $payment->setPaidAt(null);
            $payment->setTransactionReference(null);
        } else {
            $this->addFlash(
                'success',
                'Le paiement de cet échange a déjà été effectué.'
            );

            return $this->redirectToRoute(
                'app_account_order_detail',
                ['orderNumber' => $order->getOrderNumber()]
            );
        }

        $entityManager->flush();

        return $this->render(
            'pages/account/exchange_payment.html.twig',
            [
                'returnRequest' => $returnRequest,
                'payment' => $payment,
            ]
        );
    }


    #[Route('/account/favorites', name: 'app_account_favorites', methods: ['GET'])]
    public function favorites(
        FavoriteRepository $favoriteRepository
    ): Response {
        $user = $this->getUser();

        // Vérifier que le client est connecté.
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les favoris du client connecté.
        $favorites = $favoriteRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('pages/account/favorites.html.twig', [
            'favorites' => $favorites,
            'favoritesCount' => count($favorites),
        ]);
    }

    #[Route('/account/cart', name: 'app_account_cart')]
    public function cart(CartRepository $cartRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer le panier actif du client connecté.
        $cart = $cartRepository->findOneBy([
            'user' => $user,
            'status' => 'active',
        ]);

        $items = $cart ? $cart->getItems()->toArray() : [];

        $subtotal = 0;
        $cartCount = 0;

        // Calculer le sous-total et le nombre total d'articles.
        foreach ($items as $item) {
            $subtotal += (float) $item->getUnitPrice() * $item->getQuantity();
            $cartCount += $item->getQuantity();
        }

        return $this->render('pages/account/cart.html.twig', [
            'cart' => $cart,
            'items' => $items,
            'subtotal' => $subtotal,
            'cartCount' => $cartCount,
        ]);
    }



    #[Route('/account/messages', name: 'app_account_messages', methods: ['GET'])]
    public function messages(
        ContactMessageRepository $contactMessageRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement les conversations du client connecté.
        $messages = $contactMessageRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('pages/account/messages.html.twig', [
            'messages' => $messages,
        ]);
    }


    #[Route('/account/messages/{id}', name: 'app_account_message_show', methods: ['GET'])]
    public function show(
        ContactMessage $contactMessage,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que la conversation appartient au client connecté.
        if ($contactMessage->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Cette conversation est introuvable.');
        }

        // Marquer comme lues uniquement les réponses de l'administrateur.
        $hasChanges = false;

        foreach ($contactMessage->getMessageReplies() as $reply) {
            $sender = $reply->getSender();

            if (
                $sender !== null &&
                in_array('ROLE_ADMIN', $sender->getRoles(), true) &&
                !$reply->isReadByClient()
            ) {
                $reply->setIsReadByClient(true);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $entityManager->flush();
        }

        return $this->render('pages/account/message_show.html.twig', [
            'message' => $contactMessage,
        ]);
    }


    #[Route(
        '/account/messages/{id}/reply',
        name: 'app_account_message_reply',
        methods: ['POST']
    )]
    public function replyToMessage(
        ContactMessage $contactMessage,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que le message appartient au client connecté.
        if ($contactMessage->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Ce message est introuvable.');
        }

        $content = trim((string) $request->request->get('content', ''));
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('client_reply_' . $contactMessage->getId(), $csrfToken)) {
            $this->addFlash('error', 'La requête a expiré. Veuillez réessayer.');
        } elseif ($content === '') {
            $this->addFlash('error', 'Veuillez saisir votre réponse.');
        } else {
            // Enregistrer la réponse du client.
            $reply = new MessageReply();
            $reply->setContactMessage($contactMessage);
            $reply->setSender($user);
            $reply->setContent($content);

            // Signaler à l'administrateur qu'une nouvelle réponse est arrivée.
            $contactMessage->setIsRead(false);

            $entityManager->persist($reply);
            $entityManager->flush();

            $this->addFlash('success', 'Votre réponse a été envoyée.');
        }

        return $this->redirectToRoute('app_account_message_show', [
            'id' => $contactMessage->getId(),
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\ProductPromotionCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    /**
     * Afficher le panier du client.
     */
    #[Route('/cart', name: 'app_cart', methods: ['GET'])]
    public function index(
        Request $request,
        CartRepository $cartRepository,
        ProductPromotionCalculator $promotionCalculator,
        EntityManagerInterface $entityManager
    ): Response {
        $cart = $this->findCurrentCart($request, $cartRepository);

        $items = $cart ? $cart->getItems()->toArray() : [];

        $subtotal = 0;
        $count = 0;
        $pricesUpdated = false;

        foreach ($items as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();

            if (!$product) {
                continue;
            }

            // Récupérer le prix de base de la variante ou du produit.
            $basePrice = $variant?->getPriceOverride() ?? $product->getPrice();

            if ($basePrice !== null) {
                // Calculer le prix en tenant compte des promotions actives.
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
            }

            $subtotal += (float) $item->getUnitPrice() * $item->getQuantity();
            $count += $item->getQuantity();
        }

        // Enregistrer les nouveaux prix si nécessaire.
        if ($pricesUpdated) {
            $entityManager->flush();
        }

        return $this->render('pages/cart/index.html.twig', [
            'cart' => $cart,
            'items' => $items,
            'subtotal' => $subtotal,
            'cartCount' => $count,
        ]);
    }

    /**
     * Ajouter une variante au panier.
     */
    #[Route('/cart/ajouter/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(
        ProductVariant $variant,
        Request $request,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager,
        ProductPromotionCalculator $promotionCalculator
    ): Response {
        $product = $variant->getProduct();

        if (!$product || !$product->isActive() || !$variant->isActive()) {
            $this->addFlash('error', 'Ce produit n’est plus disponible.');

            return $this->redirectToRoute('app_cart');
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'cart_add',
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        // Vérifier la quantité demandée.
        $quantity = filter_var(
            $request->request->get('quantity'),
            FILTER_VALIDATE_INT
        );

        if ($quantity === false || $quantity < 1) {
            $this->addFlash('error', 'Veuillez sélectionner une quantité valide.');

            return $this->redirectToRoute('app_product_detail', [
                'slug' => $product->getSlug(),
            ]);
        }

        $stock = $variant->getStockQuantity();

        if ($stock <= 0) {
            $this->addFlash('error', 'Cette variante est en rupture de stock.');

            return $this->redirectToRoute('app_product_detail', [
                'slug' => $product->getSlug(),
            ]);
        }

        // Récupérer ou créer le panier du client.
        $cart = $this->getOrCreateCart($request, $cartRepository);

        // Rechercher si cette variante existe déjà dans le panier.
        $existingItem = null;

        foreach ($cart->getItems() as $item) {
            if ($item->getVariant()?->getId() === $variant->getId()) {
                $existingItem = $item;
                break;
            }
        }

        $newQuantity = $quantity;

        if ($existingItem) {
            $newQuantity += $existingItem->getQuantity();
        }

        // Ne jamais dépasser le stock disponible.
        if ($newQuantity > $stock) {
            $this->addFlash(
                'error',
                sprintf('Stock insuffisant. Il reste seulement %d article(s).', $stock)
            );

            return $this->redirectToRoute('app_product_detail', [
                'slug' => $product->getSlug(),
            ]);
        }

        // Déterminer le prix de base de la variante.
        $basePrice = $variant->getPriceOverride() ?? $product->getPrice();

        if ($basePrice === null) {
            $this->addFlash('error', 'Le prix de ce produit est indisponible.');

            return $this->redirectToRoute('app_product_detail', [
                'slug' => $product->getSlug(),
            ]);
        }

        // Appliquer la meilleure promotion disponible.
        $pricing = $promotionCalculator->calculate(
            $product,
            (float) $basePrice
        );

        $unitPrice = number_format(
            $pricing['price'],
            2,
            '.',
            ''
        );

        if ($existingItem) {
            // Mettre à jour la quantité et le prix de l'article existant.
            $existingItem->setQuantity($newQuantity);
            $existingItem->setUnitPrice($unitPrice);
            $existingItem->setUpdatedAt(new \DateTimeImmutable());
        } else {
            // Créer une nouvelle ligne dans le panier.
            $cartItem = new CartItem();
            $cartItem->setProduct($product);
            $cartItem->setVariant($variant);
            $cartItem->setQuantity($quantity);
            $cartItem->setUnitPrice($unitPrice);

            $cart->addItem($cartItem);

            $entityManager->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($cart);
        $entityManager->flush();

        $this->addFlash('success', 'Le produit a été ajouté à votre panier.');

        return $this->redirectToRoute('app_product_detail', [
            'slug' => $product->getSlug(),
        ]);
    }

    /**
     * Modifier la quantité d'un article.
     */
    #[Route(
        '/cart/item/{id}/quantite',
        name: 'app_cart_update_quantity',
        methods: ['POST']
    )]
    public function updateQuantity(
        int $id,
        Request $request,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $cart = $this->findCurrentCart($request, $cartRepository);
        $item = $cart ? $this->findCartItem($cart, $id) : null;

        if (!$cart || !$item) {
            $this->addFlash('error', 'Article introuvable dans votre panier.');

            return $this->redirectToCartPage($request);
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'cart_update_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        // Vérifier la quantité demandée.
        $quantity = filter_var(
            $request->request->get('quantity'),
            FILTER_VALIDATE_INT
        );

        if ($quantity === false || $quantity < 1) {
            $this->addFlash('error', 'Veuillez saisir une quantité valide.');

            return $this->redirectToCartPage($request);
        }

        $variant = $item->getVariant();

        // Vérifier le stock disponible.
        if ($variant) {
            if (!$variant->isActive() || $quantity > $variant->getStockQuantity()) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Quantité indisponible. Stock actuel : %d article(s).',
                        $variant->getStockQuantity()
                    )
                );

                return $this->redirectToCartPage($request);
            }
        }

        // Enregistrer la nouvelle quantité.
        $item->setQuantity($quantity);
        $item->setUpdatedAt(new \DateTimeImmutable());

        $cart->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'La quantité a été mise à jour.');

        return $this->redirectToCartPage($request);
    }

    /**
     * Supprimer un article du panier.
     */
    #[Route(
        '/cart/item/{id}/supprimer',
        name: 'app_cart_remove',
        methods: ['POST']
    )]
    public function remove(
        int $id,
        Request $request,
        CartRepository $cartRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $cart = $this->findCurrentCart($request, $cartRepository);
        $item = $cart ? $this->findCartItem($cart, $id) : null;

        if (!$cart || !$item) {
            $this->addFlash('error', 'Article introuvable dans votre panier.');

            return $this->redirectToCartPage($request);
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'cart_remove_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        // Supprimer l'article du panier.
        $cart->removeItem($item);
        $cart->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'L’article a été supprimé du panier.');

        return $this->redirectToCartPage($request);
    }

    /**
     * Récupérer le panier actif du client connecté ou du visiteur.
     */
    private function findCurrentCart(
        Request $request,
        CartRepository $cartRepository
    ): ?Cart {
        $user = $this->getUser();

        if ($user instanceof User) {
            return $cartRepository->findOneBy([
                'user' => $user,
                'status' => 'active',
            ]);
        }

        $token = $request->getSession()->get('cart_token');

        if (!is_string($token) || $token === '') {
            return null;
        }

        return $cartRepository->findOneBy([
            'sessionToken' => $token,
            'status' => 'active',
        ]);
    }

    /**
     * Récupérer ou créer le panier actif.
     */
    private function getOrCreateCart(
        Request $request,
        CartRepository $cartRepository
    ): Cart {
        $user = $this->getUser();

        if ($user instanceof User) {
            $cart = $cartRepository->findOneBy([
                'user' => $user,
                'status' => 'active',
            ]);

            if ($cart) {
                return $cart;
            }

            $cart = new Cart();
            $cart->setUser($user);

            $user->addCart($cart);

            return $cart;
        }

        // Créer un identifiant de session pour le visiteur.
        $session = $request->getSession();

        $token = $session->get('cart_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set('cart_token', $token);
        }

        $cart = $cartRepository->findOneBy([
            'sessionToken' => $token,
            'status' => 'active',
        ]);

        if ($cart) {
            return $cart;
        }

        $cart = new Cart();
        $cart->setSessionToken($token);

        return $cart;
    }

    /**
     * Rechercher un article appartenant au panier courant.
     */
    private function findCartItem(Cart $cart, int $id): ?CartItem
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Rediriger le client vers la page de panier utilisée.
     */
    private function redirectToCartPage(Request $request): Response
    {
        if ($request->request->get('return_to') === 'account') {
            return $this->redirectToRoute('app_account_cart');
        }

        return $this->redirectToRoute('app_cart');
    }
}

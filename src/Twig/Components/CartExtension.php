<?php

namespace App\Twig\Components;

use App\Entity\User;
use App\Repository\CartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CartExtension extends AbstractExtension
{
    public function __construct(
        private CartRepository $cartRepository,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_count', [$this, 'getCartCount']),
        ];
    }

    /**
     * Calculer la quantité totale des articles du panier actif.
     */
    public function getCartCount(): int
    {
        $user = $this->security->getUser();

        // Récupérer le panier du client connecté.
        if ($user instanceof User) {
            $cart = $this->cartRepository->findOneBy([
                'user' => $user,
                'status' => 'active',
            ]);
        } else {
            // Récupérer le panier du visiteur grâce à son identifiant de session.
            $request = $this->requestStack->getCurrentRequest();

            if (!$request || !$request->hasSession()) {
                return 0;
            }

            $token = $request->getSession()->get('cart_token');

            if (!is_string($token) || $token === '') {
                return 0;
            }

            $cart = $this->cartRepository->findOneBy([
                'sessionToken' => $token,
                'status' => 'active',
            ]);
        }

        if (!$cart) {
            return 0;
        }

        // Additionner les quantités de tous les articles.
        $count = 0;

        foreach ($cart->getItems() as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }
}

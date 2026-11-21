<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FavoritesController extends AbstractController
{

    // Afficher la liste publique des favoris.
    #[Route('/favorites', name: 'app_favorites', methods: ['GET'])]
    public function index(
        FavoriteRepository $favoriteRepository
    ): Response {
        // Vérifier que le client est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                'Connectez-vous pour consulter vos favoris.'
            );

            return $this->redirectToRoute('app_login');
        }

        // Récupérer les favoris du client connecté.
        $favorites = $favoriteRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('pages/favorites/index.html.twig', [
            'favorites' => $favorites,
            'favoritesCount' => count($favorites),
        ]);
    }

    // Ajouter un produit aux favoris.
    #[Route('/favorites/{id}/add', name: 'app_favorite_add', methods: ['POST'])]
    public function add(
        Product $product,
        Request $request,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que le client est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                'Connectez-vous pour ajouter un produit à vos favoris.'
            );

            return $this->redirectToRoute('app_login');
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'favorite_add_' . $product->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Vérifier si le produit existe déjà dans les favoris.
        $existingFavorite = $favoriteRepository->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);

        if (!$existingFavorite) {
            $favorite = new Favorite();
            $favorite->setUser($user);
            $favorite->setProduct($product);

            $entityManager->persist($favorite);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le produit a été ajouté à vos favoris.'
            );
        } else {
            $this->addFlash(
                'success',
                'Ce produit figure déjà dans vos favoris.'
            );
        }

        // Revenir à la page depuis laquelle le formulaire a été envoyé.
        $referer = $request->headers->get('referer');

        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);

            if ($refererHost === $request->getHost()) {
                return $this->redirect($referer);
            }
        }

        return $this->redirectToRoute('app_account_favorites');
    }


    // Retirer un produit de la liste des favoris.
    #[Route('/favorites/{id}/remove', name: 'app_favorite_remove', methods: ['POST'])]
    public function remove(
        Favorite $favorite,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que le client est connecté.
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que le favori appartient au client connecté.
        if ($favorite->getUser() !== $user) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas supprimer ce favori.'
            );
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'favorite_remove_' . $favorite->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        // Supprimer le favori de la base de données.
        $entityManager->remove($favorite);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Le produit a été retiré de vos favoris.'
        );

        // Revenir à la page depuis laquelle le formulaire a été envoyé.
        $referer = $request->headers->get('referer');

        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);

            if ($refererHost === $request->getHost()) {
                return $this->redirect($referer);
            }
        }

        return $this->redirectToRoute('app_favorites');
    }
}

<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration', name: 'admin_')]
#[IsGranted('ROLE_MANAGER')]
class AdminSearchController extends AbstractController
{
    #[Route('/recherche', name: 'search', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        CustomerOrderRepository $orderRepository,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $query = trim((string) $request->query->get('q', ''));

        $products = [];
        $categories = [];
        $orders = [];
        $clients = [];
        $variants = [];

        if ($query !== '') {
            $term = '%' . mb_strtolower($query) . '%';

            // Recherche des produits.
            $products = $productRepository->createQueryBuilder('p')
                ->andWhere('LOWER(p.name) LIKE :term OR LOWER(p.slug) LIKE :term OR LOWER(p.brand) LIKE :term')
                ->setParameter('term', $term)
                ->orderBy('p.name', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Recherche des catÃ©gories.
            $categories = $categoryRepository->createQueryBuilder('c')
                ->andWhere('LOWER(c.name) LIKE :term OR LOWER(c.slug) LIKE :term')
                ->setParameter('term', $term)
                ->orderBy('c.name', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Recherche des commandes.
            $orders = $orderRepository->createQueryBuilder('o')
                ->andWhere('LOWER(o.orderNumber) LIKE :term')
                ->setParameter('term', $term)
                ->orderBy('o.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Recherche des clients sans les administrateurs ni les gestionnaires.
            $clientIds = $entityManager->getConnection()->executeQuery(
                'SELECT id FROM `user`
                 WHERE JSON_CONTAINS(roles, JSON_QUOTE(:adminRole)) = 0
                 AND JSON_CONTAINS(roles, JSON_QUOTE(:managerRole)) = 0
                 AND (
                    LOWER(firstname) LIKE :term
                    OR LOWER(lastname) LIKE :term
                    OR LOWER(email) LIKE :term
                 )
                 LIMIT 5',
                [
                    'adminRole' => 'ROLE_ADMIN',
                    'managerRole' => 'ROLE_MANAGER',
                    'term' => $term,
                ]
            )->fetchFirstColumn();
            
            if ($clientIds !== []) {
                $clients = $userRepository->findBy(
                    ['id' => $clientIds],
                    ['firstname' => 'ASC']
                );
            }

            // Recherche des variantes et des rÃ©fÃ©rences de stock.
            $variants = $variantRepository->createQueryBuilder('v')
                ->innerJoin('v.product', 'p')
                ->addSelect('p')
                ->andWhere(
                    'LOWER(v.sku) LIKE :term
                    OR LOWER(v.color) LIKE :term
                    OR LOWER(v.size) LIKE :term
                    OR LOWER(p.name) LIKE :term'
                )
                ->setParameter('term', $term)
                ->orderBy('p.name', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/search/index.html.twig', [
            'query' => $query,
            'products' => $products,
            'categories' => $categories,
            'orders' => $orders,
            'clients' => $clients,
            'variants' => $variants,
        ]);
    }
}

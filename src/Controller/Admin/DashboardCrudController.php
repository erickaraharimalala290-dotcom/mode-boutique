<?php

namespace App\Controller\Admin;

use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration', name: 'admin_')]
#[IsGranted('ROLE_MANAGER')]
class DashboardCrudController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        CustomerOrderRepository $orderRepository,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository
    ): Response {
        return $this->render('admin/dashboard/index.html.twig', [
            'totalProducts' => $productRepository->countActiveProducts(),
            'totalOrders' => $orderRepository->count([]),
            'totalClients' => $userRepository->countClients(),
            'lowStockCount' => $variantRepository->countLowStockVariants(),
        ]);
    }
}

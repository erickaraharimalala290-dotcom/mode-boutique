<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/clients', name: 'admin_customers_')]
#[IsGranted('ROLE_MANAGER')]
class CustomerCrudController extends AbstractController
{
    // Afficher la liste des clients.
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        // Récupérer les utilisateurs du plus récent au plus ancien.
        $users = $userRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        // Préparer les données des clients.
        $customers = [];

        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'new' => 0,
            'orders' => 0,
        ];

        // Définir la période des nouveaux clients.
        $last30Days = new \DateTimeImmutable('-30 days');

        foreach ($users as $user) {

            // Exclure les administrateurs et les managers de la liste des clients.
            if (
                in_array('ROLE_ADMIN', $user->getRoles(), true)
                || in_array('ROLE_MANAGER', $user->getRoles(), true)
            ) {
                continue;
            }

            // Compter les commandes du client.
            $ordersCount = $user->getCustomerOrders()->count();

            // Préparer le nom complet.
            $firstname = $user->getFirstname() ?? '';
            $lastname = $user->getLastname() ?? '';

            $name = trim($firstname . ' ' . $lastname);

            // Préparer les initiales.
            $initials = mb_strtoupper(
                mb_substr($firstname, 0, 1)
                    . mb_substr($lastname, 0, 1)
            );

            // Déterminer le statut du compte.
            $status = $user->isActive()
                ? 'Actif'
                : 'Inactif';

            // Ajouter le client à la liste.
            $customers[] = [
                'id' => $user->getId(),
                'name' => $name,
                'email' => $user->getEmail(),
                'initials' => $initials,
                'profilePicture' => $user->getProfilePicture(),
                'registeredAt' => $user->getCreatedAt()?->format('d/m/Y') ?? 'â€”',
                'status' => $status,
                'orders' => $ordersCount,
            ];

            // Calculer les statistiques.
            $stats['total']++;
            $stats['orders'] += $ordersCount;

            if ($user->isActive()) {
                $stats['active']++;
            } else {
                $stats['inactive']++;
            }

            if (
                $user->getCreatedAt() !== null
                && $user->getCreatedAt() >= $last30Days
            ) {
                $stats['new']++;
            }
        }

        // dd(array_map(
        //     static fn(array $customer) => [
        //         'id' => $customer['id'],
        //         'name' => $customer['name'],
        //     ],
        //     $customers
        // ));

        return $this->render('admin/customer/index.html.twig', [
            'customers' => $customers,
            'stats' => $stats,
        ]);
    }

    // Activer ou dÃ©sactiver le compte d'un client.
    #[Route('/{id}/statut', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // EmpÃªcher la modification des comptes administrateurs et managers.
        if (
            in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_MANAGER', $user->getRoles(), true)
        ) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier le jeton CSRF.
        if (!$this->isCsrfTokenValid(
            'toggle-status-' . $user->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException();
        }

        // Inverser le statut du compte.
        $user->setIsActive(!$user->isActive());

        $entityManager->flush();

        // Afficher un message de confirmation.
        $this->addFlash(
            'success',
            $user->isActive()
                ? 'Le compte client a été réactivé.'
                : 'Le compte client a été désactivé.'
        );

        return $this->redirectToRoute('admin_customers_index');
    }
}

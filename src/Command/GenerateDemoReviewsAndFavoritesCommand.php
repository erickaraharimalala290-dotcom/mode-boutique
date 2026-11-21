<?php

namespace App\Command;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-demo-reviews-favorites',
    description: 'Ajoute des avis et favoris fictifs sans supprimer les données existantes.'
)]
class GenerateDemoReviewsAndFavoritesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Permet de tester sans enregistrer les nouvelles données.
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simule la création sans enregistrer les données.'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $dryRun = $input->getOption('dry-run');

        // Récupérer uniquement les utilisateurs actifs.
        $users = $this->entityManager
            ->getRepository(User::class)
            ->findBy(['isActive' => true]);

        // Exclure les administrateurs et les managers.
        $clients = array_values(array_filter(
            $users,
            static function (User $user): bool {
                $roles = $user->getRoles();

                return !in_array('ROLE_ADMIN', $roles, true)
                    && !in_array('ROLE_MANAGER', $roles, true);
            }
        ));

        // Récupérer uniquement les produits actifs.
        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['isActive' => true]);

        if (count($clients) === 0 || count($products) === 0) {
            $io->warning(
                'Aucun client actif ou produit actif disponible.'
            );

            return Command::SUCCESS;
        }

        // Commentaires fictifs en français pour alimenter les avis.
        $comments = [
            'Très bonne qualité, je suis satisfaite de mon achat.',
            'Article conforme aux photos. Je recommande !',
            'Belle finition et très agréable à porter.',
            'Excellent rapport qualité-prix.',
            'Je suis vraiment contente de mon achat.',
            'Produit élégant et de bonne qualité.',
            'Très joli article, je ne regrette pas mon choix.',
            'Livraison et produit satisfaisants.',
            'La qualité est au rendez-vous.',
            'Très bel article, je recommande vivement.',
            'Conforme à mes attentes, merci !',
            'Un très bon achat, je suis ravie.',
        ];

        $reviewRepository = $this->entityManager
            ->getRepository(Review::class);

        $favoriteRepository = $this->entityManager
            ->getRepository(Favorite::class);

        $newReviews = 0;
        $newFavorites = 0;

        // Répartir les avis et les favoris entre les différents clients.
        foreach ($clients as $clientIndex => $client) {

            // Chaque client reçoit jusqu'à 3 avis sur des produits différents.
            for ($i = 0; $i < 3; $i++) {
                $productIndex = ($clientIndex * 3 + $i * 7)
                    % count($products);

                $product = $products[$productIndex];

                // Vérifier qu'un avis n'existe pas déjà pour ce client et ce produit.
                $existingReview = $reviewRepository->findOneBy([
                    'user' => $client,
                    'product' => $product,
                ]);

                if ($existingReview !== null) {
                    continue;
                }

                $review = new Review();

                $review
                    ->setUser($client)
                    ->setProduct($product)
                    ->setRating(4 + (($clientIndex + $i) % 2))
                    ->setComment(
                        $comments[($clientIndex * 3 + $i) % count($comments)]
                    );

                $this->entityManager->persist($review);

                $newReviews++;
            }

            // Chaque client reçoit jusqu'à 4 favoris.
            for ($i = 0; $i < 4; $i++) {
                $productIndex = ($clientIndex * 4 + $i * 5 + 2)
                    % count($products);

                $product = $products[$productIndex];

                // Vérifier que le produit n'est pas déjà dans les favoris.
                $existingFavorite = $favoriteRepository->findOneBy([
                    'user' => $client,
                    'product' => $product,
                ]);

                if ($existingFavorite !== null) {
                    continue;
                }

                $favorite = new Favorite();

                $favorite
                    ->setUser($client)
                    ->setProduct($product);

                $this->entityManager->persist($favorite);

                $newFavorites++;
            }
        }

        // Enregistrer uniquement si le mode simulation n'est pas activé.
        if (!$dryRun) {
            $this->entityManager->flush();

            $io->success(sprintf(
                '%d avis et %d favoris ont été ajoutés.',
                $newReviews,
                $newFavorites
            ));
        } else {
            $io->note('Mode simulation : aucune donnée ne sera enregistrée.');

            $io->success(sprintf(
                '%d avis et %d favoris seraient ajoutés.',
                $newReviews,
                $newFavorites
            ));
        }

        return Command::SUCCESS;
    }
}

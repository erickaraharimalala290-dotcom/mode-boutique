<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Repository\CategoryRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $imageDirectory = dirname(__DIR__, 2) . '/public/images/products';
        $now = new \DateTimeImmutable();

        // Chaque produit possède une image principale et des images par couleur.
        $products = [
            [
                'name' => 'Robe courte élégante',
                'slug' => 'robe-courte-elegante',
                'category' => 'robe',
                'brand' => 'Élégance Paris',
                'price' => '85000',
                'description' => 'Robe courte élégante disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'robe-courte-bleu.jpg', 'color' => 'Bleu'],
                    ['file' => 'robe-courte-gris.jpg', 'color' => 'Gris'],
                    ['file' => 'robe-courte-rose.jpg', 'color' => 'Rose'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Robe fleurie',
                'slug' => 'robe-fleurie',
                'category' => 'robe',
                'brand' => 'Élégance Paris',
                'price' => '95000',
                'description' => 'Robe fleurie pour un style frais et élégant.',
                'images' => [
                    ['file' => 'robe-feuries-bleu.jpg', 'color' => 'Bleu'],
                    ['file' => 'robe-fleurie-long.jpg', 'color' => 'Fleuri'],
                    ['file' => 'robe-fleurie-violet.jpg', 'color' => 'Violet'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Robe de soirée',
                'slug' => 'robe-de-soiree',
                'category' => 'robe',
                'brand' => 'Élégance Paris',
                'price' => '120000',
                'description' => 'Robe de soirée raffinée disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'robe-soirée-beige.jpg', 'color' => 'Beige'],
                    ['file' => 'robe-soirée-gris.jpg', 'color' => 'Gris'],
                    ['file' => 'robe-soirée-marron.jpg', 'color' => 'Marron'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Pantalon jean femme',
                'slug' => 'pantalon-jean-femme',
                'category' => 'pantalon',
                'brand' => 'Urban Style',
                'price' => '75000',
                'description' => 'Pantalon jean femme confortable.',
                'images' => [
                    ['file' => 'pantalon-jean-femme.jpg', 'color' => 'Bleu'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Pantalon work femme',
                'slug' => 'pantalon-work-femme',
                'category' => 'pantalon',
                'brand' => 'Urban Style',
                'price' => '78000',
                'description' => 'Pantalon femme au style moderne.',
                'images' => [
                    ['file' => 'pantalon-work-femme.jpg', 'color' => 'Noir'],
                    ['file' => 'pantalon-work-femme-bleu.jpg', 'color' => 'Bleu'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Crop-top femme',
                'slug' => 'crop-top-femme',
                'category' => 'top-femme',
                'brand' => 'Urban Style',
                'price' => '35000',
                'description' => 'Crop-top femme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'crop-top-blanc-femme.jpg', 'color' => 'Blanc'],
                    ['file' => 'crop-top-bleu-femme.jpg', 'color' => 'Bleu'],
                    ['file' => 'crop-top-noir-femme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'T-shirt femme',
                'slug' => 't-shirt-femme',
                'category' => 'vetement-femme',
                'brand' => 'Urban Style',
                'price' => '30000',
                'description' => 'T-shirt femme confortable et facile à porter.',
                'images' => [
                    ['file' => 't-shirt-bleu-femme.jpg', 'color' => 'Bleu'],
                    ['file' => 'T-shirt-noir-femme.jpg', 'color' => 'Noir'],
                    ['file' => 'T-shirt-rose-femme.jpg', 'color' => 'Rose'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Sweat femme',
                'slug' => 'sweat-femme',
                'category' => 'vetement-femme',
                'brand' => 'Urban Style',
                'price' => '65000',
                'description' => 'Sweat femme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sweat-bordeau-femme.jpg', 'color' => 'Bordeaux'],
                    ['file' => 'sweat-maron-femme.jpg', 'color' => 'Marron'],
                    ['file' => 'sweat-noir-femme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'name' => 'Sneakers femme',
                'slug' => 'sneakers-femme',
                'category' => 'sneakers-femme',
                'brand' => 'Urban Step',
                'price' => '110000',
                'description' => 'Sneakers femme disponibles en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sneakers-femme-grena.jpg', 'color' => 'Grenat'],
                    ['file' => 'sneakers-femme-noir.jpg', 'color' => 'Noir'],
                    ['file' => 'sneakers-femme-rose.jpg', 'color' => 'Rose'],
                ],
                'sizes' => ['36', '37', '38', '39', '40'],
            ],
            [
                'name' => 'Talons femme',
                'slug' => 'talons-femme',
                'category' => 'talons-femme',
                'brand' => 'Élégance Paris',
                'price' => '95000',
                'description' => 'Chaussures à talons élégantes.',
                'images' => [
                    ['file' => 'talon-grena.jpg', 'color' => 'Grenat'],
                    ['file' => 'talon-gris.jpg', 'color' => 'Gris'],
                    ['file' => 'talon-noir.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['36', '37', '38', '39', '40'],
            ],
            [
                'name' => 'Bottes femme',
                'slug' => 'bottes-femme',
                'category' => 'botte-femme',
                'brand' => 'Urban Step',
                'price' => '135000',
                'description' => 'Bottes femme disponibles en plusieurs couleurs.',
                'images' => [
                    ['file' => 'botte-femme-blanc.jpg', 'color' => 'Blanc'],
                    ['file' => 'botte-femme-gris.jpg', 'color' => 'Gris'],
                    ['file' => 'botte-femme-noir.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['36', '37', '38', '39', '40'],
            ],
            [
                'name' => 'Sac à main femme',
                'slug' => 'sac-a-main-femme',
                'category' => 'sac-a-main',
                'brand' => 'Élégance Paris',
                'price' => '90000',
                'description' => 'Sac à main femme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sac-blanc-femme.jpg', 'color' => 'Blanc'],
                    ['file' => 'sac-noir-femme.jpg', 'color' => 'Noir'],
                    ['file' => 'sac-rose-femme.jpg', 'color' => 'Rose'],
                ],
                'sizes' => ['Unique'],
            ],
            [
                'name' => 'Montre femme',
                'slug' => 'montre-femme',
                'category' => 'montre-femme',
                'brand' => 'Élégance Paris',
                'price' => '100000',
                'description' => 'Montre femme au design élégant.',
                'images' => [
                    ['file' => 'montre-femme-1.jpg', 'color' => 'Modèle 1'],
                    ['file' => 'montre-femme-2.jpg', 'color' => 'Modèle 2'],
                    ['file' => 'montre-femme-3.jpg', 'color' => 'Modèle 3'],
                ],
                'sizes' => ['Unique'],
            ],
            [
                'name' => 'Bijoux femme',
                'slug' => 'bijoux-femme',
                'category' => 'bijoux-femme',
                'brand' => 'Élégance Paris',
                'price' => '45000',
                'description' => 'Collection de bijoux femme.',
                'images' => [
                    ['file' => 'bijoux-femme-1.jpg', 'color' => 'Modèle 1'],
                    ['file' => 'bijoux-femme-2.jpg', 'color' => 'Modèle 2'],
                    ['file' => 'bijoux-femme-3.jpg', 'color' => 'Modèle 3'],
                ],
                'sizes' => ['Unique'],
            ],
            [
                'name' => 'Chemise homme',
                'slug' => 'chemise-homme',
                'category' => 'chemise-homme',
                'brand' => 'Urban Style',
                'price' => '70000',
                'description' => 'Chemise homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'chemise-homme-blanc.jpg', 'color' => 'Blanc'],
                    ['file' => 'chemise-homme-bordeau.png', 'color' => 'Bordeaux'],
                    ['file' => 'chemise-homme-marron.jpg', 'color' => 'Marron'],
                ],
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Jean homme',
                'slug' => 'jean-homme',
                'category' => 'pantalon-homme',
                'brand' => 'Urban Style',
                'price' => '85000',
                'description' => 'Jean homme disponible en deux nuances de bleu.',
                'images' => [
                    ['file' => 'jean-bleu-homme.jpg', 'color' => 'Bleu'],
                    ['file' => 'jean-bleuMarine-homme.jpg', 'color' => 'Bleu marine'],
                ],
                'sizes' => ['38', '40', '42', '44'],
            ],
            [
                'name' => 'Pantalon cargo homme',
                'slug' => 'pantalon-cargo-homme',
                'category' => 'pantalon-homme',
                'brand' => 'Urban Style',
                'price' => '90000',
                'description' => 'Pantalon cargo homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'pantalon-cargo-beige-homme.jpg', 'color' => 'Beige'],
                    ['file' => 'pantalon-cargo-noir-homme.jpg', 'color' => 'Noir'],
                    ['file' => 'pantalon-cargo-vert-homme.jpg', 'color' => 'Vert'],
                ],
                'sizes' => ['38', '40', '42', '44'],
            ],
            [
                'name' => 'Pantalon classique homme',
                'slug' => 'pantalon-classique-homme',
                'category' => 'pantalon-homme',
                'brand' => 'Urban Style',
                'price' => '95000',
                'description' => 'Pantalon classique homme.',
                'images' => [
                    ['file' => 'pantalon-class-bleu-homme.jpg', 'color' => 'Bleu'],
                    ['file' => 'pantalon-class-gris-homme.jpg', 'color' => 'Gris'],
                ],
                'sizes' => ['38', '40', '42', '44'],
            ],
            [
                'name' => 'T-shirt homme',
                'slug' => 't-shirt-homme',
                'category' => 'top-homme',
                'brand' => 'Urban Style',
                'price' => '32000',
                'description' => 'T-shirt homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 't-shirt-homme-blanc.jpg', 'color' => 'Blanc'],
                    ['file' => 'T-shirt-gris-homme.jpg', 'color' => 'Gris'],
                    ['file' => 'T-shirt-rouge-homme.jpg', 'color' => 'Rouge'],
                ],
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Sweat homme',
                'slug' => 'sweat-homme',
                'category' => 'vetement-homme',
                'brand' => 'Urban Style',
                'price' => '70000',
                'description' => 'Sweat homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sweat-blanc-homme.jpg', 'color' => 'Blanc'],
                    ['file' => 'sweat-gris-homme.jpg', 'color' => 'Gris'],
                    ['file' => 'sweat-vert-homme.jpg', 'color' => 'Vert'],
                ],
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Sneakers homme',
                'slug' => 'sneakers-homme',
                'category' => 'sneakers-homme',
                'brand' => 'Urban Step',
                'price' => '115000',
                'description' => 'Sneakers homme disponibles en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sneakers-bleu-homme.jpg', 'color' => 'Bleu'],
                    ['file' => 'sneakers-marron-homme.jpg', 'color' => 'Marron'],
                    ['file' => 'sneakers-noir-homme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['40', '41', '42', '43', '44'],
            ],
            [
                'name' => 'Bottes homme',
                'slug' => 'bottes-homme',
                'category' => 'botte-homme',
                'brand' => 'Urban Step',
                'price' => '140000',
                'description' => 'Bottes homme disponibles en plusieurs couleurs.',
                'images' => [
                    ['file' => 'botte-gris-homme.jpg', 'color' => 'Gris'],
                    ['file' => 'botte-marron-homme.jpg', 'color' => 'Marron'],
                    ['file' => 'botte-noir-homme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['40', '41', '42', '43', '44'],
            ],
            [
                'name' => 'Sandales homme',
                'slug' => 'sandales-homme',
                'category' => 'sandale-homme',
                'brand' => 'Urban Step',
                'price' => '55000',
                'description' => 'Sandales homme disponibles en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sandale-blanc-homme.jpg', 'color' => 'Blanc'],
                    ['file' => 'sandale-marron-homme.jpg', 'color' => 'Marron'],
                    ['file' => 'sandale-noir-homme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['40', '41', '42', '43', '44'],
            ],
            [
                'name' => 'Sac homme',
                'slug' => 'sac-homme',
                'category' => 'sac-homme',
                'brand' => 'Urban Style',
                'price' => '80000',
                'description' => 'Sac homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'sac-blanc-homme.jpg', 'color' => 'Blanc'],
                    ['file' => 'sac-gris-homme.jpg', 'color' => 'Gris'],
                ],
                'sizes' => ['Unique'],
            ],
            [
                'name' => 'Montre homme',
                'slug' => 'montre-homme',
                'category' => 'montre-homme',
                'brand' => 'Urban Style',
                'price' => '105000',
                'description' => 'Montre homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'montre-argent-homme.jpg', 'color' => 'Argent'],
                    ['file' => 'montre-doré-homme.jpg', 'color' => 'Doré'],
                    ['file' => 'montre-noir-homme.jpg', 'color' => 'Noir'],
                ],
                'sizes' => ['Unique'],
            ],
            [
                'name' => 'Ceinture homme',
                'slug' => 'ceinture-homme',
                'category' => 'ceinture-homme',
                'brand' => 'Urban Style',
                'price' => '45000',
                'description' => 'Ceinture homme disponible en plusieurs couleurs.',
                'images' => [
                    ['file' => 'ceinture-marron-homme.jpg', 'color' => 'Marron'],
                    ['file' => 'ceinture-noir-homme.jpg', 'color' => 'Noir'],
                    ['file' => 'ceiture-blanc-homme.jpg', 'color' => 'Blanc'],
                ],
                'sizes' => ['Unique'],
            ],
        ];

        foreach ($products as $data) {
            // Vérifier la catégorie existante.
            $category = $this->categoryRepository->findOneBy([
                'slug' => $data['category'],
            ]);

            if (!$category) {
                throw new \RuntimeException(
                    'Catégorie introuvable : ' . $data['category']
                );
            }

            // Vérifier les images disponibles.
            $availableImages = [];

            foreach ($data['images'] as $image) {
                if (!is_file($imageDirectory . '/' . $image['file'])) {
                    throw new \RuntimeException(
                        'Image introuvable : ' . $image['file']
                    );
                }

                $availableImages[] = $image;
            }

            // Créer le produit.
            $product = new Product();

            $product
                ->setName($data['name'])
                ->setSlug($data['slug'])
                ->setDescription($data['description'])
                ->setPrice($data['price'])
                ->setBrand($data['brand'])
                ->setCategory($category)
                ->setMainImage('images/products/' . $availableImages[0]['file'])
                ->setIsActive(true)
                ->setIsFeatured(false)
                ->setCreatedAt($now);

            $manager->persist($product);

            // Créer les images supplémentaires.
            foreach (array_slice($availableImages, 1) as $index => $imageData) {
                $productImage = new ProductImage();

                $productImage
                    ->setImagePath('images/products/' . $imageData['file'])
                    ->setAltText($data['name'] . ' - ' . $imageData['color'])
                    ->setColor($imageData['color'])
                    ->setIsPrimary(false)
                    ->setSortOrder($index + 1)
                    ->setProduct($product);

                $manager->persist($productImage);
            }

            // Créer les variantes pour chaque couleur et chaque taille.
            foreach ($availableImages as $imageData) {
                foreach ($data['sizes'] as $size) {
                    $colorSlug = strtolower(
                        preg_replace('/[^a-zA-Z0-9]+/', '-', $imageData['color'])
                    );

                    $sizeSlug = strtolower(
                        preg_replace('/[^a-zA-Z0-9]+/', '-', $size)
                    );

                    $variant = new ProductVariant();

                    $variant
                        ->setSku($data['slug'] . '-' . $colorSlug . '-' . $sizeSlug)
                        ->setColor($imageData['color'])
                        ->setSize($size)
                        ->setStockQuantity(10)
                        ->setLowStockThreshold(5)
                        ->setIsActive(true)
                        ->setProduct($product);

                    $manager->persist($variant);
                }
            }
        }

        $manager->flush();
    }
}

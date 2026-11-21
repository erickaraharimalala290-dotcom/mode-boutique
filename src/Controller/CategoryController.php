<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    #[Route('/category', name: 'app_category', methods: ['GET'])]
    public function index(
        Request $request,
        CategoryRepository $categoryRepository
    ): Response {
        // Récupérer les catégories actives, triées par ordre d'affichage.
        $allCategories = $categoryRepository->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC', 'name' => 'ASC']
        );

        // Organiser les catégories selon leur parent.
        $categoriesByParent = [];

        foreach ($allCategories as $category) {
            $parentId = $category->getParent()?->getId() ?? 0;
            $categoriesByParent[$parentId][] = $category;
        }

        // Construire les collections principales Femme et Homme.
        $collections = [];

        foreach (['femme', 'homme'] as $genreSlug) {
            $genre = null;

            foreach ($categoriesByParent[0] ?? [] as $rootCategory) {
                if ($rootCategory->getSlug() === $genreSlug) {
                    $genre = $rootCategory;
                    break;
                }
            }

            if ($genre === null) {
                continue;
            }

            $collectionCategories = [];

            // Catégories enfants de Femme ou Homme.
            foreach ($categoriesByParent[$genre->getId()] ?? [] as $category) {
                $subcategories = [];

                // Sous-catégories enfants de Vêtements, Chaussures, etc.
                foreach ($categoriesByParent[$category->getId()] ?? [] as $subcategory) {
                    $subcategories[] = [
                        'id' => $subcategory->getId(),
                        'slug' => $subcategory->getSlug(),
                        'name' => $subcategory->getName(),
                        'description' => $subcategory->getDescription() ?? '',
                        'image' => $this->getCategoryImage(
                            $subcategory->getImage()
                        ),
                    ];
                }

                $collectionCategories[$category->getSlug()] = [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'name' => $category->getName(),
                    'description' => $category->getDescription() ?? '',
                    'image' => $this->getCategoryImage(
                        $category->getImage()
                    ),
                    'subcategories' => $subcategories,
                ];
            }

            $collections[$genreSlug] = [
                'id' => $genre->getId(),
                'slug' => $genre->getSlug(),
                'name' => $genre->getName(),
                'eyebrow' => $genreSlug === 'femme'
                    ? 'La mode au féminin'
                    : 'Le style au masculin',
                'description' => $genre->getDescription() ?? '',
                'image' => $this->getCategoryImage(
                    $genre->getImage()
                ),
                'categories' => $collectionCategories,
            ];
        }

        // Récupérer les paramètres transmis dans l'URL.
        $genre = strtolower((string) $request->query->get('genre', ''));
        $type = strtolower((string) $request->query->get('type', ''));
        $subcategorySlug = strtolower(
            (string) $request->query->get('sousCategorie', '')
        );

        $selectedSubcategory = null;

        // Vérifier la collection demandée.
        if (!isset($collections[$genre])) {
            $genre = '';
            $type = '';
            $subcategorySlug = '';
        } elseif (!isset($collections[$genre]['categories'][$type])) {
            $type = '';
            $subcategorySlug = '';
        } elseif ($subcategorySlug !== '') {
            // Rechercher la sous-catégorie par son véritable slug.
            foreach (
                $collections[$genre]['categories'][$type]['subcategories']
                as $subcategory
            ) {
                if ($subcategory['slug'] === $subcategorySlug) {
                    $selectedSubcategory = $subcategory;
                    break;
                }
            }

            // Revenir à la catégorie parente si elle est introuvable.
            if ($selectedSubcategory === null) {
                $subcategorySlug = '';
            }
        }

        return $this->render('pages/category/index.html.twig', [
            'collections' => $collections,
            'currentGenre' => $genre,
            'currentType' => $type,
            'currentSubcategory' => $subcategorySlug,
            'selectedSubcategory' => $selectedSubcategory,
        ]);
    }

    /**
     * Retourne le chemin de l'image enregistrée en base de données.
     * Aucune image de secours n'est utilisée.
     */
    private function getCategoryImage(?string $image): string
    {
        if ($image === null || trim($image) === '') {
            return '';
        }

        return '/uploads/categories/' . $image;
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


#[Route('/administration/categories', name: 'admin_categories_')]
#[IsGranted('ROLE_MANAGER')]
class CategoryCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CategoryRepository $categoryRepository
    ): Response {
        $categories = $categoryRepository->findBy(
            [],
            ['sortOrder' => 'ASC', 'name' => 'ASC']
        );

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $category = new Category();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile !== null) {
                $newFilename = $this->uploadImage($imageFile, $slugger);

                if ($newFilename === null) {
                    $this->addFlash(
                        'error',
                        'Une erreur est survenue lors du téléchargement de lâ€™image.'
                    );

                    return $this->render('admin/category/new.html.twig', [
                        'form' => $form,
                    ]);
                }

                $category->setImage($newFilename);
            }

            if (!$category->getSlug()) {
                $category->setSlug(
                    strtolower($slugger->slug($category->getName())->toString())
                );
            }

            if (method_exists($category, 'setCreatedAt')) {
                $category->setCreatedAt(new \DateTimeImmutable());
            }

            if (method_exists($category, 'setUpdatedAt')) {
                $category->setUpdatedAt(new \DateTimeImmutable());
            }

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'La catégorie a été créée avec succès.'
            );

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Category $category,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            // Raha misy sary vaovao vao soloina ilay taloha.
            if ($imageFile !== null) {
                $newFilename = $this->uploadImage($imageFile, $slugger);

                if ($newFilename === null) {
                    $this->addFlash(
                        'error',
                        'Une erreur est survenue lors du téléchargement de l\'image.'
                    );

                    return $this->render('admin/category/edit.html.twig', [
                        'form' => $form,
                        'category' => $category,
                    ]);
                }

                $category->setImage($newFilename);
            }

            if (!$category->getSlug()) {
                $category->setSlug(
                    strtolower($slugger->slug($category->getName())->toString())
                );
            }

            if (method_exists($category, 'setUpdatedAt')) {
                $category->setUpdatedAt(new \DateTimeImmutable());
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                'La catégorie a été modifiée avec succès.'
            );

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }


    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        Category $category,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            'delete_category_' . $category->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($category->getChildren()->count() > 0) {
            $this->addFlash(
                'error',
                'Impossible de supprimer cette catégory : elle contient des sous-catégories.'
            );

            return $this->redirectToRoute('admin_categories_index');
        }

        if ($category->getProducts()->count() > 0) {
            $this->addFlash(
                'error',
                'Impossible de supprimer cette catégory : des produits y sont associés.'
            );

            return $this->redirectToRoute('admin_categories_index');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'La catégorie a été supprimée avec succès.'
        );

        return $this->redirectToRoute('admin_categories_index');
    }


    private function uploadImage(
        \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile,
        SluggerInterface $slugger
    ): ?string {
        $originalFilename = pathinfo(
            $imageFile->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' .
            $imageFile->guessExtension();

        try {
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/categories',
                $newFilename
            );

            return $newFilename;
        } catch (FileException $exception) {
            return null;
        }
    }
}


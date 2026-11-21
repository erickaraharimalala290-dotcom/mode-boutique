<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/administration/produits', name: 'admin_products_')]
#[IsGranted('ROLE_MANAGER')]
class ProductCrudController extends AbstractController
{
    private const COLORS = [
        'Noir',
        'Beige',
        'Marron',
        'Blanc',
        'Bordeaux',
        'Bleu',
        'Gris',
        'Rose',
        'Violet',
        'Vert',
        'Rouge',
        'DorÃ©',
        'ArgentÃ©',
        'Grenat'
    ];

    private const SIZES = [
        'XS',
        'S',
        'M',
        'L',
        'XL',
        'XXL',
        '36',
        '37',
        '38',
        '39',
        '40',
        '41',
        '42',
        '43',
        '44',
        '45',
        '46',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('admin/product/index.html.twig', [
            'products' => $productRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $product = new Product();

        $form = $this->createForm(ProductType::class, $product);
        $form->get('variantsData')->setData('[]');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $product->getSlug()
                ? $slugger->slug($product->getSlug())->lower()->toString()
                : $slugger->slug($product->getName())->lower()->toString();

            $baseSlug = $slug;
            $suffix = 1;

            while ($entityManager->getRepository(Product::class)->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $suffix++;
            }

            $product->setSlug($slug);
            $product->setCreatedAt(new \DateTimeImmutable());

            try {
                $imageFile = $form->get('imageFile')->getData();

                if ($imageFile instanceof UploadedFile) {
                    $product->setMainImage(
                        $this->uploadImage($imageFile, $slugger)
                    );
                }

                $imagesFiles = $form->get('imagesFiles')->getData() ?? [];

                $imagesColors = $this->decodeImageColors(
                    $form->get('imagesColors')->getData()
                );

                $this->addProductImages(
                    $product,
                    $imagesFiles,
                    $imagesColors,
                    $slugger,
                    $entityManager
                );
            } catch (FileException $exception) {
                $this->addFlash(
                    'error',
                    'Une erreur est survenue lors du téléchargement des images.'
                );

                return $this->render('admin/product/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $entityManager->persist($product);

            if (!$this->saveVariants(
                $product,
                $form->get('variantsData')->getData(),
                $entityManager
            )) {
                return $this->render('admin/product/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le produit et ses variantes ont été ajoutés avec succès.'
            );

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(ProductType::class, $product);

        $form->get('variantsData')->setData(
            $this->encodeExistingVariants($product)
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($product->getName())->lower()->toString();

            $existingProduct = $entityManager->getRepository(Product::class)
                ->findOneBy(['slug' => $slug]);

            if ($existingProduct && $existingProduct->getId() !== $product->getId()) {
                $slug .= '-' . uniqid();
            }

            $product->setSlug($slug);
            $product->setUpdatedAt(new \DateTimeImmutable());

            try {
                $imageFile = $form->get('imageFile')->getData();

                if ($imageFile instanceof UploadedFile) {
                    $product->setMainImage(
                        $this->uploadImage($imageFile, $slugger)
                    );
                }

                $imagesFiles = $form->get('imagesFiles')->getData() ?? [];

                $imagesColors = $this->decodeImageColors(
                    $form->get('imagesColors')->getData()
                );

                $this->addProductImages(
                    $product,
                    $imagesFiles,
                    $imagesColors,
                    $slugger,
                    $entityManager
                );
            } catch (FileException $exception) {
                $this->addFlash(
                    'error',
                    'Une erreur est survenue lors du téléchargement des images.'
                );

                return $this->render('admin/product/edit.html.twig', [
                    'form' => $form,
                    'is_edit' => true,
                    'product' => $product,
                ]);
            }

            if (!$this->saveVariants(
                $product,
                $form->get('variantsData')->getData(),
                $entityManager
            )) {
                return $this->render('admin/product/edit.html.twig', [
                    'form' => $form,
                    'is_edit' => true,
                    'product' => $product,
                ]);
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le produit et ses variantes ont été modifiés avec succès.'
            );

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/edit.html.twig', [
            'form' => $form,
            'is_edit' => true,
            'product' => $product,
        ]);
    }

    #[Route('/{id}/image/{imageId}/supprimer', name: 'image_delete', requirements: ['id' => '\d+', 'imageId' => '\d+'], methods: ['POST'])]
    public function deleteImage(
        Product $product,
        int $imageId,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete-product-image-' . $imageId,
            $request->request->get('_token')
        )) {
            $this->addFlash('error', "La suppression de l'image a échoué.");

            return $this->redirectToRoute('admin_products_edit', [
                'id' => $product->getId(),
            ]);
        }

        $image = $entityManager->getRepository(ProductImage::class)->findOneBy([
            'id' => $imageId,
            'product' => $product,
        ]);

        if (!$image) {
            $this->addFlash('error', 'Image introuvable.');

            return $this->redirectToRoute('admin_products_edit', [
                'id' => $product->getId(),
            ]);
        }

        $product->getImages()->removeElement($image);
        $entityManager->remove($image);
        $entityManager->flush();

        $this->addFlash('success', "L'image a été supprimée.");

        return $this->redirectToRoute('admin_products_edit', [
            'id' => $product->getId(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete-product-' . $product->getId(),
            $request->request->get('_token')
        )) {
            $this->addFlash('error', 'La suppression a échoué. Veuillez réessayer.');

            return $this->redirectToRoute('admin_products_index');
        }

        foreach ($product->getImages() as $image) {
            $entityManager->remove($image);
        }

        // Les variantes sont conservées pour protéger les historiques de commandes.
        foreach ($product->getVariants() as $variant) {
            $variant->setIsActive(false);
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Le produit a été supprimé avec succès.');

        return $this->redirectToRoute('admin_products_index');
    }

    /**
     * Vérifie si la catégorie sélectionnée appartient à Accessoires.
     * La vérification remonte toute la hiérarchie des catégories parentes.
     */
    private function isAccessoryCategory(Product $product): bool
    {
        $category = $product->getCategory();

        while ($category !== null) {
            $categoryName = mb_strtolower(
                $category->getName() ?? ''
            );

            // Normaliser les accents pour reconnaître « Accéssoires » et « Accessoires ».
            $categoryName = strtr($categoryName, [
                'é' => 'e',
                'è' => 'e',
                'ê' => 'e',
                'ë' => 'e',
            ]);

            if (str_contains($categoryName, 'accessoire')) {
                return true;
            }

            $category = $category->getParent();
        }

        return false;
    }

    private function saveVariants(
        Product $product,
        ?string $json,
        EntityManagerInterface $entityManager
    ): bool {
        $variants = json_decode($json ?? '[]', true);

        if (!is_array($variants)) {
            $this->addFlash('error', 'Les données des variantes sont invalides.');

            return false;
        }

        $isAccessory = $this->isAccessoryCategory($product);

        $existingVariants = [];

        foreach ($product->getVariants() as $variant) {
            $existingVariants[$variant->getId()] = $variant;
        }

        $submittedIds = [];
        $combinations = [];

        foreach ($variants as $data) {
            if (!is_array($data)) {
                continue;
            }

            $color = trim((string) ($data['color'] ?? ''));
            $size = trim((string) ($data['size'] ?? ''));

            $stock = filter_var(
                $data['stockQuantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            $threshold = filter_var(
                $data['lowStockThreshold'] ?? 5,
                FILTER_VALIDATE_INT
            );

            $priceOverride = trim((string) ($data['priceOverride'] ?? ''));

            if (
                !in_array($color, self::COLORS, true)
                || (!$isAccessory && !in_array($size, self::SIZES, true))
                || ($isAccessory && $size !== '' && !in_array($size, self::SIZES, true))
                || $stock === false || $stock < 0
                || $threshold === false || $threshold < 0
            ) {
                $this->addFlash(
                    'error',
                    'Vérifiez les couleurs, tailles et quantités des variantes.'
                );

                return false;
            }

            $combination = mb_strtolower($color . '|' . $size);

            if (isset($combinations[$combination])) {
                $this->addFlash(
                    'error',
                    'Une même combinaison couleur et taille ne peut pas être répétée.'
                );

                return false;
            }

            $combinations[$combination] = true;

            if (
                $priceOverride !== ''
                && (!is_numeric($priceOverride) || (float) $priceOverride < 0)
            ) {
                $this->addFlash(
                    'error',
                    'Le prix spécial doit être un nombre positif.'
                );

                return false;
            }

            $id = isset($data['id']) && $data['id'] !== ''
                ? (int) $data['id']
                : null;

            if ($id !== null) {
                if (!isset($existingVariants[$id])) {
                    $this->addFlash(
                        'error',
                        'Une variante envoyée ne correspond pas à ce produit.'
                    );

                    return false;
                }

                $variant = $existingVariants[$id];
                $submittedIds[] = $id;
            } else {
                $variant = new ProductVariant();

                $variant->setSku(
                    $this->generateSku($product, $color, $size)
                );

                $product->addVariant($variant);
                $entityManager->persist($variant);
            }

            $variant
                ->setColor($color)
                ->setSize($size !== '' ? $size : null)
                ->setStockQuantity($stock)
                ->setLowStockThreshold($threshold)
                ->setPriceOverride($priceOverride !== '' ? $priceOverride : null)
                ->setIsActive(($data['isActive'] ?? true) === true)
                ->setUpdatedAt(new \DateTimeImmutable());
        }

        // Une variante retirée du formulaire devient inactive.
        foreach ($existingVariants as $id => $variant) {
            if (!in_array($id, $submittedIds, true)) {
                $variant->setIsActive(false);
                $variant->setUpdatedAt(new \DateTimeImmutable());
            }
        }

        return true;
    }

    private function encodeExistingVariants(Product $product): string
    {
        $data = [];

        foreach ($product->getVariants() as $variant) {
            $data[] = [
                'id' => $variant->getId(),
                'color' => $variant->getColor(),
                'size' => $variant->getSize(),
                'stockQuantity' => $variant->getStockQuantity(),
                'lowStockThreshold' => $variant->getLowStockThreshold(),
                'priceOverride' => $variant->getPriceOverride(),
                'isActive' => $variant->isActive(),
            ];
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function generateSku(
        Product $product,
        string $color,
        string $size
    ): string {
        $base = strtoupper(substr(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $product->getSlug() ?? 'PRODUIT'
            ),
            0,
            35
        ));

        $sizeCode = $size !== '' ? strtoupper($size) : 'STD';

        return $base . '-' . strtoupper(substr($color, 0, 3))
            . '-' . $sizeCode . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function decodeImageColors(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $colors = json_decode($json, true);

        if (!is_array($colors)) {
            return [];
        }

        return array_map(
            static fn($color) => in_array($color, self::COLORS, true) ? $color : null,
            $colors
        );
    }

    private function uploadImage(
        UploadedFile $imageFile,
        SluggerInterface $slugger
    ): string {
        $originalFilename = pathinfo(
            $imageFile->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $safeFilename = $slugger->slug($originalFilename)->lower();

        $newFilename = $safeFilename . '-' . uniqid()
            . '.' . $imageFile->guessExtension();

        $uploadDirectory = $this->getParameter('kernel.project_dir')
            . '/public/uploads/products';

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0775, true);
        }

        $imageFile->move($uploadDirectory, $newFilename);

        return 'uploads/products/' . $newFilename;
    }

    private function addProductImages(
        Product $product,
        array $imagesFiles,
        array $imagesColors,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ): void {
        $sortOrder = $product->getImages()->count();

        foreach ($imagesFiles as $index => $imageFile) {
            if (!$imageFile instanceof UploadedFile) {
                continue;
            }

            $color = $imagesColors[$index] ?? null;

            if (!$color) {
                continue;
            }

            $productImage = new ProductImage();

            $productImage
                ->setImagePath($this->uploadImage($imageFile, $slugger))
                ->setAltText($product->getName() . ' - ' . $color)
                ->setColor($color)
                ->setIsPrimary(false)
                ->setSortOrder($sortOrder++)
                ->setProduct($product);

            $product->addImage($productImage);
            $entityManager->persist($productImage);
        }
    }
}


<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'required' => false,
                'constraints' => [
                    new NotBlank(
                        message: 'Le nom du produit est obligatoire.'
                    ),
                    new Length(
                        max: 180,
                        maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Ex. : Robe élégante beige',
                ],
            ])

            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'help' => 'Laissez vide pour le générer automatiquement.',
                'attr' => [
                    'placeholder' => 'robe-elegante-beige',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Le description est requis.'
                    ),
                ],
            ])

            ->add('price', MoneyType::class, [
                'label' => 'Prix de vente',
                'required' => false,
                'currency' => 'MGA',
                'constraints' => [
                    new NotBlank(
                        message: 'Le prix est obligatoire.'
                    ),
                ],
            ])

            ->add('oldPrice', MoneyType::class, [
                'label' => 'Ancien prix (facultatif)',
                'currency' => 'MGA',
                'required' => false,
            ])

            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'required' => false,
            ])

            // Image principale du produit.
            ->add('imageFile', FileType::class, [
                'label' => 'Image principale',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Choisissez une image JPG, PNG ou WEBP.',
                        maxSizeMessage: 'L’image ne doit pas dépasser 2 Mo.'
                    ),
                ],
            ])

            // Images supplémentaires du produit.
            ->add('imagesFiles', FileType::class, [
                'label' => 'Images supplémentaires',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new Image(
                            maxSize: '2M',
                            mimeTypes: [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ],
                            mimeTypesMessage: 'Choisissez uniquement des images JPG, PNG ou WEBP.',
                            maxSizeMessage: 'Chaque image ne doit pas dépasser 2 Mo.'
                        ),
                    ]),
                ],
            ])

            // Couleurs associées aux images supplémentaires.
            ->add('imagesColors', TextType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'data-role' => 'images-colors',
                ],
            ])

            // Données des variantes générées par JavaScript.
            ->add('variantsData', HiddenType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
            ])

            // Catégorie du produit.
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Choisir une catégorie',

                'query_builder' => function (CategoryRepository $repository) {
                    return $repository->createQueryBuilder('c')
                        ->andWhere('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.sortOrder', 'ASC')
                        ->addOrderBy('c.name', 'ASC');
                },

                // Transmettre au JavaScript si la catégorie appartient à Accessoires.
                'choice_attr' => function (
                    ?Category $category,
                    mixed $key,
                    mixed $value
                ): array {
                    $currentCategory = $category;
                    $isAccessory = false;

                    // Vérifier la catégorie sélectionnée et tous ses parents.
                    while ($currentCategory !== null) {
                        $categoryName = mb_strtolower(
                            $currentCategory->getName() ?? ''
                        );

                        $categoryName = strtr($categoryName, [
                            'é' => 'e',
                            'è' => 'e',
                            'ê' => 'e',
                            'ë' => 'e',
                        ]);

                        if (str_contains($categoryName, 'accessoire')) {
                            $isAccessory = true;
                            break;
                        }

                        $currentCategory = $currentCategory->getParent();
                    }

                    return [
                        'data-is-accessory' => $isAccessory ? '1' : '0',
                    ];
                },

                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez choisir une catégorie.'
                    ),
                ],
            ])

            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut du produit',
                'choices' => [
                    'Publié' => true,
                    'Désactivé' => false,
                ],
                'placeholder' => false,
            ])

            ->add('isFeatured', ChoiceType::class, [
                'label' => 'Mise en vedette',
                'choices' => [
                    'En vedette' => true,
                    'Standard' => false,
                ],
                'placeholder' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex. : FEMME',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le nom de la catégorie est obligatoire.'
                    ),
                    new Assert\Length(
                        max: 100,
                        maxMessage: 'Le nom ne doit pas dépasser 100 caractères.'
                    ),
                ],
            ])

            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex. : femme',
                    'maxlength' => 150,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le slug est obligatoire.'
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                        message: 'Utilisez des minuscules, des chiffres et des tirets.'
                    ),
                    new Assert\Length(max: 150),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'Décrivez brièvement cette catégorie...',
                    'rows' => 4,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le description est requis.'
                    ),
                ]
            ])

            // Le fichier est traité par le contrôleur,
            // il n'est pas enregistré directement dans l'entité.
            ->add('imageFile', FileType::class, [
                'label' => 'Image de la catégorie',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '2M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Veuillez choisir une image JPG, PNG ou WebP.',
                        maxSizeMessage: 'L’image ne doit pas dépasser 2 Mo.'
                    ),
                ],
            ])

            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie parent',
                'placeholder' => 'Aucune (catégorie principale)',
                'required' => false,
                'query_builder' => function (
                    \App\Repository\CategoryRepository $repository
                ) use ($options) {
                    $query = $repository->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');

                    // Empêche une catégorie de devenir son propre parent.
                    $currentCategory = $options['data'];

                    if (
                        $currentCategory instanceof Category
                        && $currentCategory->getId() !== null
                    ) {
                        $query->andWhere('c.id != :currentId')
                            ->setParameter('currentId', $currentCategory->getId());
                    }

                    return $query;
                },
                
            ])

            ->add('isActive', CheckboxType::class, [
                'label' => 'Catégorie active',
                'required' => false,
                'help' => 'Une catégorie inactive ne doit pas apparaître dans la boutique publique.',
            ])

            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d’affichage',
                'required' => true,
                'empty_data' => '0',
                'attr' => [
                    'min' => 0,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Veuillez indiquer un ordre d’affichage.'
                    ),
                    new Assert\PositiveOrZero(
                        message: 'L’ordre doit être égal ou supérieur à zéro.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}

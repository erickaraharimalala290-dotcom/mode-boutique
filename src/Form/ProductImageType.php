<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Image;

class ProductImageType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder

            // Sélectionner le produit auquel les images seront associées.
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'label' => 'Produit associé',
                'placeholder' => 'Sélectionner un produit',
                'required' => true,
                'query_builder' => function (
                    ProductRepository $repository
                ) {
                    return $repository->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC');
                },
            ])

            // Permettre de sélectionner plusieurs images simultanément.
            ->add('imageFiles', FileType::class, [
                'label' => 'Images du produit',
                'mapped' => false,
                'multiple' => true,
                'required' => true,
                'constraints' => [
                    new All(
                        constraints: [
                            new Image(
                                maxSize: '2M',
                                mimeTypes: [
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ],
                                mimeTypesMessage: 'Choisissez des images JPG, PNG ou WEBP.',
                                maxSizeMessage: 'Chaque image ne doit pas dépasser 2 Mo.'
                            ),
                        ]
                    ),
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'help' => 'Formats acceptés : JPG, PNG et WEBP. Maximum 2 Mo par image.',
            ])

            // Associer les images envoyées à une couleur.
            ->add('color', TextType::class, [
                'label' => 'Couleur associée',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. : Blanc, Violet, Noir',
                ],
            ])

            // Définir le texte alternatif des images.
            ->add('altText', TextType::class, [
                'label' => 'Texte alternatif',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. : Robe violette vue de face',
                ],
            ])

            // Définir l'ordre d'affichage des images.
            ->add('sortOrder', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'required' => true,
                'data' => 0,
                'attr' => [
                    'min' => 0,
                ],
            ])

            // Définir si la première image envoyée est l'image principale.
            ->add('isPrimary', CheckboxType::class, [
                'label' => 'Définir la première image comme image principale',
                'required' => false,
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => ProductImage::class,
        ]);
    }
}
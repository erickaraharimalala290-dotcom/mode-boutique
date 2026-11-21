<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full rounded-xl border border-beige bg-white px-4 py-3 text-ink placeholder:text-ink-soft focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';

        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => $inputClass,
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre prénom.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: "/^[\p{L}]+(?:[ '\-’][\p{L}]+)*$/u",
                        message: 'Le prénom ne doit contenir que des lettres, des espaces, des apostrophes ou des tirets.'
                    ),
                ],
            ])
            ->add('lastname', TextType::class, [
                'required' => false,
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => $inputClass,
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre nom.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),

                    new Regex(
                        pattern: "/^[\p{L}]+(?:[ '\-’][\p{L}]+)*$/u",
                        message: 'Le nom ne doit contenir que des lettres, des espaces, des apostrophes ou des tirets.'
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'Adresse e-mail',
                'attr' => [
                    'placeholder' => 'exemple@email.com',
                    'class' => $inputClass,
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Length(
                        max: 180,
                        maxMessage: 'L’adresse e-mail ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'required' => false,
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => 'Au moins 6 caractères',
                        'class' => $inputClass,
                        'autocomplete' => 'new-password',
                        'minlength' => 6,
                        'maxlength' => 4096,
                    ],
                ],
                'second_options' => [
                    'required' => false,
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => 'Confirmez votre mot de passe',
                        'class' => $inputClass,
                        'autocomplete' => 'new-password',
                        'minlength' => 6,
                        'maxlength' => 4096,
                    ],
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'required' => false,
                'label' => 'J’accepte les conditions d’utilisation.',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'Vous devez accepter les conditions d’utilisation.'
                    ),
                ],
            ])
            ->add('Submit', SubmitType::class, [
                'label' => 'inscription'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

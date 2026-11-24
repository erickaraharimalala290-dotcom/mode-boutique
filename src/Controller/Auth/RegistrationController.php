<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier
    ) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();

        $form = $this->createForm(
            RegistrationFormType::class,
            $user
        );

        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {


            // Récupérer le mot de passe saisi
            $plainPassword = $form->get('plainPassword')->getData();

            // Hasher le mot de passe avant de le stocker
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword
                )
            );

            // Définir les dates de création et de modification
            $now = new \DateTimeImmutable();

            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);

            // Attribuer le rôle client par défaut
            $user->setRoles(['ROLE_USER']);

            // Enregistrer le nouvel utilisateur
            $entityManager->persist($user);
            $entityManager->flush();

            // Générer le lien de vérification et envoyer l'e-mail
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(
                        new Address(
                            'contact@sc1tsta8247.universe.wf',
                            'ModeBoutique'
                        )
                    )
                    ->to((string) $user->getEmail())
                    ->subject(
                        'Confirmez votre adresse e-mail - ModeBoutique'
                    )
                    ->htmlTemplate(
                        'pages/auth/confirmation_email.html.twig'
                    )
            );

            // Mémoriser l'utilisateur en attente de confirmation dans la session.
            $request->getSession()->set(
                'pending_verification_user_id',
                $user->getId()
            );

            // Afficher la page d'attente de confirmation de l'adresse e-mail.
            return $this->redirectToRoute('app_registration_pending');
        }

        return $this->render(
            'pages/auth/register.html.twig',
            [
                'registrationForm' => $form,

            ]
        );
    }

    /**
     * Affiche la page d'attente après la création du compte.
     */
    #[Route(
        '/inscription/confirmation',
        name: 'app_registration_pending',
        methods: ['GET']
    )]
    public function registrationPending(
        Request $request,
        UserRepository $userRepository
    ): Response {
        // Récupérer l'identifiant du compte en attente dans la session.
        $userId = $request->getSession()->get('pending_verification_user_id');

        if (null === $userId) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($userId);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // Rediriger vers la connexion si l'adresse est déjà confirmée.
        if ($user->isVerified()) {
            $request->getSession()->remove('pending_verification_user_id');

            return $this->redirectToRoute('app_login');
        }

        return $this->render(
            'pages/auth/registration_pending.html.twig',
            [
                'email' => $user->getEmail(),
            ]
        );
    }

    /**
     * Vérifie automatiquement si l'adresse e-mail a été confirmée.
     */
    #[Route(
        '/inscription/statut-verification',
        name: 'app_registration_status',
        methods: ['GET']
    )]
    public function registrationStatus(
        Request $request,
        UserRepository $userRepository
    ): JsonResponse {
        // Récupérer le compte associé à la session actuelle.
        $userId = $request->getSession()->get('pending_verification_user_id');

        if (null === $userId) {
            return new JsonResponse(['verified' => false]);
        }

        $user = $userRepository->find($userId);

        // Retourner uniquement le statut de vérification.
        $response = new JsonResponse([
            'verified' => null !== $user && $user->isVerified(),
        ]);

        // Empêcher la mise en cache de ce statut.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ): Response {
        // Récupérer l'identifiant de l'utilisateur dans le lien
        $id = $request->query->get('id');

        if (null === $id) {
            $this->addFlash(
                'verify_email_error',
                'Le lien de vérification est invalide.'
            );

            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            $this->addFlash(
                'verify_email_error',
                'Utilisateur introuvable.'
            );

            return $this->redirectToRoute('app_register');
        }

        // Vérifier le lien et confirmer l'adresse e-mail
        try {
            $this->emailVerifier->handleEmailConfirmation(
                $request,
                $user
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'verify_email_error',
                $translator->trans(
                    $exception->getReason(),
                    [],
                    'VerifyEmailBundle'
                )
            );

            return $this->redirectToRoute('app_register');
        }

        // Informer le client que son adresse est confirmée
        $this->addFlash(
            'success',
            'Votre adresse e-mail est confirmée. Vous pouvez maintenant vous connecter.'
        );

        // Rediriger vers la connexion
        return $this->redirectToRoute('app_login');
    }


    /**
     * Permet à un utilisateur de demander un nouveau lien de confirmation.
     */
    #[Route(
        '/register/renvoyer-confirmation',
        name: 'app_resend_verification',
        methods: ['GET', 'POST']
    )]
    public function resendVerification(
        Request $request,
        UserRepository $userRepository
    ): Response {
        if ($request->isMethod('POST')) {
            // Vérifier le jeton CSRF avant de traiter la demande.
            if (!$this->isCsrfTokenValid(
                'resend_verification',
                $request->request->get('_token')
            )) {
                $this->addFlash('error', 'Votre demande est invalide. Veuillez réessayer.');

                return $this->redirectToRoute('app_resend_verification');
            }

            // Récupérer et nettoyer l'adresse e-mail saisie.
            $email = trim((string) $request->request->get('email'));

            // Rechercher le compte correspondant à cette adresse.
            $user = $userRepository->findOneBy([
                'email' => $email,
            ]);

            // Envoyer un nouveau lien uniquement si le compte existe et n'est pas vérifié.
            if ($user && !$user->isVerified()) {
                $this->emailVerifier->sendEmailConfirmation(
                    'app_verify_email',
                    $user,
                    (new TemplatedEmail())
                        ->from(new Address(
                            'contact@sc1tsta8247.universe.wf',
                            'ModeBoutique'
                        ))
                        ->to((string) $user->getEmail())
                        ->subject('Confirmez votre adresse e-mail - ModeBoutique')
                        ->htmlTemplate('pages/auth/confirmation_email.html.twig')
                );
            }

            // Afficher le même message pour ne pas révéler si un compte existe.
            $this->addFlash(
                'success',
                'Si un compte non confirmé correspond à cette adresse, un nouvel e-mail de confirmation vient d’être envoyé. Vérifiez également vos courriers indésirables.'
            );

            return $this->redirectToRoute('app_resend_verification');
        }

        return $this->render('pages/auth/resend_verification.html.twig');
    }
}

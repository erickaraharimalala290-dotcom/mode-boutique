<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        // Préremplir les informations du client connecté.
        $formValues = [
            'name' => $user instanceof User
                ? trim($user->getFirstname() . ' ' . $user->getLastname())
                : '',
            'email' => $user instanceof User ? $user->getEmail() : '',
            'subject' => '',
            'orderReference' => '',
            'message' => '',
        ];

        if ($request->isMethod('POST')) {
            // Récupérer les données envoyées par le formulaire.
            $formValues['name'] = trim((string) $request->request->get('name', ''));
            $formValues['email'] = trim((string) $request->request->get('email', ''));
            $formValues['subject'] = trim((string) $request->request->get('subject', ''));
            $formValues['orderReference'] = trim((string) $request->request->get('orderReference', ''));
            $formValues['message'] = trim((string) $request->request->get('message', ''));

            // Conserver les informations du compte connecté.
            if ($user instanceof User) {
                $formValues['name'] = trim(
                    $user->getFirstname() . ' ' . $user->getLastname()
                );
                $formValues['email'] = $user->getEmail();
            }

            // Vérifier le jeton CSRF.
            if (!$this->isCsrfTokenValid(
                'contact_message',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException(
                    'Le formulaire a expiré. Veuillez réessayer.'
                );
            }

            // Vérifier les champs obligatoires.
            if (
                $formValues['name'] === ''
                || $formValues['email'] === ''
                || $formValues['subject'] === ''
                || $formValues['message'] === ''
            ) {
                $this->addFlash(
                    'error',
                    'Veuillez remplir tous les champs obligatoires.'
                );
            } elseif (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addFlash(
                    'error',
                    'Veuillez saisir une adresse e-mail valide.'
                );
            } elseif (
                mb_strlen($formValues['name']) > 150
                || mb_strlen($formValues['email']) > 180
                || mb_strlen($formValues['subject']) > 180
                || mb_strlen($formValues['orderReference']) > 100
            ) {
                $this->addFlash(
                    'error',
                    'Certaines informations sont trop longues.'
                );
            } else {
                // Ajouter la référence de commande au message si elle est renseignée.
                $message = $formValues['message'];

                if ($formValues['orderReference'] !== '') {
                    $message = "Référence de commande : "
                        . $formValues['orderReference']
                        . "\n\n"
                        . $message;
                }

                // Enregistrer le message dans la base de données.
                $contactMessage = new ContactMessage();
                $contactMessage->setName($formValues['name']);
                $contactMessage->setEmail($formValues['email']);
                $contactMessage->setSubject($formValues['subject']);
                $contactMessage->setMessage($message);

                // Associer le message au compte client connecté.
                if ($user instanceof User) {
                    $contactMessage->setUser($user);
                }

                $entityManager->persist($contactMessage);
                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Votre message a bien été envoyé. Merci de nous avoir contactés !'
                );

                return $this->redirectToRoute('app_contact');
            }
        }

        $response = new Response();

        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('pages/contact/index.html.twig', [
            'formValues' => $formValues,
            'isLoggedIn' => $user instanceof User,
        ], $response);
    }
}

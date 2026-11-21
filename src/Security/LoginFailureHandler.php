<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        // Rechercher l'exception d'origine.
        $currentException = $exception;
        $accountDisabled = false;
        $emailNotVerified = false;

        while ($currentException !== null) {
            if ($currentException instanceof DisabledException) {
                if (
                    $currentException->getMessage()
                    === UserChecker::EMAIL_NOT_VERIFIED_MESSAGE
                ) {
                    $emailNotVerified = true;
                } else {
                    $accountDisabled = true;
                }

                break;
            }

            $currentException = $currentException->getPrevious();
        }

        // Définir le message adapté selon la situation.
        if ($emailNotVerified) {
            $message = UserChecker::EMAIL_NOT_VERIFIED_MESSAGE;
        } elseif ($accountDisabled) {
            $message = 'Votre compte a été désactivé. Veuillez contacter l’administrateur.';
        } else {
            $message = 'Adresse e-mail ou mot de passe incorrect.';
        }

        $request->getSession()->set('login_error_message', $message);

        return new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
    }
}

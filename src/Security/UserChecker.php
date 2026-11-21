<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserChecker implements UserCheckerInterface
{
    // Message utilisé lorsqu'un client n'a pas encore vérifié son adresse e-mail.
    public const EMAIL_NOT_VERIFIED_MESSAGE =
        'Veuillez confirmer votre adresse e-mail avant de vous connecter.';

    // Vérifier le statut du compte avant l'authentification.
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Les administrateurs et les managers peuvent toujours se connecter.
        $isPrivileged = in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_MANAGER', $user->getRoles(), true);

        if ($isPrivileged) {
            return;
        }

        // Vérifier si le compte client est actif.
        if (!$user->isActive()) {
            $exception = new DisabledException(
                'Votre compte a été désactivé. Veuillez contacter l’administrateur.'
            );

            $exception->setUser($user);

            throw $exception;
        }

        // Vérifier si le client a confirmé son adresse e-mail.
        if (!$user->isVerified()) {
            $exception = new DisabledException(
                self::EMAIL_NOT_VERIFIED_MESSAGE
            );

            $exception->setUser($user);

            throw $exception;
        }
    }

    // Vérifier le compte après l'authentification.
    public function checkPostAuth(
        UserInterface $user,
        ?TokenInterface $token = null
    ): void {
    }
}

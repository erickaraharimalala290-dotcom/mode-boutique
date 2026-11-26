import './stimulus_bootstrap.js';
import './styles/app.css';

console.log('ModeBoutique - Application JavaScript chargée');

// Gestion du menu responsive de l'espace client.
function initAccountMenu() {
    const toggle = document.getElementById('account-menu-toggle');
    const content = document.getElementById('account-menu-content');
    const arrow = document.getElementById('account-menu-arrow');

    // Vérifier si les éléments du menu existent sur la page.
    if (!toggle || !content || !arrow) {
        return;
    }

    // Éviter d'ajouter plusieurs fois les mêmes événements.
    if (toggle.dataset.accountMenuInitialized === 'true') {
        return;
    }

    toggle.dataset.accountMenuInitialized = 'true';

    const mobileScreen = window.matchMedia('(max-width: 1023px)');

    // Adapter l'affichage du menu selon la taille de l'écran.
    function updateMenu() {
        if (mobileScreen.matches) {
            content.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
            arrow.style.transform = 'rotate(0deg)';
        } else {
            content.classList.remove('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            arrow.style.transform = 'rotate(180deg)';
        }
    }

    updateMenu();

    // Ouvrir ou fermer le menu lors du clic.
    toggle.addEventListener('click', function () {
        if (!mobileScreen.matches) {
            return;
        }

        const isOpen = !content.classList.contains('hidden');

        content.classList.toggle('hidden');

        toggle.setAttribute('aria-expanded', String(!isOpen));

        arrow.style.transform = isOpen
            ? 'rotate(0deg)'
            : 'rotate(180deg)';
    });

    // Actualiser le menu lors du changement de taille d'écran.
    mobileScreen.addEventListener('change', updateMenu);
}

// Initialiser le menu lorsque le DOM classique est prêt.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccountMenu);
} else {
    initAccountMenu();
}

// Réinitialiser le menu après chaque navigation avec Symfony UX Turbo.
document.addEventListener('turbo:load', initAccountMenu);


// Gestion automatique du statut des messages clients.
function initAutomaticMessageRead() {
    // Éviter d'enregistrer plusieurs fois le même événement.
    if (document.documentElement.dataset.messageAutoReadBound === '1') {
        return;
    }

    document.documentElement.dataset.messageAutoReadBound = '1';

    // Détecter l'ouverture d'une conversation.
    document.addEventListener('toggle', async (event) => {
        const details = event.target;

        // Vérifier qu'il s'agit bien d'une conversation ouverte.
        if (
            !(details instanceof HTMLDetailsElement) ||
            !details.open ||
            !details.hasAttribute('data-message-details')
        ) {
            return;
        }

        // Récupérer le formulaire caché de marquage automatique.
        const form = details.querySelector('[data-auto-mark-read]');

        if (!form || form.dataset.submitted === '1') {
            return;
        }

        // Éviter les requêtes répétées lors des ouvertures successives.
        form.dataset.submitted = '1';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Impossible de marquer le message comme lu.');
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error('La mise à jour du message a échoué.');
            }

            // Mettre à jour toutes les étiquettes de statut du message.
            details.querySelectorAll('[data-message-read-label]').forEach((label) => {
                label.textContent = 'Lu';

                label.classList.remove(
                    'bg-amber-50',
                    'text-amber-700',
                    'font-semibold'
                );

                label.classList.add(
                    'bg-stone-100',
                    'text-stone-500',
                    'font-medium'
                );
            });

            // Actualiser le bouton permettant de modifier manuellement le statut.
            const toggleButton = details.querySelector('[data-read-toggle-button]');

            if (toggleButton) {
                toggleButton.textContent = 'Marquer comme non lu';

                toggleButton.classList.remove(
                    'bg-emerald-700',
                    'text-white',
                    'hover:bg-emerald-800'
                );

                toggleButton.classList.add(
                    'border',
                    'border-amber-200',
                    'bg-white',
                    'text-amber-700',
                    'hover:bg-amber-50'
                );
            }

            // Décrémenter les compteurs uniquement si le message était non lu.
            if (result.wasUnread) {
                // Actualiser les badges du menu desktop et mobile.
                document.querySelectorAll('[data-notification-count="messages"]').forEach((badge) => {
                    const currentCount = Number(badge.textContent.trim()) || 0;
                    const newCount = Math.max(0, currentCount - 1);

                    badge.textContent = newCount;

                    badge.classList.toggle('hidden', newCount === 0);
                    badge.classList.toggle('inline-flex', newCount > 0);
                });

                // Diminuer le nombre de messages non lus dans les statistiques.
                const unreadStat = document.querySelector('[data-unread-messages-stat]');

                if (unreadStat) {
                    const currentCount = Number(unreadStat.textContent.trim()) || 0;
                    unreadStat.textContent = Math.max(0, currentCount - 1);
                }

                // Augmenter le nombre de messages déjà lus.
                const readStat = document.querySelector('[data-read-messages-stat]');

                if (readStat) {
                    const currentCount = Number(readStat.textContent.trim()) || 0;
                    readStat.textContent = currentCount + 1;
                }
            }

            // Enregistrer localement le nouveau statut du message.
            details.dataset.messageWasRead = '1';

        } catch (error) {
            console.error(error);

            // Autoriser une nouvelle tentative si la requête a échoué.
            form.dataset.submitted = '0';
        }
    }, true);
}

// Initialiser la gestion automatique des messages.
initAutomaticMessageRead();

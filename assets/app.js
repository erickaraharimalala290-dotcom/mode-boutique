import './stimulus_bootstrap.js';
import './styles/app.css';

console.log('ModeBoutique - Application JavaScript chargée');

// Gestion du menu responsive de l'espace client
function initAccountMenu() {
    const toggle = document.getElementById('account-menu-toggle');
    const content = document.getElementById('account-menu-content');
    const arrow = document.getElementById('account-menu-arrow');

    // Vérifier si les éléments du menu existent sur la page.
    if (!toggle || !content || !arrow) {
        return;
    }

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

        toggle.setAttribute(
            'aria-expanded',
            String(!isOpen)
        );

        arrow.style.transform = isOpen
            ? 'rotate(0deg)'
            : 'rotate(180deg)';
    });

    // Actualiser le menu lors du changement de taille d'écran.
    mobileScreen.addEventListener('change', updateMenu);
}

// Initialiser le menu lorsque le DOM est prêt.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccountMenu);
} else {
    initAccountMenu();
}

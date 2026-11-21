document.addEventListener('DOMContentLoaded', () => {
    // URL de l'endpoint Symfony qui fournit les compteurs.
    const endpoint = document.body.dataset.notificationUrl;

    // Actualiser les badges de notification sans recharger la page.
    async function refreshNotifications() {
        // Ne pas effectuer de requête lorsque l'onglet est masqué.
        if (document.hidden || !endpoint) {
            return;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const counts = await response.json();

            // Mettre à jour tous les badges correspondant aux compteurs reçus.
            Object.entries(counts).forEach(([name, value]) => {
                document
                    .querySelectorAll(`[data-notification-count="${name}"]`)
                    .forEach((badge) => {
                        const count = Number(value) || 0;

                        badge.textContent = count;
                        badge.classList.toggle('hidden', count <= 0);
                        badge.classList.toggle('inline-flex', count > 0);
                    });
            });
        } catch (error) {
            // Conserver les compteurs actuels en cas d'erreur réseau.
        }
    }

    // Effectuer une première vérification dès l'ouverture de la page.
    refreshNotifications();

    // Vérifier les notifications toutes les cinq secondes.
    window.setInterval(refreshNotifications, 5000);
});
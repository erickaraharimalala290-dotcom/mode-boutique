document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('passwordForm');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');

    if (!form || !newPassword || !confirmPassword) {
        return;
    }

    // Vérification du nouveau mot de passe
    form.addEventListener('submit', (event) => {
        if (newPassword.value.length < 6) {
            event.preventDefault();
            alert('Le nouveau mot de passe doit contenir au moins 6 caractères.');
            newPassword.focus();
            return;
        }

        if (newPassword.value !== confirmPassword.value) {
            event.preventDefault();
            alert('Le nouveau mot de passe et sa confirmation ne correspondent pas.');
            confirmPassword.focus();
        }
    });
});

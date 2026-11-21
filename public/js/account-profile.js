document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('profileForm');
    const editButton = document.getElementById('editProfileButton');
    const cancelButton = document.getElementById('cancelProfileButton');
    const actions = document.getElementById('profileActions');
    const message = document.getElementById('profileDemoMessage');

    const fileInput = document.getElementById('profilePhotoInput');
    const changePhotoButton = document.getElementById('changePhotoButton');
    const removePhotoButton = document.getElementById('removePhotoButton');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const initials = document.getElementById('profileInitials');

    const fields = [...document.querySelectorAll('[data-profile-field]')];

    if (!form || !editButton || !cancelButton || !actions) {
        return;
    }

    let savedValues = {};
    let selectedPhotoUrl = null;

    function saveCurrentValues() {
        savedValues = {};

        fields.forEach((field) => {
            savedValues[field.id] = field.value;
        });
    }

    function setEditMode(enabled) {
        fields.forEach((field) => {
            field.readOnly = !enabled;
            field.classList.toggle('bg-cream', !enabled);
            field.classList.toggle('bg-white', enabled);
            field.classList.toggle('cursor-text', enabled);
        });

        editButton.classList.toggle('hidden', enabled);
        actions.classList.toggle('hidden', !enabled);
        actions.classList.toggle('flex', enabled);

        if (enabled) {
            message.classList.add('hidden');
        }
    }

    function showPhoto(file) {
        if (selectedPhotoUrl) {
            URL.revokeObjectURL(selectedPhotoUrl);
        }

        selectedPhotoUrl = URL.createObjectURL(file);
        photoPreview.src = selectedPhotoUrl;
        photoPreview.classList.remove('hidden');
        initials.classList.add('hidden');
    }

    saveCurrentValues();

    editButton.addEventListener('click', () => {
        setEditMode(true);
        document.getElementById('firstName').focus();
    });

    changePhotoButton.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];

        if (!file) {
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            alert('Veuillez choisir une image JPG, PNG ou WebP.');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('La photo ne doit pas dépasser 5 Mo.');
            fileInput.value = '';
            return;
        }

        showPhoto(file);
        message.classList.remove('hidden');
        message.textContent =
            'Aperçu de la photo mis à jour. Elle ne sera pas conservée après avoir quitté la page.';
    });

    removePhotoButton.addEventListener('click', () => {
        fileInput.value = '';
        photoPreview.src = '';
        photoPreview.classList.add('hidden');
        initials.classList.remove('hidden');

        if (selectedPhotoUrl) {
            URL.revokeObjectURL(selectedPhotoUrl);
            selectedPhotoUrl = null;
        }

        message.classList.remove('hidden');
        message.textContent =
            'La photo a été retirée de cet aperçu uniquement.';
    });

    cancelButton.addEventListener('click', () => {
        fields.forEach((field) => {
            field.value = savedValues[field.id] ?? '';
        });

        setEditMode(false);
        message.classList.add('hidden');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        saveCurrentValues();
        setEditMode(false);

        message.classList.remove('hidden');
        message.textContent =
            'Aperçu mis à jour. Les informations ne sont pas encore enregistrées dans la base de données.';
    });

    window.addEventListener('beforeunload', () => {
        if (selectedPhotoUrl) {
            URL.revokeObjectURL(selectedPhotoUrl);
        }
    });
});

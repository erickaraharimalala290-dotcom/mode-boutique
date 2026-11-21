function initProductImages() {
    const fileInput = document.getElementById('product_imagesFiles');
    const list = document.getElementById('selected-images-list');
    const colorsInput = document.getElementById('product_imagesColors');
    const productForm = document.getElementById('product-form');

    if (!fileInput || !list || !colorsInput || !productForm) {
        return;
    }

    // Éviter d'initialiser plusieurs fois le même formulaire.
    if (productForm.dataset.imagesInitialized === 'true') {
        return;
    }

    productForm.dataset.imagesInitialized = 'true';

    const availableColors = [
        'Noir',
        'Beige',
        'Marron',
        'Blanc',
        'Bordeaux',
        'Bleu',
        'Gris',
        'Rose',
        'Violet',
        'Vert',
        'Rouge',
        'Doré', 
        'Argenté',
        'Grenat'
    ];

    // Enregistrer les couleurs sélectionnées dans le champ caché.
    function updateColors() {
        const selectedColors = Array.from(
            list.querySelectorAll('select[data-image-color]')
        ).map(function (select) {
            return select.value;
        });

        colorsInput.value = JSON.stringify(selectedColors);
    }

    // Afficher un aperçu pour chaque image sélectionnée.
    fileInput.addEventListener('change', function () {
        list.innerHTML = '';

        Array.from(fileInput.files).forEach(function (file, index) {
            const row = document.createElement('div');

            row.className =
                'flex flex-col gap-3 rounded-xl border border-[#E7DED2] p-3 sm:flex-row sm:items-center';

            const preview = document.createElement('img');
            preview.className = 'h-20 w-20 rounded-lg object-cover';
            preview.alt = file.name;
            preview.src = URL.createObjectURL(file);

            const details = document.createElement('div');
            details.className = 'min-w-0 flex-1';

            const filename = document.createElement('p');
            filename.className =
                'break-all text-sm font-medium text-[#44403C]';
            filename.textContent = file.name;

            const label = document.createElement('label');
            label.className =
                'mb-1 mt-2 block text-xs font-medium text-[#78716C]';
            label.textContent = 'Couleur de cette image';

            const select = document.createElement('select');
            select.className =
                'w-full rounded-lg border border-[#D6C7B5] bg-white px-3 py-2 text-sm';

            select.dataset.imageColor = String(index);
            select.required = true;

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Choisir une couleur';
            select.appendChild(placeholder);

            availableColors.forEach(function (color) {
                const option = document.createElement('option');
                option.value = color;
                option.textContent = color;
                select.appendChild(option);
            });

            select.addEventListener('change', updateColors);

            details.appendChild(filename);
            details.appendChild(label);
            details.appendChild(select);

            row.appendChild(preview);
            row.appendChild(details);

            list.appendChild(row);
        });

        updateColors();
    });

    // Vérifier que chaque image possède une couleur avant l'envoi.
    productForm.addEventListener('submit', function (event) {
        const selects = Array.from(
            list.querySelectorAll('select[data-image-color]')
        );

        const hasMissingColor = selects.some(function (select) {
            return select.value === '';
        });

        if (hasMissingColor) {
            event.preventDefault();

            alert(
                'Veuillez choisir une couleur pour chaque image supplémentaire.'
            );

            return;
        }

        updateColors();
    });
}

// Initialiser le script lors du chargement normal ou d'une navigation Turbo.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductImages);
} else {
    initProductImages();
}

document.addEventListener('turbo:load', initProductImages);

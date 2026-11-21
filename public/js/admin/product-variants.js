function initProductVariants() {
    const container = document.getElementById('variants-list');
    const addButton = document.getElementById('add-variant-button');
    const dataInput = document.getElementById('product_variantsData');
    const productForm = document.getElementById('product-form');
    const categorySelect = document.getElementById('product_category');

    if (!container || !addButton || !dataInput || !productForm) {
        return;
    }

    // Éviter d'initialiser plusieurs fois le même formulaire.
    if (productForm.dataset.variantsInitialized === 'true') {
        return;
    }

    productForm.dataset.variantsInitialized = 'true';

    const colors = [
        'Noir', 'Beige', 'Marron', 'Blanc', 'Bordeaux',
        'Bleu', 'Gris', 'Rose', 'Violet', 'Vert', 'Rouge', 'Doré', 'Argenté','Grenat'
    ];

    const sizes = [
        'XS', 'S', 'M', 'L', 'XL', 'XXL',
        '36', '37', '38', '39', '40', '41',
        '42', '43', '44', '45', '46'
    ];

    // Vérifier si la catégorie sélectionnée appartient à Accessoires.
    function isAccessoryCategory() {
        if (!categorySelect) {
            return false;
        }

        const selectedOption =
            categorySelect.options[categorySelect.selectedIndex];

        return selectedOption?.dataset.isAccessory === '1';
    }

    // Créer une liste déroulante.
    function createSelect(options, selected, placeholder) {
        const select = document.createElement('select');

        select.className =
            'w-full rounded-lg border border-[#D6C7B5] bg-white px-3 py-2 text-sm';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = placeholder;
        select.appendChild(emptyOption);

        options.forEach(function (value) {
            const option = document.createElement('option');

            option.value = value;
            option.textContent = value;
            option.selected = String(value) === String(selected);

            select.appendChild(option);
        });

        return select;
    }

    // Créer un champ avec son label.
    function createField(labelText, element) {
        const wrapper = document.createElement('div');
        const label = document.createElement('label');

        label.className =
            'mb-1 block text-xs font-medium text-[#57534E]';

        label.textContent = labelText;

        wrapper.appendChild(label);
        wrapper.appendChild(element);

        return wrapper;
    }

    // Enregistrer les variantes dans le champ caché.
    function updateData() {
        const accessory = isAccessoryCategory();
        const variants = [];

        container.querySelectorAll('.variant-row').forEach(function (row) {
            const value = function (field) {
                const element = row.querySelector(
                    '[data-field="' + field + '"]'
                );

                return element ? element.value : '';
            };

            const activeInput = row.querySelector(
                '[data-field="isActive"]'
            );

            variants.push({
                id: value('id') || null,
                color: value('color'),
                size: accessory ? '' : value('size'),
                stockQuantity: value('stockQuantity'),
                lowStockThreshold: value('lowStockThreshold'),
                priceOverride: value('priceOverride'),
                isActive: activeInput ? activeInput.checked : true
            });
        });

        dataInput.value = JSON.stringify(variants);
    }

    // Afficher ou masquer le champ taille / pointure.
    function updateSizeFields() {
        const accessory = isAccessoryCategory();

        container.querySelectorAll('.variant-row').forEach(function (row) {
            const sizeSelect = row.querySelector('[data-field="size"]');

            if (!sizeSelect) {
                return;
            }

            const sizeField = sizeSelect.parentElement;

            if (accessory) {
                sizeSelect.required = false;
                sizeSelect.value = '';
                sizeField.classList.add('hidden');
            } else {
                sizeSelect.required = true;
                sizeField.classList.remove('hidden');
            }
        });

        updateData();
    }

    // Créer une ligne de variante.
    function createVariantRow(variant = {}) {
        const row = document.createElement('div');

        row.className =
            'variant-row grid gap-3 rounded-xl border border-[#E7DED2] bg-[#FFFEFC] p-4 md:grid-cols-2';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.dataset.field = 'id';
        idInput.value = variant.id || '';

        const colorSelect = createSelect(
            colors,
            variant.color || '',
            'Choisir une couleur'
        );

        colorSelect.dataset.field = 'color';
        colorSelect.required = true;

        const sizeSelect = createSelect(
            sizes,
            variant.size || '',
            'Choisir une taille / pointure'
        );

        sizeSelect.dataset.field = 'size';

        const stockInput = document.createElement('input');
        stockInput.type = 'number';
        stockInput.min = '0';
        stockInput.value = variant.stockQuantity ?? 0;
        stockInput.className =
            'w-full rounded-lg border border-[#D6C7B5] px-3 py-2 text-sm';
        stockInput.dataset.field = 'stockQuantity';
        stockInput.required = true;

        const thresholdInput = document.createElement('input');
        thresholdInput.type = 'number';
        thresholdInput.min = '0';
        thresholdInput.value = variant.lowStockThreshold ?? 5;
        thresholdInput.className =
            'w-full rounded-lg border border-[#D6C7B5] px-3 py-2 text-sm';
        thresholdInput.dataset.field = 'lowStockThreshold';

        const priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.placeholder = 'Prix du produit par défaut';
        priceInput.value = variant.priceOverride ?? '';
        priceInput.className =
            'w-full rounded-lg border border-[#D6C7B5] px-3 py-2 text-sm';
        priceInput.dataset.field = 'priceOverride';

        const activeWrapper = document.createElement('label');
        activeWrapper.className =
            'flex items-center gap-2 text-sm text-[#44403C]';

        const activeInput = document.createElement('input');
        activeInput.type = 'checkbox';
        activeInput.checked = variant.isActive !== false;
        activeInput.dataset.field = 'isActive';

        activeWrapper.appendChild(activeInput);
        activeWrapper.appendChild(
            document.createTextNode('Variante active')
        );

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className =
            'rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100';
        removeButton.textContent = 'Retirer cette ligne';

        removeButton.addEventListener('click', function () {
            row.remove();
            updateData();
        });

        [
            colorSelect,
            sizeSelect,
            stockInput,
            thresholdInput,
            priceInput,
            activeInput
        ].forEach(function (element) {
            element.addEventListener('change', updateData);
            element.addEventListener('input', updateData);
        });

        row.appendChild(idInput);
        row.appendChild(createField('Couleur', colorSelect));
        row.appendChild(createField('Taille / pointure', sizeSelect));
        row.appendChild(createField('Quantité en stock', stockInput));
        row.appendChild(createField("Seuil d'alerte stock", thresholdInput));
        row.appendChild(createField('Prix spécial (facultatif)', priceInput));
        row.appendChild(activeWrapper);
        row.appendChild(removeButton);

        container.appendChild(row);

        updateSizeFields();
    }

    // Ajouter une nouvelle variante.
    addButton.addEventListener('click', function () {
        createVariantRow();
        updateData();
    });

    // Réagir au changement de catégorie.
    if (categorySelect) {
        categorySelect.addEventListener('change', updateSizeFields);
    }

    // Vérifier les variantes avant l'envoi du formulaire.
    productForm.addEventListener('submit', function (event) {
        const accessory = isAccessoryCategory();

        const rows = Array.from(
            container.querySelectorAll('.variant-row')
        );

        for (const row of rows) {
            const colorSelect = row.querySelector('[data-field="color"]');
            const sizeSelect = row.querySelector('[data-field="size"]');

            const color = colorSelect ? colorSelect.value : '';
            const size = sizeSelect ? sizeSelect.value : '';

            if (!color || (!accessory && !size)) {
                event.preventDefault();

                alert(
                    accessory
                        ? 'Veuillez choisir une couleur pour chaque variante.'
                        : 'Veuillez choisir une couleur et une taille / pointure pour chaque variante.'
                );

                return;
            }
        }

        updateData();
    });

    // Charger les variantes existantes lors de la modification d'un produit.
    try {
        const initialVariants = JSON.parse(dataInput.value || '[]');

        initialVariants.forEach(function (variant) {
            createVariantRow(variant);
        });
    } catch (error) {
        console.error(
            'Impossible de charger les variantes existantes.',
            error
        );
    }

    updateSizeFields();
}

// Initialiser le script lors du chargement normal ou d'une navigation Turbo.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductVariants);
} else {
    initProductVariants();
}

document.addEventListener('turbo:load', initProductVariants);

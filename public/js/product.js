function initializeProductPage() {
    const productDetail = document.getElementById('product-detail');
    const mainImage = document.getElementById('product-main-image');
    const caption = document.getElementById('selected-image-caption');
    const selectedColorName = document.getElementById('selected-color-name');

    const colorButtons = Array.from(document.querySelectorAll('.color-option'));
    const variantButtons = Array.from(document.querySelectorAll('.variant-option'));

    const stockMessage = document.getElementById('stock-message');
    const quantityInput = document.getElementById('quantity');
    const addToCartButton = document.getElementById('add-to-cart-button');

    const addToCartForm = document.getElementById('add-to-cart-form');
    const cartFormQuantity = document.getElementById('cart-form-quantity');

    // Ce script ne concerne que la page détail d'un produit.
    if (!productDetail || !mainImage || productDetail.dataset.initialized === 'true') {
        return;
    }

    productDetail.dataset.initialized = 'true';

    // Récupérer les variantes transmises par le contrôleur Symfony.
    let variants = [];

    try {
        variants = JSON.parse(productDetail.dataset.variants || '[]');
    } catch (error) {
        console.error('Impossible de lire les variantes du produit :', error);
        return;
    }

    // Récupérer le nom du produit depuis le texte alternatif de l'image.
    const initialAlt = mainImage.alt;
    const separatorIndex = initialAlt.lastIndexOf(' - ');

    const productName = separatorIndex >= 0
        ? initialAlt.substring(0, separatorIndex)
        : initialAlt;

    // Récupérer les sélections initiales.
    let selectedColor = colorButtons.find(
        button => button.getAttribute('aria-pressed') === 'true'
    )?.dataset.color || colorButtons[0]?.dataset.color || null;

    let selectedSize = variantButtons.find(
        button => button.getAttribute('aria-pressed') === 'true'
    )?.dataset.size || variantButtons[0]?.dataset.size || null;

    // Mettre à jour l'apparence des boutons de couleur.
    function updateColorButtons() {
        colorButtons.forEach(function (button) {
            const isSelected = button.dataset.color === selectedColor;

            button.setAttribute('aria-pressed', String(isSelected));
            button.classList.toggle('border-slate-700', isSelected);
            button.classList.toggle('bg-[#F5EBDD]', isSelected);
        });
    }

    // Mettre à jour l'apparence des boutons de taille ou de pointure.
    function updateVariantButtons() {
        variantButtons.forEach(function (button) {
            const isSelected = button.dataset.size === selectedSize;

            button.setAttribute('aria-pressed', String(isSelected));
            button.classList.toggle('border-slate-700', isSelected);
            button.classList.toggle('bg-[#F5EBDD]', isSelected);
            button.classList.toggle('ring-1', isSelected);
            button.classList.toggle('ring-slate-700', isSelected);
        });
    }

    // Rechercher la variante correspondant à la couleur et à la taille choisies.
    function findSelectedVariant() {
        return variants.find(function (variant) {
            const colorMatches = selectedColor === null
                || String(variant.color ?? '') === String(selectedColor);

            const sizeMatches = selectedSize === null
                || String(variant.size ?? '') === String(selectedSize);

            return colorMatches && sizeMatches;
        }) || null;
    }

    // Modifier l'apparence du bouton Ajouter au panier.
    function updateCartButtonStyle(disabled) {
        if (!addToCartButton) {
            return;
        }

        addToCartButton.classList.remove(
            'bg-slate-700',
            'hover:bg-slate-800',
            'bg-slate-300',
            'text-white',
            'text-slate-600',
            'cursor-not-allowed'
        );

        if (disabled) {
            addToCartButton.classList.add(
                'bg-slate-300',
                'text-slate-600',
                'cursor-not-allowed'
            );
        } else {
            addToCartButton.classList.add(
                'bg-slate-700',
                'text-white',
                'hover:bg-slate-800'
            );
        }
    }

    // Désactiver les commandes lorsqu'une variante est indisponible.
    function disablePurchase(message) {
        if (stockMessage) {
            stockMessage.textContent = message;
            stockMessage.className = 'mt-2 text-sm font-medium text-red-600';
        }

        if (addToCartButton) {
            addToCartButton.disabled = true;
        }

        if (quantityInput) {
            quantityInput.disabled = true;
            quantityInput.value = 1;
            quantityInput.removeAttribute('max');
        }

        updateCartButtonStyle(true);
    }

    // Mettre à jour le stock et les boutons de commande.
    function updateStock() {
        if (!stockMessage || !addToCartButton || !quantityInput) {
            return;
        }

        const variant = findSelectedVariant();

        // Aucune variante ne correspond à la sélection.
        if (!variant) {
            disablePurchase('Cette combinaison est indisponible.');
            return;
        }

        if (variant.isActive === false) {
            disablePurchase('Cette variante n’est plus disponible.');
            return;
        }

        const stock = Number(variant.stockQuantity);
        const threshold = Number(variant.lowStockThreshold ?? 5);

        if (!Number.isFinite(stock)) {
            disablePurchase('Stock indisponible pour le moment.');
            return;
        }

        // Variante sans stock.
        if (stock <= 0) {
            disablePurchase('Rupture de stock.');
            quantityInput.max = 0;
            return;
        }

        // Afficher la disponibilité selon la quantité restante.
        if (stock <= threshold) {
            stockMessage.textContent = stock === 1
                ? 'Plus qu’un article disponible !'
                : `Plus que ${stock} articles disponibles !`;

            stockMessage.className = 'mt-2 text-sm font-medium text-amber-700';
        } else {
            stockMessage.textContent = 'En stock';
            stockMessage.className = 'mt-2 text-sm font-medium text-green-700';
        }

        // Limiter la quantité au stock disponible.
        quantityInput.disabled = false;
        quantityInput.min = 1;
        quantityInput.max = stock;

        let currentQuantity = Number(quantityInput.value) || 1;

        if (currentQuantity > stock) {
            currentQuantity = stock;
        }

        if (currentQuantity < 1) {
            currentQuantity = 1;
        }

        quantityInput.value = currentQuantity;

        addToCartButton.disabled = false;
        updateCartButtonStyle(false);
    }

    // Sélectionner une couleur et changer l'image principale.
    colorButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedColor = button.dataset.color;

            updateColorButtons();
            updateStock();

            const imageUrl = button.dataset.image;
            const colorName = button.dataset.color;

            if (selectedColorName) {
                selectedColorName.textContent = colorName;
            }

            if (!imageUrl) {
                return;
            }

            // Précharger l'image avant de remplacer celle qui est affichée.
            const newImage = new Image();

            newImage.onload = function () {
                mainImage.src = imageUrl;
                mainImage.alt = productName + ' - ' + colorName;

                if (caption) {
                    caption.textContent = productName + ' — ' + colorName;
                }
            };

            newImage.onerror = function () {
                console.error('Impossible de charger l’image du produit :', imageUrl);
            };

            // Éviter de recharger une image déjà affichée.
            if (mainImage.src === new URL(imageUrl, document.baseURI).href) {
                if (caption) {
                    caption.textContent = productName + ' — ' + colorName;
                }

                return;
            }

            newImage.src = imageUrl;
        });
    });

    // Sélectionner une taille ou une pointure.
    variantButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedSize = button.dataset.size;

            updateVariantButtons();
            updateStock();
        });
    });

    // Vérifier la quantité saisie par le client.
    if (quantityInput) {
        quantityInput.addEventListener('change', function () {
            const variant = findSelectedVariant();

            if (!variant || Number(variant.stockQuantity) <= 0) {
                quantityInput.value = 1;
                return;
            }

            const stock = Number(variant.stockQuantity);
            let quantity = Number(quantityInput.value) || 1;

            quantity = Math.max(1, Math.min(quantity, stock));
            quantityInput.value = quantity;
        });
    }

    // Ajouter la variante sélectionnée au panier.
    if (addToCartButton && addToCartForm && cartFormQuantity) {
        addToCartButton.addEventListener('click', function () {
            const variant = findSelectedVariant();

            if (!variant || !variant.id) {
                disablePurchase('Veuillez sélectionner une variante disponible.');
                return;
            }

            const quantity = Number(quantityInput.value) || 1;
            const stock = Number(variant.stockQuantity);

            if (quantity < 1 || quantity > stock || stock <= 0) {
                disablePurchase('La quantité demandée dépasse le stock disponible.');
                return;
            }

            const urlTemplate = addToCartForm.dataset.urlTemplate;

            if (!urlTemplate) {
                console.error('URL du panier introuvable.');
                return;
            }

            // Construire l'URL avec l'identifiant réel de la variante.
            addToCartForm.action = urlTemplate.replace(
                /\/0$/,
                '/' + encodeURIComponent(variant.id)
            );

            // Transmettre la quantité au contrôleur Symfony.
            cartFormQuantity.value = quantity;

            // Envoyer le formulaire au contrôleur.
            addToCartForm.submit();
        });
    }

    // Initialiser l'affichage au chargement de la page.
    updateColorButtons();
    updateVariantButtons();
    updateStock();
}

// Fonctionner au chargement normal et lors d'une navigation Turbo.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeProductPage, { once: true });
} else {
    initializeProductPage();
}

document.addEventListener('turbo:load', initializeProductPage);

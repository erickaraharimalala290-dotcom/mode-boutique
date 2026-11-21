(function () {
    const initializedGrids = new WeakSet();

    function initializeShopPage() {
        const searchInput = document.getElementById('recherche-produit');
        const sortSelect = document.getElementById('tri-produits');
        const grid = document.getElementById('grille-produits');
        const count = document.getElementById('nombre-produits');
        const emptyMessage = document.getElementById('aucun-produit');
        const resetButton = document.getElementById('reset-filtres');
        const promoFilter = document.getElementById('filtre-promo');
        const pagination = document.getElementById('pagination-produits');

        if (
            !searchInput ||
            !sortSelect ||
            !grid ||
            !count ||
            !emptyMessage ||
            !resetButton ||
            !promoFilter ||
            !pagination
        ) {
            return;
        }

        // Empêcher d'ajouter plusieurs fois les mêmes événements.
        if (initializedGrids.has(grid)) {
            return;
        }

        initializedGrids.add(grid);

        const products = Array.from(
            grid.querySelectorAll('[data-product]')
        );

        const categoryFilters = Array.from(
            document.querySelectorAll('[data-filter-group]')
        );

        const priceFilters = Array.from(
            document.querySelectorAll('input[name="prix"]')
        );

        const pageSize = 6;
        let currentPage = 1;

        // Normaliser les textes : accents, majuscules et espaces.
        function normalize(value) {
            return String(value ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        }

        function getSelectedValues(group) {
            return categoryFilters
                .filter(function (input) {
                    return (
                        input.dataset.filterGroup === group &&
                        input.checked
                    );
                })
                .map(function (input) {
                    return normalize(input.value);
                });
        }

        // Recherche et filtres.
        function getFilteredProducts() {
            const search = normalize(searchInput.value);
            const selectedGenres = getSelectedValues('genre');
            const selectedTypes = getSelectedValues('type');

            const selectedPrice = priceFilters.find(function (input) {
                return input.checked;
            });

            const subcategory = normalize(grid.dataset.subcategory);

            let result = products.filter(function (product) {
                const name = normalize(product.dataset.name);
                const brand = normalize(product.dataset.brand);
                const category = normalize(product.dataset.category);
                const productText = normalize(product.textContent);

                const productSearchText =
                    name + ' ' + brand + ' ' + category + ' ' + productText;

                const price = Number(product.dataset.price || 0);

                const productGenre = normalize(product.dataset.genre);
                const productType = normalize(product.dataset.type);
                const productSubcategory = normalize(
                    product.dataset.subcategory
                );

                const matchesSearch =
                    search === '' || productSearchText.includes(search);

                const matchesGenre =
                    selectedGenres.length === 0 ||
                    selectedGenres.includes(productGenre);

                const matchesType =
                    selectedTypes.length === 0 ||
                    selectedTypes.includes(productType);

                const matchesSubcategory =
                    subcategory === '' ||
                    productSubcategory === subcategory;

                const matchesPromotion =
                    !promoFilter.checked ||
                    product.dataset.promo === 'true';

                let matchesPrice = true;

                if (selectedPrice) {
                    if (selectedPrice.value === 'moins-50000') {
                        matchesPrice = price < 50000;
                    } else if (selectedPrice.value === '50000-100000') {
                        matchesPrice =
                            price >= 50000 && price <= 100000;
                    } else if (selectedPrice.value === 'plus-100000') {
                        matchesPrice = price > 100000;
                    }
                }

                return (
                    matchesSearch &&
                    matchesGenre &&
                    matchesType &&
                    matchesSubcategory &&
                    matchesPromotion &&
                    matchesPrice
                );
            });

            // Tri des résultats.
            const sort = sortSelect.value;

            if (sort === 'prix-croissant') {
                result.sort(function (a, b) {
                    return (
                        Number(a.dataset.price || 0) -
                        Number(b.dataset.price || 0)
                    );
                });
            } else if (sort === 'prix-decroissant') {
                result.sort(function (a, b) {
                    return (
                        Number(b.dataset.price || 0) -
                        Number(a.dataset.price || 0)
                    );
                });
            } else if (sort === 'recent') {
                result.sort(function (a, b) {
                    return (
                        Number(b.dataset.date || 0) -
                        Number(a.dataset.date || 0)
                    );
                });
            } else if (sort === 'popularite') {
                result.sort(function (a, b) {
                    return (
                        Number(b.dataset.popularity || 0) -
                        Number(a.dataset.popularity || 0)
                    );
                });
            }

            return result;
        }

        // Créer les boutons de pagination.
        function renderPagination(totalPages) {
            pagination.innerHTML = '';

            if (totalPages <= 1) {
                pagination.classList.add('hidden');
                return;
            }

            pagination.classList.remove('hidden');

            function createButton(label, disabled, onClick) {
                const button = document.createElement('button');

                button.type = 'button';
                button.textContent = label;
                button.disabled = disabled;
                button.className =
                    'rounded-lg border border-stone-200 px-4 py-2 text-sm text-ink transition hover:border-brand hover:text-brand disabled:cursor-not-allowed disabled:opacity-40';

                button.addEventListener('click', onClick);

                return button;
            }

            pagination.appendChild(
                createButton(
                    'Précédent',
                    currentPage === 1,
                    function () {
                        if (currentPage > 1) {
                            currentPage--;
                            renderProducts();
                        }
                    }
                )
            );

            for (let page = 1; page <= totalPages; page++) {
                const button = document.createElement('button');

                button.type = 'button';
                button.textContent = String(page);
                button.setAttribute('aria-label', 'Page ' + page);

                if (page === currentPage) {
                    button.setAttribute('aria-current', 'page');
                    button.className =
                        'rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white';
                } else {
                    button.className =
                        'rounded-lg border border-stone-200 px-4 py-2 text-sm text-ink transition hover:border-brand hover:text-brand';
                }

                button.addEventListener('click', function () {
                    currentPage = page;
                    renderProducts();
                });

                pagination.appendChild(button);
            }

            pagination.appendChild(
                createButton(
                    'Suivant',
                    currentPage === totalPages,
                    function () {
                        if (currentPage < totalPages) {
                            currentPage++;
                            renderProducts();
                        }
                    }
                )
            );
        }

        // Afficher uniquement les résultats de la page active.
        function renderProducts(resetPage = false) {
            if (resetPage) {
                currentPage = 1;
            }

            const filteredProducts = getFilteredProducts();
            const totalPages = Math.ceil(
                filteredProducts.length / pageSize
            );

            if (totalPages === 0) {
                currentPage = 1;
            } else if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            const start = (currentPage - 1) * pageSize;

            const visibleProducts = filteredProducts.slice(
                start,
                start + pageSize
            );

            products.forEach(function (product) {
                product.classList.add('hidden');
            });

            visibleProducts.forEach(function (product) {
                product.classList.remove('hidden');
                grid.appendChild(product);
            });

            count.textContent =
                filteredProducts.length + ' produit(s) trouvé(s)';

            emptyMessage.classList.toggle(
                'hidden',
                filteredProducts.length !== 0
            );

            renderPagination(totalPages);
        }

        // Recherche en temps réel.
        searchInput.addEventListener('input', function () {
            renderProducts(true);
        });

        // Tri.
        sortSelect.addEventListener('change', function () {
            renderProducts(true);
        });

        // Filtres de collection et de catégorie.
        categoryFilters.forEach(function (input) {
            input.addEventListener('change', function () {
                renderProducts(true);
            });
        });

        // Filtres de budget.
        priceFilters.forEach(function (input) {
            input.addEventListener('change', function () {
                renderProducts(true);
            });
        });

        // Promotions.
        promoFilter.addEventListener('change', function () {
            renderProducts(true);
        });

        // Réinitialisation complète.
        resetButton.addEventListener('click', function () {
            searchInput.value = '';
            sortSelect.value = '';

            categoryFilters.forEach(function (input) {
                input.checked = false;
            });

            priceFilters.forEach(function (input) {
                input.checked = false;
            });

            promoFilter.checked = false;

            // Supprimer aussi les paramètres de filtre de l'URL.
            const resetUrl = resetButton.dataset.resetUrl;

            if (resetUrl) {
                window.location.href = resetUrl;
                return;
            }

            renderProducts(true);
        });

        // Affichage initial.
        renderProducts(true);
    }

    // Fonctionne même si le script s'exécute après le chargement du DOM.
    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeShopPage,
            { once: true }
        );
    } else {
        initializeShopPage();
    }

    // Prise en charge de la navigation Turbo, si elle est utilisée.
    document.addEventListener('turbo:load', initializeShopPage);
})();
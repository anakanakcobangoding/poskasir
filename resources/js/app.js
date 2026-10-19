const formatRupiah = (value) => `Rp ${Math.round(Number(value)).toLocaleString('id-ID')}`;

const escapeHtml = (value) =>
    String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);

function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('app-backdrop');
    const toggle = document.getElementById('app-sidebar-toggle');

    if (!sidebar || !backdrop || !toggle) {
        return;
    }

    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
    };

    toggle.addEventListener('click', () => setOpen(sidebar.classList.contains('-translate-x-full')));
    backdrop.addEventListener('click', () => setOpen(false));
}

function initPos() {
    const cartLines = document.getElementById('pos-cart-lines');

    if (!cartLines) {
        return;
    }

    const toppingsCatalog = new Map(
        (window.posToppings ?? []).map((topping) => [String(topping.id), topping]),
    );

    const cart = new Map();
    let modalState = null;

    const elements = {
        cartLines,
        cartEmpty: document.getElementById('pos-cart-empty'),
        lineCount: document.getElementById('pos-line-count'),
        subtotal: document.getElementById('pos-subtotal'),
        toppingTotal: document.getElementById('pos-topping-total'),
        total: document.getElementById('pos-total'),
        clearCart: document.getElementById('pos-clear-cart'),
        search: document.getElementById('pos-search'),
        noResults: document.getElementById('pos-no-results'),
        checkout: document.getElementById('pos-checkout'),
        checkoutNote: document.getElementById('pos-checkout-note'),
        filterButtons: [...document.querySelectorAll('.js-category-filter')],
        sections: [...document.querySelectorAll('.js-category-section')],
        productButtons: [...document.querySelectorAll('.js-product')],
        modal: {
            root: document.getElementById('pos-topping-modal'),
            title: document.getElementById('pos-modal-title'),
            close: document.getElementById('pos-modal-close'),
            toppings: document.getElementById('pos-modal-toppings'),
            qty: document.getElementById('pos-modal-qty'),
            qtyMinus: document.getElementById('pos-modal-qty-minus'),
            qtyPlus: document.getElementById('pos-modal-qty-plus'),
            total: document.getElementById('pos-modal-total'),
            add: document.getElementById('pos-modal-add'),
        },
    };

    const productFromButton = (button) => ({
        id: button.dataset.id,
        name: button.dataset.name,
        price: Number(button.dataset.price),
    });

    const lineKey = (productId, toppingIds) =>
        `${productId}:${[...toppingIds].sort((a, b) => Number(a) - Number(b)).join(',')}`;

    const addToCart = (product, toppingIds, quantity) => {
        const key = lineKey(product.id, toppingIds);
        const existing = cart.get(key);
        const selected = toppingIds.map((id) => toppingsCatalog.get(String(id))).filter(Boolean);

        if (existing) {
            existing.quantity += quantity;

            return;
        }

        cart.set(key, {
            key,
            name: product.name,
            basePrice: product.price,
            quantity,
            toppingNames: selected.map((topping) => topping.name),
            toppingPrice: selected.reduce((sum, topping) => sum + Number(topping.price), 0),
        });
    };

    const renderCart = () => {
        const lines = [...cart.values()];

        elements.cartLines.innerHTML = lines
            .map((line) => {
                const unitPrice = line.basePrice + line.toppingPrice;
                const toppingLabel = line.toppingNames.length > 0
                    ? `<p class="mt-0.5 text-xs text-slate-500">+ ${escapeHtml(line.toppingNames.join(', '))}</p>`
                    : '';

                return `
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">${escapeHtml(line.name)}</p>
                                ${toppingLabel}
                            </div>
                            <button type="button" data-remove data-line-key="${line.key}" class="shrink-0 text-lg leading-none text-slate-400 transition hover:text-red-600" aria-label="Hapus ${escapeHtml(line.name)}">&times;</button>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <button type="button" data-decrease data-line-key="${line.key}" class="btn-ghost h-8 w-8 px-0!">−</button>
                                <span class="w-8 text-center text-sm font-medium text-slate-700">${line.quantity}</span>
                                <button type="button" data-increase data-line-key="${line.key}" class="btn-ghost h-8 w-8 px-0!">+</button>
                            </div>
                            <span class="text-sm font-semibold text-slate-900">${formatRupiah(unitPrice * line.quantity)}</span>
                        </div>
                    </div>
                `;
            })
            .join('');

        const baseTotal = lines.reduce((sum, line) => sum + line.basePrice * line.quantity, 0);
        const toppingTotal = lines.reduce((sum, line) => sum + line.toppingPrice * line.quantity, 0);

        elements.lineCount.textContent = lines.reduce((sum, line) => sum + line.quantity, 0);
        elements.subtotal.textContent = formatRupiah(baseTotal);
        elements.toppingTotal.textContent = formatRupiah(toppingTotal);
        elements.total.textContent = formatRupiah(baseTotal + toppingTotal);
        elements.cartEmpty.hidden = lines.length > 0;
        elements.checkout.disabled = lines.length === 0;
        elements.checkoutNote.classList.add('hidden');
    };

    const changeQuantity = (key, delta) => {
        const line = cart.get(key);

        if (!line) {
            return;
        }

        line.quantity += delta;

        if (line.quantity < 1) {
            cart.delete(key);
        }

        renderCart();
    };

    const updateModalTotal = () => {
        if (!modalState) {
            return;
        }

        const toppingPrice = [...modalState.selected].reduce((sum, id) => {
            const topping = toppingsCatalog.get(String(id));

            return sum + (topping ? Number(topping.price) : 0);
        }, 0);

        elements.modal.qty.textContent = modalState.quantity;
        elements.modal.total.textContent = formatRupiah((modalState.product.price + toppingPrice) * modalState.quantity);
    };

    const closeModal = () => {
        modalState = null;
        elements.modal.root.close();
    };

    const openModal = (product) => {
        modalState = {
            product,
            quantity: 1,
            selected: new Set(),
        };

        elements.modal.title.textContent = product.name;
        elements.modal.qty.textContent = '1';
        elements.modal.toppings.innerHTML = [...toppingsCatalog.values()]
            .map((topping) => `
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                    <span class="flex items-center gap-3">
                        <input type="checkbox" value="${escapeHtml(topping.id)}" class="js-modal-topping h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-2 focus:ring-emerald-500/40">
                        <span class="text-sm text-slate-700">${escapeHtml(topping.name)}</span>
                    </span>
                    <span class="text-xs font-medium text-slate-500">+ ${formatRupiah(topping.price)}</span>
                </label>
            `)
            .join('');

        elements.modal.toppings.querySelectorAll('.js-modal-topping').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const id = checkbox.value;

                if (checkbox.checked) {
                    modalState.selected.add(id);
                } else {
                    modalState.selected.delete(id);
                }

                updateModalTotal();
            });
        });

        updateModalTotal();
        elements.modal.root.showModal();
    };

    elements.productButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const product = productFromButton(button);

            if (toppingsCatalog.size === 0) {
                addToCart(product, [], 1);
                renderCart();

                return;
            }

            openModal(product);
        });
    });

    elements.modal.qtyPlus.addEventListener('click', () => {
        if (modalState) {
            modalState.quantity += 1;
            updateModalTotal();
        }
    });

    elements.modal.qtyMinus.addEventListener('click', () => {
        if (modalState && modalState.quantity > 1) {
            modalState.quantity -= 1;
            updateModalTotal();
        }
    });

    elements.modal.close.addEventListener('click', closeModal);

    elements.modal.add.addEventListener('click', () => {
        if (!modalState) {
            return;
        }

        addToCart(modalState.product, [...modalState.selected], modalState.quantity);
        closeModal();
        renderCart();
    });

    cartLines.addEventListener('click', (event) => {
        const button = event.target.closest('[data-increase], [data-decrease], [data-remove]');

        if (!button) {
            return;
        }

        if (button.hasAttribute('data-increase')) {
            changeQuantity(button.dataset.lineKey, 1);
        } else if (button.hasAttribute('data-decrease')) {
            changeQuantity(button.dataset.lineKey, -1);
        } else {
            cart.delete(button.dataset.lineKey);
            renderCart();
        }
    });

    elements.clearCart.addEventListener('click', () => {
        cart.clear();
        renderCart();
    });

    elements.checkout.addEventListener('click', () => {
        elements.checkoutNote.classList.remove('hidden');
    });

    const applyFilters = () => {
        const query = elements.search.value.trim().toLowerCase();
        const activeCategory = elements.filterButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.category ?? 'all';
        let visibleProducts = 0;

        elements.productButtons.forEach((button) => {
            const matchesQuery = query === '' || button.dataset.search.includes(query);
            const matchesCategory = activeCategory === 'all' || button.dataset.category === activeCategory;
            const visible = matchesQuery && matchesCategory;

            button.classList.toggle('hidden', !visible);

            if (visible) {
                visibleProducts += 1;
            }
        });

        elements.sections.forEach((section) => {
            const hasVisibleProduct = section.querySelector('.js-product:not(.hidden)') !== null;

            section.classList.toggle('hidden', !hasVisibleProduct);
        });

        elements.noResults.classList.toggle('hidden', visibleProducts > 0 || elements.productButtons.length === 0);
    };

    elements.filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            elements.filterButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            applyFilters();
        });
    });

    elements.search.addEventListener('input', applyFilters);
    renderCart();
}

initSidebar();
initPos();

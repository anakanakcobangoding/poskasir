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
    const shell = document.getElementById('app-shell');
    if (!sidebar || !backdrop || !toggle || !shell) return;
    const desktop = window.matchMedia('(min-width: 1024px)');
    const hover = window.matchMedia('(hover: hover) and (pointer: fine)');
    let pinned = false;
    const setExpanded = (expanded) => {
        shell.classList.toggle('sidebar-expanded', expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
    };
    const setMobile = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    sidebar.querySelectorAll('nav a').forEach((link) => {
        const label = link.textContent.trim();
        link.setAttribute('aria-label', label);
        link.title = label;
    });
    toggle.addEventListener('click', () => {
        if (desktop.matches) { pinned = !pinned; setExpanded(pinned); }
        else setMobile(sidebar.classList.contains('-translate-x-full'));
    });
    sidebar.addEventListener('mouseenter', () => { if (desktop.matches && hover.matches) setExpanded(true); });
    sidebar.addEventListener('mouseleave', () => { if (desktop.matches && !pinned && !sidebar.contains(document.activeElement)) setExpanded(false); });
    sidebar.addEventListener('focusin', () => { if (desktop.matches) setExpanded(true); });
    sidebar.addEventListener('focusout', (event) => { if (desktop.matches && !pinned && !sidebar.contains(event.relatedTarget) && !sidebar.matches(':hover')) setExpanded(false); });
    backdrop.addEventListener('click', () => setMobile(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { pinned = false; setExpanded(false); setMobile(false); }
    });
    desktop.addEventListener('change', () => { pinned = false; setExpanded(false); setMobile(false); });
}

function initPos() {
    const cartLines = document.getElementById('pos-cart-lines');

    if (!cartLines) {
        return;
    }

    const toppingsCatalog = new Map(
        (window.posToppings ?? []).map((topping) => [String(topping.id), topping]),
    );

    const productToppings = new Map(
        Object.entries(window.posProductToppings ?? {}).map(([productId, toppingIds]) => [
            String(productId),
            (toppingIds ?? []).map(String),
        ]),
    );

    const toppingsForProduct = (productId) =>
        (productToppings.get(String(productId)) ?? [])
            .map((toppingId) => toppingsCatalog.get(String(toppingId)))
            .filter(Boolean);

    const cart = new Map();
    let modalState = null;
    let discountType = null;
    let discountValue = 0;
    let feeLabel = '';
    let feeAmount = 0;

    const elements = {
        cartLines,
        cartEmpty: document.getElementById('pos-cart-empty'),
        lineCount: document.getElementById('pos-line-count'),
        subtotal: document.getElementById('pos-subtotal'),
        toppingTotal: document.getElementById('pos-topping-total'),
        total: document.getElementById('pos-total'),
        discountRow: document.getElementById('pos-discount-row'),
        discountLabel: document.getElementById('pos-discount-label'),
        discountAmount: document.getElementById('pos-discount-amount'),
        feeRow: document.getElementById('pos-fee-row'),
        feeLabel: document.getElementById('pos-fee-label'),
        feeAmount: document.getElementById('pos-fee-amount'),
        clearCart: document.getElementById('pos-clear-cart'),
        search: document.getElementById('pos-search'),
        noResults: document.getElementById('pos-no-results'),
        checkout: document.getElementById('pos-checkout'),
        orderForm: document.getElementById('pos-order-form'),
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
        imageUrl: window.posProductImages?.[button.dataset.id] ?? null,
    });

    const lineKey = (productId, toppings) => {
        const toppingPart = [...toppings.entries()]
            .sort((a, b) => Number(a[0]) - Number(b[0]))
            .map(([id, qty]) => `${id}:${qty}`)
            .join(',');
        return `${productId}:${toppingPart}`;
    };

    const addToCart = (product, selectedToppings, quantity) => {
        checkoutToken = null;
        const key = lineKey(product.id, selectedToppings);
        const existing = cart.get(key);

        const selected = [...selectedToppings.entries()].map(([id, qty]) => {
            const topping = toppingsCatalog.get(String(id));
            return topping ? { topping, quantity: qty } : null;
        }).filter(Boolean);

        if (existing) {
            existing.quantity = Math.min(99, existing.quantity + quantity);

            return;
        }

        const toppingPrice = selected.reduce((sum, { topping, quantity }) => sum + Number(topping.price) * quantity, 0);

        cart.set(key, {
            key,
            productId: Number(product.id),
            toppings: selected.map(({ topping, quantity }) => ({
                id: Number(topping.id),
                name: topping.name,
                price: Number(topping.price),
                quantity,
            })),
            name: product.name,
            imageUrl: product.imageUrl,
            basePrice: product.price,
            quantity,
            toppingNames: selected.map(({ topping, quantity }) => `${topping.name} x${quantity}`),
            toppingPrice,
        });
    };

    const cartSubtotal = () => [...cart.values()].reduce((sum, line) => sum + (line.basePrice + line.toppingPrice) * line.quantity, 0);

    const resolvedDiscount = () => Math.min(cartSubtotal(), discountType === 'percentage' ? Math.round(cartSubtotal() * discountValue) / 100 : discountValue);

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
                        <div class="flex items-start gap-3">
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-100">${line.imageUrl ? `<img src="${escapeHtml(line.imageUrl)}" alt="" class="h-full w-full object-cover" data-cart-image onerror="this.hidden=true;this.nextElementSibling.hidden=false">` : ''}<span data-cart-image-placeholder ${line.imageUrl ? 'hidden' : ''} class="grid h-full w-full place-items-center text-[10px] text-slate-400">Foto</span></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm font-medium text-slate-900">${escapeHtml(line.name)}</p>
                                ${toppingLabel}
                                </div>
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
        const subtotal = baseTotal + toppingTotal;
        if (discountType === 'fixed' && discountValue > subtotal) discountValue = subtotal;
        if (subtotal === 0) { discountType = null; discountValue = 0; }
        const discountAmount = discountType === 'percentage' ? Math.round(subtotal * discountValue) / 100 : Math.min(subtotal, discountValue);
        elements.discountRow.hidden = discountAmount <= 0;
        elements.discountLabel.textContent = discountType === 'percentage' ? `Diskon (${discountValue}%)` : 'Diskon';
        elements.discountAmount.textContent = `− ${formatRupiah(discountAmount)}`;
        elements.feeRow.hidden = feeAmount <= 0;
        elements.feeLabel.textContent = feeLabel || 'Biaya tambahan';
        elements.feeAmount.textContent = formatRupiah(feeAmount);
        elements.total.textContent = formatRupiah(subtotal - discountAmount + feeAmount);
        elements.cartEmpty.hidden = lines.length > 0;
        elements.checkout.disabled = lines.length === 0 || window.storeIsOpen === false;
        elements.checkout.dataset.cartEmpty = String(lines.length === 0);
    };

    const changeQuantity = (key, delta) => {
        const line = cart.get(key);

        if (!line) {
            return;
        }

        checkoutToken = null;
        line.quantity = Math.min(99, line.quantity + delta);

        if (line.quantity < 1) {
            cart.delete(key);
        }

        renderCart();
    };

    const updateModalTotal = () => {
        if (!modalState) {
            return;
        }

        const toppingPrice = [...modalState.selected.entries()].reduce((sum, [id, qty]) => {
            const topping = toppingsCatalog.get(String(id));
            return sum + (topping ? Number(topping.price) * qty : 0);
        }, 0);

        elements.modal.qty.textContent = modalState.quantity;
        elements.modal.total.textContent = formatRupiah((modalState.product.price + toppingPrice) * modalState.quantity);
    };

    const syncToppingQuantity = (id, quantity) => {
        if (!modalState) return;
        const boundedQuantity = Math.max(0, Math.min(20, quantity));
        if (boundedQuantity === 0) modalState.selected.delete(id);
        else modalState.selected.set(id, boundedQuantity);
        const qtyDisplay = elements.modal.toppings.querySelector(`.js-modal-topping-qty[data-topping-id="${id}"]`);
        const minusBtn = elements.modal.toppings.querySelector(`.js-modal-topping-qty-minus[data-topping-id="${id}"]`);
        const plusBtn = elements.modal.toppings.querySelector(`.js-modal-topping-qty-plus[data-topping-id="${id}"]`);
        if (qtyDisplay) qtyDisplay.textContent = String(boundedQuantity);
        if (minusBtn) minusBtn.disabled = boundedQuantity === 0;
        if (plusBtn) plusBtn.disabled = boundedQuantity === 20;
        updateModalTotal();
    };

    elements.modal.toppings.addEventListener('click', (event) => {
        const button = event.target.closest('.js-modal-topping-qty-plus, .js-modal-topping-qty-minus');
        if (!button || !modalState) return;
        const id = button.dataset.toppingId;
        const quantity = modalState.selected.get(id) ?? 0;
        syncToppingQuantity(id, quantity + (button.classList.contains('js-modal-topping-qty-plus') ? 1 : -1));
    });

    const closeModal = () => {
        modalState = null;
        elements.modal.root.close();
    };

    const openModal = (product, availableToppings) => {
        modalState = {
            product,
            quantity: 1,
            selected: new Map(),
        };

        elements.modal.title.textContent = product.name;
        elements.modal.qty.textContent = '1';
        elements.modal.toppings.innerHTML = availableToppings
            .map((topping) => `
                <div class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                    <div class="flex min-w-0 items-center gap-3">
                        ${topping.image_url ? `<img src="${escapeHtml(topping.image_url)}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover" data-topping-image onerror="this.hidden=true">` : ''}
                        <div class="min-w-0"><span class="block truncate text-sm text-slate-700">${escapeHtml(topping.name)}</span><span class="text-xs font-medium text-slate-500">+ ${formatRupiah(topping.price)}</span></div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1" data-topping-qty>
                        <button type="button" disabled class="js-modal-topping-qty-minus btn-ghost h-8 w-8 rounded px-0!" data-topping-id="${escapeHtml(topping.id)}" aria-label="Kurangi ${escapeHtml(topping.name)}">−</button>
                        <span data-topping-id="${escapeHtml(topping.id)}" class="js-modal-topping-qty w-8 text-center text-sm font-medium text-slate-900">0</span>
                        <button type="button" class="js-modal-topping-qty-plus btn-ghost h-8 w-8 rounded px-0!" data-topping-id="${escapeHtml(topping.id)}" aria-label="Tambah ${escapeHtml(topping.name)}">+</button>
                    </div>
                </div>
            `)
            .join('');


        updateModalTotal();
        elements.modal.root.showModal();
    };

    elements.productButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const product = productFromButton(button);
            const availableToppings = toppingsForProduct(product.id);

            if (availableToppings.length === 0) {
                addToCart(product, [], 1);
                renderCart();

                return;
            }

            openModal(product, availableToppings);
        });
    });

    elements.modal.qtyPlus.addEventListener('click', () => {
        if (modalState) {
            modalState.quantity = Math.min(99, modalState.quantity + 1);
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

        addToCart(modalState.product, modalState.selected, modalState.quantity);
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
            checkoutToken = null;
            cart.delete(button.dataset.lineKey);
            renderCart();
        }
    });

    elements.clearCart.addEventListener('click', () => {
        checkoutToken = null;
        cart.clear();
        discountType = null; discountValue = 0; feeLabel = ''; feeAmount = 0;
        renderCart();
    });

    const step1 = document.getElementById('pos-checkout-step1');
    const step2 = document.getElementById('pos-checkout-step2');
    const step3 = document.getElementById('pos-checkout-step3');
    const qrisDialog = document.getElementById('pos-checkout-qris');
    const feedback = document.getElementById('pos-order-feedback');
    const step1Error = document.getElementById('pos-step1-error');
    const step3Error = document.getElementById('pos-step3-error');
    const qrisError = document.getElementById('pos-qris-error');
    const step1Table = document.getElementById('pos-step1-table');
    const step1Customer = document.getElementById('pos-step1-customer');
    const step1Notes = document.getElementById('pos-step1-notes');
    const step2Total = document.getElementById('pos-step2-total');
    const step3Cash = document.getElementById('pos-step3-cash');
    const step3Qris = document.getElementById('pos-step3-qris');
    const step3Amount = document.getElementById('pos-step3-amount');
    const step3Change = document.getElementById('pos-step3-change');
    const step3PayCash = document.getElementById('pos-step3-pay-cash');
    const qrisTotal = document.getElementById('pos-qris-final-total');
    const qrisConfirmed = document.getElementById('pos-qris-confirmed');
    const qrisSubmit = document.getElementById('pos-qris-submit');
    const qrisImage = document.getElementById('pos-qris-image');
    const qrisLoading = document.getElementById('pos-qris-loading');
    const qrisMissing = document.getElementById('pos-qris-missing');
    const qrisRetry = document.getElementById('pos-qris-retry');

    const updateQrisImageState = (loading, failed) => {
        if (qrisLoading) qrisLoading.hidden = !loading;
        if (qrisImage) qrisImage.hidden = loading || failed;
        if (qrisRetry) qrisRetry.hidden = !failed;
        qrisMissing?.classList.toggle('hidden', loading || !failed);
        if (qrisSubmit) qrisSubmit.disabled = loading || failed;
        if (qrisConfirmed) qrisConfirmed.disabled = loading || failed;
    };

    if (qrisImage) {
        qrisImage.addEventListener('load', () => updateQrisImageState(false, false));
        qrisImage.addEventListener('error', () => updateQrisImageState(false, true));
        updateQrisImageState(!qrisImage.complete, qrisImage.complete && qrisImage.naturalWidth === 0);
        qrisRetry?.addEventListener('click', () => {
            updateQrisImageState(true, false);
            qrisImage.src = `${qrisImage.src.split('?')[0]}?retry=${Date.now()}`;
        });
    } else {
        updateQrisImageState(false, true);
    }
    const typeButtons = [...document.querySelectorAll('.pos-order-type-btn')];
    const platformButtons = [...document.querySelectorAll('.pos-delivery-platform-btn')];
    const methodButtons = [...document.querySelectorAll('.pos-payment-method-btn')];

    let checkoutState = { type: 'TA', platform: null, customer: '', notes: '', table: '' };
    let paymentMethod = null;
    let submitting = false;
    let checkoutToken = null;

    const newCheckoutToken = () => {
        if (window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }

        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const cartTotal = () => Math.max(0, cartSubtotal() - resolvedDiscount() + feeAmount);

    const toggleActive = (button, active) => {
        button.classList.toggle('border-emerald-600', active);
        button.classList.toggle('bg-emerald-600', active);
        button.classList.toggle('text-white', active);
        button.setAttribute('aria-pressed', String(active));
    };

    const hideError = (element) => element?.classList.add('hidden');

    const showError = (element, message) => {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.remove('hidden');
    };

    const showFeedback = (message) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.remove('hidden', 'bg-red-50', 'text-red-700');
        feedback.classList.add('bg-emerald-50', 'text-emerald-700');
    };

    const hideFeedback = () => feedback?.classList.add('hidden');

    const updateTypeUi = () => {
        typeButtons.forEach((button) => toggleActive(button, button.dataset.type === checkoutState.type));
        document.getElementById('pos-step1-di')?.classList.toggle('hidden', checkoutState.type !== 'DI');
        document.getElementById('pos-step1-dlv')?.classList.toggle('hidden', checkoutState.type !== 'DLV');
        platformButtons.forEach((button) => toggleActive(button, button.dataset.platform === checkoutState.platform));
    };

    const updatePaymentUi = () => {
        methodButtons.forEach((button) => toggleActive(button, button.dataset.method === paymentMethod));
        step3Cash?.classList.toggle('hidden', paymentMethod !== 'cash');
        step3Qris?.classList.toggle('hidden', paymentMethod !== 'qris');
        step3PayCash?.classList.toggle('hidden', paymentMethod !== 'cash');
    };

    const updateChange = () => {
        const amount = Number(step3Amount?.value ?? 0);
        const change = Number.isFinite(amount) ? Math.max(0, amount - cartTotal()) : 0;

        if (step3Change) {
            step3Change.textContent = formatRupiah(change);
        }
    };

    const resetCheckout = () => {
        checkoutState = { type: 'TA', platform: null, customer: '', notes: '', table: '' };
        paymentMethod = null;

        if (step1Table) step1Table.value = '';
        if (step3Amount) step3Amount.value = '';
        if (qrisConfirmed) qrisConfirmed.checked = false;

        [step1Error, step3Error, qrisError].forEach(hideError);
        updateTypeUi();
        updatePaymentUi();
        updateChange();
    };

    const closeCheckoutDialogs = () => {
        [step1, step2, step3, qrisDialog].forEach((dialog) => dialog?.open && dialog.close());
    };

    const buildOrderPayload = () => {
        const lines = [...cart.values()];

        const payload = {
            type: checkoutState.type,
            customer_name: checkoutState.customer,
            table_number: checkoutState.type === 'DI' ? checkoutState.table : '',
            delivery_address: '',
            delivery_platform: checkoutState.type === 'DLV' ? checkoutState.platform : '',
            notes: checkoutState.notes,
            payment_status: 'paid',
            payment_method: paymentMethod,
            payment_confirmed: 1,
            discount_type: discountType,
            discount_value: discountValue,
            additional_fee_label: feeLabel,
            additional_fee_amount: feeAmount,
            items: lines.map((line) => ({
                product_id: line.productId,
                quantity: line.quantity,
                toppings: line.toppings.map((t) => ({
                    id: t.id,
                    quantity: t.quantity,
                })),
            })),
        };

        if (paymentMethod === 'cash') {
            payload.amount_paid = Number(step3Amount?.value ?? 0);
        }

        return payload;
    };

    const firstErrorMessage = (data) => {
        if (data?.errors) {
            const first = Object.values(data.errors)[0];

            if (Array.isArray(first) && first.length > 0) {
                return first[0];
            }
        }

        return data?.message ?? 'Pesanan gagal disimpan. Coba lagi.';
    };

    const submitOrder = async (errorElement, button) => {
        if (window.storeIsOpen === false) {
            showError(errorElement, 'Toko sedang tutup. Transaksi tidak dapat diproses. Silakan hubungi admin untuk membuka toko kembali.');
            return;
        }

        if (submitting) {
            return;
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const originalLabel = button?.textContent;

        submitting = true;
        hideFeedback();
        hideError(errorElement);

        if (button) {
            button.disabled = true;
            button.textContent = 'Menyimpan...';
        }

        try {
            const response = await fetch(elements.orderForm.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Idempotency-Key': checkoutToken,
                },
                body: JSON.stringify(buildOrderPayload()),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showError(errorElement, firstErrorMessage(data));

                return;
            }

            cart.clear();
            discountType = null; discountValue = 0; feeLabel = ''; feeAmount = 0;
            renderCart();
            resetCheckout();
            closeCheckoutDialogs();
            window.location.href = data.receipt_url;
        } catch (error) {
            showError(errorElement, 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.');
        } finally {
            submitting = false;

            if (button) {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        }
    };

    const openStep2 = () => {
        if (step2Total) {
            step2Total.textContent = formatRupiah(cartTotal());
        }

        hideError(document.getElementById('pos-step2-error'));
        step2?.showModal();
    };

    const openStep3 = () => {
        hideError(step3Error);
        updatePaymentUi();
        updateChange();
        step3?.showModal();
    };

    const openQris = () => {
        if (qrisTotal) {
            qrisTotal.textContent = formatRupiah(cartTotal());
        }

        if (qrisConfirmed) {
            qrisConfirmed.checked = false;
        }

        hideError(qrisError);
        step3?.close();
        qrisDialog?.showModal();
    };

    const discountDialog = document.getElementById('pos-discount-dialog');
    const discountTypeInput = document.getElementById('pos-discount-type');
    const discountValueInput = document.getElementById('pos-discount-value');
    const discountError = document.getElementById('pos-discount-error');
    document.getElementById('pos-discount-open')?.addEventListener('click', () => {
        discountTypeInput.value = discountType ?? 'fixed'; discountValueInput.value = discountValue || ''; discountError.hidden = true; discountDialog.showModal();
    });
    document.querySelector('[data-discount-cancel]')?.addEventListener('click', () => discountDialog.close());
    document.querySelector('[data-discount-save]')?.addEventListener('click', () => {
        const value = Number(discountValueInput.value); const type = discountTypeInput.value; const base = cartSubtotal();
        if (!Number.isFinite(value) || value < 0 || (type === 'percentage' && value > 100) || (type === 'fixed' && value > base)) { discountError.textContent = 'Nilai diskon harus valid dan tidak boleh melebihi subtotal.'; discountError.hidden = false; return; }
        discountType = value === 0 ? null : type; discountValue = value; checkoutToken = null; renderCart(); updateChange(); discountDialog.close(); showFeedback('Diskon diperbarui.');
    });
    document.querySelector('[data-discount-remove]')?.addEventListener('click', () => { discountType = null; discountValue = 0; checkoutToken = null; renderCart(); updateChange(); discountDialog.close(); showFeedback('Diskon dihapus.'); });
    const feeDialog = document.getElementById('pos-fee-dialog');
    const feeNameInput = document.getElementById('pos-fee-name'); const feeValueInput = document.getElementById('pos-fee-value-input'); const feeError = document.getElementById('pos-fee-error');
    document.getElementById('pos-fee-open')?.addEventListener('click', () => { feeNameInput.value = feeLabel; feeValueInput.value = feeAmount || ''; feeError.hidden = true; feeDialog.showModal(); });
    document.querySelector('[data-fee-cancel]')?.addEventListener('click', () => feeDialog.close());
    document.querySelector('[data-fee-save]')?.addEventListener('click', () => {
        const amount = Number(feeValueInput.value); const label = feeNameInput.value.trim();
        if (!Number.isFinite(amount) || amount < 0 || (amount > 0 && !label)) { feeError.textContent = 'Masukkan label dan nominal biaya yang valid.'; feeError.hidden = false; return; }
        feeLabel = amount > 0 ? label : ''; feeAmount = amount; checkoutToken = null; renderCart(); updateChange(); feeDialog.close(); showFeedback('Biaya tambahan diperbarui.');
    });
    document.querySelector('[data-fee-remove]')?.addEventListener('click', () => { feeLabel = ''; feeAmount = 0; checkoutToken = null; renderCart(); updateChange(); feeDialog.close(); showFeedback('Biaya tambahan dihapus.'); });

    elements.checkout?.addEventListener('click', () => {
        if (cart.size === 0 || window.storeIsOpen === false) {
            return;
        }

        resetCheckout();
        checkoutToken ??= newCheckoutToken();
        hideFeedback();
        step1?.showModal();
    });

    typeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            checkoutState.type = button.dataset.type;

            if (checkoutState.type !== 'DLV') {
                checkoutState.platform = null;
            }

            updateTypeUi();
        });
    });

    platformButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (checkoutState.type !== 'DLV') {
                return;
            }

            checkoutState.platform = button.dataset.platform;
            updateTypeUi();
        });
    });

    document.getElementById('pos-step1-close')?.addEventListener('click', () => step1?.close());
    document.getElementById('pos-step1-cancel')?.addEventListener('click', () => step1?.close());

    document.getElementById('pos-step1-next')?.addEventListener('click', () => {
        hideError(step1Error);

        if (checkoutState.type === 'DI' && (step1Table?.value ?? '').trim() === '') {
            showError(step1Error, 'Nomor meja wajib diisi untuk pesanan dine-in.');

            return;
        }

        if (checkoutState.type === 'DLV' && !checkoutState.platform) {
            showError(step1Error, 'Pilih platform delivery (ShopeeFood, GoFood, atau GrabFood).');

            return;
        }

        checkoutState.customer = (step1Customer?.value ?? '').trim();
        checkoutState.notes = (step1Notes?.value ?? '').trim();
        checkoutState.table = (step1Table?.value ?? '').trim();

        step1?.close();
        openStep2();
    });

    document.getElementById('pos-step2-close')?.addEventListener('click', () => step2?.close());
    document.getElementById('pos-step2-back')?.addEventListener('click', () => {
        step2?.close();
        step1?.showModal();
    });
    document.getElementById('pos-step2-next')?.addEventListener('click', () => {
        step2?.close();
        openStep3();
    });

    document.getElementById('pos-step3-close')?.addEventListener('click', () => step3?.close());
    document.getElementById('pos-step3-back')?.addEventListener('click', () => {
        step3?.close();
        step2?.showModal();
    });

    methodButtons.forEach((button) => {
        button.addEventListener('click', () => {
            paymentMethod = button.dataset.method;
            hideError(step3Error);
            updatePaymentUi();
            updateChange();
        });
    });

    step3Amount?.addEventListener('input', updateChange);

    step3PayCash?.addEventListener('click', () => {
        const amount = Number(step3Amount?.value ?? '');

        if (!Number.isFinite(amount) || amount <= 0) {
            showError(step3Error, 'Uang tunai yang diterima wajib diisi.');

            return;
        }

        if (amount < cartTotal()) {
            showError(step3Error, 'Uang tunai yang diterima kurang dari total pesanan.');

            return;
        }

        submitOrder(step3Error, step3PayCash);
    });

    document.getElementById('pos-step3-qris-next')?.addEventListener('click', openQris);

    document.getElementById('pos-qris-close')?.addEventListener('click', () => {
        qrisDialog?.close();
        step3?.showModal();
    });

    document.getElementById('pos-qris-back')?.addEventListener('click', () => {
        qrisDialog?.close();
        step3?.showModal();
    });

    qrisSubmit?.addEventListener('click', () => {
        if (!qrisConfirmed?.checked) {
            showError(qrisError, 'Centang konfirmasi setelah pembayaran QRIS diverifikasi.');

            return;
        }

        submitOrder(qrisError, qrisSubmit);
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
    resetCheckout();
    renderCart();
}

initStoreOperation();
initSidebar();
initImagePreviews();
initPos();

function initStoreOperation() {
    const root = document.getElementById('store-operation');
    if (!root) return;
    const dialog = document.getElementById('store-status-dialog');
    const confirm = dialog?.querySelector('[data-store-confirm]');
    const cancel = dialog?.querySelector('[data-store-cancel]');
    const error = dialog?.querySelector('[data-store-dialog-error]');
    let pending = false;
    let target = null;
    let revision = 0;
    const apply = (status) => {
        if (!['open', 'closed'].includes(status)) return;
        root.dataset.status = status;
        document.querySelectorAll('.store-status-badge').forEach((badge) => { badge.dataset.open = String(status === 'open'); });
        window.storeIsOpen = status === 'open';
        document.querySelectorAll('[data-store-status-label]').forEach((label) => { label.textContent = window.storeIsOpen ? 'TOKO BUKA' : 'TOKO TUTUP'; });
        document.querySelectorAll('[data-store-action]').forEach((button) => { button.textContent = window.storeIsOpen ? 'Tutup Toko' : 'Buka Toko'; });
        document.querySelectorAll('[data-store-closed-banner]').forEach((banner) => { banner.hidden = window.storeIsOpen; });
        document.querySelectorAll('[data-store-start]').forEach((link) => {
            link.textContent = window.storeIsOpen ? 'Mulai Kasir' : 'Kasir Sedang Tutup';
            link.setAttribute('aria-disabled', String(!window.storeIsOpen));
            link.tabIndex = window.storeIsOpen ? 0 : -1;
        });
        const checkout = document.getElementById('pos-checkout');
        if (checkout) checkout.disabled = !window.storeIsOpen || checkout.dataset.cartEmpty !== 'false';
        if (!window.storeIsOpen) {
            document.querySelectorAll('dialog[id^="pos-checkout"]').forEach((modal) => modal.close());
        }
    };
    apply(root.dataset.status);
    document.querySelectorAll('[data-store-start]').forEach((link) => link.addEventListener('click', (event) => { if (!window.storeIsOpen) event.preventDefault(); }));
    document.querySelectorAll('[data-store-reveal]').forEach((button) => button.addEventListener('click', () => {
        const control = button.closest('[data-store-control]');
        control.classList.toggle('is-revealed');
        button.setAttribute('aria-expanded', String(control.classList.contains('is-revealed')));
    }));
    document.querySelectorAll('[data-store-action]').forEach((button) => button.addEventListener('click', () => {
        if (!dialog || pending) return;
        target = root.dataset.status === 'open' ? 'closed' : 'open';
        dialog.querySelector('#store-status-title').textContent = target === 'closed' ? 'Tutup toko sekarang?' : 'Buka toko kembali?';
        dialog.querySelector('[data-store-dialog-description]').textContent = target === 'closed'
            ? 'Toko akan beralih ke status tutup. Transaksi baru dan pembayaran yang belum selesai tidak dapat diproses selama toko tutup.'
            : 'Toko akan kembali menerima transaksi sesuai hak akses pengguna.';
        confirm.textContent = target === 'closed' ? 'Ya, Tutup Toko' : 'Ya, Buka Toko';
        error.hidden = true;
        dialog.showModal();
    }));
    cancel?.addEventListener('click', () => { if (!pending) dialog.close(); });
    dialog?.addEventListener('cancel', (event) => { if (pending) event.preventDefault(); });
    confirm?.addEventListener('click', async () => {
        if (pending) return;
        pending = true;
        revision++;
        confirm.disabled = true;
        cancel.disabled = true;
        const label = confirm.textContent;
        confirm.textContent = 'Memproses...';
        error.hidden = true;
        try {
            const response = await fetch(target === 'open' ? root.dataset.openUrl : root.dataset.closeUrl, {
                method: 'PUT', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            });
            const data = await response.json();
            if (!response.ok || !['open', 'closed'].includes(data.status)) throw new Error(data.message || 'Status gagal disimpan. Coba lagi.');
            apply(data.status);
            dialog.close();
        } catch (failure) {
            error.textContent = failure.message || 'Tidak dapat menghubungi server. Coba lagi.';
            error.hidden = false;
        } finally {
            pending = false;
            revision++;
            confirm.disabled = false;
            cancel.disabled = false;
            confirm.textContent = label;
        }
    });
    let refreshing = false;
    const refresh = async () => {
        if (document.hidden || pending || refreshing) return;
        refreshing = true;
        const currentRevision = revision;
        try {
            const response = await fetch(root.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) {
                const data = await response.json();
                if (!pending && currentRevision === revision) apply(data.status);
            }
        } catch { /* Backend remains authoritative when a status refresh is unavailable. */ }
        finally { refreshing = false; }
    };
    setInterval(refresh, 5000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('pageshow', refresh);
}

function initImagePreviews() {
    document.querySelectorAll('[data-image-preview-input]').forEach((input) => {
        const preview = document.getElementById(input.dataset.imagePreviewInput);
        if (!preview) return;
        const initialSource = preview.getAttribute('src');
        const syncPreview = () => {
            const file = input.files?.[0];
            if (file) {
                if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
                preview.dataset.objectUrl = URL.createObjectURL(file);
                preview.src = preview.dataset.objectUrl;
                preview.hidden = false;
                return;
            }
            if (initialSource) {
                preview.src = initialSource;
                preview.hidden = Boolean(document.querySelector('[name="remove_image"]:checked'));
            } else {
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        };
        input.addEventListener('change', syncPreview);
        document.querySelector('[name="remove_image"]')?.addEventListener('change', syncPreview);
    });
}

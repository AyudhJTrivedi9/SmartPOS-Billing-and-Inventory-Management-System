/* SmartPOS billing screen: search, cart, discount and live totals.
 * These numbers are for display only. The server recalculates everything
 * from database prices when the invoice is saved (pos/save.php). */
(function () {
    'use strict';

    var app = document.getElementById('posApp');
    if (!app) { return; }

    var $ = function (id) { return document.getElementById(id); };

    var form         = $('posForm');
    var searchWrap   = $('searchWrap');
    var searchInput  = $('productSearch');
    var resultsBox   = $('searchResults');
    var searchStatus = $('searchStatus');
    var alertBox     = $('posAlert');
    var cartBody     = $('cartBody');
    var cartEmpty    = $('cartEmpty');
    var cartWrap     = $('cartTableWrap');
    var cartCount    = $('cartCount');
    var clearBtn     = $('clearCart');
    var customerSel  = $('customerSelect');
    var discType     = $('discountType');
    var discValue    = $('discountValue');
    var discError    = $('discountError');
    var sumItems     = $('sumItems');
    var sumSubtotal  = $('sumSubtotal');
    var sumDiscount  = $('sumDiscount');
    var sumGrand     = $('sumGrand');
    var saveBtn      = $('saveBtn');
    var saveHint     = $('saveHint');
    var cashInput    = $('cashReceived');
    var changeBox    = $('changeBox');
    var lastGrand    = 0;   // grand total in paise, used by the change calculator

    var DEFAULT_STATUS = searchStatus.textContent;
    var searchUrl = app.dataset.searchUrl;

    var cart = new Map();          // product id (string) -> item
    var debounceTimer = null;
    var controller = null;
    var notifyTimer = null;
    var submitting = false;

    /* ---------- helpers ---------- */
    function toPaise(price) { return Math.round(Number(price) * 100); }

    // en-US grouping so it matches the PHP money() helper (e.g. 12,499.00)
    function fmt(paise) {
        return '\u20B9 ' + (paise / 100).toLocaleString('en-US', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function notify(message, type) {
        alertBox.className = 'alert alert-' + (type || 'warning') + ' mt-2 mb-0 py-2 small';
        alertBox.textContent = message;
        clearTimeout(notifyTimer);
        notifyTimer = setTimeout(function () { alertBox.classList.add('d-none'); }, 4500);
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    /* ---------- product search (AJAX) ---------- */
    function hideResults() {
        resultsBox.classList.add('d-none');
        resultsBox.innerHTML = '';
    }

    function runSearch(query) {
        if (controller) { controller.abort(); }
        controller = new AbortController();
        searchStatus.textContent = 'Searching...';

        fetch(searchUrl + '?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('server returned HTTP ' + res.status); }
                return res.json();
            })
            .then(function (data) {
                if (!data.success) { throw new Error(data.message || 'unexpected response'); }
                searchStatus.textContent = DEFAULT_STATUS;
                renderResults(data.products);
            })
            .catch(function (err) {
                if (err.name === 'AbortError') { return; }
                searchStatus.textContent = 'Search failed: ' + err.message + ' (open the browser console, F12, for details)';
                console.error('POS search error:', err);
                hideResults();
            });
    }

    function renderResults(products) {
        resultsBox.innerHTML = '';

        if (!products.length) {
            resultsBox.appendChild(el('div', 'p-3 text-muted small', 'No products found.'));
            resultsBox.classList.remove('d-none');
            return;
        }

        products.forEach(function (p) {
            var btn = el('button', 'pos-result-item');
            btn.type = 'button';
            btn.setAttribute('role', 'option');
            btn.disabled = p.stock < 1;

            var left = el('div');
            left.appendChild(el('div', 'fw-semibold', p.name));
            left.appendChild(el('div', 'small text-muted', p.brand));

            var right = el('div', 'text-end text-nowrap');
            right.appendChild(el('div', 'fw-semibold', fmt(toPaise(p.price))));
            var badge = el('span', 'badge ' + (p.stock < 1 ? 'text-bg-danger' : (p.stock <= 10 ? 'text-bg-warning' : 'text-bg-success')),
                p.stock < 1 ? 'Out of stock' : p.stock + ' in stock');
            right.appendChild(badge);

            btn.appendChild(left);
            btn.appendChild(right);
            btn.addEventListener('click', function () { addToCart(p); });
            resultsBox.appendChild(btn);
        });

        resultsBox.classList.remove('d-none');
    }

    function enabledResults() {
        return Array.prototype.slice.call(resultsBox.querySelectorAll('.pos-result-item:not(:disabled)'));
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { runSearch(searchInput.value.trim()); }, 250);
    });

    // Open the list when the box is clicked (click, not focus, so adding a
    // product and re-focusing the box does not pop the list open again).
    searchInput.addEventListener('click', function () {
        if (resultsBox.classList.contains('d-none')) { runSearch(searchInput.value.trim()); }
    });

    searchInput.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();               // never submit the sale from the search box
            var first = enabledResults()[0];
            if (first) { first.click(); }
        } else if (ev.key === 'ArrowDown') {
            var items = enabledResults();
            if (items.length) { ev.preventDefault(); items[0].focus(); }
        } else if (ev.key === 'Escape') {
            hideResults();
        }
    });

    resultsBox.addEventListener('keydown', function (ev) {
        var items = enabledResults();
        var idx = items.indexOf(document.activeElement);
        if (ev.key === 'ArrowDown' && idx < items.length - 1) {
            ev.preventDefault(); items[idx + 1].focus();
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            if (idx > 0) { items[idx - 1].focus(); } else { searchInput.focus(); }
        } else if (ev.key === 'Escape') {
            hideResults(); searchInput.focus();
        }
    });

    document.addEventListener('click', function (ev) {
        if (!searchWrap.contains(ev.target)) { hideResults(); }
    });

    /* ---------- cart ---------- */
    function addToCart(p) {
        var key = String(p.id);
        var existing = cart.get(key);

        if (p.stock < 1) {
            notify('"' + p.name + '" is out of stock.', 'danger');
            return;
        }

        if (existing) {
            existing.stock = p.stock;                   // refresh with latest stock
            existing.price = p.price;
            if (existing.qty + 1 > existing.stock) {
                notify('Only ' + existing.stock + ' unit(s) of "' + p.name + '" available. Quantity not increased.', 'warning');
            } else {
                existing.qty += 1;
                existing.invalid = false;
                renderCart();
                notify('Increased quantity of "' + p.name + '" to ' + existing.qty + '.', 'success');
            }
        } else {
            cart.set(key, {
                id: p.id, name: p.name, brand: p.brand, price: p.price,
                stock: p.stock, qty: 1, invalid: false, msg: ''
            });
            renderCart();
        }

        searchInput.value = '';
        hideResults();
        searchInput.focus();
    }

    function buildRow(item) {
        var tr = document.createElement('tr');
        tr.dataset.id = String(item.id);

        var tdName = el('td');
        tdName.appendChild(el('div', 'fw-semibold', item.name));
        tdName.appendChild(el('div', 'small text-muted', item.brand));

        var tdPrice = el('td', 'text-end text-nowrap', fmt(toPaise(item.price)));

        var tdQty = el('td');
        var input = el('input', 'form-control form-control-sm cart-qty');
        input.type = 'number';
        input.name = 'items[' + item.id + ']';
        input.id = 'qty-' + item.id;
        input.min = '1';
        input.max = String(item.stock);
        input.step = '1';
        input.value = String(item.qty);
        input.dataset.id = String(item.id);
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('aria-label', 'Quantity for ' + item.name);
        tdQty.appendChild(input);
        tdQty.appendChild(el('div', 'invalid-feedback', ''));
        tdQty.appendChild(el('div', 'form-text mt-0', 'Max ' + item.stock));

        var tdLine = el('td', 'text-end fw-semibold text-nowrap');
        tdLine.appendChild(el('span', 'line-total', ''));

        var tdRemove = el('td', 'text-end');
        var rm = el('button', 'btn btn-sm btn-outline-danger remove-btn');
        rm.type = 'button';
        rm.dataset.id = String(item.id);
        rm.setAttribute('aria-label', 'Remove ' + item.name + ' from cart');
        rm.innerHTML = '<i class="bi bi-x-lg"></i>';
        tdRemove.appendChild(rm);

        tr.appendChild(tdName);
        tr.appendChild(tdPrice);
        tr.appendChild(tdQty);
        tr.appendChild(tdLine);
        tr.appendChild(tdRemove);
        return tr;
    }

    function renderCart() {
        cartBody.innerHTML = '';
        cart.forEach(function (item) { cartBody.appendChild(buildRow(item)); });
        cart.forEach(function (item) { updateRow(item); });

        var has = cart.size > 0;
        cartEmpty.classList.toggle('d-none', has);
        cartWrap.classList.toggle('d-none', !has);
        clearBtn.disabled = !has;
        updateTotals();
    }

    function updateRow(item) {
        var tr = cartBody.querySelector('tr[data-id="' + item.id + '"]');
        if (!tr) { return; }
        var input = tr.querySelector('.cart-qty');
        var effQty = item.invalid ? 0 : item.qty;

        input.classList.toggle('is-invalid', item.invalid);
        tr.querySelector('.invalid-feedback').textContent = item.msg || '';
        tr.querySelector('.line-total').textContent = fmt(toPaise(item.price) * effQty);
    }

    cartBody.addEventListener('input', function (ev) {
        var input = ev.target.closest('.cart-qty');
        if (!input) { return; }
        var item = cart.get(input.dataset.id);
        if (!item) { return; }

        var raw = input.value.trim();
        item.invalid = false;
        item.msg = '';

        if (!/^\d+$/.test(raw) || parseInt(raw, 10) < 1) {
            item.invalid = true;
            item.msg = 'Enter a whole number of 1 or more.';
        } else {
            var q = parseInt(raw, 10);
            item.qty = q;
            if (q > item.stock) {
                item.invalid = true;
                item.msg = 'Only ' + item.stock + ' in stock.';
            }
        }
        updateRow(item);
        updateTotals();
    });

    // When the user leaves the field, snap the value back into the allowed range.
    cartBody.addEventListener('change', function (ev) {
        var input = ev.target.closest('.cart-qty');
        if (!input) { return; }
        var item = cart.get(input.dataset.id);
        if (!item) { return; }

        var raw = input.value.trim();
        var q = /^\d+$/.test(raw) ? parseInt(raw, 10) : 1;
        if (q < 1) { q = 1; }
        if (q > item.stock) {
            q = item.stock;
            notify('Quantity reduced to the available stock (' + item.stock + ') for "' + item.name + '".', 'warning');
        }
        item.qty = q;
        item.invalid = false;
        item.msg = '';
        input.value = String(q);
        updateRow(item);
        updateTotals();
    });

    cartBody.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.remove-btn');
        if (!btn) { return; }
        cart.delete(btn.dataset.id);
        renderCart();
    });

    clearBtn.addEventListener('click', function () {
        if (cart.size && window.confirm('Remove all products from the cart?')) {
            cart.clear();
            renderCart();
        }
    });

    /* ---------- discount + totals ---------- */
    function updateDiscountInput() {
        var none = discType.value === 'none';
        discValue.disabled = none;
        if (none) { discValue.value = ''; }
        discValue.max = discType.value === 'percentage' ? '100' : '';
        discValue.placeholder = discType.value === 'percentage' ? '% e.g. 10' : (none ? '0' : 'Amount');
    }

    function updateTotals() {
        var subtotal = 0, units = 0, anyInvalid = false;

        cart.forEach(function (item) {
            var q = item.invalid ? 0 : item.qty;
            subtotal += toPaise(item.price) * q;
            units += q;
            if (item.invalid) { anyInvalid = true; }
        });

        // Discount
        var type = discType.value;
        var raw = discValue.value.trim();
        var discount = 0;
        var errMsg = '';
        var needsValue = false;

        if (type !== 'none') {
            if (raw === '') {
                needsValue = true;
            } else if (!/^\d{1,8}(\.\d{1,2})?$/.test(raw)) {
                errMsg = 'Enter a positive number with up to 2 decimals.';
            } else {
                var v = Math.round(parseFloat(raw) * 100);   // percent*100 or paise
                if (v <= 0) {
                    errMsg = 'Discount must be greater than 0.';
                } else if (type === 'percentage') {
                    if (v > 10000) { errMsg = 'Percentage cannot exceed 100%.'; }
                    else { discount = Math.round(subtotal * v / 10000); }
                } else {
                    if (v > subtotal) { errMsg = 'Fixed discount cannot exceed the subtotal (' + fmt(subtotal) + ').'; }
                    else { discount = v; }
                }
            }
        }

        discount = Math.min(discount, subtotal);
        var grand = Math.max(0, subtotal - discount);
        lastGrand = grand;

        discValue.classList.toggle('is-invalid', errMsg !== '');
        discError.textContent = errMsg;

        sumItems.textContent = String(units);
        sumSubtotal.textContent = fmt(subtotal);
        sumDiscount.textContent = '- ' + fmt(discount);
        sumGrand.textContent = fmt(grand);
        cartCount.textContent = String(cart.size);

        // Can the invoice be saved?
        var reason = '';
        if (cart.size === 0) { reason = 'Add at least one product to the cart.'; }
        else if (anyInvalid) { reason = 'Fix the highlighted quantities first.'; }
        else if (errMsg) { reason = 'Fix the discount value first.'; }
        else if (needsValue) { reason = 'Enter a discount value or choose "No discount".'; }
        else if (!customerSel.value) { reason = 'Select a customer.'; }

        saveBtn.disabled = submitting || reason !== '';
        saveHint.textContent = reason;
        updateChange();
    }

    /* ---------- cash received / change due (display only) ---------- */
    function updateChange() {
        var raw = cashInput.value.trim();
        if (raw === '' || lastGrand <= 0) { changeBox.classList.add('d-none'); return; }
        if (!/^\d{1,8}(\.\d{1,2})?$/.test(raw)) {
            changeBox.textContent = 'Enter a valid amount.';
            changeBox.classList.remove('d-none');
            return;
        }
        var paid = Math.round(parseFloat(raw) * 100);
        changeBox.classList.remove('d-none');
        if (paid >= lastGrand) {
            changeBox.innerHTML = '<span class="text-muted">Change to return:</span> <strong>' + fmt(paid - lastGrand) + '</strong>';
        } else {
            changeBox.innerHTML = '<span class="text-danger">Short by <strong>' + fmt(lastGrand - paid) + '</strong></span>';
        }
    }
    cashInput.addEventListener('input', updateChange);

    /* ---------- quick-add buttons and keyboard shortcuts ---------- */
    var quick = $('quickPicks');
    if (quick) {
        quick.addEventListener('click', function (ev) {
            var b = ev.target.closest('.quick-chip');
            if (!b) { return; }
            addToCart({
                id: parseInt(b.dataset.id, 10), name: b.dataset.name, brand: b.dataset.brand,
                price: parseFloat(b.dataset.price), stock: parseInt(b.dataset.stock, 10)
            });
        });
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'F2') {                       // jump to product search
            ev.preventDefault();
            searchInput.focus();
            searchInput.select();
            runSearch(searchInput.value.trim());
        } else if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {   // save the sale
            ev.preventDefault();
            if (!saveBtn.disabled) { form.requestSubmit(); }
        }
    });

    discType.addEventListener('change', function () { updateDiscountInput(); updateTotals(); });
    discValue.addEventListener('input', updateTotals);
    customerSel.addEventListener('change', updateTotals);

    /* ---------- submit ---------- */
    form.addEventListener('submit', function (ev) {
        if (saveBtn.disabled || submitting) {
            ev.preventDefault();
            return;
        }
        submitting = true;
        // Disable after the browser has collected the form data.
        setTimeout(function () {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving invoice...';
        }, 0);
    });

    // After "Back" from the invoice page, reload so the form and one-time token are fresh.
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) { window.location.reload(); }
    });

    /* ---------- init (restore cart after a failed save) ---------- */
    try {
        var initial = JSON.parse($('posInitial').textContent || '[]');
        initial.forEach(function (p) {
            cart.set(String(p.id), {
                id: p.id, name: p.name, brand: p.brand, price: p.price,
                stock: p.stock, qty: p.qty, invalid: false, msg: ''
            });
        });
    } catch (e) { /* ignore malformed data */ }

    updateDiscountInput();
    renderCart();
})();
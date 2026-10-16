<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/invoice_functions.php';
require_once __DIR__ . '/../includes/report_functions.php';

$customers = get_customers();

// Restore the previous attempt if the last save failed validation (see pos/save.php).
$old = $_SESSION['pos_old'] ?? null;
unset($_SESSION['pos_old']);

$selectedCustomer = 1;
$discountType     = 'none';
$discountValue    = '';
$paymentMethod    = 'cash';
$initialCart      = [];
$quickPicks       = get_quick_pick_products(6);

if (is_array($old)) {
    $cid = (int) ($old['customer_id'] ?? 0);
    if (isset($customers[$cid])) {
        $selectedCustomer = $cid;
    }
    if (in_array($old['discount_type'] ?? '', ['none', 'percentage', 'fixed'], true)) {
        $discountType = $old['discount_type'];
    }
    if (isset(payment_methods()[$old['payment_method'] ?? ''])) {
        $paymentMethod = $old['payment_method'];
    }
    $discountValue = is_string($old['discount_value'] ?? null) ? $old['discount_value'] : '';

    $oldItems = is_array($old['items'] ?? null) ? $old['items'] : [];
    $found    = get_products_by_ids(array_keys($oldItems));
    foreach ($oldItems as $pid => $qty) {
        $pid = (int) $pid;
        if (!isset($found[$pid])) {
            continue;
        }
        $stock = (int) $found[$pid]['stock_quantity'];
        if ($stock < 1) {
            continue;
        }
        $initialCart[] = [
            'id'    => $pid,
            'name'  => (string) $found[$pid]['name'],
            'brand' => (string) $found[$pid]['brand_name'],
            'price' => (float) $found[$pid]['sell_price'],
            'stock' => $stock,
            'qty'   => max(1, min((int) $qty, $stock)),
        ];
    }
}

$formToken = issue_form_token('invoice');

$pageTitle    = 'POS Billing';
$activePage   = 'pos';
$breadcrumbs  = ['Dashboard' => 'index.php', 'POS Billing' => null];
$extraScripts = ['assets/js/pos.js'];

require __DIR__ . '/../includes/header.php';
?>

<form method="post" action="<?= e(url('pos/save.php')) ?>" id="posForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="form_token" value="<?= e($formToken) ?>">

    <div id="posApp" data-search-url="<?= e(url('pos/search.php')) ?>">
        <div class="row g-3">

            <!-- LEFT: search + cart -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-search me-2"></i>Find products</div>
                    <div class="card-body">
                        <div class="position-relative" id="searchWrap">
                            <label for="productSearch" class="form-label">Search by product name or brand</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="search" class="form-control" id="productSearch" maxlength="100"
                                       autocomplete="off" placeholder="Type e.g. &quot;logitech&quot; or &quot;router&quot;"
                                       aria-controls="searchResults">
                            </div>
                            <div class="pos-results d-none" id="searchResults" role="listbox" aria-label="Product search results"></div>
                        </div>
                        <div class="form-text" id="searchStatus" aria-live="polite">
                            Click a product to add it. Adding the same product again increases its quantity.
                        </div>
                        <?php if ($quickPicks): ?>
                            <div class="mt-3">
                                <div class="small text-muted mb-2"><i class="bi bi-stars me-1"></i>Quick add &ndash; top sellers</div>
                                <div class="d-flex flex-wrap gap-2" id="quickPicks">
                                    <?php foreach ($quickPicks as $q): ?>
                                        <button type="button" class="quick-chip"
                                                data-id="<?= (int) $q['id'] ?>"
                                                data-name="<?= e($q['name']) ?>"
                                                data-brand="<?= e($q['brand']) ?>"
                                                data-price="<?= e(number_format($q['price'], 2, '.', '')) ?>"
                                                data-stock="<?= (int) $q['stock'] ?>">
                                            <?= e($q['name']) ?> <small><?= e(money($q['price'])) ?></small>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="alert d-none mt-2 mb-0 py-2 small" id="posAlert" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cart3 me-2"></i>Cart <span class="badge text-bg-secondary ms-1" id="cartCount">0</span></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearCart" disabled>
                            <i class="bi bi-trash3 me-1"></i>Clear cart
                        </button>
                    </div>

                    <div id="cartEmpty" class="empty-state">
                        <i class="bi bi-cart-x"></i>
                        <p class="mb-0">The cart is empty. Search for a product above to add it.</p>
                    </div>

                    <div class="table-responsive d-none" id="cartTableWrap">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Price</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col" class="text-end">Line total</th>
                                    <th scope="col"><span class="visually-hidden">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody id="cartBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT: customer + discount + summary -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person me-2"></i>Customer</div>
                    <div class="card-body">
                        <label for="customerSelect" class="form-label required">Select customer</label>
                        <select class="form-select" id="customerSelect" name="customer_id" required>
                            <?php foreach ($customers as $cid => $cname): ?>
                                <option value="<?= (int) $cid ?>"<?= $cid === $selectedCustomer ? ' selected' : '' ?>>
                                    <?= e($cname) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="bi bi-calculator me-2"></i>Discount &amp; summary</div>
                    <div class="card-body">
                        <div class="form-label mb-1" id="payLabel">Payment method</div>
                        <div class="pay-options mb-3" role="radiogroup" aria-labelledby="payLabel">
                            <?php foreach (payment_methods() as $key => [$plabel, $picon]): ?>
                                <label class="pay-option">
                                    <input type="radio" name="payment_method" value="<?= e($key) ?>"<?= $key === $paymentMethod ? ' checked' : '' ?>>
                                    <span><i class="bi <?= e($picon) ?>"></i><?= e($plabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-2 mb-1">
                            <div class="col-7">
                                <label for="discountType" class="form-label">Discount type</label>
                                <select class="form-select" id="discountType" name="discount_type">
                                    <option value="none"<?= $discountType === 'none' ? ' selected' : '' ?>>No discount</option>
                                    <option value="percentage"<?= $discountType === 'percentage' ? ' selected' : '' ?>>Percentage (%)</option>
                                    <option value="fixed"<?= $discountType === 'fixed' ? ' selected' : '' ?>>Fixed amount (<?= e(CURRENCY) ?>)</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="discountValue" class="form-label">Value</label>
                                <input type="number" class="form-control" id="discountValue" name="discount_value"
                                       min="0" step="0.01" inputmode="decimal" placeholder="0"
                                       value="<?= e($discountValue) ?>">
                            </div>
                        </div>
                        <div class="small text-danger mb-2" id="discountError" aria-live="polite"></div>

                        <div class="summary-row"><span>Items</span><span id="sumItems">0</span></div>
                        <div class="summary-row"><span>Subtotal</span><span id="sumSubtotal"><?= e(money(0)) ?></span></div>
                        <div class="summary-row text-danger"><span>Discount</span><span id="sumDiscount">- <?= e(money(0)) ?></span></div>
                        <div class="summary-row summary-total"><span>Grand total</span><span id="sumGrand"><?= e(money(0)) ?></span></div>

                        <div class="mt-3" id="cashBox">
                            <label for="cashReceived" class="form-label mb-1">Cash received <span class="text-muted">(optional, for change)</span></label>
                            <input type="number" class="form-control" id="cashReceived" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 2000">
                            <div class="change-box mt-2 d-none" id="changeBox" aria-live="polite"></div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" id="saveBtn" disabled>
                            <i class="bi bi-save me-2"></i>Save Invoice <kbd class="ms-1 d-none d-md-inline">Ctrl+Enter</kbd>
                        </button>
                        <div class="form-text text-center" id="saveHint" aria-live="polite"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<script type="application/json" id="posInitial"><?=
    json_encode($initialCart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
?></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
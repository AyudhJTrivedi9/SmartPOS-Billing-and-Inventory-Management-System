<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$id = parse_uint(get_str('id'));
$invoice = null;
if ($id !== null && $id > 0) {
    $stmt = db()->prepare('SELECT * FROM invoices WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    $invoice = $row === false ? null : $row;
}

if ($invoice === null) {
    flash('danger', 'That invoice does not exist.');
    redirect('invoices/index.php');
}

$itemStmt = db()->prepare(
    'SELECT product_name, brand_name, quantity, unit_price, line_total
       FROM invoice_items WHERE invoice_id = :id ORDER BY id'
);
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll();

if ($invoice['discount_type'] === 'percentage') {
    $pct = rtrim(rtrim(number_format((float) $invoice['discount_value'], 2, '.', ''), '0'), '.');
    $discountLabel = 'Discount (' . $pct . '%)';
} elseif ($invoice['discount_type'] === 'fixed') {
    $discountLabel = 'Discount (fixed amount)';
} else {
    $discountLabel = 'Discount';
}

$autoPrint = get_str('print') === '1';

// Ready-made WhatsApp message (opens wa.me; the cashier picks the contact).
$units = 0;
foreach ($items as $it) { $units += (int) $it['quantity']; }
$waText = APP_NAME . " invoice " . $invoice['invoice_number'] . "\n"
        . "Customer: " . $invoice['customer_name'] . "\n"
        . "Items: " . count($items) . " (" . $units . " units)\n"
        . "Total paid: " . money($invoice['grand_total']) . " via " . (payment_methods()[$invoice['payment_method']][0] ?? 'Cash') . "\n"
        . "Thank you for shopping with us!";
$waUrl = 'https://wa.me/?text=' . rawurlencode($waText);

$pageTitle   = 'Invoice ' . $invoice['invoice_number'];
$activePage  = 'invoices';
$breadcrumbs = ['Dashboard' => 'index.php', 'Invoices' => 'invoices/index.php', $invoice['invoice_number'] => null];

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="<?= e(url('invoices/index.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All invoices
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print invoice
    </button>
    <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="btn btn-success">
        <i class="bi bi-whatsapp me-1"></i>Share on WhatsApp
    </a>
    <a href="<?= e(url('pos/index.php')) ?>" class="btn btn-outline-primary ms-auto">
        <i class="bi bi-plus-lg me-1"></i>New sale
    </a>
</div>

<div class="invoice-sheet" data-bs-theme="light">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h3 mb-0"><i class="bi bi-lightning-charge-fill text-primary me-1"></i><?= e(APP_NAME) ?></h2>
            <div class="text-muted small">Product &amp; Billing Management System</div>
        </div>
        <div class="text-md-end">
            <div class="invoice-title h5 mb-1">INVOICE</div>
            <div class="fw-bold fs-5"><?= e($invoice['invoice_number']) ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6 mb-3 mb-sm-0">
            <div class="text-muted small text-uppercase">Billed to</div>
            <div class="fw-semibold fs-6"><?= e($invoice['customer_name']) ?></div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <div class="text-muted small text-uppercase">Invoice date</div>
            <div class="fw-semibold"><?= e(date('d M Y, h:i A', strtotime((string) $invoice['invoice_date']))) ?></div>
            <div class="mt-1"><span class="text-muted small text-uppercase">Paid by</span> <?= payment_badge((string) $invoice['payment_method']) ?></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th scope="col" style="width:50px">#</th>
                    <th scope="col">Product</th>
                    <th scope="col">Brand</th>
                    <th scope="col" class="text-end">Qty</th>
                    <th scope="col" class="text-end">Unit price</th>
                    <th scope="col" class="text-end">Line total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($it['product_name']) ?></td>
                    <td><?= e($it['brand_name']) ?></td>
                    <td class="text-end"><?= (int) $it['quantity'] ?></td>
                    <td class="text-end text-nowrap"><?= e(money($it['unit_price'])) ?></td>
                    <td class="text-end text-nowrap"><?= e(money($it['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row justify-content-end">
        <div class="col-md-5 col-lg-4">
            <div class="summary-row"><span>Subtotal</span><span><?= e(money($invoice['subtotal'])) ?></span></div>
            <div class="summary-row">
                <span><?= e($discountLabel) ?></span>
                <span>- <?= e(money($invoice['discount_amount'])) ?></span>
            </div>
            <div class="summary-row summary-total"><span>Grand total</span><span><?= e(money($invoice['grand_total'])) ?></span></div>
        </div>
    </div>

    <p class="text-center text-muted small mt-5 mb-0">Thank you for your business.</p>
</div>

<p class="text-muted small mt-3 no-print">
    <i class="bi bi-lock me-1"></i>Invoices are a permanent record and cannot be edited. Prices and product names
    shown here are the values at the time of sale.
</p>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
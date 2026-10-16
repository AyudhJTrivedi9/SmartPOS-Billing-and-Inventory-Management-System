<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

$id = parse_uint(get_str('id'));
$product = $id !== null && $id > 0 ? find_product($id) : null;

if ($product === null) {
    flash('danger', 'That product does not exist.');
    redirect('products/index.php');
}

$sales  = product_sales_summary($id);
$recent = product_recent_invoices($id, 5);

$cost   = (float) $product['cost_price'];
$sell   = (float) $product['sell_price'];
$profit = $sell - $cost;
$margin = $sell > 0 ? ($profit / $sell) * 100 : 0.0;

$pageTitle   = 'Product Details';
$activePage  = 'products';
$breadcrumbs = ['Dashboard' => 'index.php', 'Products' => 'products/index.php', 'Product Details' => null];

require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam me-2"></i>Product #<?= (int) $product['id'] ?></span>
                <?= stock_badge((int) $product['stock_quantity']) ?>
            </div>
            <div class="card-body">
                <h2 class="h4 mb-1"><?= e($product['name']) ?></h2>
                <p class="text-muted mb-4"><i class="bi bi-tag me-1"></i><?= e($product['brand_name']) ?></p>

                <dl class="row mb-0">
                    <dt class="col-sm-5">Cost price</dt>
                    <dd class="col-sm-7"><?= e(money($cost)) ?></dd>

                    <dt class="col-sm-5">Selling price</dt>
                    <dd class="col-sm-7"><?= e(money($sell)) ?></dd>

                    <dt class="col-sm-5">Profit per unit</dt>
                    <dd class="col-sm-7 <?= $profit < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= e(money($profit)) ?> (<?= e(number_format($margin, 1)) ?>% margin)
                    </dd>

                    <dt class="col-sm-5">Stock quantity</dt>
                    <dd class="col-sm-7"><?= (int) $product['stock_quantity'] ?> units</dd>

                    <dt class="col-sm-5">Created</dt>
                    <dd class="col-sm-7"><?= e(date('d M Y, h:i A', strtotime((string) $product['created_at']))) ?></dd>

                    <dt class="col-sm-5">Last updated</dt>
                    <dd class="col-sm-7"><?= e(date('d M Y, h:i A', strtotime((string) $product['updated_at']))) ?></dd>
                </dl>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <a href="<?= e(url('products/edit.php?id=' . (int) $product['id'])) ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <form method="post" action="<?= e(url('products/delete.php')) ?>" class="d-inline"
                      data-confirm="Delete &quot;<?= e($product['name']) ?>&quot; (<?= e($product['brand_name']) ?>)? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
                <a href="<?= e(url('products/index.php')) ?>" class="btn btn-outline-secondary ms-auto">
                    <i class="bi bi-arrow-left me-1"></i>Back to list
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-box-arrow-in-down me-2"></i>Quick restock</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('products/restock.php')) ?>" class="row g-2 align-items-end" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <div class="col-7">
                        <label for="add_qty" class="form-label mb-1">Units received</label>
                        <input type="number" class="form-control" id="add_qty" name="add_qty" min="1" max="100000" step="1" inputmode="numeric" required placeholder="e.g. 20">
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary w-100" data-loading-text="Adding...">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                </form>
                <div class="form-text mt-2">Adds to the current stock of <?= (int) $product['stock_quantity'] ?> units.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-graph-up me-2"></i>Sales summary</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fs-4 fw-bold"><?= $sales['units_sold'] ?></div>
                        <small class="text-muted">Units sold</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 fw-bold"><?= $sales['invoices'] ?></div>
                        <small class="text-muted">Invoices</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-5 fw-bold"><?= e(money($sales['revenue'])) ?></div>
                        <small class="text-muted">Revenue</small>
                    </div>
                </div>
                <?php if ($sales['invoices'] > 0): ?>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>This product appears on invoices, so it cannot be deleted.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt me-2"></i>Recent invoices</div>
            <?php if (!$recent): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-receipt"></i>
                    <p class="mb-0">This product has not been sold yet.</p>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recent as $r): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="<?= e(url('invoices/view.php?id=' . (int) $r['id'])) ?>" class="fw-semibold">
                                    <?= e($r['invoice_number']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= e($r['customer_name']) ?> &middot; <?= e(date('d M Y', strtotime((string) $r['invoice_date']))) ?>
                                </div>
                            </div>
                            <span class="text-end small">
                                <?= (int) $r['quantity'] ?> &times; <?= e(money($r['unit_price'])) ?><br>
                                <strong><?= e(money($r['line_total'])) ?></strong>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
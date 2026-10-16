<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

$search = get_str('q');
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$page = parse_uint(get_str('page') !== '' ? get_str('page') : '1') ?? 1;

$lowOnly = get_str('low') === '1';
$result = get_product_list($search, $page, $lowOnly);
$rows   = $result['rows'];
$pg     = $result['pagination'];
$query  = array_filter(['q' => $search, 'low' => $lowOnly ? '1' : ''], static fn($v) => $v !== '');

$pageTitle   = 'Products';
$activePage  = 'products';
$breadcrumbs = ['Dashboard' => 'index.php', 'Products' => null];

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= e(url('products/index.php')) ?>" class="d-flex gap-2 flex-grow-1" style="max-width: 520px;" role="search">
        <label for="q" class="visually-hidden">Search products</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="q" name="q" maxlength="100"
                   value="<?= e($search) ?>" placeholder="Search by product name or brand">
        </div>
        <?php if ($lowOnly): ?><input type="hidden" name="low" value="1"><?php endif; ?>
        <button type="submit" class="btn btn-outline-primary">Search</button>
        <?php if ($search !== '' || $lowOnly): ?>
            <a href="<?= e(url('products/index.php')) ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <a href="<?= e(url('products/index.php') . ($lowOnly ? ($search !== '' ? '?q=' . urlencode($search) : '') : '?' . http_build_query(array_filter(['q' => $search, 'low' => '1'])))) ?>"
       class="btn <?= $lowOnly ? 'btn-warning' : 'btn-outline-warning' ?>">
        <i class="bi bi-exclamation-triangle me-1"></i>Low stock only
    </a>

    <a href="<?= e(url('products/add.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Product
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Product name</th>
                    <th scope="col">Brand</th>
                    <th scope="col" class="text-end">Cost price</th>
                    <th scope="col" class="text-end">Selling price</th>
                    <th scope="col" class="text-center">Stock</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-box-seam"></i>
                            <?php if ($search !== ''): ?>
                                <p class="mb-2">No products match &ldquo;<?= e($search) ?>&rdquo;<?= $lowOnly ? ' with low stock' : '' ?>.</p>
                                <a href="<?= e(url('products/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Clear search</a>
                            <?php else: ?>
                                <p class="mb-2">No products yet. Add your first product to get started.</p>
                                <a href="<?= e(url('products/add.php')) ?>" class="btn btn-sm btn-primary">Add Product</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($rows as $p): ?>
                <tr>
                    <td class="text-muted">#<?= (int) $p['id'] ?></td>
                    <td class="fw-semibold"><?= e($p['name']) ?></td>
                    <td><?= e($p['brand_name']) ?></td>
                    <td class="text-end"><?= e(money($p['cost_price'])) ?></td>
                    <td class="text-end"><?= e(money($p['sell_price'])) ?></td>
                    <td class="text-center">
                        <?php
                        $sq  = (int) $p['stock_quantity'];
                        $pct = min(100, (int) round($sq / max(1, LOW_STOCK_THRESHOLD * 4) * 100));
                        $clr = $sq <= 0 ? '#dc3545' : ($sq <= LOW_STOCK_THRESHOLD ? '#f59e0b' : '#6366f1');
                        ?>
                        <?= stock_badge($sq) ?>
                        <div class="stock-meter mt-1 mx-auto" title="Stock level"><span style="width:<?= $pct ?>%;background:<?= $clr ?>"></span></div>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('products/view.php?id=' . (int) $p['id'])) ?>"
                           class="btn btn-sm btn-outline-secondary" title="View details"
                           aria-label="View <?= e($p['name']) ?>"><i class="bi bi-eye"></i></a>
                        <a href="<?= e(url('products/edit.php?id=' . (int) $p['id'])) ?>"
                           class="btn btn-sm btn-outline-primary" title="Edit"
                           aria-label="Edit <?= e($p['name']) ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="<?= e(url('products/delete.php')) ?>" class="d-inline"
                              data-confirm="Delete &quot;<?= e($p['name']) ?>&quot; (<?= e($p['brand_name']) ?>)? This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                    aria-label="Delete <?= e($p['name']) ?>"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pg['total'] > 0): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">
                Showing <?= $pg['offset'] + 1 ?>&ndash;<?= $pg['offset'] + count($rows) ?>
                of <?= $pg['total'] ?> product<?= $pg['total'] === 1 ? '' : 's' ?>
            </small>
            <?= render_pagination($pg, $query, 'products/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
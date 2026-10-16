<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/report_functions.php';
 
$pdo = db();
 
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalInvoices = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$totalSales    = (float) $pdo->query('SELECT COALESCE(SUM(grand_total), 0) FROM invoices')->fetchColumn();
 
$lowStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE stock_quantity <= :t');
$lowStmt->execute([':t' => LOW_STOCK_THRESHOLD]);
$lowStockCount = (int) $lowStmt->fetchColumn();
 
$todayStr  = date('Y-m-d');
$weekly    = get_daily_sales(date('Y-m-d', strtotime('-6 days')), $todayStr);
$weekTotal = array_sum(array_column($weekly, 'sales'));
$chart     = [
    'labels' => array_column($weekly, 'label'),
    'sales'  => array_map('floatval', array_column($weekly, 'sales')),
];
 
$today    = get_sales_summary($todayStr, $todayStr);
$recent   = get_recent_invoices(5);
$lowStock = get_low_stock_products(5);
 
$pageTitle   = 'Dashboard';
$activePage  = 'dashboard';
$breadcrumbs = ['Dashboard' => null];
 
require __DIR__ . '/includes/header.php';
 
$cards = [
    ['Products',  (string) $totalProducts, 'bi-box-seam',             'primary', 'products/index.php'],
    ['Invoices',  (string) $totalInvoices, 'bi-receipt',              'info',    'invoices/index.php'],
    ['Sales',     money($totalSales),      'bi-currency-rupee',       'success', 'reports/index.php'],
    ['Low stock', (string) $lowStockCount, 'bi-exclamation-triangle', $lowStockCount > 0 ? 'warning' : 'success', 'products/index.php?low=1'],
];
?>
 
<!-- Title + actions -->

<!-- Title + actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">Administrator</h2>
        <small class="text-muted">
            Today: <strong><?= e(money($today['sales'])) ?></strong>
            from <?= (int) $today['invoices'] ?> invoice<?= (int) $today['invoices'] === 1 ? '' : 's' ?>
        </small>
    </div>

    <!-- Keep both buttons close together -->
    <div class="d-flex align-items-center gap-2">
        <a href="<?= e(url('pos/index.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Sale
        </a>

        <a href="<?= e(url('logout.php')) ?>"
           class="btn btn-outline-danger"
           onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>
</div>

 
<!-- Stat cards -->
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon, $color, $link]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e(url($link)) ?>" class="text-decoration-none text-reset">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-icon bg-<?= e($color) ?>-subtle text-<?= e($color) ?>">
                            <i class="bi <?= e($icon) ?>"></i>
                        </span>
                        <div>
                            <div class="stat-value"><?= e($value) ?></div>
                            <div class="stat-label"><?= e($label) ?></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
 
<div class="row g-3">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Sales &ndash; last 7 days</div>
            <div class="card-body">
                <?php if ($weekTotal <= 0): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No sales in the last 7 days.</div>
                <?php else: ?>
                    <div style="position:relative;height:260px"><canvas id="weekChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
 
    <!-- Low stock -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Low stock</div>
            <?php if (!$lowStock): ?>
                <div class="empty-state"><i class="bi bi-check-circle"></i>All products are well stocked.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($lowStock as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <a href="<?= e(url('products/view.php?id=' . (int) $p['id'])) ?>" class="text-truncate">
                                <?= e($p['name']) ?>
                            </a>
                            <?= stock_badge((int) $p['stock_quantity']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
 
    <!-- Recent invoices -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent invoices</span>
                <a href="<?= e(url('invoices/index.php')) ?>" class="small">View all</a>
            </div>
            <?php if (!$recent): ?>
                <div class="empty-state"><i class="bi bi-receipt"></i>No invoices yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Invoice #</th><th>Customer</th><th>Date</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $inv): ?>
                            <tr>
                                <td><a href="<?= e(url('invoices/view.php?id=' . (int) $inv['id'])) ?>" class="fw-semibold"><?= e($inv['invoice_number']) ?></a></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td class="text-nowrap"><?= e(date('d M Y, h:i A', strtotime((string) $inv['invoice_date']))) ?></td>
                                <td class="text-end fw-semibold"><?= e(money($inv['grand_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
 
<?php if ($weekTotal > 0): ?>
<script type="application/json" id="chartData"><?=
    json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
?></script>
<script src="<?= e(url('assets/vendor/chart/chart.umd.min.js')) ?>"></script>
<script>
(function () {
    var data = JSON.parse(document.getElementById('chartData').textContent);
    new Chart(document.getElementById('weekChart'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{ label: 'Sales (\u20B9)', data: data.sales, backgroundColor: '#6366f1', borderRadius: 6 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
})();
</script>
<?php endif; ?>
 
<?php require __DIR__ . '/includes/footer.php'; ?>
 
<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/report_functions.php';

const REPORT_MAX_DAYS = 366;

$today = date('Y-m-d');
$from  = get_str('from');
$to    = get_str('to');

if ($from === '' && $to === '') {
    $from = date('Y-m-d', strtotime('-29 days'));
    $to   = $today;
} else {
    if ($from !== '' && !is_valid_date($from)) {
        flash('warning', 'The "From" date was invalid, so the default was used.');
        $from = '';
    }
    if ($to !== '' && !is_valid_date($to)) {
        flash('warning', 'The "To" date was invalid, so the default was used.');
        $to = '';
    }
    if ($to === '')   { $to = $today; }
    if ($from === '') { $from = date('Y-m-d', strtotime($to . ' -29 days')); }
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
    flash('info', 'The dates were in the wrong order, so they were swapped.');
}

$days = (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
if ($days > REPORT_MAX_DAYS) {
    flash('warning', 'Reports are limited to ' . REPORT_MAX_DAYS . ' days. Showing the last 30 days instead.');
    $from = date('Y-m-d', strtotime($to . ' -29 days'));
    $days = 30;
}

$summary = get_sales_summary($from, $to);
$daily   = get_daily_sales($from, $to);
$best    = get_best_sellers($from, $to, 10);
$payMix  = get_payment_breakdown($from, $to);

$hasSales  = $summary['invoices'] > 0;
$chart     = [
    'labels' => array_column($daily, 'label'),
    'sales'  => array_map(static fn($d) => $d['sales'], $daily),
];
$activeDays = array_reverse(array_values(array_filter($daily, static fn($d) => $d['invoices'] > 0)));

$quick = [
    'Today'      => [$today, $today],
    'Last 7 days'  => [date('Y-m-d', strtotime('-6 days')), $today],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), $today],
    'This month'   => [date('Y-m-01'), $today],
];

$pageTitle   = 'Sales Reports';
$activePage  = 'reports';
$breadcrumbs = ['Dashboard' => 'index.php', 'Sales Reports' => null];

require __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('reports/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="from" class="form-label mb-1">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="to" class="form-label mb-1">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
            <div class="col-md-4 d-flex flex-wrap gap-1 justify-content-md-end">
                <?php foreach ($quick as $label => [$qf, $qt]): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?= e(url('reports/index.php') . '?' . http_build_query(['from' => $qf, 'to' => $qt])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </form>
        <div class="small text-muted mt-2">
            Showing <?= e(date('d M Y', strtotime($from))) ?> to <?= e(date('d M Y', strtotime($to))) ?>
            (<?= $days ?> day<?= $days === 1 ? '' : 's' ?>)
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['Invoices',          (string) $summary['invoices'], 'bi-receipt',       'bg-primary-subtle text-primary'],
        ['Gross (subtotal)',  money($summary['subtotal']),   'bi-cash-stack',    'bg-info-subtle text-info'],
        ['Discounts given',   money($summary['discounts']),  'bi-percent',       'bg-warning-subtle text-warning'],
        ['Net sales',         money($summary['sales']),      'bi-wallet2',       'bg-success-subtle text-success'],
        ['Estimated profit',  money($summary['profit']),     'bi-graph-up-arrow', $summary['profit'] < 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'],
    ];
    foreach ($cards as [$label, $value, $icon, $cls]): ?>
        <div class="col-6 col-xl">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon <?= e($cls) ?>"><i class="bi <?= e($icon) ?>"></i></span>
                    <div>
                        <div class="stat-value fs-5"><?= e($value) ?></div>
                        <div class="stat-label"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="small text-muted">Net sales = invoice grand totals. Estimated profit = net sales minus the cost price of items sold (cost prices are the snapshots saved on each invoice).</p>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart-line me-2"></i>Daily sales</div>
    <div class="card-body">
        <?php if (!$hasSales): ?>
            <div class="empty-state py-4">
                <i class="bi bi-bar-chart"></i>
                <p class="mb-0">No sales in this period. Try a different date range.</p>
            </div>
        <?php else: ?>
            <div style="position:relative;height:300px;"><canvas id="salesChart" aria-label="Daily sales chart" role="img"></canvas></div>
            <div id="chartFallback" class="alert alert-warning d-none mt-3 mb-0">
                The chart library could not be loaded (check your internet connection). The table below still shows all figures.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-wallet2 me-2"></i>Sales by payment method</div>
    <div class="card-body">
        <?php if (!$hasSales): ?>
            <div class="empty-state py-3"><i class="bi bi-pie-chart"></i><p class="mb-0">No sales in this period.</p></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach (payment_methods() as $key => [$plabel, $picon]):
                    $share = $summary['sales'] > 0 ? $payMix[$key]['sales'] / $summary['sales'] * 100 : 0; ?>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi <?= e($picon) ?> fs-5 text-muted"></i>
                            <strong><?= e($plabel) ?></strong>
                            <span class="ms-auto small text-muted"><?= (int) $payMix[$key]['invoices'] ?> invoice<?= $payMix[$key]['invoices'] === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="fs-5 fw-bold"><?= e(money($payMix[$key]['sales'])) ?></div>
                        <div class="progress mt-1" style="height:7px" role="progressbar" aria-label="<?= e($plabel) ?> share"
                             aria-valuenow="<?= (int) round($share) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:<?= e(number_format($share, 1, '.', '')) ?>%;background:var(--sp-primary)"></div>
                        </div>
                        <div class="small text-muted"><?= e(number_format($share, 1)) ?>% of net sales</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy me-2"></i>Best-selling products</div>
            <?php if (!$best): ?>
                <div class="empty-state py-4"><i class="bi bi-trophy"></i><p class="mb-0">No products sold in this period.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Product</th>
                                <th scope="col" class="text-end">Units sold</th>
                                <th scope="col" class="text-end">Sales value</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($best as $i => $b): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <a href="<?= e(url('products/view.php?id=' . $b['product_id'])) ?>" class="fw-semibold"><?= e($b['name']) ?></a>
                                    <div class="small text-muted"><?= e($b['brand']) ?></div>
                                </td>
                                <td class="text-end"><?= $b['units'] ?></td>
                                <td class="text-end text-nowrap"><?= e(money($b['revenue'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">Sales value is before invoice-level discounts.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-week me-2"></i>Sales by day</div>
            <?php if (!$activeDays): ?>
                <div class="empty-state py-4"><i class="bi bi-calendar-x"></i><p class="mb-0">No sales in this period.</p></div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col" class="text-end">Invoices</th>
                                <th scope="col" class="text-end">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activeDays as $d): ?>
                            <tr>
                                <td><?= e(date('D, d M Y', strtotime($d['date']))) ?></td>
                                <td class="text-end"><?= $d['invoices'] ?></td>
                                <td class="text-end text-nowrap"><?= e(money($d['sales'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($hasSales): ?>
<script type="application/json" id="chartData"><?=
    json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
?></script>
<script src="<?= e(url("assets/vendor/chart/chart.umd.min.js")) ?>"></script>
<script>
(function () {
    var canvas = document.getElementById('salesChart');
    if (typeof Chart === 'undefined') {
        document.getElementById('chartFallback').classList.remove('d-none');
        canvas.parentNode.classList.add('d-none');
        return;
    }
    var data = JSON.parse(document.getElementById('chartData').textContent);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Sales (\u20B9)',
                data: data.sales,
                backgroundColor: 'rgba(99, 102, 241, 0.85)',
                borderRadius: 4
            }]
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
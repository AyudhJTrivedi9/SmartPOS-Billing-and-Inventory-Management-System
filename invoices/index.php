<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$search = mb_substr(get_str('q'), 0, 100);
$from   = get_str('from');
$to     = get_str('to');

if ($from !== '' && !is_valid_date($from)) {
    flash('warning', 'The "From" date was invalid and has been ignored.');
    $from = '';
}
if ($to !== '' && !is_valid_date($to)) {
    flash('warning', 'The "To" date was invalid and has been ignored.');
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
    flash('info', 'The dates were in the wrong order, so they were swapped.');
}

$where  = [];
$params = [];

if ($search !== '') {
    $like = '%' . like_escape($search) . '%';
    $where[] = '(invoice_number LIKE :q1 OR customer_name LIKE :q2)';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
}
if ($from !== '') {
    $where[] = 'invoice_date >= :from';
    $params[':from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'invoice_date < :to';
    $params[':to'] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = db()->prepare('SELECT COUNT(*), COALESCE(SUM(grand_total), 0) FROM invoices' . $whereSql);
$cnt->execute($params);
[$total, $sumTotal] = $cnt->fetch(PDO::FETCH_NUM);
$total    = (int) $total;
$sumTotal = (float) $sumTotal;

$page = parse_uint(get_str('page') !== '' ? get_str('page') : '1') ?? 1;
$pg   = paginate($total, $page);

$stmt = db()->prepare(
    'SELECT id, invoice_number, customer_name, payment_method, invoice_date, subtotal,
            discount_type, discount_value, discount_amount, grand_total
       FROM invoices' . $whereSql . '
   ORDER BY invoice_date DESC, id DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$query = array_filter(['q' => $search, 'from' => $from, 'to' => $to], static fn($v) => $v !== '');
$filtered = $query !== [];

$pageTitle   = 'Invoices';
$activePage  = 'invoices';
$breadcrumbs = ['Dashboard' => 'index.php', 'Invoices' => null];

require __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('invoices/index.php')) ?>" class="row g-2 align-items-end" role="search">
            <div class="col-md-4">
                <label for="q" class="form-label mb-1">Invoice number or customer</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="100"
                       value="<?= e($search) ?>" placeholder="e.g. INV-2026 or Rahul">
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label mb-1">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label mb-1">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($filtered): ?>
                    <a href="<?= e(url('invoices/index.php')) ?>" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
                <a href="<?= e(url('invoices/export.php') . ($query ? '?' . http_build_query($query) : '')) ?>"
                   class="btn btn-outline-secondary ms-auto" title="Download the filtered list as a CSV file (opens in Excel)">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </a>
                <a href="<?= e(url('pos/index.php')) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-plus-lg me-1"></i>New sale
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Invoice #</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Date</th>
                    <th scope="col">Paid by</th>
                    <th scope="col" class="text-end">Subtotal</th>
                    <th scope="col" class="text-end">Discount</th>
                    <th scope="col" class="text-end">Grand total</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-receipt"></i>
                            <?php if ($filtered): ?>
                                <p class="mb-2">No invoices match your filters.</p>
                                <a href="<?= e(url('invoices/index.php')) ?>" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                            <?php else: ?>
                                <p class="mb-2">No invoices yet. Create your first sale in POS Billing.</p>
                                <a href="<?= e(url('pos/index.php')) ?>" class="btn btn-sm btn-primary">Open POS</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($rows as $inv):
                $vid = (int) $inv['id'];
                if ($inv['discount_type'] === 'percentage') {
                    $pct = rtrim(rtrim(number_format((float) $inv['discount_value'], 2, '.', ''), '0'), '.');
                    $discText = $pct . '% (' . money($inv['discount_amount']) . ')';
                } elseif ($inv['discount_type'] === 'fixed') {
                    $discText = money($inv['discount_amount']);
                } else {
                    $discText = '';
                } ?>
                <tr>
                    <td class="fw-semibold text-nowrap">
                        <a href="<?= e(url('invoices/view.php?id=' . $vid)) ?>"><?= e($inv['invoice_number']) ?></a>
                    </td>
                    <td><?= e($inv['customer_name']) ?></td>
                    <td class="text-nowrap"><?= e(date('d M Y, h:i A', strtotime((string) $inv['invoice_date']))) ?></td>
                    <td><?= payment_badge((string) $inv['payment_method']) ?></td>
                    <td class="text-end text-nowrap"><?= e(money($inv['subtotal'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?= $discText !== '' ? e($discText) : '<span class="text-muted">&mdash;</span>' ?>
                    </td>
                    <td class="text-end fw-semibold text-nowrap"><?= e(money($inv['grand_total'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('invoices/view.php?id=' . $vid)) ?>" class="btn btn-sm btn-outline-secondary"
                           title="View details" aria-label="View <?= e($inv['invoice_number']) ?>"><i class="bi bi-eye"></i></a>
                        <a href="<?= e(url('invoices/view.php?id=' . $vid . '&print=1')) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-primary" title="Print"
                           aria-label="Print <?= e($inv['invoice_number']) ?>"><i class="bi bi-printer"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total > 0): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">
                Showing <?= $pg['offset'] + 1 ?>&ndash;<?= $pg['offset'] + count($rows) ?> of <?= $total ?>
                invoice<?= $total === 1 ? '' : 's' ?>
                &middot; Total of all matching invoices: <strong><?= e(money($sumTotal)) ?></strong>
            </small>
            <?= render_pagination($pg, $query, 'invoices/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
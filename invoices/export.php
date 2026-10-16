<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Download the invoice list as CSV (read-only GET). Honours the same filters as
 * invoices/index.php: q (invoice number / customer), from, to.
 */
$search = mb_substr(get_str('q'), 0, 100);
$from   = get_str('from');
$to     = get_str('to');
if ($from !== '' && !is_valid_date($from)) { $from = ''; }
if ($to !== '' && !is_valid_date($to)) { $to = ''; }
if ($from !== '' && $to !== '' && $from > $to) { [$from, $to] = [$to, $from]; }

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

$stmt = db()->prepare(
    'SELECT invoice_number, customer_name, payment_method, invoice_date, subtotal,
            discount_type, discount_value, discount_amount, grand_total
       FROM invoices' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
   ORDER BY invoice_date DESC, id DESC LIMIT 5000'
);
$stmt->execute($params);

/** Stop spreadsheet formula injection: text starting with = + - @ gets a leading quote. */
$safe = static function (string $v): string {
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartpos-invoices-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows symbols correctly
fputcsv($out, ['Invoice #', 'Customer', 'Paid by', 'Date', 'Subtotal', 'Discount type', 'Discount value', 'Discount amount', 'Grand total'], ',', '"', '\\');
foreach ($stmt->fetchAll() as $r) {
    fputcsv($out, [
        $r['invoice_number'],
        $safe((string) $r['customer_name']),
        payment_methods()[$r['payment_method']][0] ?? 'Cash',
        $r['invoice_date'],
        $r['subtotal'],
        $r['discount_type'],
        $r['discount_value'],
        $r['discount_amount'],
        $r['grand_total'],
    ], ',', '"', '\\');
}
fclose($out);

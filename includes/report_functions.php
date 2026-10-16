<?php
declare(strict_types=1);

/**
 * Shared queries for the dashboard and the reports page.
 * Date filters use "invoice_date >= start AND invoice_date < next day" so the
 * index on invoice_date can be used and the whole end date is included.
 */

/** @return array{0:string,1:string} [start datetime, exclusive end datetime] */
function date_range_bounds(string $from, string $to): array
{
    $end = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    return [$from . ' 00:00:00', $end . ' 00:00:00'];
}

/** @return array{invoices:int, subtotal:float, discounts:float, sales:float, cost:float, profit:float} */
function get_sales_summary(string $from, string $to): array
{
    [$start, $end] = date_range_bounds($from, $to);

    $stmt = db()->prepare(
        'SELECT COUNT(*)                      AS invoices,
                COALESCE(SUM(subtotal), 0)        AS subtotal,
                COALESCE(SUM(discount_amount), 0) AS discounts,
                COALESCE(SUM(grand_total), 0)     AS sales
           FROM invoices
          WHERE invoice_date >= :s AND invoice_date < :e'
    );
    $stmt->execute([':s' => $start, ':e' => $end]);
    $r = $stmt->fetch();

    $costStmt = db()->prepare(
        'SELECT COALESCE(SUM(ii.cost_price * ii.quantity), 0)
           FROM invoice_items ii
           JOIN invoices i ON i.id = ii.invoice_id
          WHERE i.invoice_date >= :s AND i.invoice_date < :e'
    );
    $costStmt->execute([':s' => $start, ':e' => $end]);
    $cost = (float) $costStmt->fetchColumn();

    $sales = (float) $r['sales'];

    return [
        'invoices'  => (int) $r['invoices'],
        'subtotal'  => (float) $r['subtotal'],
        'discounts' => (float) $r['discounts'],
        'sales'     => $sales,
        'cost'      => $cost,
        'profit'    => round($sales - $cost, 2),
    ];
}

/**
 * One entry per calendar day in the range (days without sales are 0).
 * @return array<int, array{date:string, label:string, invoices:int, sales:float}>
 */
function get_daily_sales(string $from, string $to): array
{
    [$start, $end] = date_range_bounds($from, $to);

    $stmt = db()->prepare(
        'SELECT DATE(invoice_date) AS d, COUNT(*) AS invoices, COALESCE(SUM(grand_total), 0) AS sales
           FROM invoices
          WHERE invoice_date >= :s AND invoice_date < :e
       GROUP BY DATE(invoice_date)'
    );
    $stmt->execute([':s' => $start, ':e' => $end]);

    $byDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $byDay[(string) $row['d']] = $row;
    }

    $out    = [];
    $period = new DatePeriod(
        new DateTimeImmutable($from),
        new DateInterval('P1D'),
        new DateTimeImmutable(substr($end, 0, 10))
    );
    foreach ($period as $day) {
        $key   = $day->format('Y-m-d');
        $out[] = [
            'date'     => $key,
            'label'    => $day->format('d M'),
            'invoices' => isset($byDay[$key]) ? (int) $byDay[$key]['invoices'] : 0,
            'sales'    => isset($byDay[$key]) ? (float) $byDay[$key]['sales'] : 0.0,
        ];
    }
    return $out;
}

/**
 * Best-selling products by units sold in the range. Uses the invoice item
 * snapshots, so renamed or repriced products still report correctly.
 * "revenue" is the line value before any invoice-level discount.
 * @return array<int, array{product_id:int, name:string, brand:string, units:int, revenue:float}>
 */
function get_best_sellers(string $from, string $to, int $limit = 10): array
{
    [$start, $end] = date_range_bounds($from, $to);

    $stmt = db()->prepare(
        'SELECT ii.product_id,
                MAX(ii.product_name) AS name,
                MAX(ii.brand_name)   AS brand,
                SUM(ii.quantity)     AS units,
                SUM(ii.line_total)   AS revenue
           FROM invoice_items ii
           JOIN invoices i ON i.id = ii.invoice_id
          WHERE i.invoice_date >= :s AND i.invoice_date < :e
       GROUP BY ii.product_id
       ORDER BY units DESC, revenue DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':s', $start);
    $stmt->bindValue(':e', $end);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'product_id' => (int) $r['product_id'],
            'name'       => (string) $r['name'],
            'brand'      => (string) $r['brand'],
            'units'      => (int) $r['units'],
            'revenue'    => (float) $r['revenue'],
        ];
    }
    return $rows;
}

/** @return array<int,array> products at or below the low-stock threshold, lowest first */
function get_low_stock_products(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT id, name, brand_name, stock_quantity
           FROM products
          WHERE stock_quantity <= :t
       ORDER BY stock_quantity ASC, name ASC
          LIMIT :limit'
    );
    $stmt->bindValue(':t', LOW_STOCK_THRESHOLD, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** @return array<int,array> */
function get_recent_invoices(int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT id, invoice_number, customer_name, invoice_date, grand_total
           FROM invoices
       ORDER BY invoice_date DESC, id DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
/**
 * Sales split by payment method in a date range (methods with no sales are included as 0).
 * @return array<string, array{invoices:int, sales:float}> keyed by payment method
 */
function get_payment_breakdown(string $from, string $to): array
{
    [$start, $end] = date_range_bounds($from, $to);

    $out = [];
    foreach (array_keys(payment_methods()) as $key) {
        $out[$key] = ['invoices' => 0, 'sales' => 0.0];
    }

    $stmt = db()->prepare(
        'SELECT payment_method, COUNT(*) AS invoices, COALESCE(SUM(grand_total), 0) AS sales
           FROM invoices
          WHERE invoice_date >= :s AND invoice_date < :e
       GROUP BY payment_method'
    );
    $stmt->execute([':s' => $start, ':e' => $end]);
    foreach ($stmt->fetchAll() as $r) {
        $k = (string) $r['payment_method'];
        if (isset($out[$k])) {
            $out[$k] = ['invoices' => (int) $r['invoices'], 'sales' => (float) $r['sales']];
        }
    }
    return $out;
}

/** Customers ranked by total spend (all time). @return array<int,array{customer:string, invoices:int, spent:float}> */
function get_top_customers(int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT customer_name, COUNT(*) AS invoices, SUM(grand_total) AS spent
           FROM invoices
       GROUP BY customer_name
       ORDER BY spent DESC, invoices DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'customer' => (string) $r['customer_name'],
            'invoices' => (int) $r['invoices'],
            'spent'    => (float) $r['spent'],
        ];
    }
    return $rows;
}

/**
 * Best sellers that are still in stock, for the POS quick-add buttons.
 * Falls back to the newest in-stock products when nothing has been sold yet.
 * @return array<int,array{id:int,name:string,brand:string,price:float,stock:int}>
 */
function get_quick_pick_products(int $limit = 6): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.brand_name, p.sell_price, p.stock_quantity,
                COALESCE(SUM(ii.quantity), 0) AS sold
           FROM products p
      LEFT JOIN invoice_items ii ON ii.product_id = p.id
          WHERE p.stock_quantity > 0
       GROUP BY p.id, p.name, p.brand_name, p.sell_price, p.stock_quantity
       ORDER BY sold DESC, p.id DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'id'    => (int) $r['id'],
            'name'  => (string) $r['name'],
            'brand' => (string) $r['brand_name'],
            'price' => (float) $r['sell_price'],
            'stock' => (int) $r['stock_quantity'],
        ];
    }
    return $rows;
}

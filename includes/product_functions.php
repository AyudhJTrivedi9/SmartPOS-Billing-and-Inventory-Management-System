<?php
declare(strict_types=1);

/**
 * Product-related database and validation helpers.
 * Used by the products/ pages (and later by the POS pages).
 */

const PRODUCT_NAME_MAX  = 150;
const PRODUCT_BRAND_MAX = 100;

/** Empty form values for the "Add Product" page. @return array<string,string> */
function product_form_defaults(): array
{
    return [
        'name'           => '',
        'brand_name'     => '',
        'cost_price'     => '',
        'sell_price'     => '',
        'stock_quantity' => '0',
    ];
}

/** Read the product form fields from $_POST as trimmed strings. @return array<string,string> */
function product_input_from_post(): array
{
    return [
        'name'           => post_str('name'),
        'brand_name'     => post_str('brand_name'),
        'cost_price'     => post_str('cost_price'),
        'sell_price'     => post_str('sell_price'),
        'stock_quantity' => post_str('stock_quantity'),
    ];
}

/** Fetch one product row or null. */
function find_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Id of another product with the same name + brand (case-insensitive), or null. */
function find_duplicate_product(string $name, string $brand, ?int $ignoreId = null): ?int
{
    $sql = 'SELECT id FROM products WHERE name = :name AND brand_name = :brand';
    $params = [':name' => $name, ':brand' => $brand];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> :ignore';
        $params[':ignore'] = $ignoreId;
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * Validate product input on the server.
 *
 * @param  array<string,string> $in raw trimmed strings
 * @return array{errors: array<string,string>, clean: array<string,mixed>}
 */
function validate_product_input(array $in, ?int $ignoreId = null): array
{
    $errors = [];

    // Collapse repeated spaces so "Wireless   Mouse" and "Wireless Mouse" are the same.
    $name  = preg_replace('/\s+/u', ' ', $in['name']) ?? $in['name'];
    $brand = preg_replace('/\s+/u', ' ', $in['brand_name']) ?? $in['brand_name'];

    if ($name === '') {
        $errors['name'] = 'Product name is required.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Product name must be at least 2 characters.';
    } elseif (mb_strlen($name) > PRODUCT_NAME_MAX) {
        $errors['name'] = 'Product name cannot exceed ' . PRODUCT_NAME_MAX . ' characters.';
    }

    if ($brand === '') {
        $errors['brand_name'] = 'Brand name is required.';
    } elseif (mb_strlen($brand) > PRODUCT_BRAND_MAX) {
        $errors['brand_name'] = 'Brand name cannot exceed ' . PRODUCT_BRAND_MAX . ' characters.';
    }

    $cost = null;
    if ($in['cost_price'] === '') {
        $errors['cost_price'] = 'Cost price is required.';
    } else {
        $cost = parse_money($in['cost_price']);
        if ($cost === null) {
            $errors['cost_price'] = 'Enter a valid non-negative amount with up to 2 decimals (max 99999999.99).';
        }
    }

    $sell = null;
    if ($in['sell_price'] === '') {
        $errors['sell_price'] = 'Selling price is required.';
    } else {
        $sell = parse_money($in['sell_price']);
        if ($sell === null) {
            $errors['sell_price'] = 'Enter a valid non-negative amount with up to 2 decimals (max 99999999.99).';
        }
    }

    $stock = null;
    if ($in['stock_quantity'] === '') {
        $errors['stock_quantity'] = 'Stock quantity is required (use 0 if none).';
    } else {
        $stock = parse_uint($in['stock_quantity']);
        if ($stock === null) {
            $errors['stock_quantity'] = 'Stock must be a whole number, 0 or more.';
        }
    }

    // Duplicate check only when name and brand themselves are valid.
    if (!isset($errors['name']) && !isset($errors['brand_name'])) {
        $dupId = find_duplicate_product($name, $brand, $ignoreId);
        if ($dupId !== null) {
            $errors['name'] = 'This product already exists for this brand (ID ' . $dupId . ').';
        }
    }

    return [
        'errors' => $errors,
        'clean'  => [
            'name'           => $name,
            'brand_name'     => $brand,
            'cost_price'     => $cost,
            'sell_price'     => $sell,
            'stock_quantity' => $stock,
        ],
    ];
}

/**
 * Paginated product list with optional search on name or brand.
 *
 * @return array{rows: array<int,array>, pagination: array}
 */
function get_product_list(string $search, int $page, bool $lowOnly = false): array
{
    $conds  = [];
    $params = [];

    if ($search !== '') {
        $like = '%' . like_escape($search) . '%';
        $conds[] = '(name LIKE :q1 OR brand_name LIKE :q2)';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
    }
    if ($lowOnly) {
        $conds[] = 'stock_quantity <= ' . (int) LOW_STOCK_THRESHOLD;
    }
    $where = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

    $countStmt = db()->prepare('SELECT COUNT(*) FROM products' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pg = paginate($total, $page);

    $stmt = db()->prepare(
        'SELECT id, name, brand_name, cost_price, sell_price, stock_quantity
           FROM products' . $where . '
       ORDER BY id DESC
          LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'pagination' => $pg];
}

/** How many invoice lines reference this product? (> 0 means it cannot be deleted) */
function product_invoice_line_count(int $productId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM invoice_items WHERE product_id = :id');
    $stmt->execute([':id' => $productId]);
    return (int) $stmt->fetchColumn();
}

/** @return array{units_sold:int, revenue:float, invoices:int} */
function product_sales_summary(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(quantity), 0)   AS units_sold,
                COALESCE(SUM(line_total), 0) AS revenue,
                COUNT(DISTINCT invoice_id)   AS invoices
           FROM invoice_items
          WHERE product_id = :id'
    );
    $stmt->execute([':id' => $productId]);
    $r = $stmt->fetch();

    return [
        'units_sold' => (int) $r['units_sold'],
        'revenue'    => (float) $r['revenue'],
        'invoices'   => (int) $r['invoices'],
    ];
}

/** Most recent invoices that contain this product. @return array<int,array> */
function product_recent_invoices(int $productId, int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT i.id, i.invoice_number, i.customer_name, i.invoice_date,
                ii.quantity, ii.unit_price, ii.line_total
           FROM invoice_items ii
           JOIN invoices i ON i.id = ii.invoice_id
          WHERE ii.product_id = :id
       ORDER BY i.invoice_date DESC, i.id DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** True if a PDOException is a duplicate-key violation (MySQL error 1062). */
function is_duplicate_key_error(PDOException $ex): bool
{
    return ($ex->errorInfo[1] ?? 0) === 1062;
}
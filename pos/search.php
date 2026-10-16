<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * AJAX endpoint: GET pos/search.php?q=...
 * Every word typed must match the product name or the brand, so
 * "logitech mouse" finds "Wireless Mouse M221" by Logitech.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$q = get_str('q');
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$sql    = 'SELECT id, name, brand_name, sell_price, stock_quantity FROM products';
$params = [];

if ($q !== '') {
    $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $terms = array_slice($terms, 0, 5);
    $conds = [];
    foreach ($terms as $i => $term) {
        $conds[] = "(name LIKE :n{$i} OR brand_name LIKE :b{$i})";
        $like = '%' . like_escape($term) . '%';
        $params[":n{$i}"] = $like;
        $params[":b{$i}"] = $like;
    }
    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
}

$sql .= ' ORDER BY (stock_quantity = 0) ASC, name ASC LIMIT 15';

$stmt = db()->prepare($sql);
$stmt->execute($params);

$products = [];
foreach ($stmt->fetchAll() as $row) {
    $products[] = [
        'id'    => (int) $row['id'],
        'name'  => (string) $row['name'],
        'brand' => (string) $row['brand_name'],
        'price' => (float) $row['sell_price'],
        'stock' => (int) $row['stock_quantity'],
    ];
}

echo json_encode(['success' => true, 'products' => $products]);
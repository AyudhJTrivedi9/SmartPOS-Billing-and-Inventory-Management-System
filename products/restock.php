<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

/** POST-only: add received units to a product's stock (atomic, no read-modify-write race). */
if (!is_post()) {
    flash('warning', 'Use the Quick restock form on the product page.');
    redirect('products/index.php');
}
verify_csrf();

$id  = parse_uint(post_str('id'));
$qty = parse_uint(post_str('add_qty'));

$product = $id !== null && $id > 0 ? find_product($id) : null;
if ($product === null) {
    flash('danger', 'That product does not exist.');
    redirect('products/index.php');
}
if ($qty === null || $qty < 1 || $qty > 100000) {
    flash('danger', 'Enter a whole number of units from 1 to 100000.');
    redirect('products/view.php?id=' . $id);
}
if ((int) $product['stock_quantity'] + $qty > 999999999) {
    flash('danger', 'That would exceed the maximum stock value.');
    redirect('products/view.php?id=' . $id);
}

$stmt = db()->prepare('UPDATE products SET stock_quantity = stock_quantity + :q WHERE id = :id');
$stmt->execute([':q' => $qty, ':id' => $id]);

$new = (int) find_product($id)['stock_quantity'];
flash('success', 'Added ' . $qty . ' unit' . ($qty === 1 ? '' : 's') . ' to "' . $product['name'] . '". Stock is now ' . $new . '.');
redirect('products/view.php?id=' . $id);

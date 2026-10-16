<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

/**
 * POST-only delete endpoint. A direct visit (GET) never deletes anything.
 * Products already used on an invoice are protected and cannot be removed.
 */
if (!is_post()) {
    flash('warning', 'Products can only be deleted using the Delete button.');
    redirect('products/index.php');
}

verify_csrf();

$id = parse_uint(post_str('id'));
$product = $id !== null && $id > 0 ? find_product($id) : null;

if ($product === null) {
    flash('danger', 'That product does not exist or was already deleted.');
    redirect('products/index.php');
}

$label = $product['name'] . ' (' . $product['brand_name'] . ')';

$lines = product_invoice_line_count($id);
if ($lines > 0) {
    flash(
        'danger',
        'Cannot delete "' . $label . '": it appears on ' . $lines . ' invoice line'
        . ($lines === 1 ? '' : 's') . '. Deleting it would break your sales history. '
        . 'Set its stock to 0 instead if you no longer sell it.'
    );
    redirect('products/index.php');
}

try {
    $stmt = db()->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    flash('success', 'Product "' . $label . '" was deleted.');
} catch (PDOException $ex) {
    // 1451 = foreign key violation (e.g. an invoice was created a moment ago)
    if (($ex->errorInfo[1] ?? 0) === 1451) {
        flash('danger', 'Cannot delete "' . $label . '": it is used on an invoice.');
    } else {
        throw $ex;
    }
}

redirect('products/index.php');
<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

$id = parse_uint(get_str('id'));
$product = $id !== null && $id > 0 ? find_product($id) : null;

if ($product === null) {
    flash('danger', 'That product does not exist.');
    redirect('products/index.php');
}

$form = [
    'name'           => (string) $product['name'],
    'brand_name'     => (string) $product['brand_name'],
    'cost_price'     => number_format((float) $product['cost_price'], 2, '.', ''),
    'sell_price'     => number_format((float) $product['sell_price'], 2, '.', ''),
    'stock_quantity' => (string) $product['stock_quantity'],
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $form = product_input_from_post();
    $v = validate_product_input($form, $id);
    $errors = $v['errors'];

    if (!$errors) {
        $c = $v['clean'];
        try {
            $stmt = db()->prepare(
                'UPDATE products
                    SET name = :name, brand_name = :brand, cost_price = :cost,
                        sell_price = :sell, stock_quantity = :stock
                  WHERE id = :id'
            );
            $stmt->execute([
                ':name'  => $c['name'],
                ':brand' => $c['brand_name'],
                ':cost'  => $c['cost_price'],
                ':sell'  => $c['sell_price'],
                ':stock' => $c['stock_quantity'],
                ':id'    => $id,
            ]);

            flash('success', 'Product "' . $c['name'] . '" was updated. Existing invoices keep their original prices.');
            if ($c['sell_price'] < $c['cost_price']) {
                flash('warning', 'Note: the selling price is lower than the cost price, so this product will sell at a loss.');
            }
            redirect('products/index.php');
        } catch (PDOException $ex) {
            if (is_duplicate_key_error($ex)) {
                $errors['name'] = 'Another product with this name and brand already exists.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle   = 'Edit Product';
$activePage  = 'products';
$breadcrumbs = ['Dashboard' => 'index.php', 'Products' => 'products/index.php', 'Edit Product' => null];

$formAction  = url('products/edit.php') . '?id=' . $id;
$submitLabel = 'Update Product';
$cancelUrl   = url('products/index.php');

require __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Edit product #<?= (int) $id ?></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the highlighted fields and try again.
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/_form.php'; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
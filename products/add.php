<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/product_functions.php';

$form   = product_form_defaults();
$errors = [];

if (is_post()) {
    verify_csrf();

    $form = product_input_from_post();
    $v = validate_product_input($form);
    $errors = $v['errors'];

    if (!$errors) {
        $c = $v['clean'];
        try {
            $stmt = db()->prepare(
                'INSERT INTO products (name, brand_name, cost_price, sell_price, stock_quantity)
                 VALUES (:name, :brand, :cost, :sell, :stock)'
            );
            $stmt->execute([
                ':name'  => $c['name'],
                ':brand' => $c['brand_name'],
                ':cost'  => $c['cost_price'],
                ':sell'  => $c['sell_price'],
                ':stock' => $c['stock_quantity'],
            ]);

            flash('success', 'Product "' . $c['name'] . '" was added successfully.');
            if ($c['sell_price'] < $c['cost_price']) {
                flash('warning', 'Note: the selling price is lower than the cost price, so this product will sell at a loss.');
            }
            redirect('products/index.php');
        } catch (PDOException $ex) {
            if (is_duplicate_key_error($ex)) {
                $errors['name'] = 'This product already exists for this brand.';
            } else {
                throw $ex; // logged and shown as a generic error by bootstrap.php
            }
        }
    }
}

$pageTitle   = 'Add Product';
$activePage  = 'products';
$breadcrumbs = ['Dashboard' => 'index.php', 'Products' => 'products/index.php', 'Add Product' => null];

$formAction  = url('products/add.php');
$submitLabel = 'Save Product';
$cancelUrl   = url('products/index.php');

require __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>New product</div>
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
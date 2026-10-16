<?php
declare(strict_types=1);

/**
 * Shared Add/Edit product form (included by add.php and edit.php).
 * Expects: $form (array<string,string>), $errors (array<string,string>),
 *          $formAction (string), $submitLabel (string), $cancelUrl (string)
 */
$field = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? ' is-invalid' : '';
};
?>
<form method="post" action="<?= e($formAction) ?>" novalidate data-loading>
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-8">
            <label for="name" class="form-label required">Product name</label>
            <input type="text" class="form-control<?= $field('name') ?>" id="name" name="name"
                   maxlength="<?= PRODUCT_NAME_MAX ?>" required
                   value="<?= e($form['name']) ?>" placeholder="e.g. Wireless Mouse M221">
            <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label for="brand_name" class="form-label required">Brand name</label>
            <input type="text" class="form-control<?= $field('brand_name') ?>" id="brand_name" name="brand_name"
                   maxlength="<?= PRODUCT_BRAND_MAX ?>" required
                   value="<?= e($form['brand_name']) ?>" placeholder="e.g. Logitech">
            <?php if (isset($errors['brand_name'])): ?>
                <div class="invalid-feedback"><?= e($errors['brand_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label for="cost_price" class="form-label required">Cost price (<?= e(CURRENCY) ?>)</label>
            <input type="number" class="form-control<?= $field('cost_price') ?>" id="cost_price" name="cost_price"
                   min="0" max="99999999.99" step="0.01" inputmode="decimal" required
                   value="<?= e($form['cost_price']) ?>" placeholder="0.00">
            <?php if (isset($errors['cost_price'])): ?>
                <div class="invalid-feedback"><?= e($errors['cost_price']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label for="sell_price" class="form-label required">Selling price (<?= e(CURRENCY) ?>)</label>
            <input type="number" class="form-control<?= $field('sell_price') ?>" id="sell_price" name="sell_price"
                   min="0" max="99999999.99" step="0.01" inputmode="decimal" required
                   value="<?= e($form['sell_price']) ?>" placeholder="0.00">
            <?php if (isset($errors['sell_price'])): ?>
                <div class="invalid-feedback"><?= e($errors['sell_price']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label for="stock_quantity" class="form-label required">Stock quantity</label>
            <input type="number" class="form-control<?= $field('stock_quantity') ?>" id="stock_quantity" name="stock_quantity"
                   min="0" max="999999999" step="1" inputmode="numeric" required
                   value="<?= e($form['stock_quantity']) ?>">
            <?php if (isset($errors['stock_quantity'])): ?>
                <div class="invalid-feedback"><?= e($errors['stock_quantity']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary" data-loading-text="Saving...">
            <i class="bi bi-check-lg me-1"></i><?= e($submitLabel) ?>
        </button>
        <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
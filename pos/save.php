<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/invoice_functions.php';

/**
 * POST-only endpoint that validates the sale and creates the invoice.
 * Only product ids and quantities are read from the browser; prices,
 * names, totals and the customer name are all taken from the server.
 */
if (!is_post()) {
    redirect('pos/index.php');
}

verify_csrf();

// One-time token: a repeated submit of the same form is rejected.
if (!consume_form_token('invoice', post_str('form_token'))) {
    flash('warning', 'This sale form expired or was already submitted. Check the Invoices page before creating it again.');
    redirect('pos/index.php');
}

$errors    = [];
$customers = get_customers();

// ---- Customer (looked up from the static list by id) ----
$customerId   = parse_uint(post_str('customer_id'));
$customerName = ($customerId !== null && isset($customers[$customerId])) ? $customers[$customerId] : null;
if ($customerName === null) {
    $errors[] = 'Please select a valid customer.';
}

// ---- Cart: product_id => quantity ----
$rawItems = $_POST['items'] ?? [];
if (!is_array($rawItems)) {
    $rawItems = [];
}
if (!$rawItems) {
    $errors[] = 'Your cart is empty. Add at least one product.';
}
if (count($rawItems) > MAX_CART_LINES) {
    $errors[] = 'A single invoice can have at most ' . MAX_CART_LINES . ' different products.';
    $rawItems = array_slice($rawItems, 0, MAX_CART_LINES, true);
}

$items = [];
foreach ($rawItems as $key => $value) {
    $pid = parse_uint((string) $key);
    $qty = is_string($value) ? parse_uint(trim($value)) : null;

    if ($pid === null || $pid < 1) {
        $errors[] = 'The cart contains an invalid product.';
        continue;
    }
    if ($qty === null || $qty < 1 || $qty > MAX_LINE_QTY) {
        $errors[] = 'Invalid quantity for product #' . $pid . ': use a whole number from 1 to ' . MAX_LINE_QTY . '.';
        continue;
    }
    $items[$pid] = $qty;
}

// ---- Payment method (must be one of the allowed keys) ----
$paymentMethod = post_str('payment_method');
if (!isset(payment_methods()[$paymentMethod])) {
    $errors[] = 'Please choose a valid payment method.';
    $paymentMethod = 'cash';
}

// ---- Discount (raw values; validated against the real subtotal later) ----
$discountType  = post_str('discount_type');
$discountValue = $discountType === 'none' ? '' : post_str('discount_value');

// Remember the input so the POS page can restore the cart if anything fails.
$_SESSION['pos_old'] = [
    'customer_id'    => $customerId,
    'discount_type'  => $discountType,
    'discount_value' => $discountValue,
    'payment_method' => $paymentMethod,
    'items'          => $items,
];

// ---- Create the invoice (transaction inside create_invoice) ----
if (!$errors) {
    $result = create_invoice($customerName, $items, $discountType, $discountValue, $paymentMethod);

    if ($result['success']) {
        unset($_SESSION['pos_old']);
        flash('success', 'Invoice ' . $result['invoice_number'] . ' was saved successfully and stock was updated.');
        redirect('invoices/view.php?id=' . (int) $result['invoice_id']);
    }

    $errors = $result['errors'];
}

foreach (array_slice(array_unique($errors), 0, 5) as $msg) {
    flash('danger', $msg);
}
redirect('pos/index.php');
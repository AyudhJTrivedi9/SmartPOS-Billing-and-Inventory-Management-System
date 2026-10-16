<?php
declare(strict_types=1);

/**
 * Invoice creation logic. All money maths is done in integer cents so there
 * are no floating-point rounding surprises.
 */

const MAX_CART_LINES     = 100;
const MAX_LINE_QTY       = 100000;
const MAX_SUBTOTAL_CENTS = 999999999999;   // 9,999,999,999.99 (fits DECIMAL(12,2))

/** Convert a money value (e.g. "599.00" or 599.0) to integer cents. */
function to_cents(float|string $amount): int
{
    return (int) round(((float) $amount) * 100);
}

/** Convert integer cents to a decimal string like "1234.50" (safe for DECIMAL columns). */
function cents_to_decimal(int $cents): string
{
    $sign  = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
}

/**
 * Validate and calculate the discount on the server.
 *
 * - none:       no discount
 * - percentage: 0.01 to 100 (%)
 * - fixed:      more than 0 and not more than the subtotal
 *
 * @return array{error: ?string, type: string, value_cents: int, amount_cents: int}
 */
function calculate_discount(string $type, string $rawValue, int $subtotalCents): array
{
    $fail = static fn(string $msg): array =>
        ['error' => $msg, 'type' => 'none', 'value_cents' => 0, 'amount_cents' => 0];

    if (!in_array($type, ['none', 'percentage', 'fixed'], true)) {
        return $fail('Invalid discount type.');
    }
    if ($type === 'none') {
        return ['error' => null, 'type' => 'none', 'value_cents' => 0, 'amount_cents' => 0];
    }
    if ($rawValue === '') {
        return $fail('Enter a discount value or choose "No discount".');
    }

    $value = parse_money($rawValue);
    if ($value === null) {
        return $fail('Discount must be a positive number with up to 2 decimals.');
    }
    $valueCents = to_cents($value);
    if ($valueCents <= 0) {
        return $fail('Discount must be greater than 0.');
    }

    if ($type === 'percentage') {
        if ($valueCents > 10000) {
            return $fail('A percentage discount cannot exceed 100%.');
        }
        // valueCents = percent * 100, so amount = subtotal * percent / 100 (rounded half up)
        $amount = intdiv($subtotalCents * $valueCents + 5000, 10000);
    } else {
        if ($valueCents > $subtotalCents) {
            return $fail('The fixed discount (' . money(cents_to_decimal($valueCents))
                . ') cannot exceed the subtotal (' . money(cents_to_decimal($subtotalCents)) . ').');
        }
        $amount = $valueCents;
    }

    return [
        'error'        => null,
        'type'         => $type,
        'value_cents'  => $valueCents,
        'amount_cents' => min($amount, $subtotalCents),
    ];
}

/**
 * Next invoice number for today: INV-YYYYMMDD-0001, -0002, ...
 * Must be called inside a transaction (FOR UPDATE locks the last row).
 * The UNIQUE key on invoice_number is the final safety net.
 */
function generate_invoice_number(PDO $pdo): string
{
    $prefix = 'INV-' . date('Ymd') . '-';

    $stmt = $pdo->prepare(
        'SELECT invoice_number FROM invoices
          WHERE invoice_number LIKE :prefix
       ORDER BY invoice_number DESC LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $seq = $last === false ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Plain (non-locking) fetch of products by id, keyed by id.
 * @param  int[] $ids
 * @return array<int, array>
 */
function get_products_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, name, brand_name, sell_price, stock_quantity FROM products WHERE id IN ($in)"
    );
    $stmt->execute($ids);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['id']] = $row;
    }
    return $out;
}

/**
 * Create an invoice atomically.
 *
 * Prices and totals are ALWAYS read from the database here; nothing the
 * browser calculated is trusted. Product rows are locked (FOR UPDATE) so two
 * cashiers cannot oversell the same stock.
 *
 * @param array<int,int> $qtyByProduct product_id => quantity
 * @return array{success: bool, errors: string[], invoice_id?: int, invoice_number?: string}
 */
function create_invoice(string $customerName, array $qtyByProduct, string $discountType, string $discountValue, string $paymentMethod = 'cash'): array
{
    $attempt = 0;
    while (true) {
        $attempt++;
        try {
            return create_invoice_once($customerName, $qtyByProduct, $discountType, $discountValue, $paymentMethod);
        } catch (PDOException $ex) {
            $code = (int) ($ex->errorInfo[1] ?? 0);
            // 1062 duplicate invoice number, 1213 deadlock, 1205 lock wait timeout: retry briefly
            if ($attempt < 3 && in_array($code, [1062, 1213, 1205], true)) {
                usleep(150000);
                continue;
            }
            throw $ex;
        }
    }
}

/** @return array{success: bool, errors: string[], invoice_id?: int, invoice_number?: string} */
function create_invoice_once(string $customerName, array $qtyByProduct, string $discountType, string $discountValue, string $paymentMethod = 'cash'): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $ids = array_keys($qtyByProduct);
        sort($ids); // consistent lock order avoids deadlocks

        // 1. Lock and read the current product rows
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, name, brand_name, cost_price, sell_price, stock_quantity
               FROM products WHERE id IN ($in) ORDER BY id FOR UPDATE"
        );
        $stmt->execute($ids);

        $products = [];
        foreach ($stmt->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }

        // 2. Validate each line and price it from the DB
        $errors        = [];
        $lines         = [];
        $subtotalCents = 0;

        foreach ($ids as $pid) {
            $qty = $qtyByProduct[$pid];

            if (!isset($products[$pid])) {
                $errors[] = 'A product in your cart no longer exists. Remove it and try again.';
                continue;
            }

            $p     = $products[$pid];
            $stock = (int) $p['stock_quantity'];
            $label = $p['name'] . ' (' . $p['brand_name'] . ')';

            if ($stock <= 0) {
                $errors[] = '"' . $label . '" is out of stock.';
                continue;
            }
            if ($qty > $stock) {
                $errors[] = 'Only ' . $stock . ' unit' . ($stock === 1 ? '' : 's')
                          . ' of "' . $label . '" in stock (you requested ' . $qty . ').';
                continue;
            }

            $unitCents      = to_cents($p['sell_price']);
            $lineCents      = $unitCents * $qty;
            $subtotalCents += $lineCents;

            $lines[] = [
                'product_id' => $pid,
                'name'       => (string) $p['name'],
                'brand'      => (string) $p['brand_name'],
                'qty'        => $qty,
                'unit'       => $unitCents,
                'cost'       => to_cents($p['cost_price']),
                'line'       => $lineCents,
            ];
        }

        if (!$errors && $subtotalCents > MAX_SUBTOTAL_CENTS) {
            $errors[] = 'The invoice total is too large. Split it into smaller invoices.';
        }

        // 3. Discount
        $discount = null;
        if (!$errors) {
            $discount = calculate_discount($discountType, $discountValue, $subtotalCents);
            if ($discount['error'] !== null) {
                $errors[] = $discount['error'];
            }
        }

        if ($errors) {
            $pdo->rollBack();
            return ['success' => false, 'errors' => $errors];
        }

        $grandCents = max(0, $subtotalCents - $discount['amount_cents']);

        // 4. Insert invoice
        $number = generate_invoice_number($pdo);

        $ins = $pdo->prepare(
            'INSERT INTO invoices
                (invoice_number, customer_name, payment_method, invoice_date, subtotal,
                 discount_type, discount_value, discount_amount, grand_total)
             VALUES
                (:number, :customer, :pay, :date, :subtotal, :dtype, :dvalue, :damount, :grand)'
        );
        $ins->execute([
            ':number'   => $number,
            ':customer' => $customerName,
            ':pay'      => $paymentMethod,
            ':date'     => date('Y-m-d H:i:s'),
            ':subtotal' => cents_to_decimal($subtotalCents),
            ':dtype'    => $discount['type'],
            ':dvalue'   => cents_to_decimal($discount['value_cents']),
            ':damount'  => cents_to_decimal($discount['amount_cents']),
            ':grand'    => cents_to_decimal($grandCents),
        ]);
        $invoiceId = (int) $pdo->lastInsertId();

        // 5. Insert items (historical snapshots) and reduce stock
        $itemStmt = $pdo->prepare(
            'INSERT INTO invoice_items
                (invoice_id, product_id, product_name, brand_name, quantity, unit_price, cost_price, line_total)
             VALUES
                (:invoice, :product, :pname, :brand, :qty, :unit, :cost, :line)'
        );
        $stockStmt = $pdo->prepare(
            'UPDATE products
                SET stock_quantity = stock_quantity - :qty
              WHERE id = :id AND stock_quantity >= :need'
        );

        foreach ($lines as $l) {
            $itemStmt->execute([
                ':invoice' => $invoiceId,
                ':product' => $l['product_id'],
                ':pname'   => $l['name'],
                ':brand'   => $l['brand'],
                ':qty'     => $l['qty'],
                ':unit'    => cents_to_decimal($l['unit']),
                ':cost'    => cents_to_decimal($l['cost']),
                ':line'    => cents_to_decimal($l['line']),
            ]);

            $stockStmt->execute([
                ':qty'  => $l['qty'],
                ':id'   => $l['product_id'],
                ':need' => $l['qty'],
            ]);
            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException('Stock update failed for product #' . $l['product_id']);
            }
        }

        $pdo->commit();

        return [
            'success'        => true,
            'errors'         => [],
            'invoice_id'     => $invoiceId,
            'invoice_number' => $number,
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex; // logged and shown generically by bootstrap.php (or retried above)
    }
}
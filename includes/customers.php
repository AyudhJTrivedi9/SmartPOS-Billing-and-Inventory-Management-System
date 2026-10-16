<?php
declare(strict_types=1);

/**
 * Get unique customer names from existing invoices.
 *
 * @return array<int, string> ID => customer name
 */
function get_customers(): array
{
    $stmt = db()->query(
        "SELECT DISTINCT TRIM(customer_name) AS name
         FROM invoices
         WHERE customer_name IS NOT NULL
           AND TRIM(customer_name) <> ''
         ORDER BY name ASC"
    );

    $customers = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $customers[$index + 1] = (string) $row['name'];
    }

    return $customers;
}

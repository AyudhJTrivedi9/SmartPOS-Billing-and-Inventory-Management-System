<?php
declare(strict_types=1);

/**
 * Loaded at the top of every page.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/customers.php';

set_exception_handler(function (Throwable $ex): void {
    log_error($ex);

    if (!headers_sent()) {
        http_response_code(500);
    }

    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
        return;
    }

    $hint = '';
    if (APP_ENV === 'development') {
        $hint = '<p class="text-muted small">Developer hint: check <code>storage/logs/app.log</code>.</p>'
              . '<pre class="text-start d-inline-block small bg-white border rounded p-2">' . e($ex->getMessage()) . '</pre>';
        if ($ex instanceof PDOException) {
            $hint .= '<p class="small">Start MySQL in XAMPP and import <code>database.sql</code> in phpMyAdmin.</p>';
        }
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Server Error</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<link href="' . e(BASE_URL) . '/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"></head>'
        . '<body class="bg-light"><div class="container py-5 text-center">'
        . '<h1 class="display-6">Something went wrong</h1>'
        . '<p class="lead">We could not complete your request. Please try again.</p>'
        . $hint
        . '<a class="btn btn-primary" href="' . e(BASE_URL) . '/index.php">Back to Dashboard</a>'
        . '</div></body></html>';
});
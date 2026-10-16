<?php
declare(strict_types=1);

/* mbstring is optional: tiny fallbacks so the app never crashes without it. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int { return preg_match_all('/./su', $s) ?: 0; }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null, ?string $enc = null): string
    {
        preg_match_all('/./su', $s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}

/* ------------------------------------------------------------------
 * Output / URL helpers
 * ------------------------------------------------------------------ */

/** Escape a value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a number as currency, e.g. ₹ 1,249.00 */
function money(float|int|string $amount): string
{
    return CURRENCY . ' ' . number_format((float) $amount, 2);
}

/** Build an application URL from a path relative to the project root. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect to an app path and stop the script. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from POST (empty string if missing or not a string). */
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Trimmed string from GET (empty string if missing or not a string). */
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/* ------------------------------------------------------------------
 * Logging
 * ------------------------------------------------------------------ */

function log_error(Throwable $ex): void
{
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        get_class($ex),
        $ex->getMessage(),
        $ex->getFile(),
        $ex->getLine()
    );
    @error_log($line, 3, LOG_FILE);
}

/* ------------------------------------------------------------------
 * Flash messages (stored in session, shown once)
 * ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type:string, message:string}> */
function pull_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/* ------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the CSRF token on a POST request. The token may come from the form
 * field or from the X-CSRF-Token header (AJAX). Stops with HTTP 419 on failure.
 */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valid = is_string($sent)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);

    if (!$valid) {
        http_response_code(419);
        $json = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Security token expired. Please reload the page.']);
        } else {
            echo 'Security token invalid or expired. Please go back, reload the page and try again.';
        }
        exit;
    }
}

/* ------------------------------------------------------------------
 * One-time form tokens (prevent duplicate submissions, e.g. invoice save)
 * ------------------------------------------------------------------ */

function issue_form_token(string $name): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['form_tokens'][$name] = $token;
    return $token;
}

/** Returns true once; the token is destroyed on use so a repeat submit fails. */
function consume_form_token(string $name, string $sent): bool
{
    $stored = $_SESSION['form_tokens'][$name] ?? '';
    unset($_SESSION['form_tokens'][$name]);
    return $stored !== '' && hash_equals($stored, $sent);
}

/* ------------------------------------------------------------------
 * Validation helpers
 * ------------------------------------------------------------------ */

/**
 * Parse a non-negative money value with up to 2 decimals (max 99,999,999.99).
 * Returns null for anything invalid (negative, letters, 3+ decimals...).
 */
function parse_money(string $value): ?float
{
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value)) {
        return null;
    }
    return round((float) $value, 2);
}

/** Parse a non-negative whole number (max 9 digits). Returns null if invalid. */
function parse_uint(string $value): ?int
{
    if (!preg_match('/^\d{1,9}$/', $value)) {
        return null;
    }
    return (int) $value;
}

/** Valid calendar date in Y-m-d format? */
function is_valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

/** Escape LIKE wildcards so a user typing % or _ searches literally. */
function like_escape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

/* ------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------ */

/** @return array{page:int, pages:int, perPage:int, offset:int, total:int} */
function paginate(int $total, int $page, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);

    return [
        'page'    => $page,
        'pages'   => $pages,
        'perPage' => $perPage,
        'offset'  => ($page - 1) * $perPage,
        'total'   => $total,
    ];
}

/**
 * Render Bootstrap pagination links.
 * @param array $p     result of paginate()
 * @param array $query current filters (without "page")
 * @param string $path page path, e.g. 'products/index.php'
 */
function render_pagination(array $p, array $query, string $path): string
{
    if ($p['pages'] <= 1) {
        return '';
    }

    $link = function (int $page) use ($query, $path): string {
        return e(url($path) . '?' . http_build_query(array_merge($query, ['page' => $page])));
    };

    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0 flex-wrap">';

    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . $link(max(1, $p['page'] - 1)) . '">&laquo; Prev</a></li>';

    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '">'
               . '<a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
    }
    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($p['pages']) . '">' . $p['pages'] . '</a></li>';
    }

    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . $link(min($p['pages'], $p['page'] + 1)) . '">Next &raquo;</a></li>';

    return $html . '</ul></nav>';
}

/** Allowed payment methods: key => [label, bootstrap icon]. */
function payment_methods(): array
{
    return [
        'cash' => ['Cash', 'bi-cash-coin'],
        'upi'  => ['UPI',  'bi-phone'],
        'card' => ['Card', 'bi-credit-card'],
    ];
}

/** Small badge showing how an invoice was paid. */
function payment_badge(string $method): string
{
    $m = payment_methods()[$method] ?? ['Cash', 'bi-cash-coin'];
    return '<span class="badge badge-soft"><i class="bi ' . e($m[1]) . ' me-1"></i>' . e($m[0]) . '</span>';
}

/** Badge HTML describing stock level. */
function stock_badge(int $qty): string
{
    if ($qty <= 0) {
        return '<span class="badge text-bg-danger">Out of stock</span>';
    }
    if ($qty <= LOW_STOCK_THRESHOLD) {
        return '<span class="badge text-bg-warning">Low: ' . $qty . '</span>';
    }
    return '<span class="badge text-bg-success">' . $qty . ' in stock</span>';
}
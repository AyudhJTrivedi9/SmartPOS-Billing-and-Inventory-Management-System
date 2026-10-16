<?php
declare(strict_types=1);

/**
 * Shared layout header.
 * Pages set before including:
 *   $pageTitle   (string)
 *   $activePage  ('dashboard'|'products'|'pos'|'invoices'|'reports')
 *   $breadcrumbs (array of label => url|null)
 */
$pageTitle   = $pageTitle   ?? APP_NAME;
$activePage  = $activePage  ?? '';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <script>
        /* Apply the saved theme before the page paints (no flash). */
        (function () {
            try {
                var t = localStorage.getItem('smartpos-theme');
                if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-bs-theme', t); }
            } catch (e) { /* storage blocked: use default light theme */ }
        })();
    </script>
    <link href="<?= e(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/vendor/icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=4" rel="stylesheet">
    <link href="<?= e(url('assets/css/print.css')) ?>" rel="stylesheet" media="print">
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="main-wrapper">

    <header class="topbar no-print">
        <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebarToggle"
                aria-label="Toggle navigation menu">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h1 class="topbar-title h5 mb-0"><?= e($pageTitle) ?></h1>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="<?= e(url('pos/index.php')) ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-cart-plus me-1"></i><span class="d-none d-sm-inline">New Sale</span>
            </a>
            <span class="text-muted small d-none d-md-inline">
                <i class="bi bi-calendar3 me-1"></i><?= e(date('D, d M Y')) ?>
            </span>
            <button type="button" class="btn theme-toggle" id="themeToggle" aria-label="Switch between light and dark theme" title="Light / dark theme">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>
            <span class="avatar" title="Administrator"><i class="bi bi-person-fill"></i></span>
        </div>
    </header>

    <main class="content-area">

        <?php if ($breadcrumbs): ?>
            <nav aria-label="breadcrumb" class="breadcrumb-nav no-print">
                <ol class="breadcrumb">
                    <?php
                    $last = array_key_last($breadcrumbs);
                    foreach ($breadcrumbs as $label => $link):
                        if ($label === $last || $link === null): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= e(url($link)) ?>"><?= e($label) ?></a></li>
                        <?php endif;
                    endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <?php foreach (pull_flashes() as $f):
            $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
            $icon = ['success' => 'check-circle-fill', 'danger' => 'exclamation-triangle-fill',
                     'warning' => 'exclamation-circle-fill', 'info' => 'info-circle-fill'][$type]; ?>
            <div class="alert alert-<?= e($type) ?> alert-dismissible fade show auto-dismiss no-print" role="alert">
                <i class="bi bi-<?= e($icon) ?> me-2"></i><?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
<?php
declare(strict_types=1);

$navItems = [
    'dashboard' => ['Dashboard',        'index.php',           'bi-speedometer2'],
    'products'  => ['Products',         'products/index.php',  'bi-box-seam'],
    'pos'       => ['POS Billing',      'pos/index.php',       'bi-cart-check'],
    'invoices'  => ['Invoices',         'invoices/index.php',  'bi-receipt'],
    'reports'   => ['Sales Reports',    'reports/index.php',   'bi-graph-up-arrow'],
];
?>
<aside class="sidebar no-print" id="sidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="<?= e(url('index.php')) ?>">
        <span class="brand-logo"><i class="bi bi-lightning-charge-fill"></i></span>
        <span><?= e(APP_NAME) ?></span>
    </a>

    <div class="sidebar-section">Menu</div>
    <nav class="sidebar-nav">
        <ul class="nav flex-column gap-1">
            <?php foreach ($navItems as $key => [$label, $path, $icon]): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>"
                       href="<?= e(url($path)) ?>"
                       <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= e($icon) ?>"></i>
                        <span><?= e($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>


    <div class="sidebar-footer">
        <small>SmartPOS v3.0 &middot; Billing Suite</small>
    </div>
</aside>
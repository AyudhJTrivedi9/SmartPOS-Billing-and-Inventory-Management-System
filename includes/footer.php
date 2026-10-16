<?php
declare(strict_types=1);

/**
 * Closes the layout opened in header.php.
 * Pages may set $extraScripts = ['assets/js/pos.js'] before including this file.
 */
$extraScripts = $extraScripts ?? [];
?>
    </main>

    <footer class="app-footer no-print">
        &copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &middot; Product &amp; Billing Management
    </footer>
</div><!-- /.main-wrapper -->

<!-- Generic confirmation dialog (used by forms with data-confirm="...") -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Please confirm
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmMessage">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmOk">Yes, continue</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=3"></script>
<?php foreach ($extraScripts as $script): ?>
    <script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
/* SmartPOS global behaviour: sidebar toggle, confirm dialogs, loading states. */
(function () {
    'use strict';

    // ---------- Mobile sidebar ----------
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('sidebarToggle');

    function setSidebar(open) {
        if (!sidebar || !backdrop) { return; }
        sidebar.classList.toggle('show', open);
        backdrop.classList.toggle('show', open);
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('show'));
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () { setSidebar(false); });
    }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { setSidebar(false); }
    });

    // ---------- Confirmation dialog for forms with data-confirm ----------
    var modalEl = document.getElementById('confirmModal');
    var modal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;
    var pendingForm = null;

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        if (form.dataset.confirm && !form.dataset.confirmed && modal) {
            ev.preventDefault();
            pendingForm = form;
            document.getElementById('confirmMessage').textContent = form.dataset.confirm;
            modal.show();
            return;
        }

        // Loading state: disable submit buttons after the browser has built the form data.
        if (form.dataset.loading !== undefined || form.dataset.confirmed) {
            setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    btn.disabled = true;
                    btn.classList.add('btn-loading');
                    var label = btn.dataset.loadingText || 'Please wait...';
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + label;
                });
            }, 0);
        }
    });

    var okBtn = document.getElementById('confirmOk');
    if (okBtn) {
        okBtn.addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                modal.hide();
                pendingForm.submit();
                pendingForm = null;
            }
        });
    }

    // ---------- Auto-dismiss success/info alerts ----------
    setTimeout(function () {
        document.querySelectorAll('.alert.auto-dismiss.alert-success, .alert.auto-dismiss.alert-info')
            .forEach(function (el) {
                if (window.bootstrap) { bootstrap.Alert.getOrCreateInstance(el).close(); } else { el.remove(); }
            });
    }, 6000);

    // ---------- Light / dark theme toggle (remembered in this browser) ----------
    var themeBtn = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');
    function syncThemeIcon() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (themeIcon) { themeIcon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'; }
    }
    syncThemeIcon();
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('smartpos-theme', next); } catch (e) { /* ignore */ }
            syncThemeIcon();
        });
    }

    // ---------- Shared helper for AJAX pages ----------
    window.SmartPOS = {
        csrfToken: function () {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },
        formatMoney: function (n) {
            return '\u20B9 ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
})();
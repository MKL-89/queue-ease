/* =====================================================================
   QueueEase - Shared JavaScript (all dashboard and public pages)
   File: assets/js/app.js
   ===================================================================== */
(function () {
    'use strict';

    // ---- Mobile sidebar toggle ----------------------------------------
    var toggle = document.getElementById('menuToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    }

    // ---- Dismissible alerts (auto-hide success messages) ----------------
    document.querySelectorAll('[data-dismissible]').forEach(function (alert) {
        var close = alert.querySelector('.alert-close');
        if (close) {
            close.addEventListener('click', function () { alert.remove(); });
        }
        if (alert.classList.contains('alert-success')) {
            setTimeout(function () { alert.style.transition = 'opacity .4s'; alert.style.opacity = '0'; }, 5000);
            setTimeout(function () { alert.remove(); }, 5500);
        }
    });

    // ---- Confirmation for dangerous actions ---------------------------
    // <form data-confirm="Delete this service?"> or <button data-confirm="...">
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var submitter = event.submitter;
        var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // ---- Instant table search ----------------------------------------
    // <input data-table-search="#tableId">
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        var table = document.querySelector(input.getAttribute('data-table-search'));
        if (!table) { return; }
        input.addEventListener('input', function () {
            var term = input.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                if (row.querySelector('.table-empty')) { return; }
                row.style.display = row.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
            });
        });
    });

    // ---- Live clock in the top bar -----------------------------------
    var clock = document.querySelector('[data-live-clock]');
    if (clock) {
        var updateClock = function () {
            var now = new Date();
            clock.textContent = now.toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
                + ', ' + now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        };
        updateClock();
        setInterval(updateClock, 30000);
    }

    // ---- Greeting ("Good morning, Grace · ") ----------------------------
    var greeting = document.querySelector('[data-greeting]');
    if (greeting) {
        var hour = new Date().getHours();
        var part = hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening');
        var name = greeting.getAttribute('data-name');
        greeting.textContent = part + (name ? ', ' + name : '') + ' · ';
    }

    // ---- Light / dark theme toggle (choice remembered in this browser) ---
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('qe-theme', next); } catch (e) { /* storage blocked */ }
            window.dispatchEvent(new CustomEvent('themechange', { detail: next }));
        });
    });

    // ---- Animated counters on stat cards --------------------------------
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.stat-value').forEach(function (el) {
        var target = el.textContent.trim();
        if (reduceMotion || !/^\d+$/.test(target) || parseInt(target, 10) === 0) { return; }
        var end = parseInt(target, 10);
        var start = null;
        var duration = 900;
        el.textContent = '0';
        function step(ts) {
            if (!start) { start = ts; }
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(end * eased);
            if (progress < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    });

    // ---- Toast notifications: showToast('Saved', 'success') -------------
    window.showToast = function (message, type) {
        var stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'info');
        toast.textContent = message;
        stack.appendChild(toast);
        setTimeout(function () { toast.classList.add('hide'); }, 3800);
        setTimeout(function () { toast.remove(); }, 4300);
    };

    // ---- Helper used by other scripts: POST with CSRF token -----------
    window.postJSON = function (url, data) {
        var body = new FormData();
        Object.keys(data || {}).forEach(function (key) { body.append(key, data[key]); });
        body.append('csrf_token', window.APP.csrf);
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Unexpected server response (' + response.status + ').' };
            });
        });
    };

    // Escape text before inserting it into HTML (XSS protection on the client side)
    window.escapeHtml = function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
})();

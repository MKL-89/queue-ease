/* =====================================================================
   QueueEase - Staff counter console
   File: assets/js/staff.js
   Sends queue actions to queue/api/staff_action.php with AJAX and keeps
   the console up to date by polling every 5 seconds.
   ===================================================================== */
(function () {
    'use strict';

    var root = document.getElementById('console');
    if (!root) { return; }

    var counterId = root.getAttribute('data-counter');
    var endpoint = root.getAttribute('data-endpoint');
    var busy = false;
    var buttons = root.querySelectorAll('[data-action]');
    var labels = { called: 'Called - waiting for customer', serving: 'Now serving' };

    function $(id) { return document.getElementById(id); }

    function showMessage(text, type) {
        var box = $('consoleMessage');
        if (!text) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="alert alert-' + type + '"><span>' + escapeHtml(text) + '</span></div>';
        clearTimeout(showMessage.timer);
        showMessage.timer = setTimeout(function () { box.innerHTML = ''; }, 5000);
    }

    function setButtons(status) {
        // Which buttons make sense for the current ticket status
        var allowed = {
            none: ['call'],
            called: ['recall', 'skip', 'start'],
            serving: ['complete']
        }[status || 'none'] || [];
        buttons.forEach(function (btn) {
            btn.disabled = busy || allowed.indexOf(btn.getAttribute('data-action')) === -1;
        });
    }

    function render(state) {
        root.querySelectorAll('[data-s]').forEach(function (el) {
            el.textContent = state[el.getAttribute('data-s')];
        });

        var cur = state.current;
        if (cur) {
            $('currentLabel').textContent = labels[cur.status] || 'Current ticket';
            $('currentNumber').textContent = cur.ticket_number;
            var meta = escapeHtml(cur.customer) + (cur.phone ? ' &middot; ' + escapeHtml(cur.phone) : '')
                + '<br>Called at ' + escapeHtml(cur.called_at)
                + (cur.started_at ? ' &middot; Started ' + escapeHtml(cur.started_at) : '')
                + (cur.recall_count ? ' &middot; Recalled ' + cur.recall_count + 'x' : '');
            $('currentMeta').innerHTML = meta;
        } else {
            $('currentLabel').textContent = 'Current ticket';
            $('currentNumber').textContent = '----';
            $('currentMeta').textContent = state.waiting_count > 0
                ? 'Press "Call Next" to call the next customer.'
                : 'No customers waiting.';
        }
        setButtons(cur ? cur.status : 'none');

        var list = $('waitingList');
        if (!state.waiting.length) {
            list.innerHTML = '<li class="muted">Nobody is waiting.</li>';
        } else {
            list.innerHTML = state.waiting.map(function (w) {
                return '<li><span><span class="ticket-no">' + escapeHtml(w.ticket_number) + '</span><br><small class="muted">'
                    + escapeHtml(w.customer) + '</small></span><span class="small muted text-right">' + escapeHtml(w.since)
                    + '<br>waited ' + escapeHtml(w.waited) + '</span></li>';
            }).join('');
        }
    }

    function load() {
        if (busy) { return; }
        fetch(endpoint + '?counter_id=' + encodeURIComponent(counterId), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (r.status === 401) { window.location.reload(); }
                return r.json();
            })
            .then(function (res) { if (res.state) { render(res.state); } })
            .catch(function () { /* try again on the next poll */ });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var confirmText = btn.getAttribute('data-confirm');
            if (confirmText && !window.confirm(confirmText)) { return; }
            busy = true;
            setButtons('busy');
            postJSON(endpoint, { counter_id: counterId, action: btn.getAttribute('data-action') })
                .then(function (res) {
                    busy = false;
                    showMessage(res.message, res.success ? 'success' : 'error');
                    if (window.showToast) { window.showToast(res.message, res.success ? 'success' : 'error'); }
                    if (res.state) { render(res.state); } else { load(); }
                })
                .catch(function () {
                    busy = false;
                    showMessage('Network error. Please try again.', 'error');
                    load();
                });
        });
    });

    // ---- Keyboard shortcuts: N call next, R recall, S start, C complete, K skip ----
    var keys = { n: 'call', r: 'recall', s: 'start', c: 'complete', k: 'skip' };
    document.addEventListener('keydown', function (event) {
        var tag = (event.target.tagName || '').toLowerCase();
        if (event.ctrlKey || event.metaKey || event.altKey || tag === 'input' || tag === 'textarea' || tag === 'select') { return; }
        var action = keys[event.key.toLowerCase()];
        if (!action) { return; }
        var btn = root.querySelector('[data-action="' + action + '"]');
        if (btn && !btn.disabled) {
            event.preventDefault();
            btn.click();
        }
    });

    load();
    setInterval(load, 5000);
})();

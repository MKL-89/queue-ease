/* =====================================================================
   QueueEase - Live ticket tracking (AJAX polling)
   File: assets/js/ticket.js
   Updates the ticket card (#liveTicket) every few seconds and alerts the
   customer (sound + browser notification + voice) when the ticket is called.
   ===================================================================== */
(function () {
    'use strict';

    var card = document.getElementById('liveTicket');
    if (!card) { return; }

    // ---- QR code that opens this ticket's tracking page (qrcode-generator, MIT) ----
    var qrBox = card.querySelector('[data-qr]');
    if (qrBox && typeof qrcode === 'function') {
        var qr = qrcode(0, 'M');
        qr.addData(qrBox.getAttribute('data-qr'));
        qr.make();
        qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    } else if (qrBox) {
        qrBox.parentNode.remove();
    }

    var reference = card.getAttribute('data-reference');
    var endpoint = card.getAttribute('data-endpoint');
    var interval = (parseInt(card.getAttribute('data-interval'), 10) || 8) * 1000;
    var lastStatus = card.getAttribute('data-status');
    var lastRecall = null;
    var alertsOn = false;
    var timer = null;
    var finalStatuses = ['served', 'skipped', 'cancelled'];

    var labels = { waiting: 'Waiting', called: 'Called', serving: 'Serving', served: 'Served', skipped: 'Skipped', cancelled: 'Cancelled' };

    function field(name) { return card.querySelector('[data-field="' + name + '"]'); }

    function formatMinutes(min) {
        if (min <= 0) { return '0 min'; }
        if (min < 60) { return min + ' min'; }
        return Math.floor(min / 60) + ' h ' + (min % 60) + ' min';
    }

    // Short "ding" generated with the Web Audio API (no sound file needed)
    function beep() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.35].forEach(function (offset) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.frequency.value = 880;
                osc.connect(gain);
                gain.connect(ctx.destination);
                gain.gain.setValueAtTime(0.25, ctx.currentTime + offset);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.3);
                osc.start(ctx.currentTime + offset);
                osc.stop(ctx.currentTime + offset + 0.3);
            });
        } catch (e) { /* audio not supported */ }
    }

    function notifyCalled(data) {
        var text = 'Ticket ' + data.ticket_number + ' - please go to ' + (data.counter_name || 'the counter');
        if (alertsOn) {
            beep();
            if ('speechSynthesis' in window) {
                var msg = new SpeechSynthesisUtterance('Ticket ' + data.ticket_number.split('').join(' ') + ', please proceed to ' + (data.counter_name || 'the counter'));
                msg.rate = 0.9;
                window.speechSynthesis.speak(msg);
            }
        }
        if ('Notification' in window && Notification.permission === 'granted') {
            try { new Notification('It is your turn!', { body: text }); } catch (e) { /* ignore */ }
        }
        if (navigator.vibrate) { navigator.vibrate([300, 150, 300]); }
        document.title = '▶ ' + data.ticket_number + ' called!';
    }

    function render(data) {
        field('message').textContent = data.message;
        field('message').className = 'ticket-status-msg st-' + data.status;
        var waiting = data.status === 'waiting';
        field('position').textContent = waiting ? data.position : '-';
        field('people_ahead').textContent = waiting ? data.people_ahead : '-';
        field('estimated_wait').textContent = waiting ? formatMinutes(data.estimated_wait) : '-';
        field('counter_name').textContent = data.counter_name || 'To be assigned';
        field('called_at').textContent = data.called_at || '-';
        field('status_badge').innerHTML = '<span class="badge badge-' + escapeHtml(data.status) + '">' + escapeHtml(labels[data.status] || data.status) + '</span>';

        if (data.status === 'called' && (lastStatus !== 'called' || (lastRecall !== null && data.recall_count > lastRecall))) {
            notifyCalled(data);
        }
        if (data.status !== 'waiting') {
            var cancelForm = document.querySelector('[data-cancel-form]');
            if (cancelForm) { cancelForm.remove(); }
        }
        lastStatus = data.status;
        lastRecall = data.recall_count;

        if (finalStatuses.indexOf(data.status) > -1) {
            clearInterval(timer);
            // Reload once so the page shows the final state (and the rating form when served)
            setTimeout(function () { window.location.reload(); }, 2500);
            var note = document.querySelector('[data-live-note]');
            if (note) { note.textContent = 'This ticket is closed.'; }
        }
    }

    function poll() {
        fetch(endpoint + '?ref=' + encodeURIComponent(reference), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res.success) { render(res.ticket); } })
            .catch(function () { /* network problem: try again next time */ });
    }

    var alertBtn = document.getElementById('enableAlerts');
    if (alertBtn) {
        alertBtn.addEventListener('click', function () {
            alertsOn = true;
            beep();
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            alertBtn.textContent = 'Alerts enabled';
            alertBtn.disabled = true;
        });
    }

    if (finalStatuses.indexOf(lastStatus) === -1) {
        timer = setInterval(poll, interval);
        poll();
    }
})();

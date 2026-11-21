/* =====================================================================
   QueueEase - Live display (AJAX polling + voice announcements)
   File: assets/js/display.js
   ===================================================================== */
(function () {
    'use strict';

    var root = document.getElementById('display');
    var endpoint = root.getAttribute('data-endpoint');
    var interval = (parseInt(root.getAttribute('data-interval'), 10) || 5) * 1000;
    var seenKeys = null;           // call keys already shown (null = first load)
    var voiceOn = false;
    var speechQueue = [];
    var lastTicker = '';

    function $(id) { return document.getElementById(id); }
    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---- Clock ------------------------------------------------------------
    function tick() {
        var now = new Date();
        $('clockTime').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        $('clockDate').textContent = now.toLocaleDateString([], { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    }
    tick();
    setInterval(tick, 1000);

    // ---- Voice announcements (SpeechSynthesis API) -------------------------
    // "MED004" is read as "M E D zero zero four" so it is clear to listeners.
    function spokenTicket(number) {
        var words = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        return number.split('').map(function (ch) {
            return /[0-9]/.test(ch) ? words[parseInt(ch, 10)] : ch;
        }).join(' ');
    }

    function chime(done) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            [659, 523].forEach(function (freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                osc.connect(gain);
                gain.connect(ctx.destination);
                var t = ctx.currentTime + i * 0.4;
                gain.gain.setValueAtTime(0.3, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
                osc.start(t);
                osc.stop(t + 0.6);
            });
            setTimeout(done, 900);
        } catch (e) { done(); }
    }

    function speakNext() {
        if (!voiceOn || !speechQueue.length || window.speechSynthesis.speaking) { return; }
        var item = speechQueue.shift();
        chime(function () {
            var text = 'Ticket number ' + spokenTicket(item.ticket_number) + ', please proceed to ' + item.counter + '.';
            var msg = new SpeechSynthesisUtterance(text);
            msg.rate = 0.9;
            msg.lang = 'en-GB';
            msg.onend = function () { setTimeout(speakNext, 600); };
            window.speechSynthesis.speak(msg);
        });
    }

    var voiceBtn = $('voiceToggle');
    if (!('speechSynthesis' in window)) {
        voiceBtn.style.display = 'none';
    }
    voiceBtn.addEventListener('click', function () {
        // Browsers only allow speech after a user click, so voice starts switched off
        voiceOn = !voiceOn;
        voiceBtn.classList.toggle('on', voiceOn);
        voiceBtn.querySelector('span').textContent = voiceOn ? 'Voice on' : 'Voice off';
        if (voiceOn) {
            var test = new SpeechSynthesisUtterance('Voice announcements enabled');
            test.rate = 0.95;
            window.speechSynthesis.speak(test);
        } else {
            window.speechSynthesis.cancel();
            speechQueue = [];
        }
    });

    $('fullscreenBtn').addEventListener('click', function () {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen && document.exitFullscreen();
        }
    });

    // ---- Rendering ----------------------------------------------------------
    function render(data) {
        // Now calling (most recent call)
        var latest = data.recent.length ? data.recent[0] : null;
        if (latest && (latest.status === 'called' || latest.status === 'serving')) {
            $('nowNumber').textContent = latest.ticket_number;
            $('nowCounter').textContent = 'Proceed to ' + latest.counter;
            $('nowService').textContent = latest.service;
        } else {
            $('nowNumber').textContent = '----';
            $('nowCounter').textContent = 'Please wait for your number';
            $('nowService').textContent = '';
        }

        // Counters
        $('counterGrid').innerHTML = data.counters.map(function (c) {
            var cls = c.status ? ' ' + c.status : '';
            var num = c.ticket_number
                ? '<div class="num">' + esc(c.ticket_number) + '</div><div class="st">' + (c.status === 'serving' ? 'Serving' : 'Calling') + '</div>'
                : '<div class="num idle">Available</div>';
            return '<div class="d-counter' + cls + '"><div class="name">' + esc(c.name) + '</div><div class="svc">' + esc(c.service) + '</div>' + num + '</div>';
        }).join('') || '<p class="d-empty">No counters open.</p>';

        // Recently called
        $('recentList').innerHTML = data.recent.map(function (r) {
            return '<li><span class="num">' + esc(r.ticket_number) + '</span><span><span class="ctr">' + esc(r.counter)
                + '</span><span class="time">' + esc(r.time) + '</span></span></li>';
        }).join('') || '<li class="d-empty">No tickets called yet today.</li>';

        // Waiting per service
        $('waitingGroups').innerHTML = data.waiting.map(function (w) {
            var chips = w.tickets.length
                ? w.tickets.map(function (t) { return '<span class="d-chip">' + esc(t) + '</span>'; }).join('')
                  + (w.count > w.tickets.length ? '<span class="d-chip">+' + (w.count - w.tickets.length) + '</span>' : '')
                : '<span class="d-empty">No one waiting</span>';
            return '<div class="d-wgroup"><div class="d-wgroup-head"><span>' + esc(w.service) + '</span><span>' + w.count + '</span></div><div class="d-chips">' + chips + '</div></div>';
        }).join('');

        // Announcements ticker
        var tickerText = data.announcements.map(function (a) { return a.title + ': ' + a.message; }).join('     •     ');
        if (tickerText && tickerText !== lastTicker) {
            $('tickerText').textContent = tickerText;
            lastTicker = tickerText;
        }

        // Detect new calls and recalls -> flash + voice
        var keys = data.recent.map(function (r) { return r.key; });
        if (seenKeys !== null) {
            var fresh = data.recent.filter(function (r) { return r.announce && seenKeys.indexOf(r.key) === -1; });
            if (fresh.length) {
                var now = document.querySelector('.d-now');
                now.classList.remove('flash');
                void now.offsetWidth;          // restart the CSS animation
                now.classList.add('flash');
                fresh.reverse().forEach(function (r) { speechQueue.push(r); });
                speakNext();
            }
        }
        seenKeys = keys;
    }

    function poll() {
        fetch(endpoint, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    render(data);
                    $('connStatus').className = 'd-status';
                    $('connStatus').lastChild.textContent = 'Live';
                }
            })
            .catch(function () {
                $('connStatus').className = 'd-status offline';
                $('connStatus').lastChild.textContent = 'Reconnecting...';
            });
    }

    poll();
    setInterval(poll, interval);
})();

(function () {
    'use strict';

    var CSRF = window.AU_CSRF || '';
    var TICK_MS = 60;
    var ROLL_DURATION_MS = 2200;
    var PAYOUT_RATIO = 1.7;

    var diceShell = document.getElementById('dice-shell');
    var diceValue = document.getElementById('dice-value');
    var diceStatus = document.getElementById('dice-status');
    var zoneUnder = document.getElementById('zone-under');
    var zoneAbove = document.getElementById('zone-above');
    var balanceEl = document.getElementById('au-balance');
    var historyStrip = document.getElementById('au-history-strip');
    var payoutPreview = document.getElementById('au-payout-preview');
    var entryChipsWrap = document.getElementById('au-entry-chips');
    var streakBanner = document.getElementById('au-streak-banner');
    var tbStreak = document.getElementById('au-tb-streak');
    var bbWinrate = document.getElementById('au-bb-winrate');
    var bbProfit = document.getElementById('au-bb-profit');

    var insightEg = document.getElementById('insight-endgame');
    var insightEgConfetti = document.getElementById('insight-endgame-confetti');
    var egResult = document.getElementById('insight-eg-result');
    var egSub = document.getElementById('insight-eg-sub');
    var egd1 = document.getElementById('insight-egd1');
    var egd2 = document.getElementById('insight-egd2');
    var egStats = document.getElementById('insight-eg-stats');
    var egRw = document.getElementById('insight-eg-rw');
    var egFlavor = document.getElementById('insight-eg-flavor');
    var egBtns = document.getElementById('insight-eg-btns');
    var egtl = document.getElementById('insight-egtl');
    var egbl = document.getElementById('insight-egbl');
    var btnContinue = document.getElementById('insight-eg-continue');
    var btnArena = document.getElementById('insight-eg-arena');

    if (!diceShell || !diceValue || !zoneUnder || !zoneAbove || !balanceEl) {
        return;
    }

    var rolling = false;
    var tickTimer = null;
    var winStreak = 0;

    var INSIGHT_EG = {
        win: {
            result: 'VICTORY',
            sub: 'YOUR READ ON THE NEXUS WAS TRUE',
            color: '#00f0ff',
            bgClass: 'win'
        },
        lose: {
            result: 'DEFEAT',
            sub: 'THE DICE SAW OTHERWISE',
            color: '#d400ff',
            bgClass: 'lose'
        }
    };

    var FLAVOR_WIN = [
        'Probability bent in your favor — this time.',
        'The cold blue and hot magenta zones align with your call.',
        'One roll, one truth. Yours held.',
        'The holographic die remembers your name.'
    ];
    var FLAVOR_LOSE = [
        'Randomness is a harsh teacher. Try again.',
        'The nexus flickered — not in your favor today.',
        'Variance happens. Sharpen your read.',
        'The void owes you nothing. Roll once more.'
    ];

    function getActiveChip() {
        if (!entryChipsWrap) return null;
        return entryChipsWrap.querySelector('.entry-chip.active');
    }

    function getEntry() {
        var chip = getActiveChip();
        if (!chip) return 200;
        return parseInt(chip.getAttribute('data-value'), 10) || 200;
    }

    function getPayout(entry) {
        return Math.floor(entry * PAYOUT_RATIO);
    }

    function updatePayoutPreview() {
        if (!payoutPreview) return;
        var entry = getEntry();
        payoutPreview.textContent = getPayout(entry).toLocaleString() + ' KP';
    }

    if (entryChipsWrap) {
        entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (c) {
                    c.classList.remove('active');
                });
                chip.classList.add('active');
                updatePayoutPreview();
            });
        });
        updatePayoutPreview();
    }

    function setZonesEnabled(enabled) {
        [zoneUnder, zoneAbove].forEach(function (z) {
            if (!z) return;
            z.classList.toggle('is-disabled', !enabled);
            z.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        });
    }

    function clearInsightConfetti() {
        if (insightEgConfetti) insightEgConfetti.innerHTML = '';
    }

    function fillInsightConfetti(isWin) {
        if (!insightEgConfetti) return;
        insightEgConfetti.innerHTML = '';
        if (isWin) {
            var colors = ['#00f0ff', '#d400ff', '#ffc820', '#00ff88', '#ff2244', '#ffffff'];
            for (var i = 0; i < 60; i++) {
                var p = document.createElement('div');
                p.className = 'confetti-p';
                p.style.cssText =
                    'left:' +
                    Math.random() * 100 +
                    '%;top:-10px;width:' +
                    (4 + Math.random() * 6) +
                    'px;height:' +
                    (6 + Math.random() * 10) +
                    'px;background:' +
                    colors[~~(Math.random() * colors.length)] +
                    ';border-radius:' +
                    (Math.random() > 0.5 ? '50%' : '1px') +
                    ';--dur:' +
                    (2 + Math.random() * 3) +
                    's;--delay:' +
                    Math.random() * 1.5 +
                    's;--rot:' +
                    (360 + Math.random() * 720) +
                    'deg';
                insightEgConfetti.appendChild(p);
            }
        } else {
            for (var j = 0; j < 30; j++) {
                var q = document.createElement('div');
                q.className = 'confetti-p';
                q.style.cssText =
                    'left:' +
                    Math.random() * 100 +
                    '%;top:-10px;width:' +
                    (2 + Math.random() * 3) +
                    'px;height:' +
                    (2 + Math.random() * 3) +
                    'px;background:rgba(' +
                    (150 + ~~(Math.random() * 60)) +
                    ',' +
                    (50 + ~~(Math.random() * 40)) +
                    ',' +
                    (80 + ~~(Math.random() * 40)) +
                    ',0.6);border-radius:50%;--dur:' +
                    (4 + Math.random() * 4) +
                    's;--delay:' +
                    Math.random() * 2 +
                    's;--rot:' +
                    Math.random() * 360 +
                    'deg';
                insightEgConfetti.appendChild(q);
            }
        }
    }

    function hideInsightEndgame() {
        if (!insightEg) return;
        insightEg.classList.remove('show', 'win', 'lose', 'insight-eg-error');
        insightEg.setAttribute('aria-hidden', 'true');
        clearInsightConfetti();
        [egtl, egResult, egSub, egd1, egd2, egFlavor, egBtns, egbl].forEach(function (el) {
            if (el) el.classList.remove('anim');
        });
    }

    function showInsightError(message) {
        if (!insightEg) return;
        hideInsightEndgame();
        insightEg.classList.add('insight-eg-error');
        insightEg.style.setProperty('--egc', '#ff2244');
        insightEg.className = 'insight-endgame-root insight-eg-error show lose';
        insightEg.setAttribute('aria-hidden', 'false');
        fillInsightConfetti(false);

        if (egStats) egStats.innerHTML = '';
        if (egRw) egRw.innerHTML = '';
        if (egFlavor) {
            egFlavor.textContent = '';
            egFlavor.classList.remove('anim');
        }
        if (egResult) {
            egResult.className = 'eg-res';
            egResult.textContent = 'ERROR';
        }
        if (egSub) {
            egSub.className = 'eg-sub';
            egSub.textContent = message || 'Error';
        }
        if (egd1) egd1.style.display = 'none';
        if (egd2) egd2.style.display = 'none';

        setTimeout(function () {
            if (egtl) egtl.classList.add('anim');
        }, 50);
        setTimeout(function () {
            if (egResult) egResult.classList.add('anim');
        }, 200);
        setTimeout(function () {
            if (egSub) egSub.classList.add('anim');
        }, 400);
        setTimeout(function () {
            if (egBtns) egBtns.classList.add('anim');
        }, 500);
        setTimeout(function () {
            if (egbl) egbl.classList.add('anim');
        }, 600);
    }

    function showInsightEndgame(win, choice, value, entry, payout, xp) {
        if (!insightEg) return;
        insightEg.classList.remove('insight-eg-error');
        if (egd1) egd1.style.display = '';
        if (egd2) egd2.style.display = '';

        var data = win ? INSIGHT_EG.win : INSIGHT_EG.lose;
        var kpDelta = win ? payout : -entry;
        var flavors = win ? FLAVOR_WIN : FLAVOR_LOSE;
        var flav = flavors[~~(Math.random() * flavors.length)];

        fillInsightConfetti(win);

        insightEg.style.setProperty('--egc', data.color);
        insightEg.className = 'insight-endgame-root';
        void insightEg.offsetWidth;
        insightEg.classList.add('show', data.bgClass);
        insightEg.setAttribute('aria-hidden', 'false');

        if (egResult) {
            egResult.className = 'eg-res';
            egResult.textContent = data.result;
        }
        if (egSub) {
            egSub.className = 'eg-sub';
            egSub.textContent = data.sub;
        }
        if (egFlavor) {
            egFlavor.textContent = '';
            egFlavor.classList.remove('anim');
        }
        if (egStats) egStats.innerHTML = '';
        if (egRw) egRw.innerHTML = '';

        [egtl, egResult, egSub, egd1, egd2, egFlavor, egBtns, egbl].forEach(function (el) {
            if (el) el.classList.remove('anim');
        });

        setTimeout(function () {
            if (egtl) egtl.classList.add('anim');
        }, 50);
        setTimeout(function () {
            if (egResult) egResult.classList.add('anim');
        }, 400);
        setTimeout(function () {
            if (egSub) egSub.classList.add('anim');
        }, 650);
        setTimeout(function () {
            if (egd1) egd1.classList.add('anim');
        }, 850);

        var choiceLabel = choice === 'under' ? 'UNDER' : 'ABOVE';
        var statDefs = [
            { v: value, l: 'ROLLED' },
            { text: choiceLabel, l: 'PICK', isText: true },
            { v: entry, l: 'STAKE' }
        ];
        statDefs.forEach(function (s, i) {
            var si = document.createElement('div');
            si.className = 'eg-si';
            if (s.isText) {
                si.innerHTML =
                    '<div class="eg-sv eg-text-sm">' +
                    s.text +
                    '</div><div class="eg-sl">' +
                    s.l +
                    '</div>';
            } else {
                si.innerHTML =
                    '<div class="eg-sv" id="insight-egsv' +
                    i +
                    '">0</div><div class="eg-sl">' +
                    s.l +
                    '</div>';
            }
            if (egStats) egStats.appendChild(si);
            setTimeout(
                function () {
                    si.classList.add('anim');
                    if (!s.isText) {
                        var target = s.v;
                        var svEl = document.getElementById('insight-egsv' + i);
                        var t0 = performance.now();
                        function tick() {
                            var p = Math.min((performance.now() - t0) / 500, 1);
                            var ease = 1 - Math.pow(1 - p, 3);
                            if (svEl) svEl.textContent = Math.round(target * ease).toLocaleString();
                            if (p < 1) requestAnimationFrame(tick);
                        }
                        tick();
                    }
                },
                1000 + i * 200
            );
        });

        setTimeout(function () {
            if (egd2) egd2.classList.add('anim');
        }, 1650);

        if (egRw) {
            var ri = document.createElement('div');
            ri.className = 'eg-ri';
            var rvCls = 'eg-rv kp' + (win ? '' : ' lose');
            var kpStr = (win ? '+' : '−') + (win ? payout : entry).toLocaleString();
            ri.innerHTML =
                '<span class="' +
                rvCls +
                '" id="insight-eg-kpval">' +
                (win ? '+0' : '−0') +
                '</span><span class="eg-ri-lbl">KND POINTS</span>';
            egRw.appendChild(ri);
            setTimeout(function () {
                ri.classList.add('anim');
                var elKp = document.getElementById('insight-eg-kpval');
                var tgt = win ? payout : entry;
                var t0 = performance.now();
                function tickKp() {
                    var p = Math.min((performance.now() - t0) / 700, 1);
                    var ease = 1 - Math.pow(1 - p, 3);
                    var cur = Math.round(tgt * ease);
                    if (elKp) elKp.textContent = (win ? '+' : '−') + cur.toLocaleString();
                    if (p < 1) requestAnimationFrame(tickKp);
                }
                tickKp();
            }, 2100);
        }

        setTimeout(function () {
            if (!egFlavor) return;
            egFlavor.classList.add('anim');
            var ci = 0;
            function typeChar() {
                if (!egFlavor) return;
                if (ci < flav.length) {
                    egFlavor.textContent = '"' + flav.substring(0, ci + 1);
                    ci++;
                    setTimeout(typeChar, 22 + Math.random() * 12);
                } else {
                    egFlavor.textContent = '"' + flav + '"';
                }
            }
            egFlavor.textContent = '"';
            typeChar();
        }, 2600);

        setTimeout(function () {
            if (egBtns) egBtns.classList.add('anim');
        }, 3200);
        setTimeout(function () {
            if (egbl) egbl.classList.add('anim');
        }, 3300);

        if (typeof xp === 'number' && xp > 0 && typeof showXpGain === 'function') {
            /* XP toast handled by existing caller timing */
        }
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function startRoll() {
        rolling = true;
        setZonesEnabled(false);
        if (entryChipsWrap) {
            entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (c) {
                c.disabled = true;
            });
        }
        diceShell.classList.remove('result-critical');
        diceShell.className = 'dice-shell rolling';
        diceStatus.textContent = 'Rolling…';
        hideInsightEndgame();
        if (streakBanner) streakBanner.classList.remove('show');

        tickTimer = setInterval(function () {
            diceValue.textContent = String(Math.floor(Math.random() * 10) + 1);
        }, TICK_MS);
    }

    function updateStreakUi(win) {
        if (win) {
            winStreak += 1;
        } else {
            winStreak = 0;
        }
        if (tbStreak) {
            tbStreak.textContent = '🔥 STREAK: ' + winStreak;
        }
        if (streakBanner) {
            streakBanner.classList.remove('hot', 'cold', 'show');
            if (winStreak >= 2) {
                streakBanner.textContent = '🔥 ' + winStreak + ' WIN STREAK';
                streakBanner.classList.add('hot', 'show');
            } else if (!win && winStreak === 0) {
                streakBanner.textContent = '';
            }
        }
    }

    function computeStatsFromStrip() {
        if (!historyStrip || !bbWinrate || !bbProfit) return;
        var dots = historyStrip.querySelectorAll('.history-dot');
        if (!dots.length) {
            bbWinrate.textContent = '—';
            bbProfit.textContent = '—';
            bbProfit.classList.remove('neg');
            return;
        }
        var wins = 0;
        var net = 0;
        for (var i = 0; i < dots.length; i++) {
            var el = dots[i];
            if (el.classList.contains('win')) wins += 1;
            var delta = parseInt(el.getAttribute('data-delta-kp'), 10);
            if (!isNaN(delta)) net += delta;
        }
        var wr = Math.round((wins / dots.length) * 100);
        bbWinrate.textContent = wr + '%';
        bbProfit.textContent = (net >= 0 ? '+' : '') + net.toLocaleString();
        bbProfit.classList.toggle('neg', net < 0);
    }

    function stopRoll(value, win, choice, entry, payout, xp) {
        clearInterval(tickTimer);
        tickTimer = null;

        diceValue.textContent = String(value);
        var isUnder = value <= 5;
        diceShell.className = 'dice-shell ' + (isUnder ? 'result-under' : 'result-above');
        if (!win) {
            diceShell.classList.add('result-critical');
        }

        diceStatus.textContent = win ? 'WIN' : 'LOSE';

        updateStreakUi(win);

        rolling = false;
        setZonesEnabled(true);
        if (entryChipsWrap) {
            entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (c) {
                c.disabled = false;
            });
        }

        setTimeout(function () {
            showInsightEndgame(win, choice, value, entry, payout, xp);
        }, 300);
    }

    function addHistoryDot(r) {
        if (!historyStrip) return;
        var dot = document.createElement('div');
        dot.className = 'history-dot ' + (r.win ? 'win' : 'lose');
        dot.setAttribute('data-delta-kp', r.win ? String(r.payout) : String(-r.entry));
        var ch = r.choice ? String(r.choice).charAt(0).toUpperCase() : '?';
        dot.innerHTML = String(r.rolled) + '<span class="hd-choice">' + ch + '</span>';
        var rows = historyStrip.querySelectorAll('.history-dot');
        if (rows.length >= 10) {
            historyStrip.removeChild(rows[rows.length - 1]);
        }
        historyStrip.insertBefore(dot, historyStrip.firstChild);
        computeStatsFromStrip();
    }

    var _rawBalance = parseInt(balanceEl.textContent.replace(/\D/g, ''), 10) || 0;

    function updateBalance(val) {
        _rawBalance = parseInt(val, 10) || 0;
        balanceEl.textContent = _rawBalance.toLocaleString();
        var ccCoins = document.getElementById('cc-coins');
        if (ccCoins) ccCoins.textContent = _rawBalance.toLocaleString();
        var navBadge = document.querySelector('.sc-nav-badge');
        if (navBadge) {
            if (_rawBalance > 0) {
                navBadge.innerHTML = '<i class="fas fa-coins"></i> ' + _rawBalance.toLocaleString();
                navBadge.style.display = '';
            } else {
                navBadge.style.display = 'none';
            }
        }
    }

    function doRoll(choice) {
        if (rolling) return;

        var entry = getEntry();
        if (_rawBalance < entry) {
            showInsightError('Not enough KP. You need ' + entry.toLocaleString() + ' KP.');
            if (typeof kndToast === 'function') kndToast('error', 'Not enough KP.');
            return;
        }

        startRoll();

        var fd = new FormData();
        fd.append('choice', choice);
        fd.append('entry_kp', entry);
        fd.append('csrf_token', CSRF);

        var rollStart = Date.now();

        fetch('/api/above-under/roll.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                var elapsed = Date.now() - rollStart;
                var remaining = Math.max(0, ROLL_DURATION_MS - elapsed);

                if (d.ok) {
                    var dx = d.data;
                    if (dx.xp_delta > 0 && typeof showXpGain === 'function') {
                        setTimeout(function () {
                            showXpGain(dx.xp_delta);
                        }, remaining + 150);
                    }
                    if (dx.level_up && dx.old_level != null && dx.new_level != null) {
                        var oldLvl = Number(dx.old_level);
                        var newLvl = Number(dx.new_level);
                        if (typeof updateNavLevelBadge === 'function') {
                            setTimeout(function () {
                                updateNavLevelBadge(newLvl);
                            }, remaining + 200);
                        }
                        if (typeof showLevelUp === 'function') {
                            setTimeout(function () {
                                showLevelUp(oldLvl, newLvl);
                            }, remaining + 400);
                        }
                        if (typeof kndToast === 'function') {
                            setTimeout(function () {
                                kndToast('success', 'Level Up: ' + oldLvl + ' → ' + newLvl);
                            }, remaining + 500);
                        }
                    } else if (dx.level && typeof updateNavLevelBadge === 'function') {
                        setTimeout(function () {
                            updateNavLevelBadge(dx.level);
                        }, remaining + 200);
                    }
                    setTimeout(function () {
                        stopRoll(dx.rolled, dx.win, dx.choice, dx.entry, dx.payout, dx.xp_awarded);
                        updateBalance(dx.points_balance);
                        addHistoryDot({
                            choice: dx.choice,
                            rolled: dx.rolled,
                            win: dx.win,
                            payout: dx.payout,
                            entry: dx.entry,
                            xp: dx.xp_awarded
                        });
                    }, remaining);
                } else {
                    clearInterval(tickTimer);
                    tickTimer = null;
                    diceShell.className = 'dice-shell';
                    diceValue.textContent = '—';
                    diceStatus.textContent = 'Ready';
                    rolling = false;
                    setZonesEnabled(true);
                    if (entryChipsWrap) {
                        entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (c) {
                            c.disabled = false;
                        });
                    }
                    var errMsg = (d.error && d.error.message) || 'Error';
                    showInsightError(errMsg);
                    if (typeof kndToast === 'function') kndToast('error', errMsg);
                }
            })
            .catch(function () {
                clearInterval(tickTimer);
                tickTimer = null;
                diceShell.className = 'dice-shell';
                diceValue.textContent = '—';
                diceStatus.textContent = 'Ready';
                rolling = false;
                setZonesEnabled(true);
                if (entryChipsWrap) {
                    entryChipsWrap.querySelectorAll('.entry-chip').forEach(function (c) {
                        c.disabled = false;
                    });
                }
                showInsightError('Network error. Try again.');
                if (typeof kndToast === 'function') kndToast('error', 'Network error. Try again.');
            });
    }

    zoneUnder.addEventListener('click', function () {
        doRoll('under');
    });
    zoneAbove.addEventListener('click', function () {
        doRoll('above');
    });

    function zoneKey(e, choice) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            doRoll(choice);
        }
    }
    zoneUnder.addEventListener('keydown', function (e) {
        zoneKey(e, 'under');
    });
    zoneAbove.addEventListener('keydown', function (e) {
        zoneKey(e, 'above');
    });

    if (btnContinue) {
        btnContinue.addEventListener('click', function (e) {
            e.preventDefault();
            hideInsightEndgame();
        });
    }
    if (btnArena) {
        btnArena.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.parent && window.parent !== window) {
                window.parent.location.href = '/knd-arena.php';
            } else {
                window.location.href = '/knd-arena.php';
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!insightEg || !insightEg.classList.contains('show')) return;
        hideInsightEndgame();
    });

    computeStatsFromStrip();
})();

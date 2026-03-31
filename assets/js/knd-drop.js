(function () {
    'use strict';

    var CSRF     = window.DROP_CSRF || '';
    var ENDS_AT  = window.DROP_ENDS_AT;
    var STARTS_AT = window.DROP_STARTS_AT;
    var ENTRY    = Number(window.DROP_ENTRY || 100);
    var DROP_MAX = 10;
    var SCAN_MS  = 2200;
    var CRACK_MS = 400;
    var REVEAL_DELAY = {
        common: 300,
        special: 400,
        rare: 500,
        epic: 600,
        legendary: 800
    };

    var balanceEl     = document.getElementById('drop-balance');
    var balanceStrip  = document.getElementById('drop-balance-strip');
    var resultEl      = document.getElementById('drop-result');
    var historyEl     = document.getElementById('drop-history');
    var countdownEl   = document.getElementById('drop-countdown');
    var fragmentsEl  = document.getElementById('drop-fragments');
    var pityEl       = document.getElementById('drop-pity');
    var pityWrap     = document.getElementById('drop-pity-wrap');
    var capsules      = document.querySelectorAll('.drop-capsule');
    var overlayEl     = document.getElementById('drop-reveal-overlay');
    var revealCapsule = document.getElementById('drop-reveal-capsule');
    var revealItem   = document.getElementById('drop-reveal-item');
    var revealCard   = document.getElementById('drop-reveal-card');
    var revealClose  = document.getElementById('drop-reveal-close');
    var introOverlay = document.getElementById('drop-intro-overlay');
    var introClose   = document.getElementById('drop-intro-close');
    var seasonProgress = document.getElementById('drop-season-progress');
    var seasonProgressBar = document.getElementById('drop-season-progress-bar');
    var limitEl = document.getElementById('drop-limit');
    var limitResetEl = document.getElementById('drop-limit-reset');

    var _rawBalance = parseInt((balanceEl ? balanceEl.textContent : '0').replace(/\D/g, ''), 10) || 0;
    var _dropLimitRemaining = null;
    var _dropLimitResetsAt = null;
    var playing = false;
    var _lastRevealData = null;

    var rarityStyles = {
        common:    { bg: 'rgba(160,174,192,.15)', border: 'rgba(160,174,192,.4)', text: '#a0aec0' },
        special:   { bg: 'rgba(139,92,246,.15)',   border: 'rgba(139,92,246,.4)',   text: '#8b5cf6' },
        rare:      { bg: 'rgba(66,153,225,.15)',   border: 'rgba(66,153,225,.4)',   text: '#4299e1' },
        epic:      { bg: 'rgba(159,122,234,.15)',  border: 'rgba(159,122,234,.4)',  text: '#9f7aea' },
        legendary: { bg: 'rgba(236,201,75,.15)',  border: 'rgba(236,201,75,.4)',   text: '#ecc94b' }
    };

    function formatBadgeLabel(badge) {
        return String(badge)
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function updateBalance(val) {
        _rawBalance = parseInt(val, 10) || 0;
        if (balanceEl) balanceEl.textContent = _rawBalance.toLocaleString();
        var b = document.querySelector('.sc-nav-badge');
        if (b) {
            if (_rawBalance > 0) {
                b.innerHTML = '<i class="fas fa-coins"></i> ' + _rawBalance.toLocaleString();
                b.style.display = '';
            } else {
                b.style.display = 'none';
            }
        }
    }

    function updateFragments(val) {
        if (fragmentsEl) {
            fragmentsEl.textContent = parseInt(val, 10).toLocaleString();
        }
    }

    function updatePity(boost) {
        if (!pityEl || !pityWrap) return;
        var b = parseInt(boost, 10) || 0;
        if (b > 0) {
            pityWrap.style.display = 'block';
            pityEl.textContent = '+' + b + '% rare+';
        } else {
            pityWrap.style.display = 'none';
        }
    }

    function setCapsules(enabled) {
        var canDrop = enabled && (_dropLimitRemaining === null || _dropLimitRemaining > 0);
        capsules.forEach(function (c) {
            if (canDrop) c.classList.remove('disabled');
            else c.classList.add('disabled');
        });
    }

    function formatResetsIn(resetsAt) {
        if (!resetsAt) return '';
        var diff = Math.max(0, Math.floor((new Date(resetsAt + 'Z') - Date.now()) / 1000));
        if (diff <= 0) return '';
        var h = Math.floor(diff / 3600);
        var m = Math.floor((diff % 3600) / 60);
        if (h > 0) return 'Resets in ' + h + 'h ' + m + 'm';
        return 'Resets in ' + m + ' min';
    }

    function updateDropLimit(status) {
        if (!status || !limitEl) return;
        var used = status.used || 0;
        var remaining = status.remaining ?? (DROP_MAX - used);
        var max = status.max || DROP_MAX;
        _dropLimitRemaining = remaining;
        limitEl.textContent = used + '/' + max;
        limitEl.style.color = remaining <= 0 ? '#ef4444' : (remaining <= 2 ? '#f59e0b' : '#22c55e');
        if (limitResetEl) {
            if (status.resets_at && remaining <= 0) {
                _dropLimitResetsAt = status.resets_at;
                limitResetEl.textContent = formatResetsIn(status.resets_at);
                limitResetEl.style.display = 'block';
            } else {
                _dropLimitResetsAt = null;
                limitResetEl.style.display = 'none';
            }
        }
        setCapsules(!playing);
    }

    function fetchDropLimitStatus() {
        fetch('/api/drop/status.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok && d.data) updateDropLimit(d.data);
            })
            .catch(function () {});
    }

    function resetCapsule(el) {
        if (!el) return;
        el.classList.remove('active', 'scanning', 'drop-rarity-common', 'drop-rarity-special', 'drop-rarity-rare', 'drop-rarity-epic', 'drop-rarity-legendary');
        var inner = el.querySelector('.drop-capsule-inner');
        if (inner) {
            inner.innerHTML = '<i class="fas fa-box-open"></i>';
        }
        var hint = el.querySelector('.drop-capsule-hint');
        if (hint) hint.style.display = '';
    }

    function shakeBalanceStrip() {
        if (balanceStrip) {
            balanceStrip.classList.remove('shake');
            void balanceStrip.offsetWidth;
            balanceStrip.classList.add('shake');
            setTimeout(function () { balanceStrip.classList.remove('shake'); }, 500);
        }
    }

    function showOnboarding() {
        try {
            if (localStorage.getItem('knd_drop_intro_seen')) return;
            if (introOverlay) {
                introOverlay.classList.add('active');
                introOverlay.setAttribute('aria-hidden', 'false');
            }
        } catch (e) {}
    }

    function hideOnboarding() {
        try {
            localStorage.setItem('knd_drop_intro_seen', '1');
        } catch (e) {}
        if (introOverlay) {
            introOverlay.classList.remove('active');
            introOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    function buildRevealCard(data) {
        var rc = rarityStyles[data.rarity] || rarityStyles.common;
        var item = data.item || {};
        var isDupe = data.was_duplicate;
        var fragments = data.fragments_awarded || 0;
        var badges = data.badges_unlocked || [];
        var pityBoost = data.pity_boost || 0;

        var html = '<div class="drop-reveal-card" style="border-color:' + rc.border + ';">';
        if (item.asset_path) {
            html += '<img src="' + item.asset_path + '" alt="" class="drop-reveal-card-img" style="border:2px solid ' + rc.border + ';">';
        }
        html += '<div class="drop-reveal-card-name" style="color:' + rc.text + ';">' + (item.name || 'Avatar Item') + '</div>';
        html += '<div class="drop-reveal-card-slot text-white-50">' + (item.slot ? item.slot.toUpperCase() : 'ITEM') + '</div>';
        if (isDupe) {
            html += '<span class="badge bg-secondary mb-1"><i class="fas fa-copy me-1"></i>DUPLICATE</span>';
            if (fragments > 0) {
                html += '<div class="text-white-50 small"><i class="fas fa-gem me-1"></i>+' + fragments + ' Fragments</div>';
            }
        } else {
            html += '<span class="badge bg-success mb-1"><i class="fas fa-star me-1"></i>NEW!</span>';
        }
        html += '<div class="text-white-50 small">+' + data.xp_awarded + ' XP</div>';
        if (pityBoost > 0) {
            html += '<div class="drop-reveal-pity">Pity active: +' + pityBoost + '% rare+</div>';
        }
        if (badges.length > 0) {
            html += '<div class="mt-2">';
            badges.forEach(function (b) {
                html += '<span class="badge bg-warning text-dark me-1">' + formatBadgeLabel(b) + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function showResultInPage(data) {
        var rc = rarityStyles[data.rarity] || rarityStyles.common;
        var item = data.item || {};
        var isDupe = data.was_duplicate;
        var fragments = data.fragments_awarded || 0;
        var badges = data.badges_unlocked || [];

        var html = '<div class="alert alert-info text-center mb-0" style="background:' + rc.bg + '; border:2px solid ' + rc.border + ';">';
        html += '<div class="mb-2"><span class="badge" style="background:' + rc.bg + '; color:' + rc.text + '; border:1px solid ' + rc.border + '; font-size:.95rem; padding:.5rem 1rem;">' + data.rarity.toUpperCase() + '</span></div>';
        html += '<div class="mb-2"><strong style="font-size:1.1rem; color:' + rc.text + ';">' + (item.name || 'Avatar Item') + '</strong></div>';
        html += '<div class="text-white-50 small mb-2">' + (item.slot ? item.slot.toUpperCase() : 'ITEM') + '</div>';
        if (item.asset_path) {
            html += '<div class="mb-3"><img src="' + item.asset_path + '" alt="" style="max-width:160px; max-height:160px; border-radius:12px; border:1px solid ' + rc.border + ';"></div>';
        }
        if (isDupe) {
            html += '<div class="mb-2"><span class="badge bg-secondary"><i class="fas fa-copy me-1"></i>DUPLICATE</span></div>';
            if (fragments > 0) html += '<div class="mb-2" style="color:#a78bfa;"><i class="fas fa-gem me-1"></i><strong>+' + fragments + ' Fragments</strong></div>';
        } else {
            html += '<div class="mb-2"><span class="badge bg-success"><i class="fas fa-star me-1"></i>NEW!</span></div>';
        }
        html += '<div class="text-white-50 small">+' + data.xp_awarded + ' XP</div>';
        if (badges.length > 0) {
            html += '<div class="mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.1);">';
            html += '<div class="text-warning mb-2"><i class="fas fa-award me-1"></i><strong>Badge' + (badges.length > 1 ? 's' : '') + ' Unlocked!</strong></div>';
            badges.forEach(function (b) {
                html += '<span class="badge bg-warning text-dark me-1 mb-1">' + formatBadgeLabel(b) + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';

        resultEl.innerHTML = html;
        resultEl.style.display = 'block';
    }

    function addHistoryRow(d) {
        var rc = rarityStyles[d.rarity] || rarityStyles.common;
        var item = d.item || {};
        var itemName = item.name || 'Avatar Item';
        var isDupe = d.was_duplicate;
        var fragments = d.fragments_awarded || 0;
        var assetPath = item.asset_path || '';

        var emptyEl = historyEl && historyEl.querySelector('.drop-history-empty');
        if (emptyEl) emptyEl.remove();

        var card = document.createElement('div');
        card.className = 'drop-history-card';
        var thumbHtml = assetPath
            ? '<img src="' + assetPath + '" alt="" class="drop-history-card-thumb" style="border:1px solid ' + rc.border + ';">'
            : '<div class="drop-history-card-thumb" style="background:' + rc.bg + '; border:1px solid ' + rc.border + '; display:flex; align-items:center; justify-content:center;"><i class="fas fa-image text-white-50"></i></div>';
        var nameHtml = itemName + (isDupe && fragments > 0 ? ' <span style="color:#a78bfa;">+' + fragments + ' frags</span>' : '');
        card.innerHTML = thumbHtml +
            '<div class="drop-history-card-info">' +
            '<div class="drop-history-card-name" style="color:' + rc.text + ';">' + nameHtml + '</div>' +
            '<div class="drop-history-card-meta">' +
            '<span class="badge" style="background:' + rc.bg + '; color:' + rc.text + '; border:1px solid ' + rc.border + '; font-size:.65rem;">' + d.rarity.charAt(0).toUpperCase() + d.rarity.slice(1) + '</span> ' +
            '+' + d.xp_awarded + ' XP · Just now</div></div>';

        if (historyEl) {
            historyEl.insertBefore(card, historyEl.firstChild);
            var cards = historyEl.querySelectorAll('.drop-history-card');
            if (cards.length > 10) historyEl.removeChild(cards[cards.length - 1]);
        }
    }

    function closeRevealModal() {
        if (overlayEl) {
            overlayEl.classList.remove('active', 'legendary-shake');
            overlayEl.setAttribute('aria-hidden', 'true');
        }
        revealItem.classList.remove('visible', 'reveal-common', 'reveal-special', 'reveal-rare', 'reveal-epic', 'reveal-legendary');
        revealItem.style.opacity = '0';
        revealItem.style.transform = 'scale(0.5)';
        revealCapsule.classList.remove('crack');
        revealCapsule.innerHTML = '';
    }

    function runChoreographedReveal(capsuleEl, dd) {
        _lastRevealData = { dd: dd, capsuleEl: capsuleEl };
        var rarity = dd.rarity;
        var rc = rarityStyles[rarity] || rarityStyles.common;
        var delay = REVEAL_DELAY[rarity] || 300;

        if (typeof DropAudio !== 'undefined') {
            DropAudio.unlock();
            DropAudio.playReveal(rarity);
        }
        overlayEl.classList.add('active');
        overlayEl.setAttribute('aria-hidden', 'false');
        revealCapsule.innerHTML = '<div class="knd-drop-scanner" aria-hidden="true">' +
            '<div class="knd-drop-scanner-ring"></div>' +
            '<div class="knd-drop-scanner-ring knd-drop-scanner-ring--delay"></div>' +
            '<div class="knd-drop-scanner-core"></div></div>';
        revealCapsule.style.borderColor = rc.border;
        if (rc.text.indexOf('#') === 0) {
            var hex = rc.text.slice(1);
            var r = parseInt(hex.substr(0, 2), 16), g = parseInt(hex.substr(2, 2), 16), b = parseInt(hex.substr(4, 2), 16);
            revealCapsule.style.boxShadow = '0 0 60px rgba(' + r + ',' + g + ',' + b + ',0.5)';
        } else {
            revealCapsule.style.boxShadow = '0 0 60px ' + rc.border;
        }
        revealItem.style.display = 'none';
        revealCard.innerHTML = buildRevealCard(dd);
        revealCard.style.borderColor = rc.border;
        revealItem.querySelector('.drop-reveal-rays').style.color = rc.text;

        if (rarity === 'epic' || rarity === 'legendary') {
            revealItem.classList.add('reveal-' + rarity);
        } else {
            revealItem.classList.add('reveal-' + rarity);
        }

        if (rarity === 'legendary') {
            overlayEl.classList.add('legendary-shake');
            if (typeof kndConfetti === 'function') {
                kndConfetti({ duration: 2800, count: 40 });
            }
            if (typeof kndToast === 'function') {
                kndToast('success', 'LEGENDARY DROP!');
            }
        }

        setTimeout(function () {
            revealCapsule.classList.add('crack');
        }, SCAN_MS);

        setTimeout(function () {
            revealCapsule.style.display = 'none';
            revealItem.style.display = 'flex';
            revealItem.classList.add('visible');
        }, SCAN_MS + CRACK_MS);

        setTimeout(function () {
            if (dd.xp_delta > 0 && typeof showXpGain === 'function') {
                showXpGain(dd.xp_delta);
            }
        }, SCAN_MS + CRACK_MS + delay);

        if (dd.level_up && dd.old_level != null && dd.new_level != null) {
            setTimeout(function () {
                if (typeof updateNavLevelBadge === 'function') updateNavLevelBadge(dd.new_level);
                if (typeof showLevelUp === 'function') showLevelUp(dd.old_level, dd.new_level);
                if (typeof kndToast === 'function') kndToast('success', 'Level Up: ' + dd.old_level + ' → ' + dd.new_level);
            }, SCAN_MS + CRACK_MS + 800);
        } else if (dd.level && typeof updateNavLevelBadge === 'function') {
            setTimeout(function () { updateNavLevelBadge(dd.level); }, SCAN_MS + CRACK_MS + 400);
        }

        if (dd.badges_unlocked && dd.badges_unlocked.length > 0 && typeof kndToast === 'function') {
            setTimeout(function () {
                dd.badges_unlocked.forEach(function (b) {
                    kndToast('success', 'Badge Unlocked: ' + String(b).replace(/_/g, ' '));
                });
            }, SCAN_MS + CRACK_MS + 1000);
        }

        var closeAfter = (rarity === 'legendary' ? 5500 : rarity === 'epic' ? 4500 : 3500);
        var timeoutId = setTimeout(function () {
            finishReveal(dd, capsuleEl);
        }, closeAfter);

        revealClose._dropRevealTimeout = timeoutId;
    }

    function finishReveal(dd, capsuleEl) {
        if (revealClose && revealClose._dropRevealTimeout) {
            clearTimeout(revealClose._dropRevealTimeout);
            revealClose._dropRevealTimeout = null;
        }
        closeRevealModal();
        showResultInPage(dd);
        updateBalance(dd.balance);
        if (dd.fragments_total !== undefined) updateFragments(dd.fragments_total);
        addHistoryRow(dd);
        fetch('/api/avatar/fragments.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok && d.data && d.data.pity_boost !== undefined) updatePity(d.data.pity_boost);
            })
            .catch(function () {});
        fetchDropLimitStatus();
        resetCapsule(capsuleEl);
        playing = false;
        setCapsules(true);
        if (revealCapsule) revealCapsule.style.display = 'flex';
        _lastRevealData = null;
    }

    function playDrop(capsuleEl) {
        if (playing) return;

        if (_dropLimitRemaining === 0) {
            resultEl.innerHTML = '<div class="alert alert-warning text-center mb-0"><i class="fas fa-clock me-2"></i>Hourly limit reached. Max ' + DROP_MAX + ' drops per hour.' + (_dropLimitResetsAt ? ' <span class="d-block mt-2 small">' + formatResetsIn(_dropLimitResetsAt) + '</span>' : '') + '</div>';
            resultEl.style.display = 'block';
            if (typeof kndToast === 'function') kndToast('error', 'Hourly limit reached');
            return;
        }

        if (_rawBalance < ENTRY) {
            shakeBalanceStrip();
            resultEl.innerHTML = '<div class="alert alert-danger text-center mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Not enough KP. Need ' + ENTRY.toLocaleString() + '.</div>';
            resultEl.style.display = 'block';
            if (typeof kndToast === 'function') kndToast('error', 'Need ' + ENTRY + ' KP');
            return;
        }

        playing = true;
        setCapsules(false);
        resultEl.style.display = 'none';

        capsuleEl.classList.add('active', 'scanning');
        var inner = capsuleEl.querySelector('.drop-capsule-inner');
        if (inner) {
            inner.innerHTML = '<div class="knd-drop-scanner" aria-hidden="true">' +
                '<div class="knd-drop-scanner-ring"></div>' +
                '<div class="knd-drop-scanner-ring knd-drop-scanner-ring--delay"></div>' +
                '<div class="knd-drop-scanner-core"></div></div>';
        }
        var hint = capsuleEl.querySelector('.drop-capsule-hint');
        if (hint) hint.style.display = 'none';

        if (typeof DropAudio !== 'undefined') {
            DropAudio.unlock();
            DropAudio.playScan();
        }
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        var startTime = Date.now();

        fetch('/api/drop/play.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var elapsed = Date.now() - startTime;
                var remaining = Math.max(0, SCAN_MS - elapsed);

                setTimeout(function () {
                    capsuleEl.classList.remove('scanning');

                    if (d.ok) {
                        var dd = d.data;
                        var rarity = dd.rarity;
                        capsuleEl.classList.add('drop-rarity-' + rarity);
                        var rc = rarityStyles[rarity] || rarityStyles.common;
                        var itemName = (dd.item && dd.item.name) ? dd.item.name.substring(0, 12) : rarity;
                        if (inner) {
                            inner.innerHTML = '<div style="font-size:.55rem; text-transform:uppercase; color:' + rc.text + ';">' + rarity + '</div>' +
                                '<div style="font-size:.7rem; font-weight:700; color:#fff;">' + itemName + '</div>';
                        }
                        if (hint) hint.style.display = '';

                        runChoreographedReveal(capsuleEl, dd);
                    } else {
                        resetCapsule(capsuleEl);
                        var err = d && d.error;
                        var errMsg = (d && d.message) || (err && err.message) || (d && d.error) || 'Error';
                        if (err && err.code === 'RATE_LIMITED') {
                            fetchDropLimitStatus();
                            var resetsAt = err.resets_at;
                            var extra = resetsAt ? ' <span class="d-block mt-2 small">' + formatResetsIn(resetsAt) + '</span>' : '';
                            resultEl.innerHTML = '<div class="alert alert-warning text-center mb-0"><i class="fas fa-clock me-2"></i>' + errMsg + extra + '</div>';
                        } else {
                            resultEl.innerHTML = '<div class="alert alert-danger text-center mb-0"><i class="fas fa-exclamation-triangle me-2"></i>' + errMsg + '</div>';
                        }
                        resultEl.style.display = 'block';
                        if (typeof kndToast === 'function') kndToast('error', errMsg);
                        playing = false;
                        setCapsules(true);
                    }
                }, remaining);
            })
            .catch(function () {
                resetCapsule(capsuleEl);
                resultEl.innerHTML = '<div class="alert alert-danger text-center mb-0">Network error. Try again.</div>';
                resultEl.style.display = 'block';
                if (typeof kndToast === 'function') kndToast('error', 'Network error. Try again.');
                playing = false;
                setCapsules(true);
            });
    }

    if (revealClose) {
        revealClose.addEventListener('click', function () {
            if (revealClose._dropRevealTimeout) {
                clearTimeout(revealClose._dropRevealTimeout);
                revealClose._dropRevealTimeout = null;
            }
            if (_lastRevealData) {
                finishReveal(_lastRevealData.dd, _lastRevealData.capsuleEl);
            } else {
                closeRevealModal();
                playing = false;
                setCapsules(true);
                if (revealCapsule) revealCapsule.style.display = 'flex';
            }
        });
    }

    if (introClose) {
        introClose.addEventListener('click', hideOnboarding);
    }

    capsules.forEach(function (c) {
        c.addEventListener('click', function () { playDrop(c); });
    });

    if (countdownEl && ENDS_AT) {
        var endDate = new Date(ENDS_AT + 'Z');
        function tick() {
            var diff = Math.max(0, Math.floor((endDate - Date.now()) / 1000));
            if (diff <= 0) {
                countdownEl.textContent = 'Season ended';
                if (seasonProgress) seasonProgress.style.display = 'none';
                return;
            }
            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            countdownEl.textContent = d + 'd ' + h + 'h ' + m + 'm ' + s + 's';

            if (seasonProgress && seasonProgressBar && STARTS_AT) {
                var startDate = new Date(STARTS_AT + 'Z');
                var total = Math.floor((endDate - startDate) / 1000);
                var elapsed = total - diff;
                var pct = total > 0 ? Math.min(100, Math.max(0, (elapsed / total) * 100)) : 0;
                seasonProgress.style.display = 'block';
                seasonProgressBar.style.width = pct + '%';
            }
        }
        tick();
        setInterval(tick, 1000);
    }

    fetch('/api/avatar/fragments.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok && d.data) {
                if (d.data.fragments !== undefined) updateFragments(d.data.fragments);
                if (d.data.pity_boost !== undefined) updatePity(d.data.pity_boost);
            }
        })
        .catch(function () {});

    fetchDropLimitStatus();

    function tickLimitCountdown() {
        if (!limitResetEl || limitResetEl.style.display !== 'block' || !_dropLimitResetsAt) return;
        var txt = formatResetsIn(_dropLimitResetsAt);
        limitResetEl.textContent = txt;
        if (!txt) fetchDropLimitStatus();
    }
    setInterval(tickLimitCountdown, 1000);

    setTimeout(showOnboarding, 600);
})();

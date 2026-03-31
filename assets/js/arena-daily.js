(function () {
    'use strict';

    var CSRF = window.ARENA_CSRF || '';
    var REWARDS = {1:20, 2:25, 3:30, 4:35, 5:40, 6:45, 7:60};

    var claimBtn     = document.getElementById('daily-claim-btn');
    var claimText    = document.getElementById('daily-claim-text');
    var dailyMsg     = document.getElementById('daily-msg');
    var streakBadge  = document.getElementById('daily-streak-badge');
    var missionsList = document.getElementById('missions-list');

    function updateNavBadge(val) {
        var b = document.querySelector('.sc-nav-badge');
        if (!b) return;
        if (val > 0) {
            b.innerHTML = '<i class="fas fa-coins"></i> ' + Number(val).toLocaleString();
            b.style.display = '';
        } else {
            b.style.display = 'none';
        }
    }

    // ── Daily Streak ──
    function renderDaily(d) {
        var streak = d.streak || 0;
        var nextDay = d.next_day || 1;
        var canClaim = d.can_claim;
        var todayKp = d.today_reward_kp || 0;

        streakBadge.textContent = 'Day ' + (canClaim ? nextDay : streak) + '/7';

        for (var i = 1; i <= 7; i++) {
            var dot = document.getElementById('daily-dot-' + i);
            var kpLabel = document.getElementById('daily-kp-' + i);
            if (!dot) continue;

            kpLabel.textContent = REWARDS[i] + ' KP';

            if (!canClaim && i <= streak) {
                dot.style.borderColor = '#4ade80';
                dot.style.color = '#4ade80';
                dot.style.background = 'rgba(74,222,128,.12)';
            } else if (canClaim && i === nextDay) {
                dot.style.borderColor = '#00d4ff';
                dot.style.color = '#00d4ff';
                dot.style.background = 'rgba(0,212,255,.15)';
                dot.style.boxShadow = '0 0 8px rgba(0,212,255,.4)';
            } else if (canClaim && i < nextDay) {
                dot.style.borderColor = '#4ade80';
                dot.style.color = '#4ade80';
                dot.style.background = 'rgba(74,222,128,.12)';
            } else {
                dot.style.borderColor = 'rgba(255,255,255,.15)';
                dot.style.color = 'rgba(255,255,255,.4)';
                dot.style.background = 'rgba(255,255,255,.03)';
                dot.style.boxShadow = 'none';
            }
        }

        if (canClaim) {
            claimBtn.disabled = false;
            claimText.textContent = 'Claim ' + todayKp + ' KP';
            if (d.is_day7) {
                claimText.textContent += ' + 20 XP';
            }
        } else {
            claimBtn.disabled = true;
            claimText.textContent = 'Claimed today ✓';
        }
    }

    function loadDaily() {
        fetch('/api/arena/daily-status.php', {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) renderDaily(d.data);
            })
            .catch(function () {
                claimText.textContent = 'Error loading';
            });
    }

    if (claimBtn) {
        claimBtn.addEventListener('click', function () {
            if (claimBtn.disabled) return;
            claimBtn.disabled = true;
            claimText.textContent = 'Claiming…';

            var fd = new FormData();
            fd.append('csrf_token', CSRF);

            fetch('/api/arena/daily-claim.php', {method: 'POST', body: fd, credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        var dd = d.data || d;
                        if (dd.xp_delta > 0 && typeof showXpGain === 'function') {
                            setTimeout(function () { showXpGain(dd.xp_delta); }, 200);
                        }
                        var lup = dd.level_up === true || dd.level_up === 1;
                        var oldL = dd.old_level != null ? Number(dd.old_level) : NaN;
                        var newL = dd.new_level != null ? Number(dd.new_level) : NaN;
                        if (lup && !isNaN(oldL) && !isNaN(newL) && newL > oldL) {
                            if (typeof updateNavLevelBadge === 'function') {
                                setTimeout(function () { updateNavLevelBadge(newL); }, 300);
                            }
                            if (typeof showLevelUp === 'function') {
                                setTimeout(function () { showLevelUp(oldL, newL); }, 400);
                            }
                            if (typeof kndToast === 'function') {
                                setTimeout(function () { kndToast('success', 'Level Up: ' + oldL + ' → ' + newL); }, 500);
                            }
                        } else if (dd.level != null && typeof updateNavLevelBadge === 'function') {
                            setTimeout(function () { updateNavLevelBadge(Number(dd.level)); }, 200);
                        }
                        var msg = '+' + dd.reward_kp + ' KP';
                        if (dd.bonus_xp > 0) msg += ' + ' + dd.bonus_xp + ' XP';
                        dailyMsg.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + msg + '</span>';
                        dailyMsg.style.display = 'block';
                        updateNavBadge(dd.balance);
                        loadDaily();
                    } else {
                        var errMsg = d.error && d.error.message || 'Error';
                        dailyMsg.innerHTML = '<span class="text-danger">' + errMsg + '</span>';
                        dailyMsg.style.display = 'block';
                        if (typeof kndToast === 'function') kndToast('error', errMsg);
                        claimBtn.disabled = false;
                        claimText.textContent = 'Claim';
                    }
                })
                .catch(function () {
                    dailyMsg.innerHTML = '<span class="text-danger">Network error</span>';
                    dailyMsg.style.display = 'block';
                    if (typeof kndToast === 'function') kndToast('error', 'Network error');
                    claimBtn.disabled = false;
                    claimText.textContent = 'Claim';
                });
        });
    }

    // ── Missions ──
    function renderMissions(missions) {
        if (!missionsList) return;
        if (!missions || missions.length === 0) {
            missionsList.innerHTML = '<p class="text-white-50 text-center small mb-0">No active missions today.</p>';
            return;
        }

        var html = '';
        missions.forEach(function (m) {
            var pct = Math.min(100, Math.round((m.progress / m.target) * 100));
            var barColor = m.claimed ? '#4ade80' : (m.completed ? '#00d4ff' : 'rgba(0,212,255,.5)');
            var statusHtml = '';

            if (m.claimed) {
                statusHtml = '<span class="badge bg-success" style="font-size:.7rem;">Claimed ✓</span>';
            } else if (m.can_claim) {
                statusHtml = '<button class="btn btn-sm btn-neon-primary mission-claim-btn" data-code="' + m.code + '" style="font-size:.7rem; padding:.2rem .6rem;">Claim +' + m.reward_kp + ' KP</button>';
            } else {
                statusHtml = '<span class="text-white-50" style="font-size:.75rem;">' + m.progress + '/' + m.target + '</span>';
            }

            html += '<div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid rgba(255,255,255,.06);">';
            html += '  <div class="flex-grow-1 me-3">';
            html += '    <div class="d-flex justify-content-between align-items-center mb-1">';
            html += '      <span class="small fw-bold">' + m.title + '</span>';
            html += '      <span class="text-white-50" style="font-size:.7rem;">' + m.reward_kp + ' KP' + (m.reward_xp > 0 ? ' + ' + m.reward_xp + ' XP' : '') + '</span>';
            html += '    </div>';
            html += '    <div style="background:rgba(255,255,255,.08); border-radius:4px; height:6px; overflow:hidden;">';
            html += '      <div style="width:' + pct + '%; height:100%; background:' + barColor + '; border-radius:4px; transition:width .3s;"></div>';
            html += '    </div>';
            html += '    <div class="text-white-50 mt-1" style="font-size:.65rem;">' + m.description + '</div>';
            html += '  </div>';
            html += '  <div class="text-end" style="min-width:80px;">' + statusHtml + '</div>';
            html += '</div>';
        });

        missionsList.innerHTML = html;

        missionsList.querySelectorAll('.mission-claim-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = this.dataset.code;
                var btnEl = this;
                btnEl.disabled = true;
                btnEl.textContent = '…';

                var fd = new FormData();
                fd.append('csrf_token', CSRF);
                fd.append('code', code);

                fetch('/api/arena/mission-claim.php', {method: 'POST', body: fd, credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.ok) {
                            var dd = d.data || d;
                            if (dd.xp_delta > 0 && typeof showXpGain === 'function') {
                                setTimeout(function () { showXpGain(dd.xp_delta); }, 200);
                            }
                            var lup = dd.level_up === true || dd.level_up === 1;
                            var oldL = dd.old_level != null ? Number(dd.old_level) : NaN;
                            var newL = dd.new_level != null ? Number(dd.new_level) : NaN;
                            if (lup && !isNaN(oldL) && !isNaN(newL) && newL > oldL) {
                                if (typeof updateNavLevelBadge === 'function') {
                                    setTimeout(function () { updateNavLevelBadge(newL); }, 300);
                                }
                                if (typeof showLevelUp === 'function') {
                                    setTimeout(function () { showLevelUp(oldL, newL); }, 400);
                                }
                                if (typeof kndToast === 'function') {
                                    setTimeout(function () { kndToast('success', 'Level Up: ' + oldL + ' → ' + newL); }, 500);
                                }
                            } else if (dd.level != null && typeof updateNavLevelBadge === 'function') {
                                setTimeout(function () { updateNavLevelBadge(Number(dd.level)); }, 200);
                            }
                            updateNavBadge(dd.balance);
                            loadMissions();
                        } else {
                            var errMsg = d.error && d.error.message || 'Error';
                            if (typeof kndToast === 'function') kndToast('error', errMsg);
                            btnEl.disabled = false;
                            btnEl.textContent = 'Claim';
                        }
                    })
                    .catch(function () {
                        if (typeof kndToast === 'function') kndToast('error', 'Network error');
                        btnEl.disabled = false;
                        btnEl.textContent = 'Claim';
                    });
            });
        });
    }

    function loadMissions() {
        fetch('/api/arena/missions.php', {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) renderMissions(d.data.missions);
            })
            .catch(function () {
                if (missionsList) missionsList.innerHTML = '<p class="text-danger small text-center">Error loading missions.</p>';
            });
    }

    // Init
    loadDaily();
    loadMissions();
})();

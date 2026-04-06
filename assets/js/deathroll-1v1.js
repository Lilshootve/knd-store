// KND Store - KND LastRoll (next-gen Death Roll 1v1) Client Logic
(function () {
    'use strict';

    function kndCssVar(name) {
        try {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '';
        } catch (e) {
            return '';
        }
    }

    /* ── Inject game-over animation CSS ── */
    var _lrStyle = document.createElement('style');
    _lrStyle.textContent = [
        '@keyframes lrGoLine{to{transform:scaleX(1)}}',
        '@keyframes lrGoPop{to{opacity:1;transform:scale(1)}}',
        '@keyframes lrGoSlam{to{opacity:1;transform:translateY(0) scaleX(1)}}',
        '@keyframes lrGoFade{to{opacity:1;transform:translateY(0)}}',
        '@keyframes lrConfetti{0%{opacity:.9;transform:translateY(0) rotate(0)}50%{opacity:.8}100%{opacity:0;transform:translateY(320px) rotate(var(--rot,600deg))}}',
        '.lr-go-kp{margin-top:10px;font-family:var(--lr-FM,"Share Tech Mono",monospace);font-size:1rem;letter-spacing:1.5px}',
        '.lr-go-kp.win{color:var(--color-success)}',
        '.lr-go-kp.lose{color:var(--text-secondary)}',
        '.lr-go-stat{text-align:center;opacity:0;transform:translateY(10px);animation:lrGoFade .4s ease forwards}',
        '.lr-go-sv{font-family:var(--lr-FD,Orbitron,monospace);font-size:22px;font-weight:700;color:var(--accent-action);text-shadow:none}',
        '.lr-go-sl{font-family:var(--lr-FM,"Share Tech Mono",monospace);font-size:7px;letter-spacing:2.5px;color:var(--text-secondary);text-transform:uppercase;margin-top:2px}',
    ].join('\n');
    document.head.appendChild(_lrStyle);

    // ── Utilities ───────────────────────────────────────────
    function post(url, data) {
        var fd = new FormData();
        for (var k in data) fd.append(k, data[k]);
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function updateNavBadge(val) {
        var navBadge = document.querySelector('.sc-nav-badge');
        if (!navBadge) return;
        if (val > 0) {
            navBadge.innerHTML = '<i class="fas fa-coins"></i> ' + Number(val).toLocaleString();
            navBadge.style.display = '';
        } else {
            navBadge.style.display = 'none';
        }
    }

    function get(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function timeAgo(dateStr) {
        var diff = Math.floor((Date.now() - new Date(dateStr + ' UTC').getTime()) / 1000);
        if (diff < 60) return diff + 's';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        return Math.floor(diff / 3600) + 'h';
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ── LOBBY PAGE ──────────────────────────────────────────
    if (document.getElementById('rooms-tbody')) {
        initLobby();
    }

    function initLobby() {
        /* Bootstrap modals must sit on document.body so they are not clipped by
         * .lobby-content overflow:hidden / stacked under scanlines (z-index 9999). */
        document.querySelectorAll('.modal').forEach(function (m) {
            if (m.parentNode !== document.body) {
                document.body.appendChild(m);
            }
        });

        function refreshLobby() {
            get('/api/deathroll-1v1/lobby_state.php')
                .then(function (d) {
                    if (!d.ok) return;
                    renderRooms(d.data.public_rooms);
                    renderOnline(d.data.online_users);
                    var el = document.getElementById('lobby-active-games');
                    if (el) el.textContent = d.data.active_games;
                })
                .catch(function () {});
        }

        function renderRooms(rooms) {
            var tbody = document.getElementById('rooms-tbody');
            if (!rooms || rooms.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-white-50">No rooms available. Create one!</td></tr>';
                return;
            }
            var html = '';
            rooms.forEach(function (r) {
                var maxVal = r.initial_max || 1000;
                var rEntry = parseInt(r.entry_kp) || 100;
                html += '<tr>';
                html += '<td><code style="font-size:1.1em; letter-spacing:2px;">' + r.code + '</code></td>';
                html += '<td>' + escHtml(r.creator) + '</td>';
                html += '<td><span class="badge bg-secondary">' + Number(maxVal).toLocaleString() + '</span></td>';
                html += '<td><span class="badge bg-dark border border-info" style="font-size:.8rem;"><i class="fas fa-coins me-1" style="color:var(--knd-neon-blue);"></i>' + rEntry + ' KP</span></td>';
                html += '<td class="text-white-50">' + timeAgo(r.created_at) + ' ago</td>';
                html += '<td><button class="btn btn-sm btn-outline-neon btn-join-room" data-code="' + r.code + '"><i class="fas fa-sign-in-alt me-1"></i>Join</button></td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        }

        function renderOnline(users) {
            var list = document.getElementById('online-list');
            var countEl = document.getElementById('online-count');
            if (countEl) countEl.textContent = users.length;
            if (!users || users.length === 0) {
                list.innerHTML = '<li class="text-white-50">No one online.</li>';
                return;
            }
            var html = '';
            users.forEach(function (u) {
                var isMe = u.username === MY_USERNAME;
                html += '<li class="lastroll-online-item d-flex align-items-center mb-2">';
                html += '<span class="lastroll-online-dot me-2"></span>';
                html += '<span' + (isMe ? ' class="fw-bold"' : '') + '>' + escHtml(u.username) + (isMe ? ' (you)' : '') + '</span>';
                html += '</li>';
            });
            list.innerHTML = html;
        }

        function pingPresence() {
            post('/api/presence/ping.php', { csrf_token: CSRF }).catch(function () {});
        }

        document.getElementById('rooms-tbody').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-join-room');
            if (!btn) return;
            joinRoom(btn.getAttribute('data-code'));
        });

        // Entry KP payout preview
        var entryKpSelect = document.getElementById('create-entry-kp');
        var payoutPreview = document.getElementById('create-payout-preview');
        if (entryKpSelect && payoutPreview) {
            entryKpSelect.addEventListener('change', function () {
                var e = parseInt(entryKpSelect.value) || 100;
                payoutPreview.textContent = Math.floor(e * 1.5).toLocaleString();
            });
        }

        var createForm = document.getElementById('form-create-room');
        if (createForm) {
            createForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var vis = createForm.querySelector('[name=visibility]').value;
                var iMax = createForm.querySelector('[name=initial_max]');
                var eKp = createForm.querySelector('[name=entry_kp]');
                post('/api/deathroll-1v1/create_room.php', {
                    csrf_token: CSRF,
                    visibility: vis,
                    initial_max: iMax ? iMax.value : '1000',
                    entry_kp: eKp ? eKp.value : '100'
                }).then(function (d) {
                    if (d.ok) {
                        var url = d.data.join_url;
                        if (window.self !== window.top && url) {
                            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
                        }
                        window.location.href = url;
                    } else {
                        var el = document.getElementById('create-result');
                        el.innerHTML = '<div class="alert alert-danger mb-0">' + d.error.message + '</div>';
                        el.style.display = 'block';
                    }
                }).catch(function () {});
            });
        }

        var joinForm = document.getElementById('form-join-code');
        if (joinForm) {
            joinForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var code = joinForm.querySelector('[name=code]').value.toUpperCase();
                joinRoom(code);
            });
        }

        function joinRoom(code) {
            post('/api/deathroll-1v1/join_room.php', {
                csrf_token: CSRF,
                code: code
            }).then(function (d) {
                if (d.ok) {
                    if (typeof d.data.my_kp_balance !== 'undefined') updateNavBadge(d.data.my_kp_balance);
                    var embed = (window.self !== window.top);
                    var url = d.data.join_url || ('/death-roll-game.php?code=' + encodeURIComponent(code));
                    if (embed) {
                        url += (url.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
                    }
                    window.location.href = url;
                } else {
                    var alertEl = document.getElementById('join-alert');
                    if (alertEl) {
                        alertEl.innerHTML = '<div class="alert alert-danger mb-0">' + d.error.message + '</div>';
                        alertEl.style.display = 'block';
                    } else {
                        alert(d.error.message);
                    }
                }
            }).catch(function () {});
        }

        // ── My Rooms ──────────────────────────────────────────
        var btnMyRooms = document.getElementById('btn-myrooms');
        if (btnMyRooms) {
            btnMyRooms.addEventListener('click', loadMyRooms);
        }

        function loadMyRooms() {
            var container = document.getElementById('myrooms-list');
            var countEl = document.getElementById('myrooms-count');
            container.innerHTML = '<p class="text-white-50 text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</p>';

            get('/api/deathroll-1v1/my_rooms.php')
                .then(function (d) {
                    if (!d.ok) {
                        container.innerHTML = '<p class="text-danger text-center">' + (d.error ? d.error.message : 'Error') + '</p>';
                        return;
                    }
                    if (countEl) countEl.textContent = d.data.active_count + ' / ' + d.data.max_rooms;
                    renderMyRooms(d.data.rooms, container);
                })
                .catch(function () {
                    container.innerHTML = '<p class="text-danger text-center">Connection error</p>';
                });
        }

        function renderMyRooms(rooms, container) {
            if (!rooms || rooms.length === 0) {
                container.innerHTML = '<p class="text-white-50 text-center">No rooms yet. Create one!</p>';
                return;
            }
            var html = '<div class="list-group list-group-flush">';
            rooms.forEach(function (r) {
                var statusBadge = '';
                var actionBtn = '';
                var embedQ = (window.self !== window.top) ? '&embed=1' : '';
                if (r.status === 'waiting') {
                    statusBadge = '<span class="badge bg-warning text-dark">Waiting</span>';
                    actionBtn = '<a href="/death-roll-game.php?code=' + r.code + embedQ + '" class="btn btn-sm btn-outline-neon">Enter</a>';
                } else if (r.status === 'playing') {
                    statusBadge = '<span class="badge bg-success">Playing</span>';
                    actionBtn = '<a href="/death-roll-game.php?code=' + r.code + embedQ + '" class="btn btn-sm btn-neon-primary">Enter</a>';
                } else {
                    var reasonTag = '';
                    if (r.finished_reason === 'timeout') reasonTag = ' <small class="text-white-50">(timeout)</small>';
                    else if (r.finished_reason === 'abandoned') reasonTag = ' <small class="text-white-50">(abandoned)</small>';

                    if (r.result === 'won') {
                        statusBadge = '<span class="badge bg-info">Won</span>' + reasonTag;
                    } else if (r.result === 'lost') {
                        statusBadge = '<span class="badge bg-danger">Lost</span>' + reasonTag;
                    } else {
                        statusBadge = '<span class="badge bg-secondary">Finished</span>' + reasonTag;
                    }
                    actionBtn = '<a href="/death-roll-game.php?code=' + r.code + embedQ + '" class="btn btn-sm btn-outline-light">View</a>';
                }

                var oppText = r.opponent ? 'vs ' + escHtml(r.opponent) : '<span class="text-white-50">no opponent</span>';
                var visIcon = r.visibility === 'private' ? '<i class="fas fa-lock text-white-50 me-1" title="Private"></i>' : '';

                html += '<div class="list-group-item bg-transparent border-secondary d-flex justify-content-between align-items-center py-3">';
                html += '  <div>';
                html += '    <div class="d-flex align-items-center gap-2 mb-1">';
                html += '      ' + visIcon;
                html += '      <code style="font-size:1.05em; letter-spacing:2px;">' + r.code + '</code>';
                html += '      ' + statusBadge;
                html += '    </div>';
                html += '    <div class="small text-white-50">' + oppText + ' &middot; ' + timeAgo(r.last_activity) + ' ago</div>';
                html += '  </div>';
                html += '  <div>' + actionBtn + '</div>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        refreshLobby();
        setInterval(refreshLobby, 5000);
        pingPresence();
        setInterval(pingPresence, 15000);
    }

    // ── GAME PAGE ───────────────────────────────────────────
    if (typeof GAME_CODE !== 'undefined' && document.getElementById('btn-roll')) {
        initGame();
    }

    function initGame() {
        var lastRollCount = 0;
        var isRolling = false;
        var gameFinished = false;
        var rematchPolling = false;
        var rematchOffered = false;
        var countdownInterval = null;
        var serverSecondsLeft = null;
        var localCountdownStart = null;

        var btnRoll = document.getElementById('btn-roll');
        var btnRematchReq = document.getElementById('btn-rematch-request');
        var btnRematchAccept = document.getElementById('btn-rematch-accept');
        var btnRematchDecline = document.getElementById('btn-rematch-decline');
        var btnCopy = document.getElementById('btn-copy-link');
        var timerBar = document.getElementById('turn-timer-bar');
        var timerValue = document.getElementById('turn-timer-value');
        var timerProgress = document.getElementById('turn-timer-progress');

        // ── SVG HUD Dice state machine ──
        var diceWrap   = document.getElementById('dr-dice-wrap');
        var diceNum    = document.getElementById('dr-dice-num');
        var diceStatus = document.getElementById('dr-dice-status');
        var diceRolling = false;
        var diceTickTimer = null;
        var diceSafetyTimer = null;
        var diceState = 'idle';
        var rollStartTime = 0;
        var MIN_ROLL_MS = 800;
        var localCurrentMax = 1000;

        function setDiceValue(text) {
            if (!diceNum) return;
            diceNum.textContent = text;
            diceNum.classList.toggle('dr-critical', parseInt(text) === 1);
        }

        function startDiceRoll() {
            if (!diceWrap) return;
            diceRolling = true;
            diceState = 'rolling';
            rollStartTime = Date.now();
            diceWrap.className = 'dr-hud-card dr-rolling';
            if (diceStatus) diceStatus.textContent = 'Rolling\u2026';
            diceNum.classList.remove('dr-critical');

            var tickCount = 0;
            var maxTicks = 12;
            if (diceTickTimer) clearInterval(diceTickTimer);
            diceTickTimer = setInterval(function () {
                var m = localCurrentMax > 1 ? localCurrentMax : 1000;
                setDiceValue(Math.floor(Math.random() * m) + 1);
                if (window.LastRollSFX) window.LastRollSFX.playRollTick();
                tickCount++;
                if (tickCount >= maxTicks) { clearInterval(diceTickTimer); diceTickTimer = null; }
            }, 60);

            if (diceSafetyTimer) clearTimeout(diceSafetyTimer);
            diceSafetyTimer = setTimeout(function () { forceStopDice(); }, 10000);
        }

        function applyDiceResult(finalValue) {
            diceRolling = false;
            diceState = 'result';
            if (diceTickTimer) { clearInterval(diceTickTimer); diceTickTimer = null; }
            if (diceSafetyTimer) { clearTimeout(diceSafetyTimer); diceSafetyTimer = null; }
            diceWrap.className = 'dr-hud-card dr-result';
            setDiceValue(finalValue);
            var isCritical = parseInt(finalValue) === 1;
            if (window.LastRollSFX) window.LastRollSFX.playLand(isCritical);
            if (diceStatus) diceStatus.textContent = isCritical ? 'FATAL!' : 'Ready';
        }

        function stopDiceRoll(finalValue) {
            if (!diceWrap) return;
            if (finalValue !== '' && finalValue !== undefined && finalValue !== null) {
                var elapsed = Date.now() - rollStartTime;
                var delay = Math.max(0, MIN_ROLL_MS - elapsed);
                if (delay > 0) {
                    setTimeout(function () { applyDiceResult(finalValue); }, delay);
                } else {
                    applyDiceResult(finalValue);
                }
            } else {
                diceRolling = false;
                diceState = 'idle';
                if (diceTickTimer) { clearInterval(diceTickTimer); diceTickTimer = null; }
                if (diceSafetyTimer) { clearTimeout(diceSafetyTimer); diceSafetyTimer = null; }
                diceWrap.className = 'dr-hud-card';
                setDiceValue('\u2014');
                if (diceStatus) diceStatus.textContent = '';
            }
        }

        function forceStopDice() {
            diceRolling = false;
            diceState = 'idle';
            if (diceTickTimer) { clearInterval(diceTickTimer); diceTickTimer = null; }
            if (diceSafetyTimer) { clearTimeout(diceSafetyTimer); diceSafetyTimer = null; }
            if (diceWrap) diceWrap.className = 'dr-hud-card';
            setDiceValue('\u2014');
            if (diceStatus) diceStatus.textContent = '';
        }

        function opponentDicePop(value) {
            if (!diceWrap || diceRolling) return;
            diceState = 'opponent';
            diceWrap.className = 'dr-hud-card dr-opp-pop';
            setDiceValue(value || '\u2014');
            if (diceStatus) diceStatus.textContent = (TEXTS.rolled || 'rolled') + ' ' + value;
            var isCrit = parseInt(value) === 1;
            if (window.LastRollSFX) window.LastRollSFX.playLand(isCrit);
        }

        var TURN_DURATION = 8;
        var activeTurnDuration = TURN_DURATION;
        var anchorSecondsLeft = null;
        var anchorAt = null;
        var anchorTurnStartedAt = null;
        var anchorTurnUserId = null;

        function syncCountdown(s) {
            if (s.game.status !== 'playing' || !s.game.turn_started_at || s.game.turn_seconds_left === null || s.game.turn_seconds_left === undefined) {
                stopCountdown();
                return;
            }

            var dur = s.game.turn_duration || TURN_DURATION;
            activeTurnDuration = dur;
            var srvLeft = Math.max(0, Math.min(dur, s.game.turn_seconds_left));
            var turnChanged = (s.game.turn_started_at !== anchorTurnStartedAt) || (s.game.turn_user_id !== anchorTurnUserId);

            if (turnChanged) {
                anchorSecondsLeft = srvLeft;
                anchorAt = Date.now();
                anchorTurnStartedAt = s.game.turn_started_at;
                anchorTurnUserId = s.game.turn_user_id;
                timeoutPollSent = false;
            } else if (srvLeft < anchorSecondsLeft - ((Date.now() - anchorAt) / 1000)) {
                anchorSecondsLeft = srvLeft;
                anchorAt = Date.now();
            }

            if (!countdownInterval) {
                updateTimerDisplay();
                countdownInterval = setInterval(updateTimerDisplay, 200);
            }
        }

        function stopCountdown() {
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = null;
            anchorSecondsLeft = null;
            anchorAt = null;
            if (timerBar) timerBar.style.display = 'none';
        }

        var timeoutPollSent = false;

        function updateTimerDisplay() {
            if (anchorSecondsLeft === null || anchorAt === null || !timerBar) return;
            var elapsed = (Date.now() - anchorAt) / 1000;
            var dur = activeTurnDuration || TURN_DURATION;
            var left = Math.max(0, Math.min(dur, anchorSecondsLeft - elapsed));
            var secs = Math.ceil(left);

            timerBar.style.display = 'block';
            timerValue.textContent = secs;
            /* SFX tick on each second change */
            if (secs !== timerValue._lastSecs && secs <= 5) {
                if (window.LastRollSFX) window.LastRollSFX.playTimerTick(secs <= 3);
            }
            timerValue._lastSecs = secs;

            var pct = dur > 0 ? (left / dur) * 100 : 0;
            timerProgress.style.width = pct + '%';

            if (left <= 2) {
                timerValue.style.color = 'var(--color-danger)';
                timerProgress.style.background = 'var(--color-danger)';
            } else if (left <= 4) {
                timerValue.style.color = 'var(--color-warning)';
                timerProgress.style.background = 'var(--color-warning)';
            } else {
                timerValue.style.color = 'var(--accent-action)';
                timerProgress.style.background = 'var(--accent-action)';
            }

            if (left <= 0 && !timeoutPollSent && !gameFinished) {
                timeoutPollSent = true;
                pollState();
            }
        }

        function pollState() {
            get('/api/deathroll-1v1/state.php?code=' + GAME_CODE)
                .then(function (d) {
                    if (d.ok) {
                        if (typeof d.data.my_kp_balance !== 'undefined') updateNavBadge(d.data.my_kp_balance);
                        renderState(d.data);
                    }
                })
                .catch(function () {});
        }

        function renderState(s) {
            document.getElementById('p1-name').textContent = s.players.p1 ? s.players.p1.username : '—';
            document.getElementById('p2-name').textContent = s.players.p2 ? s.players.p2.username : (TEXTS.waitingP2 || 'Waiting...');

            var p1Card = document.getElementById('player1-card');
            var p2Card = document.getElementById('player2-card');
            p1Card.classList.remove('active');
            p2Card.classList.remove('active');
            p1Card.style.borderColor = 'color-mix(in srgb, var(--accent-action) 28%, transparent)';
            p2Card.style.borderColor = 'color-mix(in srgb, var(--accent-primary) 32%, transparent)';
            if (s.game.status === 'playing' && s.game.turn_user_id) {
                if (s.players.p1 && s.game.turn_user_id === s.players.p1.id) {
                    p1Card.classList.add('active');
                    p1Card.style.borderColor = 'var(--accent-action)';
                } else if (s.players.p2 && s.game.turn_user_id === s.players.p2.id) {
                    p2Card.classList.add('active');
                    p2Card.style.borderColor = 'var(--accent-primary)';
                }
            }

            var maxEl = document.getElementById('current-max-display');
            var newMax = parseInt(s.game.current_max) || 0;
            var oldMax = parseInt(maxEl.textContent) || newMax;
            
            /* Dramatic count-down animation when max changes */
            if (newMax !== oldMax && newMax < oldMax && newMax > 0) {
                animateMaxCounter(maxEl, oldMax, newMax, 600);
                /* Danger zone SFX */
                if (newMax <= 10 && oldMax > 10 && window.LastRollSFX) window.LastRollSFX.playDanger();
            } else {
                maxEl.textContent = s.game.current_max;
            }
            
            maxEl.classList.remove('danger', 'warning');
            var initMaxEl = document.getElementById('initial-max-value');
            if (initMaxEl && s.game.initial_max) {
                initMaxEl.textContent = Number(s.game.initial_max).toLocaleString();
            }
            if (s.game.current_max <= 10) {
                maxEl.classList.add('danger');
                maxEl.style.color = 'var(--color-danger)';
            } else if (s.game.current_max <= 50) {
                maxEl.classList.add('warning');
                maxEl.style.color = 'var(--color-warning)';
            } else {
                maxEl.style.color = '';
            }

            var statusEl = document.getElementById('game-status-text');
            if (s.game.status === 'waiting') {
                statusEl.textContent = TEXTS.waiting || 'Waiting for opponent';
            } else if (s.game.status === 'playing') {
                statusEl.textContent = TEXTS.playing || 'Game in progress';
            } else {
                if (s.game.finished_reason === 'timeout') {
                    statusEl.textContent = 'Timeout';
                } else if (s.game.finished_reason === 'abandoned') {
                    statusEl.textContent = TEXTS.abandoned || 'Game abandoned';
                } else {
                    statusEl.textContent = TEXTS.finished || 'Game over';
                }
            }

            // KP info
            var kpInfoEl = document.getElementById('game-kp-info');
            if (kpInfoEl && s.game.entry_kp) {
                kpInfoEl.style.display = '';
                var entryEl = document.getElementById('game-entry-kp');
                var payoutEl = document.getElementById('game-payout-kp');
                if (entryEl) entryEl.textContent = Number(s.game.entry_kp).toLocaleString();
                if (payoutEl) payoutEl.textContent = Number(s.game.payout_kp).toLocaleString();
            }

            syncCountdown(s);

            var turnInfo = document.getElementById('turn-info');
            if (s.game.status === 'waiting') {
                turnInfo.textContent = TEXTS.waitingP2 || 'Waiting for opponent...';
                btnRoll.disabled = true;
            } else if (s.game.status === 'playing' && !isRolling) {
                if (s.me.can_roll) {
                    turnInfo.innerHTML = '<span style="color: var(--accent-action);">' + (TEXTS.yourTurn || 'Your turn!') + '</span>';
                    btnRoll.disabled = false;
                } else {
                    var oppName = s.game.turn_user_id === (s.players.p1 ? s.players.p1.id : 0) ?
                        (s.players.p1 ? s.players.p1.username : '?') :
                        (s.players.p2 ? s.players.p2.username : '?');
                    turnInfo.textContent = oppName + "'s turn...";
                    btnRoll.disabled = true;
                }
            } else if (s.game.status === 'playing' && isRolling) {
                /* keep Rolling… UI; do not re-enable from stale poll */
            } else {
                turnInfo.textContent = '';
                btnRoll.disabled = true;
                btnRoll.style.display = 'none';
            }

            renderRolls(s.rolls);

            if (s.game.current_max) localCurrentMax = s.game.current_max;

            if (s.rolls.length > lastRollCount && lastRollCount > 0) {
                var latestRoll = s.rolls[s.rolls.length - 1];
                if (!diceRolling && latestRoll.username !== MY_USERNAME) {
                    opponentDicePop(latestRoll.roll_value);
                }
            }
            lastRollCount = s.rolls.length;

            // Dice state from polling (NEVER touch if rolling)
            if (!diceRolling && diceWrap) {
                if (s.game.status === 'finished') {
                    if (diceState === 'rolling') forceStopDice();
                } else if (s.game.status === 'playing' && s.me.can_roll) {
                    if (diceState !== 'rolling' && diceState !== 'result') {
                        if (diceStatus) diceStatus.textContent = 'Ready';
                    }
                } else if (s.game.status === 'playing' && !s.me.can_roll) {
                    if (diceState === 'idle' && diceStatus) diceStatus.textContent = '';
                }
            }

            if (s.game.status === 'finished' && !gameFinished) {
                gameFinished = true;
                forceStopDice();
                showGameOver(s);
                startRematchPolling();
            }
        }

        function renderRolls(rolls) {
            var container = document.getElementById('rolls-list');
            if (!rolls || rolls.length === 0) {
                container.innerHTML = '<p class="text-white-50 small">No rolls yet.</p>';
                return;
            }
            var html = '';
            rolls.forEach(function (r, i) {
                var isFatal = parseInt(r.roll_value) === 1;
                var color = isFatal ? 'var(--color-danger)' : (parseInt(r.roll_value) <= 10 ? 'var(--color-warning)' : 'var(--text-secondary)');
                var itemClass = 'd-flex justify-content-between align-items-center py-1 roll-item' + (isFatal ? ' fatal' : '') + ' border-bottom border-secondary';
                html += '<div class="' + itemClass + '">';
                html += '<span class="small">';
                html += '<strong>' + escHtml(r.username) + '</strong> ';
                html += (TEXTS.rolled || 'rolled') + ' ';
                html += '<span class="roll-value" style="color:' + color + '; font-weight:700;">' + r.roll_value + '</span>';
                html += ' <span class="text-white-50">/ ' + r.max_value + '</span>';
                html += '</span>';
                html += '<span class="text-white-50 small">#' + (i + 1) + '</span>';
                html += '</div>';
            });
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }

        // showLastRoll removed — dice HUD + roll history are sufficient

        function showGameOver(s) {
            stopCountdown();
            var panel = document.getElementById('game-over-panel');
            var icon = document.getElementById('game-over-icon');
            var text = document.getElementById('game-over-text');
            var iWon = s.game.winner_user_id === MY_USER_ID;
            var isTimeout = s.game.finished_reason === 'timeout';

            /* SFX */
            if (window.LastRollSFX) {
                if (iWon) window.LastRollSFX.playVictory();
                else window.LastRollSFX.playDefeat();
            }

            var kpMsg = '';
            if (s.game.charged && s.game.payout_kp) {
                kpMsg = iWon
                    ? '<div class="lr-go-kp win"><i class="fas fa-coins"></i> +' + Number(s.game.payout_kp).toLocaleString() + ' KP</div>'
                    : '<div class="lr-go-kp lose"><i class="fas fa-coins"></i> &minus;' + Number(s.game.entry_kp).toLocaleString() + ' KP</div>';
            }

            panel.classList.remove('win', 'lose');
            panel.classList.add(iWon ? 'win' : 'lose');

            /* Build epic result HTML */
            var mainText = isTimeout
                ? (iWon ? (TEXTS.timeoutOpponent || 'Opponent timed out!') : (TEXTS.timeoutYou || 'You lost by timeout!'))
                : (iWon ? (TEXTS.youWin || 'YOU WIN!') : (TEXTS.youLose || 'YOU LOSE!'));

            var accentColor = iWon ? 'var(--color-success)' : 'var(--color-danger)';
            var bgGrad = iWon
                ? 'color-mix(in srgb, var(--color-success) 8%, var(--bg-main))'
                : 'color-mix(in srgb, var(--color-danger) 8%, var(--bg-main))';

            panel.style.background = bgGrad;
            panel.style.border = '1px solid ' + (iWon ? 'color-mix(in srgb, var(--color-success) 28%, transparent)' : 'color-mix(in srgb, var(--color-danger) 28%, transparent)');
            panel.style.position = 'relative';
            panel.style.overflow = 'hidden';

            /* Top accent line */
            var accentLine = '<div style="position:absolute;top:0;left:0;right:0;height:2px;background:' + accentColor + ';box-shadow:none;transform:scaleX(0);animation:lrGoLine .5s cubic-bezier(.2,.8,.2,1) forwards;"></div>';

            /* Confetti for win */
            var confettiHtml = '';
            if (iWon) {
                var colors = [
                    kndCssVar('--accent-action'),
                    kndCssVar('--accent-primary'),
                    kndCssVar('--color-warning'),
                    kndCssVar('--color-success'),
                    kndCssVar('--text-primary')
                ].filter(function (x) { return x; });
                if (!colors.length) colors = ['#22d3ee', '#d6cfc7', '#ffffff'];
                for (var ci = 0; ci < 35; ci++) {
                    var c = colors[Math.floor(Math.random() * colors.length)];
                    confettiHtml += '<div style="position:absolute;left:' + (Math.random()*100) + '%;top:-8px;width:' + (3+Math.random()*5) + 'px;height:' + (5+Math.random()*8) + 'px;background:' + c + ';border-radius:' + (Math.random()>.5?'50%':'1px') + ';opacity:0;animation:lrConfetti ' + (2+Math.random()*2.5) + 's ease-in ' + (Math.random()*1) + 's forwards;"></div>';
                }
            }

            /* Result icon: only timeout (no trophy/skull on win/lose) */
            var iconBlock = '';
            if (isTimeout) {
                iconBlock =
                    '<div class="lr-go-icon" style="font-size:3.5rem;margin-bottom:8px;filter:none;opacity:0;animation:lrGoPop .5s cubic-bezier(.15,1.2,.3,1) .2s forwards;">' +
                    '⏰' +
                    '</div>';
            }

            /* Stats */
            var totalRolls = s.rolls ? s.rolls.length : 0;
            var myRolls = s.rolls ? s.rolls.filter(function(r){ return r.username === MY_USERNAME; }).length : 0;
            var lowestRoll = s.rolls && s.rolls.length ? Math.min.apply(null, s.rolls.map(function(r){ return parseInt(r.roll_value); })) : 0;

            panel.innerHTML = accentLine + confettiHtml +
                '<div style="position:relative;z-index:2;">' +
                iconBlock +
                /* Title */
                '<div style="font-family:var(--lr-FD,Orbitron,monospace);font-size:clamp(28px,5vw,42px);font-weight:900;letter-spacing:6px;color:' + accentColor + ';text-shadow:none;opacity:0;animation:lrGoSlam .4s cubic-bezier(.15,1,.3,1) .4s forwards;">' +
                    mainText +
                '</div>' +
                kpMsg +
                /* Stats row */
                '<div style="display:flex;gap:20px;justify-content:center;margin-top:16px;">' +
                    '<div class="lr-go-stat" style="animation-delay:.7s"><div class="lr-go-sv" id="lr-go-rolls">0</div><div class="lr-go-sl">TOTAL ROLLS</div></div>' +
                    '<div class="lr-go-stat" style="animation-delay:.85s"><div class="lr-go-sv" id="lr-go-myr">0</div><div class="lr-go-sl">YOUR ROLLS</div></div>' +
                    '<div class="lr-go-stat" style="animation-delay:1s"><div class="lr-go-sv" id="lr-go-low">0</div><div class="lr-go-sl">LOWEST ROLL</div></div>' +
                '</div>' +
                /* Buttons — appended after animation */
                '<div id="lr-go-btns" style="margin-top:18px;opacity:0;animation:lrGoFade .4s ease 1.6s forwards;">' +
                    '<button id="btn-rematch-request" class="btn lastroll-btn-primary" style="margin-right:8px;">' +
                        '<i class="fas fa-redo me-2"></i>' + (TEXTS.rematch || 'Rematch') +
                    '</button>' +
                    '<a href="' + (typeof lobbyUrl !== 'undefined' ? lobbyUrl : '/death-roll-lobby.php') + '" class="btn lastroll-btn-secondary">' +
                        '<i class="fas fa-arrow-left me-2"></i>' + (TEXTS.backLobby || 'Lobby') +
                    '</a>' +
                '</div>' +
                '<div id="rematch-status" class="mt-3" style="display:none;"></div>' +
                '</div>';

            panel.style.display = 'block';

            /* Animate stat count-ups */
            setTimeout(function () { animateCountUp('lr-go-rolls', totalRolls, 500); }, 700);
            setTimeout(function () { animateCountUp('lr-go-myr', myRolls, 500); }, 850);
            setTimeout(function () { animateCountUp('lr-go-low', lowestRoll, 500); }, 1000);

            /* Re-bind rematch button */
            setTimeout(function () {
                var newBtn = document.getElementById('btn-rematch-request');
                if (newBtn) {
                    btnRematchReq = newBtn;
                    newBtn.addEventListener('click', function () {
                        if (rematchOffered) return;
                        newBtn.disabled = true;
                        newBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                        post('/api/deathroll-1v1/rematch_offer.php', {
                            csrf_token: CSRF,
                            code: GAME_CODE
                        }).then(function (d) {
                            if (d.ok) {
                                rematchOffered = true;
                                newBtn.innerHTML = '<i class="fas fa-hourglass-half me-2"></i>' + (TEXTS.rematchWaiting || 'Waiting...');
                            } else {
                                alert(d.error.message);
                                newBtn.disabled = false;
                                newBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Rematch';
                            }
                        }).catch(function () {
                            newBtn.disabled = false;
                            newBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Rematch';
                        });
                    });
                }
            }, 100);
        }

        /* ── Animate counter from 0 to target ── */
        function animateCountUp(elId, target, duration) {
            var el = document.getElementById(elId);
            if (!el) return;
            var t0 = performance.now();
            function tick() {
                var p = Math.min((performance.now() - t0) / duration, 1);
                var ease = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * ease);
                if (p < 1) requestAnimationFrame(tick);
            }
            tick();
        }

        /* ── Animate max counter dramatically ── */
        function animateMaxCounter(el, from, to, duration) {
            var t0 = performance.now();
            function tick() {
                var p = Math.min((performance.now() - t0) / duration, 1);
                var ease = 1 - Math.pow(1 - p, 3);
                var val = Math.round(from + (to - from) * ease);
                el.textContent = val;
                /* Shake during count */
                if (p < 1) {
                    var shake = (1 - p) * 3;
                    el.style.transform = 'translateX(' + ((Math.random() - 0.5) * shake) + 'px)';
                    requestAnimationFrame(tick);
                } else {
                    el.textContent = to;
                    el.style.transform = '';
                }
            }
            tick();
        }

        // ── Rematch flow ─────────────────────────────────────
        var lastRematchOfferShownId = null;
        var rematchPopupInstance = null;
        var rematchAutoDeclineTimer = null;
        var REMATCH_COUNTDOWN = 10;

        function startRematchPolling() {
            if (rematchPolling) return;
            rematchPolling = true;
            pollRematchState();
            setInterval(pollRematchState, 1500);
        }

        function pollRematchState() {
            get('/api/deathroll-1v1/rematch_state.php?code=' + GAME_CODE)
                .then(function (d) {
                    if (!d.ok) return;
                    handleRematchState(d.data);
                })
                .catch(function () {});
        }

        function respondRematch(action) {
            return post('/api/deathroll-1v1/rematch_respond.php', {
                csrf_token: CSRF,
                code: GAME_CODE,
                action: action
            });
        }

        function closeRematchPopup() {
            if (rematchAutoDeclineTimer) { clearInterval(rematchAutoDeclineTimer); rematchAutoDeclineTimer = null; }
            if (rematchPopupInstance && typeof Swal !== 'undefined') {
                try { Swal.close(); } catch (e) {}
            }
            rematchPopupInstance = null;
            var offerPanel = document.getElementById('rematch-offer-panel');
            if (offerPanel) offerPanel.style.display = 'none';
        }

        function showRematchPopup(opponentName, offerId) {
            if (typeof Swal === 'undefined') return;
            if (offerId != null && offerId === lastRematchOfferShownId) return;
            if (offerId != null) lastRematchOfferShownId = offerId;

            var secondsLeft = REMATCH_COUNTDOWN;

            rematchPopupInstance = Swal.fire({
                title: '<span style="color:var(--accent-action);">&#8635; Rematch Request</span>',
                html: '<div style="font-size:1rem;color:var(--text-primary);">' +
                      '<strong>' + escHtml(opponentName) + '</strong> wants a rematch!' +
                      '</div>' +
                      '<div id="swal-rematch-cd" style="margin-top:12px;font-family:Orbitron,monospace;font-size:1.6rem;font-weight:900;color:var(--accent-action);">' + secondsLeft + '</div>' +
                      '<div style="font-size:0.7rem;color:var(--text-secondary);margin-top:4px;">Auto-decline in <span id="swal-cd-label">' + secondsLeft + '</span>s</div>',
                confirmButtonText: '<i class="fas fa-check me-1"></i> ACCEPT',
                cancelButtonText: '<i class="fas fa-times me-1"></i> DECLINE',
                showCancelButton: true,
                allowOutsideClick: false,
                allowEscapeKey: true,
                background: 'color-mix(in srgb, var(--bg-surface) 94%, transparent)',
                color: 'var(--text-primary)',
                backdrop: 'var(--overlay-scrim)',
                customClass: {
                    popup: 'dr-swal-popup',
                    confirmButton: 'dr-swal-accept',
                    cancelButton: 'dr-swal-decline'
                }
            }).then(function (result) {
                if (rematchAutoDeclineTimer) { clearInterval(rematchAutoDeclineTimer); rematchAutoDeclineTimer = null; }
                if (result.isConfirmed) {
                    respondRematch('accept').then(function (d) {
                        if (d.ok && d.data.join_url) {
                            var url = d.data.join_url;
                            if (window.self !== window.top) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
                            window.location.href = url;
                        }
                    });
                } else {
                    respondRematch('decline');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: 'info',
                            title: 'Rematch declined',
                            showConfirmButton: false,
                            timer: 2500,
                            background: 'color-mix(in srgb, var(--bg-surface) 92%, transparent)',
                            color: 'var(--text-primary)'
                        });
                    }
                }
            });

            rematchAutoDeclineTimer = setInterval(function () {
                secondsLeft--;
                var cdEl = document.getElementById('swal-rematch-cd');
                var cdLabel = document.getElementById('swal-cd-label');
                if (cdEl) cdEl.textContent = Math.max(0, secondsLeft);
                if (cdLabel) cdLabel.textContent = Math.max(0, secondsLeft);
                if (secondsLeft <= 3 && cdEl) cdEl.style.color = 'var(--color-danger)';
                if (secondsLeft <= 0) {
                    clearInterval(rematchAutoDeclineTimer);
                    rematchAutoDeclineTimer = null;
                    if (typeof Swal !== 'undefined') Swal.close();
                    respondRematch('decline');
                }
            }, 1000);
        }

        function handleRematchState(rs) {
            if (!rs.has_offer) return;

            var statusEl = document.getElementById('rematch-status');

            if (rs.offer_status === 'accepted' && rs.new_code) {
                closeRematchPopup();
                var url = '/death-roll-game.php?code=' + rs.new_code;
                if (window.self !== window.top) url += '&embed=1';
                window.location.href = url;
                return;
            }

            if (rs.offer_status === 'pending') {
                if (Number(rs.offered_to) === Number(MY_USER_ID)) {
                    showRematchPopup(rs.offered_by_username || 'Opponent', rs.offer_id);
                } else if (Number(rs.offered_by) === Number(MY_USER_ID)) {
                    rematchOffered = true;
                    if (btnRematchReq) {
                        btnRematchReq.disabled = true;
                        btnRematchReq.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + (TEXTS.rematchWaiting || 'Waiting...');
                    }
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="text-white-50"><i class="fas fa-hourglass-half me-2"></i>' + (TEXTS.rematchWaiting || 'Waiting for opponent to accept...') + '</span>';
                        statusEl.style.display = 'block';
                    }
                }
            }

            if (rs.offer_status === 'declined') {
                closeRematchPopup();
                lastRematchOfferShownId = null;
                if (statusEl) {
                    statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-times-circle me-2"></i>' + (TEXTS.rematchDeclined || 'Opponent declined.') + '</span>';
                    statusEl.style.display = 'block';
                }
                if (btnRematchReq) {
                    btnRematchReq.disabled = false;
                    btnRematchReq.innerHTML = '<i class="fas fa-redo me-2"></i>Rematch';
                }
                rematchOffered = false;
            }
        }

        // Request rematch
        if (btnRematchReq) {
            btnRematchReq.addEventListener('click', function () {
                if (rematchOffered) return;
                btnRematchReq.disabled = true;
                btnRematchReq.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                post('/api/deathroll-1v1/rematch_offer.php', {
                    csrf_token: CSRF,
                    code: GAME_CODE
                }).then(function (d) {
                    if (d.ok) {
                        rematchOffered = true;
                        btnRematchReq.innerHTML = '<i class="fas fa-hourglass-half me-2"></i>' + (TEXTS.rematchWaiting || 'Waiting...');
                        var statusEl = document.getElementById('rematch-status');
                        if (statusEl) {
                            statusEl.innerHTML = '<span class="text-white-50"><i class="fas fa-hourglass-half me-2"></i>' + (TEXTS.rematchWaiting || 'Waiting for opponent to accept...') + '</span>';
                            statusEl.style.display = 'block';
                        }
                    } else {
                        alert(d.error.message);
                        btnRematchReq.disabled = false;
                        btnRematchReq.innerHTML = '<i class="fas fa-redo me-2"></i>Rematch';
                    }
                }).catch(function () {
                    btnRematchReq.disabled = false;
                    btnRematchReq.innerHTML = '<i class="fas fa-redo me-2"></i>Rematch';
                });
            });
        }

        // Roll button
        btnRoll.addEventListener('click', function () {
            if (isRolling || btnRoll.disabled) return;
            isRolling = true;
            btnRoll.disabled = true;
            btnRoll.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Rolling...';
            if (window.LastRollSFX) window.LastRollSFX.playClick();
            startDiceRoll();

            post('/api/deathroll-1v1/roll.php', {
                csrf_token: CSRF,
                code: GAME_CODE
            }).then(function (d) {
                if (d.ok) {
                    if (typeof d.data.my_kp_balance !== 'undefined') updateNavBadge(d.data.my_kp_balance);
                    var ddata = d.data;
                    if (ddata.xp_delta > 0 && typeof showXpGain === 'function') {
                        var xpDelay = Math.max(0, MIN_ROLL_MS - (Date.now() - rollStartTime)) + 200;
                        setTimeout(function () { showXpGain(ddata.xp_delta); }, xpDelay);
                    }
                    if (ddata.level_up && ddata.old_level != null && ddata.new_level != null) {
                        var delay = Math.max(0, MIN_ROLL_MS - (Date.now() - rollStartTime)) + 300;
                        var oldLvl = Number(ddata.old_level);
                        var newLvl = Number(ddata.new_level);
                        if (typeof updateNavLevelBadge === 'function') {
                            setTimeout(function () { updateNavLevelBadge(newLvl); }, delay);
                        }
                        if (typeof showLevelUp === 'function') {
                            setTimeout(function () { showLevelUp(oldLvl, newLvl); }, delay);
                        }
                        if (typeof kndToast === 'function') {
                            setTimeout(function () { kndToast('success', 'Level Up: ' + oldLvl + ' → ' + newLvl); }, delay + 400);
                        }
                    } else if (ddata.level && typeof updateNavLevelBadge === 'function') {
                        updateNavLevelBadge(ddata.level);
                    }
                    var myLastRoll = '';
                    if (d.data.rolls && d.data.rolls.length > 0) {
                        var last = d.data.rolls[d.data.rolls.length - 1];
                        if (last.username === MY_USERNAME) myLastRoll = last.roll_value;
                    }
                    var elapsed = Date.now() - rollStartTime;
                    var delay = Math.max(0, MIN_ROLL_MS - elapsed);
                    setTimeout(function () {
                        isRolling = false;
                        btnRoll.innerHTML = '<i class="fas fa-dice me-2"></i>ROLL!';
                        renderState(d.data);
                    }, delay);
                    stopDiceRoll(myLastRoll);
                } else {
                    isRolling = false;
                    btnRoll.innerHTML = '<i class="fas fa-dice me-2"></i>ROLL!';
                    stopDiceRoll('');
                    if (typeof kndToast === 'function') kndToast('error', d.error.message);
                    else alert(d.error.message);
                    pollState();
                }
            }).catch(function () {
                isRolling = false;
                stopDiceRoll('');
                btnRoll.innerHTML = '<i class="fas fa-dice me-2"></i>ROLL!';
                pollState();
            });
        });

        // Copy link
        if (btnCopy) {
            btnCopy.addEventListener('click', function () {
                var url = window.location.origin + '/death-roll-game.php?code=' + GAME_CODE;
                navigator.clipboard.writeText(url).then(function () {
                    btnCopy.innerHTML = '<i class="fas fa-check me-1"></i>' + (TEXTS.copied || 'Copied!');
                    setTimeout(function () {
                        btnCopy.innerHTML = '<i class="fas fa-link me-1"></i>Share';
                    }, 2000);
                });
            });
        }

        function pingPresence() {
            post('/api/presence/ping.php', { csrf_token: CSRF }).catch(function () {});
        }

        // Start
        pollState();
        setInterval(pollState, 1500);
        pingPresence();
        setInterval(pingPresence, 15000);
    }
})();

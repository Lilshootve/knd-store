(function () {
    'use strict';

    var EMBEDABLE_GAMES = ['collection', 'mind-wars', 'knowledge-duel', 'drop-chamber', 'lastroll', 'insight'];

    var GAMES = {
        'collection': {
            title: 'Avatar Collection',
            desc: 'Explore all Mind Wars avatars, stats, classes, rarities, and combat kits.',
            icon: 'fa-layer-group',
            url: '/tools/cards/'
        },
        'mind-wars': {
            title: 'Mind Wars',
            desc: 'PVE arena. Select your avatar, battle AI, earn XP, Knowledge Energy, and Rank. Attack, Defend, use Abilities and Specials.',
            icon: 'fa-fist-raised',
            url: '/games/mind-wars/mind-wars-arena.php'
        },
        'knowledge-duel': {
            title: 'Knowledge Duel',
            desc: 'PvE trivia battles with your avatar. Answer 5 questions, defeat the bot, and earn XP, Knowledge Energy, and Rank.',
            icon: 'fa-brain',
            url: '/games/knowledge-duel.php'
        },
        'drop-chamber': {
            title: 'KND Drop Chamber',
            desc: 'Open capsules and discover rewards. Seasonal loot pools with Common, Rare, Epic, and Legendary drops.',
            icon: 'fa-box-open',
            url: '/games/knd-neural-link/drops.php'
        },
        'lastroll': {
            title: 'KND LastRoll',
            desc: '1v1 Death Roll — roll down from max to 1. The one who rolls 1 loses. Real-time rooms, 8s turn timer, rematch system.',
            icon: 'fa-dice-d20',
            url: '/death-roll-lobby.php'
        },
        'insight': {
            title: 'KND Insight',
            desc: 'Predict if the next number will be above or under the threshold. Fast rounds, pure probability.',
            icon: 'fa-eye',
            url: '/above-under.php'
        },
        'leaderboard': {
            title: 'Leaderboard',
            desc: 'Global rankings by XP — season standings and Hall of Fame. Compete for the top spot.',
            icon: 'fa-trophy',
            url: '/leaderboard.php'
        },
        'recent-battles': {
            title: 'Recent Battles',
            desc: 'View recent Mind Wars battles and global activity feed.',
            icon: 'fa-scroll',
            url: '/games/mind-wars/mind-wars-arena.php'
        }
    };

    var titleEl = document.getElementById('arena-game-title');
    var contentEl = document.getElementById('arena-game-content');
    var promptEl = document.getElementById('arena-select-prompt');
    var launcherEl = document.getElementById('arena-launcher-card');
    var iframeWrap = document.getElementById('arena-game-iframe-wrap');
    var gameIframe = document.getElementById('arena-game-iframe');
    var leaderboardList = document.getElementById('arena-leaderboard-list');

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function showGame(gameId) {
        var game = GAMES[gameId];
        if (!game) return;

        document.querySelectorAll('.arena-nav-item').forEach(function (el) {
            el.classList.remove('arena-nav-item-active');
            el.removeAttribute('aria-current');
            if (el.getAttribute('data-game') === gameId) {
                el.classList.add('arena-nav-item-active');
                el.setAttribute('aria-current', 'page');
            }
        });

        if (titleEl) titleEl.textContent = game.title;

        if (promptEl) promptEl.style.display = 'none';

        var isEmbedable = EMBEDABLE_GAMES.indexOf(gameId) >= 0;
        if (isEmbedable && iframeWrap && gameIframe) {
            if (launcherEl) launcherEl.classList.add('arena-hidden');
            iframeWrap.classList.remove('arena-hidden');
            gameIframe.src = game.url + '?embed=1';
        } else {
            if (iframeWrap) iframeWrap.classList.add('arena-hidden');
            if (gameIframe) gameIframe.src = 'about:blank';
            if (launcherEl) {
                launcherEl.classList.remove('arena-hidden');
                launcherEl.innerHTML =
                    '<div class="arena-launcher-icon"><i class="fas ' + escapeHtml(game.icon) + '"></i></div>' +
                    '<p class="arena-launcher-desc">' + escapeHtml(game.desc) + '</p>' +
                    '<a href="' + escapeHtml(game.url) + '" class="arena-launcher-cta">' +
                    '<i class="fas fa-play"></i> Enter' +
                    '</a>';
            }
        }

        if (typeof history !== 'undefined' && history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('game', gameId);
            history.replaceState({ game: gameId }, '', url.toString());
        }
    }

    function showSelectPrompt() {
        document.querySelectorAll('.arena-nav-item').forEach(function (el) {
            el.classList.remove('arena-nav-item-active');
            el.removeAttribute('aria-current');
        });

        if (titleEl) titleEl.textContent = 'Select a game';
        if (promptEl) promptEl.style.display = 'block';
        if (launcherEl) launcherEl.classList.add('arena-hidden');
        if (iframeWrap) iframeWrap.classList.add('arena-hidden');
        if (gameIframe) gameIframe.src = 'about:blank';

        if (typeof history !== 'undefined' && history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('game');
            history.replaceState({}, '', url.toString());
        }
    }

    function initNav() {
        document.querySelectorAll('.arena-nav-item').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var gameId = el.getAttribute('data-game');
                if (gameId) showGame(gameId);
            });
        });

        // Hero featured cards
        document.querySelectorAll('.arena-featured-card[data-game], .arena-featured-mini[data-game]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var gameId = el.getAttribute('data-game');
                if (gameId) showGame(gameId);
            });
        });

        document.querySelectorAll('.arena-hub-game-trigger[data-game]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var gameId = el.getAttribute('data-game');
                if (gameId) showGame(gameId);
            });
        });

        var params = new URLSearchParams(window.location.search);
        var gameParam = params.get('game');
        if (gameParam && GAMES[gameParam]) {
            showGame(gameParam);
        }
    }

    function loadLeaderboard() {
        if (!leaderboardList) return;

        fetch('/api/leaderboard/state.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok || !d.data) {
                    leaderboardList.innerHTML = '<li class="text-white-50 small">Unable to load leaderboard.</li>';
                    return;
                }

                var top = (d.data.topSeason || []).slice(0, 10);
                var currentUserId = window.ARENA_USER_ID || 0;

                if (top.length === 0) {
                    leaderboardList.innerHTML = '<li class="text-white-50 small">No rankings yet.</li>';
                    return;
                }

                leaderboardList.innerHTML = top.map(function (entry) {
                    var isMe = currentUserId && entry.user_id === currentUserId;
                    var cls = 'arena-leaderboard-item' + (isMe ? ' arena-current-user' : '');
                    var rank = entry.rank || 0;
                    var rankAttr = (rank <= 3) ? ' data-rank="' + rank + '"' : '';
                    return '<li class="' + cls + '"' + rankAttr + '>' +
                        '<span class="arena-leaderboard-pos">#' + rank + '</span>' +
                        '<span class="arena-leaderboard-name">' + escapeHtml(entry.username || '?') + '</span>' +
                        '<span class="arena-leaderboard-xp">' + (entry.xp || 0).toLocaleString() + '</span>' +
                        '</li>';
                }).join('');
            })
            .catch(function () {
                leaderboardList.innerHTML = '<li class="text-white-50 small">Error loading leaderboard.</li>';
            });
    }

    var activityPollInterval = 15000; /* 15 seconds */
    var activityPollTimer = null;

    function renderActivityFeed(activities, listEl, loadingEl) {
        if (loadingEl) loadingEl.classList.add('arena-hidden');
        if (!activities || activities.length === 0) {
            listEl.innerHTML = '<div class="arena-activity-item"><span class="arena-activity-icon"><i class="fas fa-info-circle"></i></span><span class="arena-activity-text">No recent activity yet. Be the first to play!</span></div>';
            return;
        }
        listEl.innerHTML = activities.map(function (a) {
            var icon = a.icon || 'fa-circle';
            return '<div class="arena-activity-item">' +
                '<span class="arena-activity-icon"><i class="fas ' + escapeHtml(icon) + '"></i></span>' +
                '<span class="arena-activity-text">' + (a.text || '') + '</span>' +
                '<span class="arena-activity-time">' + escapeHtml(a.time_ago || '') + '</span>' +
                '</div>';
        }).join('');
    }

    function loadActivityFeed(isPolling) {
        var listEl = document.getElementById('arena-activity-list');
        var loadingEl = document.getElementById('arena-activity-loading');
        if (!listEl) return;

        fetch('/api/arena/activity.php?limit=8&t=' + Date.now(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok || !d.data) return;
                renderActivityFeed(d.data.activities || [], listEl, loadingEl);
                if (isPolling && listEl.classList) {
                    listEl.classList.add('arena-activity-updated');
                    setTimeout(function () { listEl.classList.remove('arena-activity-updated'); }, 400);
                }
            })
            .catch(function () {
                if (!isPolling && loadingEl) {
                    loadingEl.classList.add('arena-hidden');
                    listEl.innerHTML = '<div class="arena-activity-item"><span class="arena-activity-icon"><i class="fas fa-wifi"></i></span><span class="arena-activity-text">Activity feed unavailable.</span></div>';
                }
            });
    }

    function startActivityPolling() {
        if (activityPollTimer) return;
        activityPollTimer = setInterval(function () {
            if (document.hidden) return;
            loadActivityFeed(true);
        }, activityPollInterval);
    }

    function stopActivityPolling() {
        if (activityPollTimer) {
            clearInterval(activityPollTimer);
            activityPollTimer = null;
        }
    }

    function loadOnlineCount() {
        var el = document.getElementById('arena-online-count');
        if (!el || !window.ARENA_USER_ID) return;
        fetch('/api/mind-wars/online_players.php?limit=1', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok && d.data && d.data.players && Array.isArray(d.data.players)) {
                    el.textContent = d.data.players.length > 0 ? Math.min(99, d.data.players.length) : '—';
                }
            })
            .catch(function () { /* ignore */ });
    }

    function initArena() {
        initNav();
        loadLeaderboard();
        loadActivityFeed(false);
        loadOnlineCount();
        startActivityPolling();
    }

    if (document.hidden !== undefined) {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopActivityPolling();
            } else {
                loadActivityFeed(true);
                startActivityPolling();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initArena);
    } else {
        initArena();
    }
})();

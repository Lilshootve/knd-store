(function () {
    'use strict';

    var seasonNameEl = document.getElementById('lb-season-name');
    var countdownEl  = document.getElementById('lb-countdown');
    var seasonTbody  = document.getElementById('lb-season-tbody');
    var hofTbody     = document.getElementById('lb-hof-tbody');
    var yourRankEl   = document.getElementById('lb-your-rank');
    var mySeasonEl   = document.getElementById('lb-my-season');
    var myAlltimeEl  = document.getElementById('lb-my-alltime');

    var endsAt = null;
    var countdownInterval = null;

    function row(r, isMe) {
        var tr = document.createElement('tr');
        if (isMe) tr.classList.add('table-active');
        var wr = r.winrate != null ? r.winrate + '%' : '—';
        var wl = (r.wins != null && r.losses != null) ? r.wins + '-' + r.losses : '—';
        tr.innerHTML =
            '<td><strong>' + r.rank + '</strong></td>' +
            '<td>' + escapeHtml(r.username) + '</td>' +
            '<td>' + (r.level || 1) + '</td>' +
            '<td>' + (r.xp || 0).toLocaleString() + '</td>' +
            '<td>' + wl + '</td>' +
            '<td>' + wr + '</td>';
        return tr;
    }

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderTable(tbody, list, emptyMsg) {
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-white-50 py-4">' + emptyMsg + '</td></tr>';
            return;
        }
        list.forEach(function (r) { tbody.appendChild(row(r, false)); });
    }

    function updateCountdown() {
        if (!countdownEl || !endsAt) return;
        var diff = Math.max(0, Math.floor((endsAt - Date.now()) / 1000));
        if (diff <= 0) {
            countdownEl.textContent = 'Season ended';
            if (countdownInterval) clearInterval(countdownInterval);
            return;
        }
        var d = Math.floor(diff / 86400);
        var h = Math.floor((diff % 86400) / 3600);
        var m = Math.floor((diff % 3600) / 60);
        var s = diff % 60;
        countdownEl.textContent = d + 'd ' + h + 'h ' + m + 'm ' + s + 's';
    }

    function load() {
        fetch('/api/leaderboard/state.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok || !d.data) return;

                var s = d.data.season;
                if (s) {
                    if (seasonNameEl) seasonNameEl.textContent = s.name;
                    if (s.ends_at) {
                        endsAt = new Date(s.ends_at + 'Z');
                        updateCountdown();
                        if (!countdownInterval) countdownInterval = setInterval(updateCountdown, 1000);
                    }
                }

                renderTable(seasonTbody, d.data.topSeason, 'No data yet — play to appear here.');
                renderTable(hofTbody, d.data.topAllTime, 'No data yet — play to appear here.');

                if (yourRankEl && d.data.myRankSeason) {
                    yourRankEl.style.display = 'block';
                    var mr = d.data.myRankSeason;
                    if (mySeasonEl) mySeasonEl.textContent = '#' + mr.rank + ' · ' + (mr.xp || 0).toLocaleString() + ' XP';
                }
                if (yourRankEl && d.data.myRankAllTime) {
                    yourRankEl.style.display = 'block';
                    var ma = d.data.myRankAllTime;
                    if (myAlltimeEl) myAlltimeEl.textContent = '#' + ma.rank + ' · ' + (ma.xp || 0).toLocaleString() + ' XP';
                }
                if (yourRankEl && !d.data.myRankSeason && !d.data.myRankAllTime) {
                    yourRankEl.style.display = 'block';
                    if (mySeasonEl) mySeasonEl.textContent = '—';
                    if (myAlltimeEl) myAlltimeEl.textContent = '—';
                }
            })
            .catch(function () {
                if (seasonTbody) seasonTbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error loading leaderboard.</td></tr>';
                if (hofTbody) hofTbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error loading leaderboard.</td></tr>';
            });
    }

    load();
})();

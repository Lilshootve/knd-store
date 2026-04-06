<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_xp.php';

define('LB_CACHE_SEC', 15);
define('LB_TOP_LIMIT', 100);

try {
    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_FAIL', 'Database error.', 500);

    $userId = isLoggedIn() ? current_user_id() : null;
    $season = null;
    $seasonInfo = null;
    $topSeason = [];
    $topAllTime = [];
    $myRankSeason = null;
    $myRankAllTime = null;
    $lastError = null;

    // Season info (tables may not exist yet)
    try {
        $season = ensure_active_season($pdo);
    } catch (\Throwable $e) {
        error_log('leaderboard ensure_season: ' . $e->getMessage());
        $lastError = $e->getMessage();
    }
    $seasonInfo = null;
    if ($season) {
        $seasonInfo = [
            'name'     => $season['name'],
            'code'     => $season['code'],
            'starts_at'=> $season['starts_at'],
            'ends_at'  => $season['ends_at'],
        ];
    }

    // Simple file cache for top lists
    $cacheDir = sys_get_temp_dir() . '/knd_lb';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . '/state_v2.json';
    $useCache = false;
    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < LB_CACHE_SEC) {
            $cached = @json_decode(file_get_contents($cacheFile), true);
            $cachedSeason = $cached['topSeason'] ?? [];
            $cachedHof = $cached['topAllTime'] ?? [];
            if ($cached && (count($cachedSeason) > 0 || count($cachedHof) > 0)) {
                $topSeason = $cachedSeason;
                $topAllTime = $cachedHof;
                $useCache = true;
            }
        }
    }

    if (!$useCache) {
        // Season (requires knd_seasons + knd_season_stats)
        if ($season) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT s.user_id, s.xp_earned, s.matches_played, s.wins, s.losses,
                            u.username, COALESCE(kux.xp, 0) AS total_xp
                     FROM knd_season_stats s
                     JOIN users u ON u.id = s.user_id
                     LEFT JOIN knd_user_xp kux ON kux.user_id = s.user_id
                     WHERE s.season_id = ?
                     ORDER BY s.xp_earned DESC
                     LIMIT " . (int) LB_TOP_LIMIT
                );
                $stmt->execute([$season['id']]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $i => $r) {
                    $w = (int) ($r['wins'] ?? 0);
                    $l = (int) ($r['losses'] ?? 0);
                    $wr = ($w + $l) > 0 ? round(100 * $w / ($w + $l), 1) : null;
                    $totalXp = (int) ($r['total_xp'] ?? 0);
                    $topSeason[] = [
                        'rank'   => $i + 1,
                        'user_id'=> (int) $r['user_id'],
                        'username'=> $r['username'],
                        'level'  => xp_calc_level($totalXp),
                        'xp'     => (int) $r['xp_earned'],
                        'wins'   => $w,
                        'losses' => $l,
                        'winrate'=> $wr,
                    ];
                }
            } catch (\Throwable $e) {
                error_log('leaderboard season: ' . $e->getMessage());
                $lastError = $e->getMessage();
            }
        }

        // Hall of Fame: use user_xp (fallback: simple query + fetch usernames)
        try {
            $stmt = $pdo->prepare(
                "SELECT ux.user_id, ux.xp, u.username
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 WHERE ux.xp > 0
                 ORDER BY ux.xp DESC
                 LIMIT " . (int) LB_TOP_LIMIT
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $stmt = $pdo->prepare(
                    "SELECT user_id, xp FROM user_xp WHERE xp > 0 ORDER BY xp DESC LIMIT " . (int) LB_TOP_LIMIT
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$r) {
                    $us = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $us->execute([$r['user_id']]);
                    $r['username'] = $us->fetchColumn() ?: '?';
                }
                unset($r);
            }
            $allTimeStats = [];
            if ($season) {
                try {
                    $stmt2 = $pdo->prepare("SELECT user_id, wins, losses FROM knd_season_stats WHERE season_id = ?");
                    $stmt2->execute([$season['id']]);
                    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                        $allTimeStats[(int)$r['user_id']] = [(int)$r['wins'], (int)$r['losses']];
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            foreach ($rows as $i => $r) {
                $uid = (int) $r['user_id'];
                $xp = (int) $r['xp'];
                $wl = $allTimeStats[$uid] ?? [0, 0];
                $wr = ($wl[0] + $wl[1]) > 0 ? round(100 * $wl[0] / ($wl[0] + $wl[1]), 1) : null;
                $topAllTime[] = [
                    'rank'   => $i + 1,
                    'user_id'=> $uid,
                    'username'=> $r['username'],
                    'level'  => xp_calc_level($xp),
                    'xp'     => $xp,
                    'wins'   => $wl[0],
                    'losses' => $wl[1],
                    'winrate'=> $wr,
                ];
            }
        } catch (\Throwable $e) {
            error_log('leaderboard hall of fame: ' . $e->getMessage());
            $lastError = $e->getMessage();
        }

        if ($cacheDir && is_writable($cacheDir) && (count($topSeason) > 0 || count($topAllTime) > 0)) {
            @file_put_contents($cacheFile, json_encode(['topSeason' => $topSeason, 'topAllTime' => $topAllTime]));
        }
    }

    if ($userId) {
        if ($season) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT xp_earned, wins, losses FROM knd_season_stats WHERE season_id = ? AND user_id = ?"
                );
                $stmt->execute([$season['id'], $userId]);
                $my = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($my) {
                    $stmt = $pdo->prepare(
                        "SELECT 1 + COUNT(*) AS r FROM knd_season_stats WHERE season_id = ? AND xp_earned > ?"
                    );
                    $stmt->execute([$season['id'], (int)$my['xp_earned']]);
                    $myRankSeason = [
                        'rank'   => (int) $stmt->fetchColumn(),
                        'xp'     => (int) $my['xp_earned'],
                        'wins'   => (int) $my['wins'],
                        'losses' => (int) $my['losses'],
                        'winrate'=> ($my['wins'] + $my['losses']) > 0
                            ? round(100 * $my['wins'] / ($my['wins'] + $my['losses']), 1) : null,
                    ];
                }
            } catch (\Throwable $e) {
                error_log('leaderboard my season rank: ' . $e->getMessage());
            }
        }

        try {
            $stmt = $pdo->prepare("SELECT xp FROM user_xp WHERE user_id = ?");
            $stmt->execute([$userId]);
            $my = $stmt->fetch(PDO::FETCH_ASSOC);
            $myXp = (int) ($my['xp'] ?? 0);
            if ($myXp > 0) {
                $stmt = $pdo->prepare("SELECT 1 + COUNT(*) AS r FROM user_xp WHERE xp > ?");
                $stmt->execute([$myXp]);
                $myRankAllTime = [
                    'rank'  => (int) $stmt->fetchColumn(),
                    'xp'    => $myXp,
                    'level' => xp_calc_level($myXp),
                ];
                if ($season) {
                    try {
                        $stmt = $pdo->prepare(
                            "SELECT wins, losses FROM knd_season_stats WHERE season_id = ? AND user_id = ?"
                        );
                        $stmt->execute([$season['id'], $userId]);
                        $s = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($s) {
                            $myRankAllTime['wins'] = (int)$s['wins'];
                            $myRankAllTime['losses'] = (int)$s['losses'];
                            $myRankAllTime['winrate'] = ($s['wins'] + $s['losses']) > 0
                                ? round(100 * $s['wins'] / ($s['wins'] + $s['losses']), 1) : null;
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
            }
        } catch (\Throwable $e) {
            error_log('leaderboard my alltime rank: ' . $e->getMessage());
        }
    }

    $data = [
        'season'       => $seasonInfo,
        'topSeason'    => $topSeason ?? [],
        'topAllTime'   => $topAllTime ?? [],
        'myRankSeason' => $myRankSeason,
        'myRankAllTime'=> $myRankAllTime,
    ];
    if (!empty($_GET['debug'])) {
        $uxCount = null;
        if ($pdo) {
            try { $uxCount = (int) $pdo->query("SELECT COUNT(*) FROM user_xp")->fetchColumn(); } catch (\Throwable $e) { $uxCount = 'err: ' . $e->getMessage(); }
        }
        $data['_debug'] = array_filter([
            'last_error' => $lastError,
            'user_xp_rows' => $uxCount,
        ]);
    }
    json_success($data);
} catch (\Throwable $e) {
    error_log('leaderboard state error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}

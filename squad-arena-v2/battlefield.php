<?php
declare(strict_types=1);
/**
 * Mind Wars Squad Arena v2 — authenticated battlefield with DB-driven squads.
 */
require_once __DIR__ . '/../config/bootstrap.php';

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/mw_lobby.php';
require_once BASE_PATH . '/includes/favicon_links.php';

require_login();

$pdo = getDBConnection();
$userId = (int) (current_user_id() ?? ($_SESSION['user_id'] ?? 0));

$L = [
    'user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0],
    'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0],
    'season' => [],
    'ranking' => [],
    'selected_avatar' => null,
    'hero_image_url' => null,
    'hero_model_url' => null,
];
if ($pdo && $userId > 0) {
    try {
        $L = mw_build_lobby_data_payload($pdo, $userId);
    } catch (Throwable $e) {
        error_log('battlefield.php mw_build_lobby_data_payload: ' . $e->getMessage());
    }
}

$levelsCss = __DIR__ . '/../assets/css/levels.css';
$lobbyCss = __DIR__ . '/../games/mind-wars/lobby.css';
$levelsCssV = is_file($levelsCss) ? filemtime($levelsCss) : 0;
$lobbyCssV = is_file($lobbyCss) ? filemtime($lobbyCss) : 0;

$boot = null;
$bootErr = 'no_engagement';

if ($pdo && $userId > 0) {
    $eng = $_SESSION['squad_arena_v2_engagement'] ?? null;
    $active = $_SESSION['squad_arena_v2_active'] ?? null;
    if (is_array($eng) && isset($eng['ally_mw_ids']) && is_array($eng['ally_mw_ids'])
        && is_array($active) && isset($active['ally_mw_ids']) && is_array($active['ally_mw_ids'])
        && $eng['ally_mw_ids'] === $active['ally_mw_ids']) {
        require_once BASE_PATH . '/squad-arena-v2/includes/squad_battle_bootstrap.php';
        $cached = $active['battle_payload'] ?? null;
        if (squad_v2_battle_payload_matches_squad(is_array($cached) ? $cached : null, $eng['ally_mw_ids'])) {
            /** @var array{ok:true,allies:list<mixed>,enemies:list<mixed>} $result */
            $result = [
                'ok' => true,
                'allies' => $cached['allies'],
                'enemies' => $cached['enemies'],
            ];
        } else {
            $result = squad_v2_build_battle_payload($pdo, $userId, $eng['ally_mw_ids']);
        }
        if ($result['ok'] ?? false) {
            $token = (string) ($active['battle_token'] ?? '');
            if ($token === '') {
                $bootErr = 'no_battle_token';
                unset($_SESSION['squad_arena_v2_engagement']);
            } else {
                $squadwarsApi = null;
                try {
                    require_once BASE_PATH . '/games/squadwars/bootstrap.php';
                    require_once BASE_PATH . '/games/squadwars/infrastructure/SquadBattleRepository.php';
                    require_once __DIR__ . '/includes/squad_v2_squadwars_bridge.php';
                    $swState = squad_v2_squadwars_state_from_payload(
                        $userId,
                        $result['allies'] ?? [],
                        $result['enemies'] ?? []
                    );
                    $swRepo = new SquadBattleRepository($pdo);
                    $swRepo->createBattle($swState);
                    $swBattleToken = (string) ($swState['battleId'] ?? '');
                    if ($swBattleToken !== '') {
                        $_SESSION['squad_arena_v2_active']['squadwars_battle_token'] = $swBattleToken;
                        $squadwarsApi = [
                            'enabled' => true,
                            'battleToken' => $swBattleToken,
                            'getBattleStateUrl' => '/games/squadwars/api/get_battle_state.php',
                            'submitRoundUrl' => '/games/squadwars/api/submit_round.php',
                        ];
                    }
                } catch (Throwable $e) {
                    error_log('battlefield.php squadwars persist skipped: ' . $e->getMessage());
                }

                $boot = [
                    'allies' => $result['allies'],
                    'enemies' => $result['enemies'],
                    'mode' => (string) ($eng['mode'] ?? 'pve'),
                    'battleToken' => $token,
                    'csrfToken' => csrf_token(),
                    'submitResultUrl' => '/squad-arena-v2/api/submit_result.php',
                    'squadwars' => $squadwarsApi,
                ];
                unset($_SESSION['squad_arena_v2_engagement']);
            }
        } else {
            $bootErr = (string) ($result['error'] ?? 'BUILD_FAILED');
            unset($_SESSION['squad_arena_v2_engagement'], $_SESSION['squad_arena_v2_active']);
        }
    } elseif (is_array($eng) && isset($eng['ally_mw_ids'])) {
        $bootErr = 'session_mismatch';
        unset($_SESSION['squad_arena_v2_engagement'], $_SESSION['squad_arena_v2_active']);
    }
}

if (!$boot) {
    header('Location: /squad-arena-v2/squad-selector.php?err=' . rawurlencode($bootErr));

    exit;
}

$bootJson = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($bootJson === false) {
    header('Location: /squad-arena-v2/squad-selector.php?err=encode');

    exit;
}

$htmlPath = __DIR__ . '/battlefield.html';
if (!is_readable($htmlPath)) {
    http_response_code(500);
    echo 'battlefield.html missing';

    exit;
}

$html = file_get_contents($htmlPath);
if ($html === false) {
    http_response_code(500);
    echo 'battlefield read error';

    exit;
}

ob_start();
require __DIR__ . '/../games/mind-wars/lobby-partials/topbar.php';
$topbarHtml = ob_get_clean();

$tokensCss = __DIR__ . '/../assets/css/knd-tokens.css';
$tokensCssV = is_file($tokensCss) ? filemtime($tokensCss) : 0;
$headInject = "\n" . generateFaviconLinks() . "\n"
    . '<link rel="stylesheet" href="/assets/css/knd-tokens.css?v=' . (int) $tokensCssV . '">' . "\n"
    . '<link rel="stylesheet" href="/assets/css/levels.css?v=' . (int) $levelsCssV . '">' . "\n"
    . '<link rel="stylesheet" href="/games/mind-wars/lobby.css?v=' . (int) $lobbyCssV . '">' . "\n";
if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', $headInject . '</head>', $html);
}

$placeholder = '<!--MW_TOPBAR_INJECT-->';
if (strpos($html, $placeholder) !== false) {
    $html = str_replace($placeholder, $topbarHtml, $html);
}

$Ljson = json_encode($L, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($Ljson === false) {
    $Ljson = '{}';
}

/*
 * Topbar solo: las animaciones / Three.js / ataques están en battlefield.html
 * (init → showVsIntro → renderUnits → MWArenaThree.boot). Un boot extra aquí
 * duplicaba el arranque y podía diferir del .html abierto solo.
 */
$bootScript = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
  var d = window.MW_BATTLEFIELD_HEADER;
  if (!d) {
    return;
  }
  var u = d.user || {};
  var cur = d.currencies || {};
  var rank = d.ranking || {};
  var sel = d.selected_avatar;
  var un = document.getElementById('tb-username');
  if (un) un.textContent = u.username || '—';
  var tlvl = document.getElementById('tb-level');
  if (tlvl) {
    var pos = rank.estimated_position != null ? '#' + rank.estimated_position : '—';
    tlvl.textContent = 'LVL ' + (u.level || 1) + ' · ' + pos;
  }
  var xpFill = document.getElementById('tb-xpfill');
  if (xpFill) xpFill.style.width = Math.min(100, Math.max(0, u.xp_fill_pct || 0)) + '%';
  var cc = document.getElementById('cc-coins');
  if (cc) cc.textContent = Number(cur.knd_points_available || 0).toLocaleString();
  var cg = document.getElementById('cc-gems');
  if (cg) cg.textContent = Number(cur.fragments_total || 0).toLocaleString();
  var tbThumb = document.getElementById('tb-avatar-thumb');
  var tbRing = document.getElementById('tb-avatar-ring');
  if (tbThumb) {
    var url = sel ? (sel.display_image_url || d.hero_image_url || '') : (d.hero_image_url || '');
    if (url) {
      tbThumb.innerHTML = '<img src="' + encodeURI(url).replace(/'/g, '%27') + '" alt="">';
      if (tbRing) tbRing.style.display = '';
    } else {
      tbThumb.textContent = '⬡';
      if (tbRing) tbRing.style.display = 'none';
    }
  }
});
</script>
HTML;

$inject = '<script>window.__MW_BATTLE_INIT__=' . $bootJson . ';</script>' . "\n"
    . '<script>window.MW_BATTLEFIELD_HEADER=' . $Ljson . ';</script>' . "\n"
    . '<script>(function(){'
    . 'if(typeof window.showSurrenderConfirm!=="function"){window.showSurrenderConfirm=function(){var el=document.getElementById("surrender-confirm");if(el)el.classList.add("show");};}'
    . 'if(typeof window.closeSurrenderConfirm!=="function"){window.closeSurrenderConfirm=function(){var el=document.getElementById("surrender-confirm");if(el)el.classList.remove("show");};}'
    . 'if(typeof window.confirmSurrender!=="function"){window.confirmSurrender=async function(){if(window.__SQUAD_ARENA_SURRENDERING||window.__SQUAD_ARENA_SHOW_RESULT_DONE)return;window.__SQUAD_ARENA_SURRENDERING=true;try{window.closeSurrenderConfirm&&window.closeSurrenderConfirm();if(typeof G!=="undefined"&&G&&G.phase!=="done"){G.phase="done";}if(typeof showResult==="function"){await showResult(false);}}catch(e){console.warn("confirmSurrender:",e);}};}'
    . '})();</script>' . "\n"
    . $bootScript;

if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', $inject . "\n</head>", $html, $count);
} else {
    $html = $inject . "\n" . $html;
}

// ── SURRENDER BUTTON (client-forfeit) ──
// battlefield.html es un template grande; aquí inyectamos el HTML/JS necesario
// para mostrar un modal de confirmación y resolver la pelea como derrota.
// Nota: usamos regex para evitar problemas con saltos de línea \r\n vs \n.
if (strpos($html, 'id="surrender-btn"') === false) {
    // Important: use a real newline so we don't end up with literal "\n" text in the DOM.
    $replacement = '$1    <button type="button" id="surrender-btn" class="surrender-btn-arena" onclick="showSurrenderConfirm()">SURRENDER</button>' . "\n" . '$2';

    // 1) Match exact static text present in template (YOUR TURN / ROUND 1)
    $pattern1 = '/(<div id="battle-hud-strip">\s*<div class="bhs-center">\s*<div id="tl"[^>]*>\s*YOUR TURN\s*<\/div>\s*<div id="rl">\s*ROUND 1\s*<\/div>\s*<\/div>\s*)(<\/div>\s*<div id="arena">)/';
    $count = 0;
    $newHtml = preg_replace($pattern1, $replacement, $html, 1, $count);
    if (is_string($newHtml) && $count > 0) {
        $html = $newHtml;
    } else {
        // 2) Fallback: match HUD strip structure without relying on exact texts.
        // Incluye explícitamente los nodos #tl y #rl para evitar que el match corte en el primer </div> interno.
        $pattern2 = '/(<div id="battle-hud-strip">\s*<div class="bhs-center">\s*<div id="tl"[^>]*>[\s\S]*?<\/div>\s*<div id="rl"[^>]*>[\s\S]*?<\/div>\s*<\/div>\s*)(<\/div>\s*<div id="arena">)/';
        $count2 = 0;
        $newHtml2 = preg_replace($pattern2, $replacement, $html, 1, $count2);
        if (is_string($newHtml2) && $count2 > 0) {
            $html = $newHtml2;
        }
    }
}

$surrenderInject = <<<HTML

<style id="mw-squad-surrender-style">
/* SURRENDER BUTTON */
#surrender-btn,.surrender-btn-arena{background:transparent;border:1px solid rgba(255,34,68,.3);color:rgba(255,100,120,.7);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;padding:5px 12px;cursor:pointer;transition:all .22s;box-sizing:border-box;margin:0}
#surrender-btn{min-width:auto;max-width:min(160px,40vw)}
#surrender-btn:hover,.surrender-btn-arena:hover{border-color:var(--red);color:var(--red);box-shadow:0 0 14px rgba(255,34,68,.25)}

/* SURRENDER CONFIRM MODAL */
#surrender-confirm{position:fixed;inset:0;z-index:550;display:flex;align-items:center;justify-content:center;background:rgba(1,4,8,.88);backdrop-filter:blur(12px);opacity:0;pointer-events:none;transition:opacity .2s}
#surrender-confirm.show{opacity:1;pointer-events:all}
.sc-box{width:min(90vw,400px);background:var(--surface);border:1px solid rgba(255,34,68,.35);padding:32px 32px 28px;display:flex;flex-direction:column;align-items:center;gap:16px;clip-path:polygon(0 10px,10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%);box-shadow:0 0 60px rgba(255,34,68,.1);position:relative}
.sc-box::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--red),transparent)}
.sc-ttl{font-family:var(--FD);font-size:12.65px;font-weight:700;letter-spacing:3px;color:var(--red);text-shadow:0 0 12px rgba(255,34,68,.5)}
.sc-txt{font-family:var(--FB);font-size:15px;color:var(--t2);text-align:center;line-height:1.6}
.sc-btns{display:flex;gap:10px}
.sc-yes{background:rgba(255,34,68,.1);border:1px solid var(--red);color:var(--red);font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;padding:9px 20px;cursor:pointer;transition:all .2s}
.sc-yes:hover{background:rgba(255,34,68,.2);box-shadow:0 0 16px rgba(255,34,68,.3)}
.sc-no{background:transparent;border:1px solid rgba(255,255,255,.12);color:var(--t2);font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;padding:9px 20px;cursor:pointer;transition:all .2s}
.sc-no:hover{border-color:var(--t2);color:var(--t1)}
</style>

<div id="surrender-confirm">
  <div class="sc-box">
    <div class="sc-ttl">SURRENDER</div>
    <p class="sc-txt">Are you sure you want to abandon the battle?<br>This counts as a defeat.</p>
    <div class="sc-btns">
      <button class="sc-yes" onclick="confirmSurrender()">YES, SURRENDER</button>
      <button class="sc-no" onclick="closeSurrenderConfirm()">CANCEL</button>
    </div>
  </div>
</div>

<script>
// Evita que showResult se dispare más de una vez (p.ej. si checkEnd también intenta resolver).
(function(){
  if (!window.__SQUAD_ARENA_SHOW_RESULT_GUARD && typeof window.showResult === 'function') {
    window.__SQUAD_ARENA_SHOW_RESULT_GUARD = true;
    var oldShowResult = window.showResult;
    window.showResult = async function(w){
      if (window.__SQUAD_ARENA_SHOW_RESULT_DONE) return;
      window.__SQUAD_ARENA_SHOW_RESULT_DONE = true;
      return oldShowResult(w);
    };
  }
})();

function showSurrenderConfirm(){
  if (window.__SQUAD_ARENA_SURRENDERING || window.__SQUAD_ARENA_SHOW_RESULT_DONE) return;
  var el = document.getElementById('surrender-confirm');
  if (!el) return;
  el.classList.add('show');
}

function closeSurrenderConfirm(){
  var el = document.getElementById('surrender-confirm');
  if (!el) return;
  el.classList.remove('show');
}

async function confirmSurrender(){
  if (window.__SQUAD_ARENA_SURRENDERING || window.__SQUAD_ARENA_SHOW_RESULT_DONE) return;
  window.__SQUAD_ARENA_SURRENDERING = true;
  closeSurrenderConfirm();
  if (typeof G !== 'undefined' && G && G.phase !== 'done') {
    G.phase = 'done';
  }
  if (typeof showResult === 'function') {
    try { await showResult(false); } catch (e) { console.warn('confirmSurrender:', e); }
  }
}

// Exponer explícitamente en window para onclick inline.
window.showSurrenderConfirm = showSurrenderConfirm;
window.closeSurrenderConfirm = closeSurrenderConfirm;
window.confirmSurrender = confirmSurrender;

// Fallback UI: si el botón no se pudo inyectar vía PHP (cambio de template),
// lo creamos aquí para que siempre exista.
(function ensureSquadSurrenderButton(){
  try{
    var existing = document.getElementById('surrender-btn');
    if (existing) {
      // Normaliza por si quedó el texto con artefactos (p.ej. '\n' visible)
      existing.textContent = 'SURRENDER';
      existing.className = 'surrender-btn-arena';
      existing.setAttribute('onclick', 'showSurrenderConfirm()');
      // Asegura el DOM exacto: #ability-hud > #apanel > #surrender-btn (antes de #ah-inner)
      var abilityHud = document.getElementById('ability-hud');
      if (abilityHud) {
        var apanel = abilityHud.querySelector('#apanel');
        var ahInner = apanel ? apanel.querySelector('#ah-inner') : null;
        if (apanel && ahInner) {
          var isCorrect = existing.parentElement === apanel && existing.nextElementSibling === ahInner;
          if (!isCorrect) apanel.insertBefore(existing, ahInner);
        } else if (apanel && existing.parentElement !== apanel) {
          apanel.appendChild(existing);
        }
      }
      // Aplica estilos pedidos en el preview.
      existing.style.marginLeft = '1350px';
      return;
    }
    var abilityHud = document.getElementById('ability-hud');
    var apanel = abilityHud ? abilityHud.querySelector('#apanel') : null;

    // Fallback anterior si por cualquier motivo no existe el panel.
    if (!abilityHud) {
      var strip = document.getElementById('battle-hud-strip');
      if (!strip) return;
      apanel = null;
    }

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'surrender-btn';
    btn.className = 'surrender-btn-arena';
    btn.style.marginLeft = '1350px';
    btn.textContent = 'SURRENDER';
    btn.setAttribute('onclick', 'showSurrenderConfirm()');
    var ahInner = apanel ? apanel.querySelector('#ah-inner') : null;
    if (abilityHud && apanel && ahInner) apanel.insertBefore(btn, ahInner);
    else if (abilityHud && apanel) apanel.appendChild(btn);
    else if (abilityHud) abilityHud.appendChild(btn);
    else strip.appendChild(btn);
  }catch(e){
    console.warn('ensureSquadSurrenderButton:', e);
  }
})();
</script>
HTML;

if (strpos($html, '</body>') !== false) {
    $html = str_replace('</body>', $surrenderInject . "\n</body>", $html);
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $html;

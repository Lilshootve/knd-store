<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/mw_avatar_models.php';
if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Resolve hero model URL for the logged-in player
$_heroModelUrl = null;
try {
    $pdo = getDBConnection();
    $uid = (int)$_SESSION['user_id'];
    $s = $pdo->prepare("SELECT fa.id, fa.name, fa.rarity FROM users u LEFT JOIN mw_avatars fa ON fa.id = u.favorite_avatar_id WHERE u.id = ?");
    $s->execute([$uid]);
    $av = $s->fetch(PDO::FETCH_ASSOC);
    if ($av && $av['id']) {
        $_heroModelUrl = mw_resolve_avatar_model_url((int)$av['id'], (string)($av['name']??''), (string)($av['rarity']??'common'));
    }
} catch (Throwable $_) {}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SANCTUM — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#020508;font-family:"Share Tech Mono",monospace;color:#c8e8f0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.035) 3px,rgba(0,0,0,.035) 4px)}

/* ── CRT OVERLAY ─────────────────────────────────────────────── */
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%);transition:none}
#crt.on{animation:crt-in .9s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .65s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50% 50% 50% 50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#ddf}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0% 0 0% 0);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0% 0 0% 0);opacity:1;background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff;opacity:1}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50% 50% 50% 50%);background:#000}}

/* ── LOADING ─────────────────────────────────────────────────── */
#load{position:fixed;inset:0;z-index:9000;background:#020508;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;transition:opacity .5s}
#load.hidden{opacity:0;pointer-events:none}
.load-logo{font-family:"Orbitron",sans-serif;font-size:26px;font-weight:900;letter-spacing:.25em;color:#fff}.load-logo span{color:#00e8ff}
.load-sub{font-size:9px;letter-spacing:.3em;color:rgba(0,232,255,.4)}
.load-bar{width:200px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden}
.load-fill{height:100%;background:linear-gradient(90deg,#00e8ff,#9b30ff);border-radius:1px;transition:width .4s ease;width:0%}

/* ── TOP BAR ─────────────────────────────────────────────────── */
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(2,5,16,.97);border-bottom:1px solid rgba(0,232,255,.07);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#00e8ff 35%,#9b30ff 50%,#00e8ff 65%,transparent 98%);opacity:.22}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(0,232,255,.15);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(0,232,255,.6);transition:all .2s;flex-shrink:0}
.back-btn:hover{border-color:rgba(0,232,255,.4);color:#00e8ff;background:rgba(0,232,255,.06)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}
#room-name{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:700;letter-spacing:.18em;color:#fff;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;padding:3px 6px;border-radius:3px;transition:all .2s}
#room-name:hover{background:rgba(0,232,255,.07);color:#00e8ff}
.tb-sep{width:1px;height:18px;background:rgba(255,255,255,.07)}
.tb-r{margin-left:auto;display:flex;align-items:center;gap:8px}
.kp-chip{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;background:rgba(255,214,0,.07);border:1px solid rgba(255,214,0,.18);cursor:default}
.kp-icon{font-size:12px}.kp-val{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:700;color:#ffd040;letter-spacing:.05em}
.kp-lbl{font-size:7px;letter-spacing:.15em;color:rgba(255,214,0,.45)}
.gear-btn{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
.gear-btn:hover{border-color:rgba(0,232,255,.3);background:rgba(0,232,255,.07)}
.gear-btn svg{width:14px;height:14px;stroke:rgba(155,215,235,.5);fill:none;stroke-width:1.5;transition:stroke .2s}
.gear-btn:hover svg{stroke:#00e8ff}

/* ── CANVAS WRAPPER ─────────────────────────────────────────── */
#cv{position:fixed;top:48px;left:0;right:0;bottom:56px;z-index:0;background:#020508}
#cv canvas{width:100%!important;height:100%!important}

/* ── LEFT CATALOG PANEL ──────────────────────────────────────── */
#lp{position:fixed;left:0;top:48px;bottom:56px;width:236px;z-index:100;background:rgba(2,5,18,.96);border-right:1px solid rgba(0,232,255,.08);backdrop-filter:blur(18px);display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .32s cubic-bezier(.2,.8,.2,1)}
#lp.open{transform:translateX(0)}
#lp::before{content:"";position:absolute;top:0;right:-1px;width:1px;height:100%;background:linear-gradient(180deg,transparent,rgba(0,232,255,.3) 40%,rgba(155,48,255,.3) 60%,transparent)}
.lp-hdr{padding:12px 13px 9px;border-bottom:1px solid rgba(0,232,255,.06);flex-shrink:0}
.lp-title{font-family:"Orbitron",sans-serif;font-size:8px;font-weight:700;letter-spacing:.24em;color:rgba(155,215,235,.5);margin-bottom:8px}
.cat-tabs{display:flex;gap:3px;flex-wrap:wrap}
.cat-tab{padding:3px 7px;border-radius:3px;font-size:7px;letter-spacing:.12em;cursor:pointer;border:1px solid rgba(255,255,255,.06);color:rgba(155,215,235,.35);transition:all .18s}
.cat-tab.on{background:rgba(0,232,255,.1);border-color:rgba(0,232,255,.3);color:#00e8ff}
.cat-tab:hover:not(.on){border-color:rgba(255,255,255,.14);color:rgba(155,215,235,.65)}
.lp-list{flex:1;overflow-y:auto;padding:7px 8px;display:flex;flex-direction:column;gap:4px}
.lp-list::-webkit-scrollbar{width:2px}.lp-list::-webkit-scrollbar-thumb{background:rgba(0,232,255,.12)}
.fi{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:5px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.018);cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.fi::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,transparent 60%,rgba(255,255,255,.02));pointer-events:none}
.fi:hover{border-color:rgba(0,232,255,.22);background:rgba(0,232,255,.05);transform:translateX(2px)}
.fi.sel{border-color:rgba(0,232,255,.5);background:rgba(0,232,255,.09);box-shadow:0 0 14px rgba(0,232,255,.1) inset}
.fi-swatch{width:28px;height:28px;border-radius:4px;flex-shrink:0;border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:13px}
.fi-info{flex:1;min-width:0}
.fi-name{font-family:"Orbitron",sans-serif;font-size:7.5px;font-weight:700;letter-spacing:.06em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e0f0ff}
.fi-meta{display:flex;align-items:center;gap:5px;margin-top:2px}
.fi-price{font-size:7px;color:#ffd040;letter-spacing:.06em}
.fi-size{font-size:6.5px;color:rgba(155,215,235,.35)}
.rarity-badge{padding:1px 5px;border-radius:2px;font-size:5.5px;letter-spacing:.1em;font-weight:700;flex-shrink:0}
.rb-common{background:rgba(180,200,220,.08);border:1px solid rgba(180,200,220,.18);color:rgba(180,200,220,.6)}
.rb-rare{background:rgba(0,168,255,.1);border:1px solid rgba(0,168,255,.3);color:#00a8ff}
.rb-special{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.25);color:#00ff88}
.rb-epic{background:rgba(155,48,255,.1);border:1px solid rgba(155,48,255,.35);color:#c06aff}
.rb-legendary{background:rgba(255,150,0,.12);border:1px solid rgba(255,150,0,.4);color:#ff9800;box-shadow:0 0 8px rgba(255,150,0,.15)}
.lp-foot{padding:8px;border-top:1px solid rgba(0,232,255,.06);flex-shrink:0}
.place-btn{width:100%;padding:9px;border-radius:5px;cursor:pointer;font-family:"Orbitron",sans-serif;font-size:8px;font-weight:900;letter-spacing:.18em;text-align:center;background:linear-gradient(135deg,rgba(0,232,255,.18),rgba(155,48,255,.12));border:1px solid rgba(0,232,255,.4);color:#00e8ff;transition:all .22s;box-shadow:0 0 18px rgba(0,232,255,.08) inset}
.place-btn:hover{box-shadow:0 0 24px rgba(0,232,255,.22) inset,0 0 16px rgba(0,232,255,.14);transform:translateY(-1px)}
.place-btn:disabled{opacity:.3;cursor:not-allowed;transform:none}

/* ── BOTTOM TOOLBAR ──────────────────────────────────────────── */
#bt{position:fixed;bottom:0;left:0;right:0;height:56px;z-index:200;background:rgba(2,5,16,.97);border-top:1px solid rgba(0,232,255,.07);display:flex;align-items:center;padding:0 14px;gap:4px}
#bt::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#00e8ff 35%,#9b30ff 50%,#00e8ff 65%,transparent 98%);opacity:.18}
.t-btn{display:flex;flex-direction:column;align-items:center;gap:2px;padding:5px 11px;border-radius:5px;cursor:pointer;border:1px solid transparent;transition:all .2s;color:rgba(90,165,190,.38);min-width:52px}
.t-btn:hover{background:rgba(255,255,255,.04);color:rgba(155,215,235,.65)}
.t-btn.on{background:rgba(0,232,255,.07);border-color:rgba(0,232,255,.22);color:#00e8ff}
.t-btn.danger.on{background:rgba(255,61,86,.07);border-color:rgba(255,61,86,.25);color:#ff3d56}
.t-icon{font-size:17px;line-height:1}.t-lbl{font-family:"Orbitron",sans-serif;font-size:5.5px;font-weight:700;letter-spacing:.14em}
.bt-sep{width:1px;height:28px;background:rgba(255,255,255,.06);margin:0 4px}
.catalog-toggle{margin-left:auto;display:flex;align-items:center;gap:5px;padding:6px 13px;border-radius:5px;border:1px solid rgba(0,232,255,.16);cursor:pointer;font-size:8px;letter-spacing:.14em;color:rgba(0,232,255,.55);transition:all .2s}
.catalog-toggle:hover{background:rgba(0,232,255,.07);color:#00e8ff;border-color:rgba(0,232,255,.35)}
.catalog-toggle.on{background:rgba(0,232,255,.1);color:#00e8ff;border-color:rgba(0,232,255,.45)}

/* ── RIGHT INFO PANEL ────────────────────────────────────────── */
#rip{position:fixed;right:0;top:48px;bottom:56px;width:200px;z-index:100;background:rgba(2,5,18,.95);border-left:1px solid rgba(0,232,255,.07);transform:translateX(100%);transition:transform .28s cubic-bezier(.2,.8,.2,1);display:flex;flex-direction:column;padding:12px}
#rip.open{transform:translateX(0)}
.rip-title{font-family:"Orbitron",sans-serif;font-size:7px;letter-spacing:.22em;color:rgba(90,165,190,.45);margin-bottom:6px}
.rip-name{font-family:"Orbitron",sans-serif;font-size:12px;font-weight:900;letter-spacing:.1em;color:#e0f0ff;line-height:1.1;margin-bottom:4px}
.rip-rarity{font-size:7px;letter-spacing:.15em;margin-bottom:10px}
.rip-stat{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(0,232,255,.05);font-size:8px}
.rip-stat-l{color:rgba(155,215,235,.4);letter-spacing:.1em}.rip-stat-r{color:#e0f0ff}
.rip-actions{margin-top:auto;display:flex;flex-direction:column;gap:5px}
.rip-btn{padding:8px;border-radius:4px;cursor:pointer;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.16em;text-align:center;border:1px solid;transition:all .2s}
.rip-rotate{background:rgba(0,232,255,.06);border-color:rgba(0,232,255,.25);color:#00e8ff}
.rip-rotate:hover{background:rgba(0,232,255,.13);box-shadow:0 0 12px rgba(0,232,255,.12)}
.rip-delete{background:rgba(255,61,86,.05);border-color:rgba(255,61,86,.2);color:#ff3d56}
.rip-delete:hover{background:rgba(255,61,86,.12);box-shadow:0 0 12px rgba(255,61,86,.12)}
.rip-close{position:absolute;top:10px;right:10px;width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:9px;color:rgba(255,255,255,.3)}

/* ── SETTINGS MODAL ──────────────────────────────────────────── */
#modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center}
#modal.open{display:flex}
.modal-box{background:#030b18;border:1px solid rgba(0,232,255,.12);border-radius:8px;padding:24px;width:340px;max-width:90vw;position:relative;box-shadow:0 0 60px rgba(0,232,255,.08)}
.modal-box::before{content:"";position:absolute;inset:0;border-radius:8px;background:linear-gradient(135deg,rgba(0,232,255,.02),transparent);pointer-events:none}
.modal-title{font-family:"Orbitron",sans-serif;font-size:10px;font-weight:900;letter-spacing:.24em;color:#fff;margin-bottom:16px}
.field{margin-bottom:12px}
.field label{display:block;font-size:7.5px;letter-spacing:.18em;color:rgba(155,215,235,.4);margin-bottom:5px}
.field input,.field select{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(0,232,255,.14);border-radius:4px;padding:7px 10px;font-family:"Share Tech Mono",monospace;font-size:11px;color:#e0f0ff;outline:none;transition:border-color .2s}
.field input:focus,.field select:focus{border-color:rgba(0,232,255,.4)}
.field input[type=color]{height:36px;padding:3px;cursor:pointer}
.modal-row{display:flex;gap:6px;margin-top:16px}
.modal-btn{flex:1;padding:9px;border-radius:5px;cursor:pointer;font-family:"Orbitron",sans-serif;font-size:7.5px;font-weight:700;letter-spacing:.16em;text-align:center;border:1px solid;transition:all .2s}
.modal-save{background:linear-gradient(135deg,rgba(0,232,255,.18),rgba(155,48,255,.12));border-color:rgba(0,232,255,.4);color:#00e8ff}
.modal-save:hover{box-shadow:0 0 18px rgba(0,232,255,.18);transform:translateY(-1px)}
.modal-cancel{background:rgba(255,255,255,.02);border-color:rgba(255,255,255,.08);color:rgba(155,215,235,.4)}
.modal-cancel:hover{border-color:rgba(255,255,255,.15);color:rgba(155,215,235,.65)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0}
.toggle-row label{font-size:8px;letter-spacing:.12em;color:rgba(155,215,235,.55)}
.toggle{width:36px;height:18px;background:rgba(255,255,255,.06);border-radius:9px;cursor:pointer;position:relative;border:1px solid rgba(255,255,255,.1);transition:background .2s}
.toggle.on{background:rgba(0,232,255,.25);border-color:rgba(0,232,255,.4)}
.toggle::after{content:"";position:absolute;top:2px;left:2px;width:12px;height:12px;border-radius:50%;background:rgba(180,210,230,.4);transition:all .2s}
.toggle.on::after{left:20px;background:#00e8ff;box-shadow:0 0 6px #00e8ff}

/* ── TOASTS ──────────────────────────────────────────────────── */
#toasts{position:fixed;bottom:70px;left:50%;transform:translateX(-50%);z-index:5000;display:flex;flex-direction:column;align-items:center;gap:6px;pointer-events:none}
.toast{padding:7px 16px;border-radius:4px;font-size:8.5px;letter-spacing:.12em;border:1px solid;backdrop-filter:blur(12px);animation:toastIn .3s ease forwards;white-space:nowrap}
.toast.ok{background:rgba(0,255,136,.07);border-color:rgba(0,255,136,.25);color:#00ff88}
.toast.err{background:rgba(255,61,86,.08);border-color:rgba(255,61,86,.25);color:#ff3d56}
.toast.info{background:rgba(0,232,255,.07);border-color:rgba(0,232,255,.22);color:#00e8ff}
@keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── BUY MODAL ───────────────────────────────────────────────── */
#buy-modal{position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.78);backdrop-filter:blur(14px);display:none;align-items:center;justify-content:center}
#buy-modal.open{display:flex}
.buy-box{background:#030b18;border:1px solid rgba(255,214,0,.2);border-radius:10px;padding:28px;width:310px;max-width:92vw;position:relative;box-shadow:0 0 70px rgba(255,214,0,.07),0 0 120px rgba(155,48,255,.05)}
.buy-box::before{content:"";position:absolute;inset:0;border-radius:10px;background:linear-gradient(135deg,rgba(255,214,0,.025),transparent 60%);pointer-events:none}
.buy-item-preview{height:76px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:rgba(255,255,255,.028);margin-bottom:16px;border:1px solid rgba(255,255,255,.06);font-size:30px;letter-spacing:.04em}
.buy-title{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:900;letter-spacing:.1em;color:#fff;margin-bottom:4px}
.buy-rarity-lbl{font-size:7px;letter-spacing:.2em;margin-bottom:16px}
.buy-price-row{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:6px;background:rgba(255,214,0,.05);border:1px solid rgba(255,214,0,.12);margin-bottom:16px}
.buy-kp-num{font-family:"Orbitron",sans-serif;font-size:20px;font-weight:900;color:#ffd040;letter-spacing:.04em}
.buy-balance-col{margin-left:auto;text-align:right}
.buy-balance-lbl{font-size:7px;color:rgba(255,214,0,.45);letter-spacing:.12em}
.buy-balance-val{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:700;color:#ffd040}
.buy-btns{display:flex;gap:6px}
.buy-confirm{flex:2;padding:10px;border-radius:5px;cursor:pointer;font-family:"Orbitron",sans-serif;font-size:8.5px;font-weight:900;letter-spacing:.2em;background:linear-gradient(135deg,rgba(255,214,0,.22),rgba(255,150,0,.15));border:1px solid rgba(255,214,0,.48);color:#ffd040;transition:all .22s}
.buy-confirm:hover{box-shadow:0 0 24px rgba(255,214,0,.2);transform:translateY(-1px)}
.buy-confirm:disabled{opacity:.35;cursor:not-allowed;transform:none}
.buy-cancel-btn{flex:1;padding:10px;border-radius:5px;cursor:pointer;font-family:"Orbitron",sans-serif;font-size:8px;font-weight:700;letter-spacing:.15em;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);color:rgba(155,215,235,.4);transition:all .2s}
.buy-cancel-btn:hover{border-color:rgba(255,255,255,.18);color:rgba(155,215,235,.65)}
.owned-badge{padding:1px 5px;border-radius:2px;font-size:5.5px;letter-spacing:.1em;font-weight:700;background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.25);color:#00ff88}
.notowned-badge{padding:1px 5px;border-radius:2px;font-size:5.5px;letter-spacing:.1em;font-weight:700;background:rgba(255,214,0,.09);border:1px solid rgba(255,214,0,.28);color:#ffd040;cursor:pointer}

/* ── HINT OVERLAY ────────────────────────────────────────────── */
#hint-overlay{position:fixed;inset:0;z-index:50;pointer-events:none;display:flex;align-items:flex-end;justify-content:center;padding-bottom:72px}
.hint-chip{background:rgba(0,232,255,.06);border:1px solid rgba(0,232,255,.12);border-radius:20px;padding:5px 16px;font-size:7.5px;letter-spacing:.16em;color:rgba(0,232,255,.4);backdrop-filter:blur(6px);transition:opacity .4s}
.hint-chip.hidden{opacity:0}

/* ── KEYBOARD SHORTCUTS ──────────────────────────────────────── */
.kb-hint{font-size:6px;color:rgba(90,165,190,.3);letter-spacing:.1em;text-align:center;margin-top:1px}

/* ── THEME-AWARE VARS ────────────────────────────────────────── */
:root{--theme-main:#00e8ff;--theme-accent:#9b30ff}
</style>
</head>
<body>

<!-- CRT -->
<div id="crt"></div>

<!-- Buy Modal -->
<div id="buy-modal">
  <div class="buy-box">
    <div class="buy-item-preview" id="buy-preview">◻</div>
    <div class="buy-title" id="buy-name">ITEM NAME</div>
    <div class="buy-rarity-lbl" id="buy-rar">COMMON</div>
    <div class="buy-price-row">
      <span style="font-size:18px">◈</span>
      <span class="buy-kp-num" id="buy-kp">0</span>
      <div class="buy-balance-col">
        <div class="buy-balance-lbl">YOUR BALANCE</div>
        <div class="buy-balance-val" id="buy-bal">—</div>
      </div>
    </div>
    <div class="buy-btns">
      <button class="buy-confirm" id="buy-confirm-btn" onclick="confirmBuy()">◈ PURCHASE</button>
      <button class="buy-cancel-btn" onclick="closeBuyModal()">CANCEL</button>
    </div>
  </div>
</div>

<!-- Hint Overlay -->
<div id="hint-overlay">
  <div class="hint-chip" id="hint-chip">WASD MOVE &nbsp;|&nbsp; E INTERACT &nbsp;|&nbsp; CATALOG → SELECT → CLICK TO PLACE &nbsp;|&nbsp; R ROTATE &nbsp;|&nbsp; ESC CANCEL</div>
</div>

<!-- Loading -->
<div id="load">
  <div class="load-logo">SANC<span>TUM</span></div>
  <div class="load-sub">INITIALIZING NEURAL SPACE</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>

<!-- Top Bar -->
<div id="tb">
  <div class="back-btn" onclick="crtGo('/games/arena-protocol/nexus-city.html')">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    NEXUS
  </div>
  <div class="tb-sep"></div>
  <div id="room-name" onclick="openSettings()">MY SANCTUM</div>
  <div class="tb-r">
    <div class="kp-chip">
      <span class="kp-icon">◈</span>
      <span class="kp-val" id="kp-val">—</span>
      <span class="kp-lbl">KP</span>
    </div>
    <div class="gear-btn" onclick="openSettings()">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
    </div>
  </div>
</div>

<!-- Canvas -->
<div id="cv"></div>

<!-- Left Catalog Panel -->
<div id="lp">
  <div class="lp-hdr">
    <div class="lp-title">⬡ NEXUS CATALOG</div>
    <div class="cat-tabs" id="cat-tabs">
      <div class="cat-tab on" data-cat="all">ALL</div>
      <div class="cat-tab" data-cat="floor">FLOOR</div>
      <div class="cat-tab" data-cat="wall">WALL</div>
      <div class="cat-tab" data-cat="decoration">DECO</div>
      <div class="cat-tab" data-cat="interactive">LIVE</div>
    </div>
  </div>
  <div class="lp-list" id="cat-list"></div>
  <div class="lp-foot">
    <button class="place-btn" id="place-btn" disabled onclick="activatePlace()">SELECT AN ITEM</button>
  </div>
</div>

<!-- Right Info Panel -->
<div id="rip">
  <div class="rip-close" onclick="deselectPlaced()">✕</div>
  <div class="rip-title">SELECTED OBJECT</div>
  <div class="rip-name" id="rip-name">—</div>
  <div class="rip-rarity" id="rip-rarity">—</div>
  <div class="rip-stat"><span class="rip-stat-l">SIZE</span><span class="rip-stat-r" id="rip-size">—</span></div>
  <div class="rip-stat"><span class="rip-stat-l">ROTATION</span><span class="rip-stat-r" id="rip-rot">0°</span></div>
  <div class="rip-stat"><span class="rip-stat-l">POSITION</span><span class="rip-stat-r" id="rip-pos">—</span></div>
  <div class="rip-actions">
    <div class="rip-btn rip-rotate" onclick="rotateSelected()">↻ ROTATE</div>
    <div class="rip-btn rip-delete" onclick="deleteSelected()">⌫ REMOVE</div>
  </div>
</div>

<!-- Bottom Toolbar -->
<div id="bt">
  <div class="t-btn on" id="mode-view" onclick="setMode('view')">
    <span class="t-icon">◉</span><span class="t-lbl">VIEW</span><span class="kb-hint">[V]</span>
  </div>
  <div class="t-btn" id="mode-place" onclick="setMode('place')">
    <span class="t-icon">⊕</span><span class="t-lbl">PLACE</span><span class="kb-hint">[P]</span>
  </div>
  <div class="t-btn" id="mode-rotate" onclick="setMode('rotate')">
    <span class="t-icon">↻</span><span class="t-lbl">ROTATE</span><span class="kb-hint">[T]</span>
  </div>
  <div class="t-btn danger" id="mode-delete" onclick="setMode('delete')">
    <span class="t-icon">⌫</span><span class="t-lbl">DELETE</span><span class="kb-hint">[X]</span>
  </div>
  <div class="bt-sep"></div>
  <div class="catalog-toggle on" id="cat-toggle" onclick="toggleCatalog()">⬡ CATALOG</div>
</div>

<!-- Settings Modal -->
<div id="modal">
  <div class="modal-box">
    <div class="modal-title">⬡ SANCTUM SETTINGS</div>
    <div class="field">
      <label>ROOM NAME</label>
      <input type="text" id="s-name" maxlength="40" placeholder="My Sanctum">
    </div>
    <div class="field">
      <label>EXTERIOR THEME</label>
      <select id="s-theme">
        <option value="cyber">CYBER</option>
        <option value="neon">NEON WAVE</option>
        <option value="dark">VOID DARK</option>
        <option value="hologram">HOLOGRAM</option>
        <option value="nature">BIO-ORGANIC</option>
      </select>
    </div>
    <div class="field">
      <label>ACCENT COLOR</label>
      <input type="color" id="s-color" value="#00e8ff">
    </div>
    <div class="toggle-row">
      <label>PUBLIC SPACE</label>
      <div class="toggle on" id="s-public" onclick="this.classList.toggle('on')"></div>
    </div>
    <div class="modal-row">
      <div class="modal-btn modal-cancel" onclick="closeSettings()">CANCEL</div>
      <div class="modal-btn modal-save" onclick="saveSettings()">SAVE</div>
    </div>
  </div>
</div>

<!-- Toasts -->
<div id="toasts"></div>

<script type="module">
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const HERO_MODEL_URL = <?php echo json_encode($_heroModelUrl); ?>;

// ─────────────────────────────────────────────────────────────────
// Globals
// ─────────────────────────────────────────────────────────────────
const GRID = 10, CS = 1.0; // GRID cells, Cell Size
let scene, camera, renderer, raycaster, clock;
let floorPlane, hoverMesh, ghostGroup;
let particles, particlePositions;
let mode = 'view', catalogOpen = true;
let selectedCatalogItem = null, selectedPlacedId = null;
let catalogue = [], placed = [], plotData = null;
let balance = 0;
let ghostRot = 0;
let ownedItems = new Set();    // Set of furniture_id the player has purchased
let buyCandidate = null;       // Item pending purchase confirmation

// Camera orbit (right-drag)
let isRightDrag = false, dragStart = {x:0,y:0};
let camAngleH = Math.PI/4, camAngleV = Math.PI/5, camDist = 22;

// ── HERO PLAYER ──
let hero = null;          // THREE.Group — the visible character
let heroMixer = null;     // AnimationMixer
let heroActionIdle = null, heroActionWalk = null;
let heroIsWalking = false;
const heroVel = new THREE.Vector3();
const heroKeys = {};
const _gltfLoader = new GLTFLoader();

const placedMeshes = new Map(); // placed_id → THREE.Group
const floorCells   = [];       // 10x10 flat planes for hover highlight
const animObjects  = [];       // { mesh, type, t0 }

// ─────────────────────────────────────────────────────────────────
// Boot
// ─────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', async () => {
    initThree();
    document.getElementById('crt').className = 'on';
    setLoadProgress(20);

    try {
        const res = await fetch('/api/nexus/sanctum.php');
        const json = await res.json();
        if (!json.ok) throw new Error(json.error?.message || 'API error');

        setLoadProgress(60);
        catalogue  = json.data.catalog;
        placed     = json.data.placed;
        plotData   = json.data.plot;
        balance    = json.data.balance;

        // Mark items player already owns (placed = owned)
        placed.forEach(p => ownedItems.add(p.furniture_id));

        document.getElementById('kp-val').textContent = balance.toLocaleString();
        const rname = plotData.house_name || (json.data.username.toUpperCase() + '\'S SANCTUM');
        document.getElementById('room-name').textContent = rname;

        buildScene();
        applyTheme(plotData.exterior_theme || 'cyber');
        renderCatalog();
        updateTabCounts();
        renderPlaced();
        spawnHero();
        setLoadProgress(100);

    } catch(e) {
        console.error('Sanctum load error:', e);
        buildScene();
        renderCatalog();
        updateTabCounts();
        spawnHero();
        setLoadProgress(100);
    }

    setTimeout(() => {
        document.getElementById('load').classList.add('hidden');
        animate();
        toast('SANCTUM PROTOCOL ONLINE', 'info');
    }, 600);
});

function setLoadProgress(p) {
    document.getElementById('load-fill').style.width = p + '%';
}

// ─────────────────────────────────────────────────────────────────
// Three.js Setup
// ─────────────────────────────────────────────────────────────────
function initThree() {
    scene    = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x020508, 0.04);
    clock    = new THREE.Clock();
    raycaster = new THREE.Raycaster();

    const wrap = document.getElementById('cv');
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type    = THREE.PCFSoftShadowMap;
    renderer.toneMapping       = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    wrap.appendChild(renderer.domElement);

    resetCamera();

    // Lights
    const ambient = new THREE.AmbientLight(0x0a1525, 1.8);
    scene.add(ambient);

    const sun = new THREE.DirectionalLight(0x90ccff, 1.2);
    sun.position.set(8, 16, 8);
    sun.castShadow = true;
    sun.shadow.mapSize.set(1024, 1024);
    sun.shadow.camera.near = 0.5;
    sun.shadow.camera.far  = 60;
    sun.shadow.camera.left = sun.shadow.camera.bottom = -12;
    sun.shadow.camera.right = sun.shadow.camera.top   = 12;
    scene.add(sun);

    const fill = new THREE.DirectionalLight(0x3d0060, 0.6);
    fill.position.set(-10, 6, -10);
    scene.add(fill);

    window.addEventListener('resize', onResize);
    wrap.addEventListener('mousemove', onMouseMove);
    wrap.addEventListener('click', onClick);
    wrap.addEventListener('wheel', onWheel, {passive:true});
    wrap.addEventListener('contextmenu', e => e.preventDefault());
    wrap.addEventListener('mousedown', onMouseDown);
    wrap.addEventListener('mouseup',   () => { isRightDrag = false; });
    wrap.addEventListener('mouseleave',() => { isRightDrag = false; });
}

function onMouseDown(e) {
    if (e.button === 2) {
        isRightDrag = true;
        dragStart = {x: e.clientX, y: e.clientY};
    }
}

function updateOrbitCamera() {
    const cx = 5, cz = 5;
    camera.position.x = cx + camDist * Math.sin(camAngleV) * Math.sin(camAngleH);
    camera.position.y = camDist * Math.cos(camAngleV);
    camera.position.z = cz + camDist * Math.sin(camAngleV) * Math.cos(camAngleH);
    camera.lookAt(cx, 0, cz);
    camera.updateProjectionMatrix();
}

function resetCamera() {
    const w = document.getElementById('cv').clientWidth;
    const h = document.getElementById('cv').clientHeight;
    const aspect = w / h;
    const d = 9;
    camera = new THREE.OrthographicCamera(-d*aspect, d*aspect, d, -d, 0.1, 200);
    camera.position.set(18, 22, 18);
    camera.lookAt(5, 0, 5);
}

function onResize() {
    const wrap = document.getElementById('cv');
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    resetCamera();
}

// ─────────────────────────────────────────────────────────────────
// Scene Building
// ─────────────────────────────────────────────────────────────────
function buildScene() {
    buildFloor();
    buildFloorReflection();
    buildWalls();
    buildCeiling();
    buildAccentLights();
    buildParticles();
    buildGhost();
}

function buildFloor() {
    // Base floor
    const baseMat = new THREE.MeshStandardMaterial({
        color: 0x040d1a, roughness: 0.8, metalness: 0.3
    });
    const base = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), baseMat);
    base.rotation.x = -Math.PI / 2;
    base.position.set(5, -0.01, 5);
    base.receiveShadow = true;
    scene.add(base);

    // Grid lines
    const gridHelper = new THREE.GridHelper(GRID, GRID, 0x0a2035, 0x0a2035);
    gridHelper.position.set(5, 0.005, 5);
    scene.add(gridHelper);

    // Floor cells (hover targets + highlights)
    const cellGeo = new THREE.PlaneGeometry(CS - 0.06, CS - 0.06);
    for (let x = 0; x < GRID; x++) {
        floorCells[x] = [];
        for (let z = 0; z < GRID; z++) {
            const mat = new THREE.MeshStandardMaterial({
                color: 0x051525, roughness: 0.9, metalness: 0.1,
                transparent: true, opacity: 0.0, depthWrite: false
            });
            const cell = new THREE.Mesh(cellGeo, mat);
            cell.rotation.x = -Math.PI / 2;
            cell.position.set(x + 0.5, 0.008, z + 0.5);
            cell.userData = { cellX: x, cellZ: z };
            cell.receiveShadow = true;
            scene.add(cell);
            floorCells[x][z] = cell;
        }
    }

    // Invisible raycast floor
    floorPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(GRID * 2, GRID * 2),
        new THREE.MeshBasicMaterial({ visible: false, side: THREE.DoubleSide })
    );
    floorPlane.rotation.x = -Math.PI / 2;
    floorPlane.position.set(5, 0, 5);
    scene.add(floorPlane);
}

function buildWalls() {
    const wallMat = new THREE.MeshStandardMaterial({
        color: 0x030c1c, roughness: 0.7, metalness: 0.4,
        emissive: 0x001020, emissiveIntensity: 0.3
    });

    // Left wall (x=0 face, z-axis)
    const lw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 3.5), wallMat.clone());
    lw.position.set(0, 1.75, 5);
    lw.rotation.y = Math.PI / 2;
    scene.add(lw);
    addCircuitLines(lw, 'left');

    // Back wall (z=0 face, x-axis)
    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 3.5), wallMat.clone());
    bw.position.set(5, 1.75, 0);
    scene.add(bw);
    addCircuitLines(bw, 'back');

    // ── Hologram "windows" — glowing recessed panels ──
    const winMat = new THREE.MeshStandardMaterial({color:0x000820,emissive:0x0033aa,emissiveIntensity:0.35,transparent:true,opacity:0.82});
    const winFrame = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.5,transparent:true,opacity:0.3});
    // Back wall windows
    [[2, 1.8],[5, 1.8],[8, 1.8]].forEach(([wx, wy]) => {
        const win = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.1, 0.04), winMat.clone());
        win.position.set(wx, wy, 0.04);
        scene.add(win);
        const frame = new THREE.Mesh(new THREE.BoxGeometry(1.64, 1.14, 0.02), winFrame.clone());
        frame.position.set(wx, wy, 0.03);
        scene.add(frame);
        // Animated scanline inside window
        const scanMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.8,transparent:true,opacity:0.18});
        const scan = new THREE.Mesh(new THREE.BoxGeometry(1.56, 0.025, 0.01), scanMat);
        scan.position.set(wx, wy - 0.5, 0.05);
        scene.add(scan);
        animObjects.push({obj:scan, type:'scan_y', base:wy - 0.52, top:wy + 0.52, speed:0.6 + wx*0.04});
    });
    // Left wall windows
    [[2, 1.8],[5, 1.8],[8, 1.8]].forEach(([wz, wy]) => {
        const win = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.1, 0.04), winMat.clone());
        win.rotation.y = Math.PI/2; win.position.set(0.04, wy, wz);
        scene.add(win);
        const frame = new THREE.Mesh(new THREE.BoxGeometry(1.64, 1.14, 0.02), winFrame.clone());
        frame.rotation.y = Math.PI/2; frame.position.set(0.03, wy, wz);
        scene.add(frame);
    });

    // ── Large holographic monitor on back wall ──
    const monBodyMat = new THREE.MeshStandardMaterial({color:0x050d1a,roughness:.3,metalness:.9});
    const monBody = new THREE.Mesh(new THREE.BoxGeometry(3.2, 1.8, 0.12), monBodyMat);
    monBody.position.set(5, 2.5, 0.07);
    scene.add(monBody);
    const screenMat = new THREE.MeshStandardMaterial({color:0x000510,emissive:0x002255,emissiveIntensity:1.2,roughness:0,metalness:.2});
    const screen = new THREE.Mesh(new THREE.BoxGeometry(3.0, 1.65, 0.06), screenMat);
    screen.position.set(5, 2.5, 0.12);
    scene.add(screen);
    // Screen grid overlay
    const sgMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:.35,transparent:true,opacity:.07,wireframe:true});
    const sg = new THREE.Mesh(new THREE.BoxGeometry(2.95, 1.6, 0.01), sgMat);
    sg.position.set(5, 2.5, 0.16);
    scene.add(sg);
    // Screen glow scanline
    const mScanMat = new THREE.MeshStandardMaterial({color:0x00aaff,emissive:0x00aaff,emissiveIntensity:1,transparent:true,opacity:0.22});
    const mScan = new THREE.Mesh(new THREE.BoxGeometry(2.95, 0.03, 0.01), mScanMat);
    mScan.position.set(5, 1.65, 0.17);
    scene.add(mScan);
    animObjects.push({obj:mScan, type:'scan_y', base:1.65, top:3.35, speed:0.35});
    // Monitor stand
    const standMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:.6,transparent:true,opacity:.4});
    const stand = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.4, 0.08), standMat);
    stand.position.set(5, 1.61, 0.1);
    scene.add(stand);
    // Monitor screen point light
    const monLight = new THREE.PointLight(0x0033ff, 1.4, 6);
    monLight.position.set(5, 2.5, 0.5);
    scene.add(monLight);
    animObjects.push({obj:monLight, type:'pulse', base:1.4, range:0.3, speed:0.28});

    // ── Baseboard glow strips ──
    const gMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.9,transparent:true,opacity:0.55});
    const strip1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.05, 0.04), gMat);
    strip1.position.set(5, 0.025, 0.02);
    scene.add(strip1);
    const strip2 = strip1.clone();
    strip2.rotation.y = Math.PI/2;
    strip2.position.set(0.02, 0.025, 5);
    scene.add(strip2);

    // Top border strips (ceiling edge)
    const topMat = gMat.clone();
    topMat.opacity = 0.25;
    const top1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.04, 0.04), topMat);
    top1.position.set(5, 3.48, 0.02);
    scene.add(top1);
    const top2 = top1.clone();
    top2.rotation.y = Math.PI/2;
    top2.position.set(0.02, 3.48, 5);
    scene.add(top2);

    // Corner post with glow
    const post = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.5, 0.08), gMat.clone());
    post.position.set(0.04, 1.75, 0.04);
    scene.add(post);
    const postLight = new THREE.PointLight(0x00e8ff, 0.8, 4);
    postLight.position.set(0.3, 1.75, 0.3);
    scene.add(postLight);
    animObjects.push({obj:postLight, type:'pulse', base:0.8, range:0.3, speed:0.9});
}

function addCircuitLines(wallMesh, side) {
    const lineMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.28,transparent:true,opacity:0.13});
    const accentMat = new THREE.MeshStandardMaterial({color:0x9b30ff,emissive:0x9b30ff,emissiveIntensity:0.35,transparent:true,opacity:0.1});

    if (side === 'back') {
        // Horizontal rails
        for (let i = 0; i < 4; i++) {
            const bar = new THREE.Mesh(new THREE.BoxGeometry(9.6, 0.016, 0.02), i%2===0 ? lineMat : accentMat);
            bar.position.set(5, 0.38 + i * 0.82, 0.02);
            scene.add(bar);
        }
        // Vertical accent lines
        [1.5, 3.5, 6.5, 8.5].forEach(x => {
            const vbar = new THREE.Mesh(new THREE.BoxGeometry(0.016, 3.2, 0.02), lineMat);
            vbar.position.set(x, 1.6, 0.02);
            scene.add(vbar);
        });
    } else if (side === 'left') {
        for (let i = 0; i < 4; i++) {
            const bar = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.016, 9.6), i%2===0 ? lineMat : accentMat);
            bar.position.set(0.02, 0.38 + i * 0.82, 5);
            scene.add(bar);
        }
        [1.5, 3.5, 6.5, 8.5].forEach(z => {
            const vbar = new THREE.Mesh(new THREE.BoxGeometry(0.02, 3.2, 0.016), lineMat);
            vbar.position.set(0.02, 1.6, z);
            scene.add(vbar);
        });
    }
}

function buildCeiling() {
    const cMat = new THREE.MeshStandardMaterial({color:0x020810,transparent:true,opacity:0.92,roughness:1,metalness:0});
    const ceil = new THREE.Mesh(new THREE.PlaneGeometry(GRID+0.1, GRID+0.1), cMat);
    ceil.rotation.x = Math.PI / 2;
    ceil.position.set(5, 3.5, 5);
    scene.add(ceil);

    const cGrid = new THREE.GridHelper(GRID, GRID, 0x04111f, 0x04111f);
    cGrid.position.set(5, 3.49, 5);
    scene.add(cGrid);

    // Overhead light fixture
    const fixtureMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:1});
    const fix = new THREE.Mesh(new THREE.BoxGeometry(2, 0.04, 0.06), fixtureMat);
    fix.position.set(5, 3.45, 5);
    scene.add(fix);
    const pl = new THREE.PointLight(0x00e8ff, 3, 12);
    pl.position.set(5, 3.3, 5);
    scene.add(pl);
    animObjects.push({obj: pl, type: 'pulse', t0: 0, base: 3, range: 0.4, speed: 0.7});
}

function buildParticles() {
    const n = 180;
    particlePositions = new Float32Array(n * 3);
    const velocities  = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
        particlePositions[i*3]   = Math.random() * GRID;
        particlePositions[i*3+1] = Math.random() * 3;
        particlePositions[i*3+2] = Math.random() * GRID;
        velocities[i*3+1] = 0.002 + Math.random() * 0.004;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(particlePositions, 3));
    geo.userData.velocities = velocities;

    const mat = new THREE.PointsMaterial({color:0x00e8ff,size:0.05,transparent:true,opacity:0.35,sizeAttenuation:true});
    particles = new THREE.Points(geo, mat);
    scene.add(particles);
}

function buildGhost() {
    ghostGroup = new THREE.Group();
    ghostGroup.visible = false;
    scene.add(ghostGroup);
}

function buildFloorReflection() {
    // Subtle reflective sheen layer on floor
    const refMat = new THREE.MeshStandardMaterial({
        color:0x001830, roughness:0.05, metalness:0.6,
        transparent:true, opacity:0.25, depthWrite:false
    });
    const ref = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), refMat);
    ref.rotation.x = -Math.PI / 2;
    ref.position.set(5, 0.002, 5);
    scene.add(ref);
}

function buildAccentLights() {
    // Corner uplights with different tints
    const corners = [
        {pos:[0.4,0.1,0.4], col:0x00e8ff},
        {pos:[9.6,0.1,0.4], col:0x9b30ff},
        {pos:[0.4,0.1,9.6], col:0x9b30ff},
        {pos:[9.6,0.1,9.6], col:0x00e8ff}
    ];
    corners.forEach((c,i) => {
        const pl = new THREE.PointLight(c.col, 2.8, 7, 1.8);
        pl.position.set(...c.pos);
        scene.add(pl);
        // Tiny emissive disc for visual source
        const dm = new THREE.MeshStandardMaterial({color:c.col,emissive:c.col,emissiveIntensity:2,transparent:true,opacity:0.7});
        const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.1,0.1,0.02,12), dm);
        disc.position.set(...c.pos);
        scene.add(disc);
        animObjects.push({obj:pl, type:'pulse', base:2.8, range:0.6, speed:0.35 + i*0.12});
    });

    // Mid-room accent point (center ceiling cluster)
    const centerGlow = new THREE.PointLight(0x001833, 1.2, 14);
    centerGlow.position.set(5, 3.2, 5);
    scene.add(centerGlow);
}

// ─────────────────────────────────────────────────────────────────
// Furniture Builder — per-shape 3D models
// ─────────────────────────────────────────────────────────────────
function buildFurnitureMesh(item) {
    const g = new THREE.Group();
    const ad = item.asset_data || {};
    const col = parseInt((ad.color || '#00e8ff').replace('#',''), 16);
    const mat = () => new THREE.MeshStandardMaterial({
        color: col, roughness: 0.35, metalness: 0.65
    });
    const shape = ad.shape || '';

    if (shape === 'chair') {
        const seat = new THREE.Mesh(new THREE.BoxGeometry(.7,.1,.65), mat());
        seat.position.set(.35,.38,.35); g.add(seat);
        const back = new THREE.Mesh(new THREE.BoxGeometry(.7,.6,.07), mat());
        back.position.set(.35,.72,.06); g.add(back);
        [[.1,.1],[.1,.56],[.6,.1],[.6,.56]].forEach(([x,z]) => {
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(.04,.04,.38,6), mat());
            leg.position.set(x,.19,z); g.add(leg);
        });
    } else if (shape === 'table') {
        const top = new THREE.Mesh(new THREE.BoxGeometry(1.8,.08,1.8), mat());
        top.position.set(.9,.7,.9); g.add(top);
        [[.15,.15],[.15,1.65],[1.65,.15],[1.65,1.65]].forEach(([x,z]) => {
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(.06,.06,.7,8), mat());
            leg.position.set(x,.35,z); g.add(leg);
        });
        if (ad.fx === 'hologram') {
            const hm = new THREE.MeshStandardMaterial({color:0x00aaff,transparent:true,opacity:.18,wireframe:false,emissive:0x0044ff,emissiveIntensity:.4});
            const hol = new THREE.Mesh(new THREE.BoxGeometry(1.5,.3,1.5), hm);
            hol.position.set(.9,.88,.9); g.add(hol);
            animObjects.push({obj:hol, type:'spin_y', speed:.4});
        }
    } else if (shape === 'lamp') {
        const base = new THREE.Mesh(new THREE.CylinderGeometry(.22,.26,.1,12), mat());
        base.position.set(.5,.05,.5); g.add(base);
        const pole = new THREE.Mesh(new THREE.CylinderGeometry(.04,.04,1.1,8), mat());
        pole.position.set(.5,.6,.5); g.add(pole);
        const smat = new THREE.MeshStandardMaterial({color:col,roughness:.5,metalness:.2,transparent:true,opacity:.7,side:THREE.DoubleSide});
        const shade = new THREE.Mesh(new THREE.ConeGeometry(.32,.38,12,1,true), smat);
        shade.position.set(.5,1.1,.5); g.add(shade);
        const glow = new THREE.Mesh(new THREE.SphereGeometry(.12,8,8),new THREE.MeshStandardMaterial({color:0xffffff,emissive:col,emissiveIntensity:2,transparent:true,opacity:.9}));
        glow.position.set(.5,.96,.5); g.add(glow);
        const pl = new THREE.PointLight(col, 3.5, 5.5, 1.2);
        pl.position.set(.5,.94,.5); g.add(pl);
        animObjects.push({obj:pl, type:'flicker', base:3.5, range:.6, speed:3+Math.random()*2});
    } else if (shape === 'rug') {
        const rugMat = new THREE.MeshStandardMaterial({color:col,roughness:.95,metalness:.0});
        const rug = new THREE.Mesh(new THREE.PlaneGeometry(1.82,1.82), rugMat);
        rug.rotation.x = -Math.PI/2; rug.position.set(.9,.005,.9); g.add(rug);
        // Hex border line
        const bMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.5,transparent:true,opacity:.4});
        const border = new THREE.Mesh(new THREE.RingGeometry(.85,.9,6), bMat);
        border.rotation.x = -Math.PI/2; border.position.set(.9,.006,.9); g.add(border);
    } else if (shape === 'trophy') {
        const bmat = mat();
        const base  = new THREE.Mesh(new THREE.CylinderGeometry(.28,.34,.18,12), bmat);
        base.position.set(.5,.09,.5); g.add(base);
        const stem  = new THREE.Mesh(new THREE.CylinderGeometry(.07,.07,.38,8), bmat);
        stem.position.set(.5,.36,.5); g.add(stem);
        const cup   = new THREE.Mesh(new THREE.CylinderGeometry(.28,.1,.46,12), bmat);
        cup.position.set(.5,.78,.5); g.add(cup);
        const gemMat = new THREE.MeshStandardMaterial({color:0xffd040,emissive:0xffd040,emissiveIntensity:1.2,metalness:.9,roughness:.1});
        const gem = new THREE.Mesh(new THREE.OctahedronGeometry(.13), gemMat);
        gem.position.set(.5,1.13,.5); g.add(gem);
        animObjects.push({obj:gem, type:'spin_y', speed:.8});
    } else if (shape === 'capsule_bed') {
        const bmat = new THREE.MeshStandardMaterial({color:0x050c18,roughness:.4,metalness:.9});
        const body = new THREE.Mesh(new THREE.CapsuleGeometry(.38,1.14,8,16), bmat);
        body.rotation.z = Math.PI/2; body.position.set(1.0,.38,.5); g.add(body);
        const glowMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.2});
        const gs = new THREE.Mesh(new THREE.BoxGeometry(1.76,.04,.03), glowMat);
        gs.position.set(1.0,.68,.19); g.add(gs);
        const gs2 = gs.clone(); gs2.position.z = .81; g.add(gs2);
        const lid = new THREE.Mesh(new THREE.CapsuleGeometry(.4,1.18,8,8),
            new THREE.MeshStandardMaterial({color:col,transparent:true,opacity:.12,metalness:.8}));
        lid.rotation.z = Math.PI/2; lid.position.set(1.0,.38,.5); g.add(lid);
    } else if (shape === 'shelf') {
        const bmat = mat();
        const back = new THREE.Mesh(new THREE.BoxGeometry(1.82,1.2,.07), bmat);
        back.position.set(.9,.6,.035); g.add(back);
        [.18,.58,1.0].forEach(y => {
            const shelf = new THREE.Mesh(new THREE.BoxGeometry(1.82,.05,.28), bmat);
            shelf.position.set(.9,y,.14); g.add(shelf);
        });
        // Data cubes on shelves
        const dm = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:.4,transparent:true,opacity:.7});
        [[.25,.21,.16],[.7,.21,.16],[1.3,.21,.16],[.45,.61,.16],[1.1,.61,.16]].forEach(([x,y,z]) => {
            const cube = new THREE.Mesh(new THREE.BoxGeometry(.12,.12,.12), dm);
            cube.position.set(x,y,z); g.add(cube);
        });
    } else if (ad.fx === 'float' || shape === 'orb') {
        const orbMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.6,transparent:true,opacity:.88,roughness:.1,metalness:.8});
        const orb = new THREE.Mesh(new THREE.SphereGeometry(.28,20,20), orbMat);
        orb.position.set(.5,.65,.5); g.add(orb);
        const core = new THREE.Mesh(new THREE.SphereGeometry(.13,12,12),
            new THREE.MeshStandardMaterial({color:0xffffff,emissive:0xffffff,emissiveIntensity:1.5,transparent:true,opacity:.8}));
        core.position.set(.5,.65,.5); g.add(core);
        const ring = new THREE.Mesh(new THREE.TorusGeometry(.36,.02,8,32),
            new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.8,transparent:true,opacity:.6}));
        ring.position.set(.5,.65,.5); ring.rotation.x = Math.PI/3; g.add(ring);
        const pl = new THREE.PointLight(col, 2.5, 4);
        pl.position.set(.5,.65,.5); g.add(pl);
        animObjects.push({obj:g, type:'float', base:.65, amp:.18, speed:1.2+Math.random()*.4});
    } else if (ad.type === 'portrait') {
        const frame = new THREE.Mesh(new THREE.BoxGeometry(.92,.92,.08),
            new THREE.MeshStandardMaterial({color:0x1a0530,metalness:.8,roughness:.2}));
        frame.position.set(.5,.7,.04); g.add(frame);
        const fgMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.5});
        const fg = new THREE.Mesh(new THREE.PlaneGeometry(.78,.78), fgMat);
        fg.position.set(.5,.7,.09); g.add(fg);
        const gl = new THREE.Mesh(new THREE.BoxGeometry(.94,.94,.01),
            new THREE.MeshStandardMaterial({color:0xffffff,transparent:true,opacity:.06,metalness:1}));
        gl.position.set(.5,.7,.1); g.add(gl);

    } else if (shape === 'terminal') {
        // Holographic workstation
        const bmat = new THREE.MeshStandardMaterial({color:0x040c1c,roughness:.3,metalness:.9});
        const desk = new THREE.Mesh(new THREE.BoxGeometry(1.8,.08,1.0), bmat);
        desk.position.set(.9,.7,.5); g.add(desk);
        [[.15,.12],[1.65,.12],[.15,.88],[1.65,.88]].forEach(([x,z]) => {
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(.04,.04,.7,6), bmat);
            leg.position.set(x,.35,z); g.add(leg);
        });
        // Main holo screen (floating above desk)
        const screenMat = new THREE.MeshStandardMaterial({color:0x000a1f,emissive:0x0033bb,emissiveIntensity:1.4,transparent:true,opacity:.88,roughness:0});
        const screen = new THREE.Mesh(new THREE.BoxGeometry(1.55,.85,.025), screenMat);
        screen.position.set(.9,1.38,.15); g.add(screen);
        const frameMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.7,transparent:true,opacity:.5});
        const sframe = new THREE.Mesh(new THREE.BoxGeometry(1.57,.87,.01), frameMat);
        sframe.position.set(.9,1.38,.14); g.add(sframe);
        // Side mini screens
        [-0.75, 0.75].forEach((ox,i) => {
            const ms = new THREE.Mesh(new THREE.BoxGeometry(.55,.38,.02),
                new THREE.MeshStandardMaterial({color:0x000515,emissive: i===0 ? 0x003300 : 0x110033,emissiveIntensity:1.2,transparent:true,opacity:.82}));
            ms.position.set(.9+ox,1.25,.2); ms.rotation.y = ox > 0 ? -0.4 : 0.4; g.add(ms);
        });
        // Keyboard glow strip
        const kb = new THREE.Mesh(new THREE.BoxGeometry(1.2,.02,.3),
            new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.4,transparent:true,opacity:.3}));
        kb.position.set(.9,.79,.5); g.add(kb);
        // Scan light
        const scanMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.2,transparent:true,opacity:.3});
        const scanLine = new THREE.Mesh(new THREE.BoxGeometry(1.53,.018,.01), scanMat);
        scanLine.position.set(.9,.96,.14); g.add(scanLine);
        animObjects.push({obj:scanLine, type:'scan_y', base:.96, top:1.78, speed:.7});
        const pl = new THREE.PointLight(col, 2.5, 5);
        pl.position.set(.9,1.2,.4); g.add(pl);
        animObjects.push({obj:pl, type:'flicker', base:2.5, range:.4, speed:1.8});

    } else if (shape === 'sofa') {
        // Luxury cyberpunk sectional sofa
        const fm = mat();
        fm.roughness = 0.75; fm.metalness = 0.1;
        const base = new THREE.Mesh(new THREE.BoxGeometry(1.85,.28,1.0), fm);
        base.position.set(.925,.14,.5); g.add(base);
        const seat = new THREE.Mesh(new THREE.BoxGeometry(1.85,.15,.85), fm);
        seat.position.set(.925,.34,.47); g.add(seat);
        const back = new THREE.Mesh(new THREE.BoxGeometry(1.85,.7,.2), fm);
        back.position.set(.925,.66,.1); g.add(back);
        const arm1 = new THREE.Mesh(new THREE.BoxGeometry(.18,.5,.95), fm);
        arm1.position.set(.09,.25,.47); g.add(arm1);
        const arm2 = arm1.clone(); arm2.position.x = 1.76; g.add(arm2);
        // Cushions
        const cushMat = new THREE.MeshStandardMaterial({color:col,roughness:.7,metalness:.05});
        [.42,.925,1.43].forEach(cx => {
            const cush = new THREE.Mesh(new THREE.BoxGeometry(.42,.1,.7), cushMat);
            cush.position.set(cx,.43,.47); g.add(cush);
        });
        // LED strip under base
        const ledMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.2,transparent:true,opacity:.6});
        const led = new THREE.Mesh(new THREE.BoxGeometry(1.85,.015,.02), ledMat);
        led.position.set(.925,.005,.98); g.add(led);
        const pl = new THREE.PointLight(col, 1.2, 3.5);
        pl.position.set(.925,.05,.98); g.add(pl);
        animObjects.push({obj:pl, type:'pulse', base:1.2, range:.3, speed:.6});

    } else if (shape === 'aquarium') {
        // Holographic fish tank
        const glassMat = new THREE.MeshStandardMaterial({color:0x001428,roughness:0,metalness:.1,transparent:true,opacity:.18,side:THREE.DoubleSide});
        const tankBody = new THREE.Mesh(new THREE.BoxGeometry(1.8,1.2,1.8), glassMat);
        tankBody.position.set(.9,.65,.9); g.add(tankBody);
        // Inner water glow
        const waterMat = new THREE.MeshStandardMaterial({color:0x001830,emissive:0x00336a,emissiveIntensity:0.9,transparent:true,opacity:.35});
        const water = new THREE.Mesh(new THREE.BoxGeometry(1.7,1.1,1.7), waterMat);
        water.position.set(.9,.65,.9); g.add(water);
        // Frame edges
        const fmat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:.5,transparent:true,opacity:.7});
        [[0,.65,0],[1.8,.65,0],[0,.65,1.8],[1.8,.65,1.8]].forEach(([x,y,z]) => {
            const post = new THREE.Mesh(new THREE.BoxGeometry(.06,1.2,.06), fmat);
            post.position.set(x,y,z); g.add(post);
        });
        // Floating fish particles
        for (let f = 0; f < 6; f++) {
            const fishMat = new THREE.MeshStandardMaterial({
                color:[0xff4400,0x00bbff,0xffcc00,0x00ff88,0xff44aa,0x88ffff][f],
                emissiveIntensity:.6, roughness:.3
            });
            fishMat.emissive = fishMat.color;
            const fish = new THREE.Mesh(new THREE.CapsuleGeometry(.04,.1,4,6), fishMat);
            fish.position.set(.3+Math.random()*1.2, .3+Math.random()*.7, .3+Math.random()*1.2);
            fish.rotation.y = Math.random()*Math.PI*2;
            g.add(fish);
            animObjects.push({obj:fish, type:'fish', bx:.3+f*.22, bz:.3+(f*.17)%1.2,
                by:.3+Math.random()*.7, speed:.3+Math.random()*.4, phase:f*1.1});
        }
        // Base stand
        const stand = new THREE.Mesh(new THREE.BoxGeometry(1.85,.12,1.85),
            new THREE.MeshStandardMaterial({color:0x040d1a,roughness:.4,metalness:.8}));
        stand.position.set(.925,.06,.925); g.add(stand);
        const pl = new THREE.PointLight(0x0055aa, 2.8, 4.5);
        pl.position.set(.9,.65,.9); g.add(pl);
        animObjects.push({obj:pl, type:'pulse', base:2.8, range:.5, speed:.4});

    } else if (shape === 'neon_sign') {
        // Wall-mounted neon sign
        const backMat = new THREE.MeshStandardMaterial({color:0x020508,roughness:.5,metalness:.7});
        const back = new THREE.Mesh(new THREE.BoxGeometry(1.7,.6,.06), backMat);
        back.position.set(.85,.7,.03); g.add(back);
        const txt = (ad.text || '⬡ NEXUS ⬡');
        // Tube glow border
        const neonMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:3.5,transparent:true,opacity:.95});
        const top = new THREE.Mesh(new THREE.BoxGeometry(1.64,.025,.025), neonMat);
        top.position.set(.85,.97,.08); g.add(top);
        const bot = top.clone(); bot.position.y = .44; g.add(bot);
        const lft = new THREE.Mesh(new THREE.BoxGeometry(.025,.5,.025), neonMat);
        lft.position.set(.09,.7,.08); g.add(lft);
        const rgt = lft.clone(); rgt.position.x = 1.61; g.add(rgt);
        // Text glow plane
        const glowMat = new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.2,transparent:true,opacity:.18});
        const glow = new THREE.Mesh(new THREE.PlaneGeometry(1.6,.52), glowMat);
        glow.position.set(.85,.7,.1); g.add(glow);
        // Wall mounting screws
        [[.18,.66],[1.52,.66],[.18,.74],[1.52,.74]].forEach(([sx,sy]) => {
            const sc = new THREE.Mesh(new THREE.CylinderGeometry(.025,.025,.02,6),
                new THREE.MeshStandardMaterial({color:0x334455,metalness:1,roughness:.2}));
            sc.position.set(sx,sy,-.01); sc.rotation.x = Math.PI/2; g.add(sc);
        });
        const pl = new THREE.PointLight(col, 2.2, 5);
        pl.position.set(.85,.7,.4); g.add(pl);
        animObjects.push({obj:pl, type:'flicker', base:2.2, range:.5, speed:2.5+Math.random()});

    } else if (shape === 'arcade') {
        // Cyberpunk arcade cabinet
        const bmat = new THREE.MeshStandardMaterial({color:0x0a0318,roughness:.35,metalness:.8});
        const cabinet = new THREE.Mesh(new THREE.BoxGeometry(.8,1.65,.55), bmat);
        cabinet.position.set(.4,.825,.275); g.add(cabinet);
        // Screen
        const scrMat = new THREE.MeshStandardMaterial({color:0x000410,emissive:0x002255,emissiveIntensity:1.5,transparent:true,opacity:.95,roughness:0});
        const scr = new THREE.Mesh(new THREE.BoxGeometry(.58,.42,.02), scrMat);
        scr.position.set(.4,1.2,.56); scr.rotation.x = -0.28; g.add(scr);
        // Screen scan
        const sScan = new THREE.Mesh(new THREE.BoxGeometry(.56,.015,.01),
            new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.5,transparent:true,opacity:.4}));
        sScan.position.set(.4,.99,.57); sScan.rotation.x = -0.28; g.add(sScan);
        animObjects.push({obj:sScan, type:'scan_y', base:.99, top:1.4, speed:1.1});
        // Control panel
        const cpMat = new THREE.MeshStandardMaterial({color:0x12032a,roughness:.4,metalness:.6});
        const cp = new THREE.Mesh(new THREE.BoxGeometry(.8,.25,.38), cpMat);
        cp.position.set(.4,.88,.44); cp.rotation.x = -.5; g.add(cp);
        // Buttons
        const btnColors = [0xff2244,0x00cc88,0xffcc00,0x0088ff,0xff44cc,0x00ffee];
        btnColors.forEach((bc, bi) => {
            const bm = new THREE.MeshStandardMaterial({color:bc,emissive:bc,emissiveIntensity:.8,roughness:.4});
            const btn = new THREE.Mesh(new THREE.CylinderGeometry(.04,.04,.025,8), bm);
            btn.position.set(.18+(bi%3)*.24,.915+Math.sin(bi*.8)*.01,.44+(Math.floor(bi/3))*.14);
            btn.rotation.x = -.5; g.add(btn);
        });
        // Joystick
        const jMat = new THREE.MeshStandardMaterial({color:0x220033,roughness:.4,metalness:.5});
        const jBase = new THREE.Mesh(new THREE.CylinderGeometry(.055,.06,.04,8), jMat);
        jBase.position.set(.7,.91,.44); jBase.rotation.x = -.5; g.add(jBase);
        const jStick = new THREE.Mesh(new THREE.CylinderGeometry(.025,.025,.12,8), jMat);
        jStick.position.set(.7,.93,.38); jStick.rotation.x = -.7; g.add(jStick);
        // Side accent strips
        [[.01,.825,.275],[.79,.825,.275]].forEach(([x,y,z]) => {
            const strip = new THREE.Mesh(new THREE.BoxGeometry(.015,1.65,.015),
                new THREE.MeshStandardMaterial({color:col,emissive:col,emissiveIntensity:1.2,transparent:true,opacity:.6}));
            strip.position.set(x,y,z); g.add(strip);
        });
        const pl = new THREE.PointLight(col, 2.0, 4.5);
        pl.position.set(.4,1.2,.8); g.add(pl);
        animObjects.push({obj:pl, type:'flicker', base:2.0, range:.6, speed:3.2});

    } else {
        // Default box
        const box = new THREE.Mesh(new THREE.BoxGeometry(.7,.7,.7), mat());
        box.position.set(.35,.35,.35); g.add(box);
    }

    // Rarity emission glow
    const rarityColors = {legendary:0xff9800, epic:0x9b30ff, special:0x00ff88};
    if (rarityColors[item.rarity]) {
        const gm = new THREE.MeshStandardMaterial({
            color:rarityColors[item.rarity], transparent:true, opacity:.12,
            side:THREE.BackSide, depthWrite:false
        });
        g.children.filter(c=>c.isMesh&&c.geometry&&!c.geometry.isBufferGeometry).forEach(c=>{
            const gv = new THREE.Mesh(c.geometry, gm);
            gv.position.copy(c.position); gv.rotation.copy(c.rotation);
            gv.scale.setScalar(1.18); g.add(gv);
        });
    }

    g.traverse(c => { if (c.isMesh) { c.castShadow = true; c.receiveShadow = true; } });
    return g;
}

// ─────────────────────────────────────────────────────────────────
// Render catalog & placed items
// ─────────────────────────────────────────────────────────────────
const rarityIcons = {common:'◻',rare:'◈',special:'◆',epic:'⬡',legendary:'✦'};
const rarityColors = {common:'#b4c8dc',rare:'#00a8ff',special:'#00ff88',epic:'#c06aff',legendary:'#ff9800'};

function renderCatalog(catFilter = 'all') {
    const list = document.getElementById('cat-list');
    list.innerHTML = '';
    catalogue.forEach(item => {
        if (catFilter !== 'all' && item.category !== catFilter) return;
        const ad = item.asset_data || {};
        const col = ad.color || '#00e8ff';
        const div = document.createElement('div');
        div.className = 'fi' + (selectedCatalogItem?.id === item.id ? ' sel' : '');
        const owned = ownedItems.has(item.id) || item.price_kp === 0;
        div.innerHTML = `
          <div class="fi-swatch" style="background:${col}22;border-color:${col}44;">
            <span style="font-size:16px">${rarityIcons[item.rarity]||'◻'}</span>
          </div>
          <div class="fi-info">
            <div class="fi-name">${item.name.toUpperCase()}</div>
            <div class="fi-meta">
              ${owned
                ? `<span class="owned-badge">✓ OWNED</span>`
                : `<span class="notowned-badge">◈ ${item.price_kp}</span>`}
              <span class="fi-size">${item.width}×${item.depth}</span>
              <span class="rarity-badge rb-${item.rarity}">${item.rarity.toUpperCase()}</span>
            </div>
          </div>`;
        div.addEventListener('click', () => {
            if (owned) {
                selectCatalogItem(item, div);
            } else {
                showBuyModal(item);
            }
        });
        list.appendChild(div);
    });
}

function selectCatalogItem(item, el) {
    document.querySelectorAll('.fi').forEach(f => f.classList.remove('sel'));
    el.classList.add('sel');
    selectedCatalogItem = item;

    const btn = document.getElementById('place-btn');
    btn.textContent = '⊕ PLACE ' + item.name.toUpperCase();
    btn.disabled = false;

    setMode('place');
    updateGhost();
}

window.activatePlace = () => {
    if (!selectedCatalogItem) return;
    const owned = ownedItems.has(selectedCatalogItem.id) || selectedCatalogItem.price_kp === 0;
    if (owned) {
        setMode('place');
    } else {
        showBuyModal(selectedCatalogItem);
    }
};

function renderPlaced() {
    placed.forEach(p => addPlacedMesh(p));
}

function addPlacedMesh(p) {
    if (placedMeshes.has(p.id)) return;
    const g = buildFurnitureMesh(p);
    g.position.set(p.cell_x * CS, 0, p.cell_y * CS);
    g.rotation.y = -(p.rotation || 0) * Math.PI / 2;
    g.userData = { placedId: p.id, item: p };
    scene.add(g);
    placedMeshes.set(p.id, g);
}

function removePlacedMesh(id) {
    const g = placedMeshes.get(id);
    if (g) { scene.remove(g); placedMeshes.delete(id); }
}

// ─────────────────────────────────────────────────────────────────
// Interaction
// ─────────────────────────────────────────────────────────────────
let mouse = new THREE.Vector2(), hoverCell = {x:-1, z:-1};

function onMouseMove(e) {
    if (isRightDrag) {
        const dx = e.clientX - dragStart.x;
        const dy = e.clientY - dragStart.y;
        dragStart = {x: e.clientX, y: e.clientY};
        camAngleH -= dx * 0.008;
        camAngleV  = Math.max(0.12, Math.min(Math.PI/2.2, camAngleV - dy * 0.006));
        updateOrbitCamera();
        return;
    }
    const wrap = document.getElementById('cv');
    const rect = wrap.getBoundingClientRect();
    mouse.x =  ((e.clientX - rect.left)  / rect.width)  * 2 - 1;
    mouse.y = -((e.clientY - rect.top)   / rect.height) * 2 + 1;
    updateHover();
}

function updateHover() {
    // Reset highlights
    resetCellHighlights();

    if (mode !== 'place' || !selectedCatalogItem) return;

    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObject(floorPlane);
    if (!hits.length) return;

    const p = hits[0].point;
    const cx = Math.floor(p.x / CS);
    const cz = Math.floor(p.z / CS);
    hoverCell = {x: cx, z: cz};

    const w = selectedCatalogItem.width || 1;
    const d = selectedCatalogItem.depth  || 1;
    let valid = true;

    for (let dx = 0; dx < w; dx++) {
        for (let dz = 0; dz < d; dz++) {
            const tx = cx + dx, tz = cz + dz;
            if (tx < 0 || tx >= GRID || tz < 0 || tz >= GRID) { valid = false; continue; }
            const occupied = placed.some(pl => {
                const pw = pl.width || 1, pd = pl.depth || 1;
                for (let px = 0; px < pw; px++)
                    for (let pz = 0; pz < pd; pz++)
                        if (pl.cell_x+px === tx && pl.cell_y+pz === tz) return true;
                return false;
            });
            if (occupied) valid = false;
            if (tx < GRID && tz < GRID) {
                const mat = floorCells[tx][tz].material;
                mat.color.set(valid ? 0x003322 : 0x330011);
                mat.emissive = new THREE.Color(valid ? 0x00ff44 : 0xff1133);
                mat.emissiveIntensity = 0.3;
                mat.opacity = 0.55;
            }
        }
    }

    // Position ghost
    if (ghostGroup) {
        ghostGroup.visible = true;
        ghostGroup.position.set(cx * CS, 0, cz * CS);
    }
}

function resetCellHighlights() {
    for (let x = 0; x < GRID; x++)
        for (let z = 0; z < GRID; z++) {
            const m = floorCells[x][z].material;
            m.opacity = 0.0;
            m.emissiveIntensity = 0;
        }
    if (ghostGroup) ghostGroup.visible = false;
    hoverCell = {x:-1, z:-1};
}

function updateGhost() {
    if (!ghostGroup) return;
    ghostGroup.clear();
    if (!selectedCatalogItem) return;
    const g = buildFurnitureMesh(selectedCatalogItem);
    g.traverse(c => {
        if (c.isMesh) {
            c.material = new THREE.MeshStandardMaterial({
                color:0x00ff88, transparent:true, opacity:.28, wireframe:false, emissive:0x00ff88, emissiveIntensity:.4
            });
        }
    });
    g.rotation.y = -ghostRot * Math.PI / 2;
    ghostGroup.add(g);
}

function onClick(e) {
    raycaster.setFromCamera(mouse, camera);

    if (mode === 'view' || mode === 'rotate' || mode === 'delete') {
        // Try to hit a placed mesh
        const meshArr = [];
        placedMeshes.forEach(g => g.traverse(c => { if (c.isMesh) meshArr.push(c); }));
        const hits = raycaster.intersectObjects(meshArr);
        if (hits.length) {
            const root = getRootGroup(hits[0].object);
            if (!root) return;
            const id = root.userData.placedId;
            if (!id) return;

            if (mode === 'view') {
                selectPlacedItem(id, root);
            } else if (mode === 'rotate') {
                apiRotate(id);
            } else if (mode === 'delete') {
                apiRemove(id);
            }
        }
        return;
    }

    if (mode === 'place' && selectedCatalogItem && hoverCell.x >= 0) {
        apiPlace(selectedCatalogItem.id, hoverCell.x, hoverCell.z, ghostRot);
    }
}

function getRootGroup(obj) {
    let cur = obj;
    while (cur.parent && !cur.userData.placedId) cur = cur.parent;
    return cur.userData.placedId ? cur : null;
}

function selectPlacedItem(id, group) {
    selectedPlacedId = id;
    const item = group.userData.item;
    document.getElementById('rip-name').textContent = item.name.toUpperCase();
    document.getElementById('rip-rarity').textContent  = item.rarity.toUpperCase();
    document.getElementById('rip-rarity').style.color  = rarityColors[item.rarity] || '#aaa';
    document.getElementById('rip-size').textContent   = `${item.width}×${item.depth}`;
    document.getElementById('rip-rot').textContent    = `${(item.rotation||0) * 90}°`;
    document.getElementById('rip-pos').textContent    = `${item.cell_x}, ${item.cell_y}`;
    document.getElementById('rip').classList.add('open');
}

window.deselectPlaced = () => {
    selectedPlacedId = null;
    document.getElementById('rip').classList.remove('open');
};

window.rotateSelected = () => { if (selectedPlacedId) apiRotate(selectedPlacedId); };
window.deleteSelected = () => { if (selectedPlacedId) { apiRemove(selectedPlacedId); window.deselectPlaced(); } };

function onWheel(e) {
    const d = e.deltaY > 0 ? 1.1 : 0.9;
    const a = document.getElementById('cv').clientWidth / document.getElementById('cv').clientHeight;
    const h = (camera.top - camera.bottom) / 2 * d;
    const w = h * a;
    camera.left = -w; camera.right = w;
    camera.top = h; camera.bottom = -h;
    camera.updateProjectionMatrix();
    // Also adjust orbit distance for consistent feel
    camDist = Math.max(10, Math.min(38, camDist * d));
}

// ─────────────────────────────────────────────────────────────────
// API Calls
// ─────────────────────────────────────────────────────────────────
async function apiPlace(fid, cx, cy, rot) {
    try {
        const r = await fetch('/api/nexus/sanctum.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'place', furniture_id:fid, cell_x:cx, cell_y:cy, rotation:rot, room:'main'})
        });
        const j = await r.json();
        if (!j.ok) { toast(j.error?.message || 'Placement failed', 'err'); return; }

        // Add to local state
        const itemData = catalogue.find(c => c.id === fid);
        const newPiece = {...itemData, id:j.data.id, cell_x:cx, cell_y:cy, rotation:rot, room:'main'};
        placed.push(newPiece);
        addPlacedMesh(newPiece);
        toast('PLACED: ' + itemData.name.toUpperCase(), 'ok');
        resetCellHighlights();
    } catch(e) { toast('Network error', 'err'); }
}

async function apiRemove(pid) {
    try {
        const r = await fetch('/api/nexus/sanctum.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'remove', placed_id:pid})
        });
        const j = await r.json();
        if (!j.ok) { toast(j.error?.message || 'Remove failed', 'err'); return; }
        placed = placed.filter(p => p.id !== pid);
        removePlacedMesh(pid);
        toast('OBJECT REMOVED', 'info');
    } catch(e) { toast('Network error', 'err'); }
}

async function apiRotate(pid) {
    try {
        const r = await fetch('/api/nexus/sanctum.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'rotate', placed_id:pid})
        });
        const j = await r.json();
        if (!j.ok) { toast(j.error?.message || 'Rotate failed', 'err'); return; }

        const pi = placed.find(p => p.id === pid);
        if (pi) {
            pi.rotation = ((pi.rotation||0)+1)%4;
            const g = placedMeshes.get(pid);
            if (g) g.rotation.y = -pi.rotation * Math.PI/2;
        }
        toast('ROTATED', 'info');
    } catch(e) { toast('Network error', 'err'); }
}

async function apiBuy(fid) {
    try {
        const r = await fetch('/api/nexus/sanctum.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'buy', furniture_id:fid})
        });
        const j = await r.json();
        if (!j.ok) { toast(j.error?.message || 'Purchase failed', 'err'); return false; }
        balance = j.data.balance;
        document.getElementById('kp-val').textContent = balance.toLocaleString();
        ownedItems.add(fid);
        renderCatalog(document.querySelector('.cat-tab.on')?.dataset.cat || 'all');
        return true;
    } catch(e) { toast('Network error', 'err'); return false; }
}

function showBuyModal(item) {
    buyCandidate = item;
    const ad = item.asset_data || {};
    document.getElementById('buy-preview').textContent = rarityIcons[item.rarity] || '◻';
    document.getElementById('buy-name').textContent = item.name.toUpperCase();
    const rlbl = document.getElementById('buy-rar');
    rlbl.textContent = item.rarity.toUpperCase();
    rlbl.style.color = rarityColors[item.rarity] || '#aaa';
    document.getElementById('buy-kp').textContent = item.price_kp.toLocaleString();
    document.getElementById('buy-bal').textContent = balance.toLocaleString();
    const btn = document.getElementById('buy-confirm-btn');
    btn.disabled = balance < item.price_kp;
    btn.textContent = balance < item.price_kp ? '✕ INSUFFICIENT KP' : '◈ PURCHASE';
    document.getElementById('buy-modal').classList.add('open');
}

window.confirmBuy = async () => {
    if (!buyCandidate) return;
    const item = buyCandidate;
    closeBuyModal();
    const ok = await apiBuy(item.id);
    if (ok) {
        toast(`PURCHASED: ${item.name.toUpperCase()}`, 'ok');
        // Auto-select for placement
        const el = document.querySelector(`.fi`);
        selectCatalogItem(item, el || document.createElement('div'));
    }
};

window.closeBuyModal = () => {
    buyCandidate = null;
    document.getElementById('buy-modal').classList.remove('open');
};

// Close buy modal on backdrop
document.getElementById('buy-modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) window.closeBuyModal();
});

// ─────────────────────────────────────────────────────────────────
// Theme System
// ─────────────────────────────────────────────────────────────────
const THEMES = {
    cyber:    {main:0x00e8ff, accent:0x9b30ff, fog:0x020508, css:'#00e8ff'},
    neon:     {main:0xff0080, accent:0xff8800, fog:0x0a0005, css:'#ff0080'},
    dark:     {main:0x9b30ff, accent:0x00e8ff, fog:0x050010, css:'#9b30ff'},
    hologram: {main:0x0088ff, accent:0x00ffdd, fog:0x000815, css:'#0088ff'},
    nature:   {main:0x00ff44, accent:0x88ff00, fog:0x010a03, css:'#00ff44'}
};

function applyTheme(theme) {
    const t = THEMES[theme] || THEMES.cyber;
    if (scene.fog) scene.fog.color.setHex(t.fog);
    // Update CSS variable for UI accents
    document.documentElement.style.setProperty('--theme-main', t.css);
    // Walk scene for known emissive objects (baseboard strips, post, etc.)
    scene.traverse(obj => {
        if (!obj.isMesh) return;
        const m = obj.material;
        if (m && m.isArray) return;
        if (m && m.emissive && m.userData && m.userData.themeColor) {
            m.color.setHex(t.main);
            m.emissive.setHex(t.main);
        }
    });
}

// ─────────────────────────────────────────────────────────────────
// Mode & UI
// ─────────────────────────────────────────────────────────────────
window.setMode = (m) => {
    mode = m;
    ['view','place','rotate','delete'].forEach(id => {
        document.getElementById('mode-'+id).classList.toggle('on', id === m);
    });
    resetCellHighlights();
    if (m !== 'view') window.deselectPlaced();
    if (m !== 'place') { ghostGroup.visible = false; }
    // Hide hint after first mode change
    document.getElementById('hint-chip').classList.add('hidden');
};

window.toggleCatalog = () => {
    catalogOpen = !catalogOpen;
    document.getElementById('lp').classList.toggle('open', catalogOpen);
    document.getElementById('cat-toggle').classList.toggle('on', catalogOpen);
};

// Init catalog open
document.getElementById('lp').classList.add('open');

// Category tabs with counts
function updateTabCounts() {
    document.querySelectorAll('.cat-tab').forEach(tab => {
        const cat = tab.dataset.cat;
        const count = cat === 'all' ? catalogue.length
            : catalogue.filter(i => i.category === cat).length;
        tab.textContent = (cat === 'all' ? 'ALL' :
            cat === 'floor' ? 'FLOOR' :
            cat === 'wall' ? 'WALL' :
            cat === 'decoration' ? 'DECO' : 'LIVE') + ` (${count})`;
    });
}

document.getElementById('cat-tabs').addEventListener('click', e => {
    const tab = e.target.closest('.cat-tab');
    if (!tab) return;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('on'));
    tab.classList.add('on');
    renderCatalog(tab.dataset.cat);
});

// Keyboard shortcuts
// ─────────────────────────────────────────────────────────────────
// Hero Player System
// ─────────────────────────────────────────────────────────────────
function mkProceduralHero() {
    const g = new THREE.Group();
    const c = new THREE.Color(0x00e8ff);
    const bMat = new THREE.MeshStandardMaterial({color:0x00e8ff,metalness:.4,roughness:.42,emissive:c.clone().multiplyScalar(.15),emissiveIntensity:1});
    // Legs
    for (const s of [-1,1]) {
        const l = new THREE.Mesh(new THREE.CapsuleGeometry(.08,.38,4,6), new THREE.MeshStandardMaterial({color:0x002838,metalness:.3,roughness:.6}));
        l.position.set(s*.1,.22,0); g.add(l);
    }
    // Body
    const bod = new THREE.Mesh(new THREE.CapsuleGeometry(.18,.32,4,10), bMat);
    bod.position.y = .68; g.add(bod);
    // Arms
    for (const s of [-1,1]) {
        const a = new THREE.Mesh(new THREE.CapsuleGeometry(.065,.28,4,6), new THREE.MeshStandardMaterial({color:0x00e8ff,metalness:.35,roughness:.5}));
        a.position.set(s*.29,.65,0); a.rotation.z = s*.35; g.add(a);
    }
    // Head
    const head = new THREE.Mesh(new THREE.SphereGeometry(.2,14,10), new THREE.MeshStandardMaterial({color:0xd0bfb0,metalness:.12,roughness:.68}));
    head.position.y = 1.06; g.add(head);
    // Visor
    const vc = new THREE.Color(0x00e8ff);
    const vis = new THREE.Mesh(new THREE.SphereGeometry(.2,14,10,0,Math.PI*2,0,Math.PI*.42),
        new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:vc,emissiveIntensity:.9,transparent:true,opacity:.55,side:THREE.DoubleSide}));
    vis.rotation.x = Math.PI*.58; vis.position.set(0,1.12,.08); g.add(vis);
    // Shadow disc
    const sh = new THREE.Mesh(new THREE.CircleGeometry(.22,16),
        new THREE.MeshStandardMaterial({color:0x00e8ff,transparent:true,opacity:.1}));
    sh.rotation.x = -Math.PI/2; sh.position.y = .01; g.add(sh);
    // Glow
    const pl = new THREE.PointLight(0x00e8ff,.8,3,2); pl.position.y = .7; g.add(pl);
    return g;
}

function spawnHero() {
    if (hero) { scene.remove(hero); hero = null; heroMixer = null; }
    if (HERO_MODEL_URL) {
        _gltfLoader.load(
            HERO_MODEL_URL,
            gltf => {
                const model = gltf.scene;
                // Normalize to ~1.25 units tall for a cozy room
                const box = new THREE.Box3().setFromObject(model);
                const size = new THREE.Vector3();
                box.getSize(size);
                const maxDim = Math.max(size.x, size.y, size.z);
                if (maxDim > 0) model.scale.setScalar(1.25 / maxDim);
                const box2 = new THREE.Box3().setFromObject(model);
                model.position.y -= box2.min.y;
                model.traverse(o => { if (o.isMesh) { o.castShadow = true; o.receiveShadow = true; } });

                hero = new THREE.Group();
                hero.add(model);
                hero.position.set(4.5, 0, 4.5); // center of room
                scene.add(hero);

                if (gltf.animations?.length) {
                    heroMixer = new THREE.AnimationMixer(model);
                    let clipIdle = null, clipWalk = null;
                    gltf.animations.forEach(clip => {
                        const n = clip.name.toLowerCase();
                        if (!clipIdle && (n.includes('idle')||n.includes('stand')||n.includes('tpose')||n.includes('t-pose'))) clipIdle = clip;
                        if (!clipWalk && (n.includes('walk')||n.includes('run')||n.includes('move'))) clipWalk = clip;
                    });
                    if (!clipIdle && gltf.animations[0]) clipIdle = gltf.animations[0];
                    if (!clipWalk && gltf.animations[1]) clipWalk = gltf.animations[1];
                    if (clipIdle) { heroActionIdle = heroMixer.clipAction(clipIdle); heroActionIdle.play(); }
                    if (clipWalk) { heroActionWalk = heroMixer.clipAction(clipWalk); }
                }
            },
            null,
            err => {
                console.warn('[hero] GLB failed, using procedural', err);
                hero = mkProceduralHero();
                hero.position.set(4.5, 0, 4.5);
                scene.add(hero);
            }
        );
    } else {
        hero = mkProceduralHero();
        hero.position.set(4.5, 0, 4.5);
        scene.add(hero);
    }
}

function tickHero(dt, t) {
    if (!hero) return;
    // Gather input (WASD)
    let ix = 0, iz = 0;
    if (heroKeys['KeyW'] || heroKeys['ArrowUp'])    iz -= 1;
    if (heroKeys['KeyS'] || heroKeys['ArrowDown'])  iz += 1;
    if (heroKeys['KeyA'] || heroKeys['ArrowLeft'])  ix -= 1;
    if (heroKeys['KeyD'] || heroKeys['ArrowRight']) ix += 1;
    const inp = new THREE.Vector3(ix, 0, iz);
    if (inp.lengthSq() > 0) inp.normalize();
    inp.multiplyScalar(4); // walk speed
    const sm = 1 - Math.exp(-10 * dt);
    heroVel.x = THREE.MathUtils.lerp(heroVel.x, inp.x, sm);
    heroVel.z = THREE.MathUtils.lerp(heroVel.z, inp.z, sm);

    const margin = 0.3;
    hero.position.x = THREE.MathUtils.clamp(hero.position.x + heroVel.x * dt, margin, GRID * CS - margin);
    hero.position.z = THREE.MathUtils.clamp(hero.position.z + heroVel.z * dt, margin, GRID * CS - margin);

    const moving = heroVel.lengthSq() > 0.01;
    if (moving) hero.rotation.y = THREE.MathUtils.lerp(hero.rotation.y, Math.atan2(heroVel.x, heroVel.z), .15);

    // Switch animations
    if (heroMixer) {
        heroMixer.update(dt);
        if (moving && !heroIsWalking && heroActionWalk) {
            heroIsWalking = true;
            if (heroActionIdle) heroActionIdle.fadeOut(0.25);
            heroActionWalk.reset().fadeIn(0.25).play();
        } else if (!moving && heroIsWalking) {
            heroIsWalking = false;
            if (heroActionWalk) heroActionWalk.fadeOut(0.25);
            if (heroActionIdle) heroActionIdle.reset().fadeIn(0.25).play();
        }
    }

    // Floating nameplate or shadow bob
    if (!heroMixer) hero.position.y = Math.sin(t * 2.5) * 0.025;
}

// E key: interact with nearest furniture
function heroInteract() {
    if (!hero) return;
    let nearest = null, nearDist = Infinity;
    placed.forEach(p => {
        const dx = hero.position.x - (p.cell_x + 0.5);
        const dz = hero.position.z - (p.cell_y + 0.5);
        const dist = Math.sqrt(dx*dx + dz*dz);
        if (dist < 2.5 && dist < nearDist) { nearDist = dist; nearest = p; }
    });
    if (nearest) {
        // Show info about this piece in the sidebar / toast
        toast(`${nearest.name || nearest.code} — ${nearest.rarity || ''}`, 'info');
        // Select it to show info panel
        const mesh = placedMeshes.get(nearest.id);
        if (mesh) selectPlacedItem(nearest.id, mesh);
    }
}

// Track WASD globally (but skip if typing in inputs)
window.addEventListener('keydown', e => {
    if (['INPUT','SELECT','TEXTAREA'].includes(e.target.tagName)) return;
    heroKeys[e.code] = true;
    if (e.code === 'KeyE') heroInteract();
});
window.addEventListener('keyup', e => { heroKeys[e.code] = false; });

// ─────────────────────────────────────────────────────────────────
// Keyboard Shortcuts
// ─────────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (['INPUT','SELECT','TEXTAREA'].includes(e.target.tagName)) return;
    if (e.key === 'r' || e.key === 'R') {
        ghostRot = (ghostRot + 1) % 4;
        updateGhost();
    }
    if (e.key === 'v' || e.key === 'V') setMode('view');
    if (e.key === 'p' || e.key === 'P') { if (selectedCatalogItem) setMode('place'); }
    if (e.key === 't' || e.key === 'T') setMode('rotate');
    if (e.key === 'x' || e.key === 'X') setMode('delete');
    if (e.key === 'Escape') {
        window.deselectPlaced();
        window.closeBuyModal();
        selectedCatalogItem = null;
        document.querySelectorAll('.fi').forEach(f => f.classList.remove('sel'));
        document.getElementById('place-btn').textContent = 'SELECT AN ITEM';
        document.getElementById('place-btn').disabled = true;
        setMode('view');
    }
    // Double-tap F = reset camera
    if (e.key === 'f' || e.key === 'F') {
        camAngleH = Math.PI/4; camAngleV = Math.PI/5; camDist = 22;
        resetCamera();
    }
});

// Settings
window.openSettings = () => {
    if (plotData) {
        document.getElementById('s-name').value  = plotData.house_name || '';
        document.getElementById('s-theme').value = plotData.exterior_theme || 'cyber';
        document.getElementById('s-color').value = plotData.exterior_color || '#00e8ff';
        if (plotData.is_public)
            document.getElementById('s-public').classList.add('on');
        else
            document.getElementById('s-public').classList.remove('on');
    }
    document.getElementById('modal').classList.add('open');
};
window.closeSettings = () => document.getElementById('modal').classList.remove('open');

window.saveSettings = async () => {
    const name  = document.getElementById('s-name').value;
    const theme = document.getElementById('s-theme').value;
    const color = document.getElementById('s-color').value;
    const pub   = document.getElementById('s-public').classList.contains('on');
    try {
        const r = await fetch('/api/nexus/sanctum.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'update_plot', house_name:name, exterior_theme:theme, exterior_color:color, is_public:pub})
        });
        const j = await r.json();
        if (j.ok) {
            const rname = name || 'MY SANCTUM';
            document.getElementById('room-name').textContent = rname.toUpperCase();
            if (plotData) { plotData.house_name = name; plotData.exterior_theme = theme; plotData.exterior_color = color; plotData.is_public = pub ? 1 : 0; }
            applyTheme(theme);
            toast('SETTINGS SAVED', 'ok');
            window.closeSettings();
        } else {
            toast(j.message || 'Save failed', 'err');
        }
    } catch(e) { toast('Network error', 'err'); }
};

// CRT transition
window.crtGo = (url) => {
    const crt = document.getElementById('crt');
    crt.className = 'off';
    setTimeout(() => location.href = url, 650);
};

// Toast
window.toast = (msg, type='info') => {
    const d = document.createElement('div');
    d.className = 'toast ' + type;
    d.textContent = msg;
    document.getElementById('toasts').appendChild(d);
    setTimeout(() => d.style.opacity = '0', 2200);
    setTimeout(() => d.remove(), 2600);
};

// Close modal on backdrop click
document.getElementById('modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) window.closeSettings();
});

// ─────────────────────────────────────────────────────────────────
// Animation Loop
// ─────────────────────────────────────────────────────────────────
function animate() {
    requestAnimationFrame(animate);
    const dt = clock.getDelta();
    const t  = clock.getElapsedTime();

    // Hero player
    tickHero(dt, t);

    // Animate registered objects
    animObjects.forEach(ao => {
        if (ao.type === 'float') {
            ao.obj.position.y = ao.base + Math.sin(t * ao.speed) * ao.amp;
            ao.obj.rotation.y += dt * 0.4;
        } else if (ao.type === 'spin_y') {
            ao.obj.rotation.y += dt * ao.speed;
        } else if (ao.type === 'flicker') {
            ao.obj.intensity = ao.base + Math.sin(t * ao.speed) * ao.range * (0.5 + 0.5*Math.sin(t * ao.speed * 3.7));
        } else if (ao.type === 'pulse') {
            ao.obj.intensity = ao.base + Math.sin(t * ao.speed) * ao.range;
        } else if (ao.type === 'scan_y') {
            ao.obj.position.y = ao.base + ((t * ao.speed) % 1.0) * (ao.top - ao.base);
        } else if (ao.type === 'fish') {
            ao.obj.position.x = ao.bx + Math.sin(t * ao.speed + ao.phase) * 0.55;
            ao.obj.position.y = ao.by + Math.sin(t * ao.speed * 0.7 + ao.phase) * 0.2;
            ao.obj.position.z = ao.bz + Math.cos(t * ao.speed + ao.phase) * 0.45;
            ao.obj.rotation.y = t * ao.speed + ao.phase;
        }
    });

    // Particles
    if (particles) {
        const pos = particles.geometry.attributes.position.array;
        const vel = particles.geometry.userData.velocities;
        for (let i = 0; i < pos.length / 3; i++) {
            pos[i*3+1] += vel[i*3+1];
            if (pos[i*3+1] > 3.2) pos[i*3+1] = 0;
        }
        particles.geometry.attributes.position.needsUpdate = true;
    }

    renderer.render(scene, camera);
}

</script>
</body>
</html>

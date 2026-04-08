<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/config.php';
if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SANCTUM — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"}}</script>
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
</style>
</head>
<body>

<!-- CRT -->
<div id="crt"></div>

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
    <span class="t-icon">◉</span><span class="t-lbl">VIEW</span>
  </div>
  <div class="t-btn" id="mode-place" onclick="setMode('place')">
    <span class="t-icon">⊕</span><span class="t-lbl">PLACE</span>
  </div>
  <div class="t-btn" id="mode-rotate" onclick="setMode('rotate')">
    <span class="t-icon">↻</span><span class="t-lbl">ROTATE</span>
  </div>
  <div class="t-btn danger" id="mode-delete" onclick="setMode('delete')">
    <span class="t-icon">⌫</span><span class="t-lbl">DELETE</span>
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
        if (!json.success) throw new Error(json.message);

        setLoadProgress(60);
        catalogue  = json.data.catalog;
        placed     = json.data.placed;
        plotData   = json.data.plot;
        balance    = json.data.balance;

        document.getElementById('kp-val').textContent = balance.toLocaleString();
        const rname = plotData.house_name || (json.data.username.toUpperCase() + '\'S SANCTUM');
        document.getElementById('room-name').textContent = rname;

        buildScene();
        renderCatalog();
        renderPlaced();
        setLoadProgress(100);

    } catch(e) {
        console.error('Sanctum load error:', e);
        buildScene();
        renderCatalog();
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
    buildWalls();
    buildCeiling();
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
    addCircuitLines(lw);

    // Back wall (z=0 face, x-axis)
    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 3.5), wallMat.clone());
    bw.position.set(5, 1.75, 0);
    scene.add(bw);
    addCircuitLines(bw);

    // Baseboard glow strips
    const gMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.8,transparent:true,opacity:0.5});
    const strip1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.05, 0.04), gMat);
    strip1.position.set(5, 0.025, 0.02);
    scene.add(strip1);
    const strip2 = strip1.clone();
    strip2.rotation.y = Math.PI/2;
    strip2.position.set(0.02, 0.025, 5);
    scene.add(strip2);

    // Corner post
    const post = new THREE.Mesh(new THREE.BoxGeometry(0.08, 3.5, 0.08), gMat.clone());
    post.position.set(0.04, 1.75, 0.04);
    scene.add(post);
}

function addCircuitLines(wallMesh) {
    // Emissive circuit-style lines overlay on wall
    const lineMat = new THREE.MeshStandardMaterial({color:0x00e8ff,emissive:0x00e8ff,emissiveIntensity:0.25,transparent:true,opacity:0.12});
    const geo = new THREE.BoxGeometry(0.02, 0.02, 8);
    for (let i = 0; i < 3; i++) {
        const bar = new THREE.Mesh(geo, lineMat);
        bar.position.copy(wallMesh.position);
        bar.rotation.copy(wallMesh.rotation);
        bar.position.y = 0.5 + i * 1.1;
        scene.add(bar);
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
        div.innerHTML = `
          <div class="fi-swatch" style="background:${col}22;border-color:${col}44;">
            <span style="font-size:16px">${rarityIcons[item.rarity]||'◻'}</span>
          </div>
          <div class="fi-info">
            <div class="fi-name">${item.name.toUpperCase()}</div>
            <div class="fi-meta">
              <span class="fi-price">◈ ${item.price_kp}</span>
              <span class="fi-size">${item.width}×${item.depth}</span>
              <span class="rarity-badge rb-${item.rarity}">${item.rarity.toUpperCase()}</span>
            </div>
          </div>`;
        div.addEventListener('click', () => selectCatalogItem(item, div));
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
    if (selectedCatalogItem) setMode('place');
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
    const d = e.deltaY > 0 ? 1.12 : 0.88;
    const a = document.getElementById('cv').clientWidth / document.getElementById('cv').clientHeight;
    const h = (camera.top - camera.bottom) / 2 * d;
    const w = h * a;
    camera.left = -w; camera.right = w;
    camera.top = h; camera.bottom = -h;
    camera.updateProjectionMatrix();
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
        if (!j.success) { toast(j.message || 'Placement failed', 'err'); return; }

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
        if (!j.success) { toast(j.message || 'Remove failed', 'err'); return; }
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
        if (!j.success) { toast(j.message || 'Rotate failed', 'err'); return; }

        const pi = placed.find(p => p.id === pid);
        if (pi) {
            pi.rotation = ((pi.rotation||0)+1)%4;
            const g = placedMeshes.get(pid);
            if (g) g.rotation.y = -pi.rotation * Math.PI/2;
        }
        toast('ROTATED', 'info');
    } catch(e) { toast('Network error', 'err'); }
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
};

window.toggleCatalog = () => {
    catalogOpen = !catalogOpen;
    document.getElementById('lp').classList.toggle('open', catalogOpen);
    document.getElementById('cat-toggle').classList.toggle('on', catalogOpen);
};

// Init catalog open
document.getElementById('lp').classList.add('open');

// Category tabs
document.getElementById('cat-tabs').addEventListener('click', e => {
    const tab = e.target.closest('.cat-tab');
    if (!tab) return;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('on'));
    tab.classList.add('on');
    renderCatalog(tab.dataset.cat);
});

// Ghost rotation with R key
document.addEventListener('keydown', e => {
    if (e.key === 'r' || e.key === 'R') {
        ghostRot = (ghostRot + 1) % 4;
        updateGhost();
    }
    if (e.key === 'Escape') {
        window.deselectPlaced();
        selectedCatalogItem = null;
        document.querySelectorAll('.fi').forEach(f => f.classList.remove('sel'));
        document.getElementById('place-btn').textContent = 'SELECT AN ITEM';
        document.getElementById('place-btn').disabled = true;
        setMode('view');
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
        if (j.success) {
            const rname = name || 'MY SANCTUM';
            document.getElementById('room-name').textContent = rname.toUpperCase();
            if (plotData) { plotData.house_name = name; plotData.exterior_theme = theme; plotData.exterior_color = color; plotData.is_public = pub ? 1 : 0; }
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

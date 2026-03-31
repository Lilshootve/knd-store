<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$mwCardCss = __DIR__ . '/../games/mind-wars/mw-avatar-cards.css';
$mwCardJs = __DIR__ . '/../games/mind-wars/mw-avatar-card.js';
$levelsCss = __DIR__ . '/../assets/css/levels.css';
$mwCardCssV = is_file($mwCardCss) ? filemtime($mwCardCss) : 0;
$mwCardJsV = is_file($mwCardJs) ? filemtime($mwCardJs) : 0;
$levelsCssV = is_file($levelsCss) ? filemtime($levelsCss) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Mind Wars Squad — Squad Select</title>
<link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo (int) $levelsCssV; ?>">
<link rel="stylesheet" href="/games/mind-wars/mw-avatar-cards.css?v=<?php echo (int) $mwCardCssV; ?>">
<style>
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Share+Tech+Mono&display=swap');

:root {
  --FD: 'Orbitron', monospace;
  --t1: rgba(220,245,255,0.93);
  --t2: rgba(150,210,230,0.6);
  --t3: rgba(90,160,185,0.38);
  --bg: #02040e;
  --panel: rgba(3,8,22,0.94);
  --cyan: #00e5ff;
  --cyan2: #0af;
  --gold: #ffd600;
  --purple: #c158ff;
  --red: #ff3d56;
  --green: #00ff88;
  --border: rgba(0,229,255,0.18);
  --border-hot: rgba(0,229,255,0.55);
  --text: #cce8ff;
  --text-dim: rgba(150,190,230,0.5);
}

* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:100%; height:100%; background:var(--bg); overflow:hidden; color:var(--text); font-family:'Share Tech Mono', monospace; cursor:default; }

/* ── SCANLINES ── */
body::after {
  content:''; position:fixed; inset:0; pointer-events:none; z-index:999;
  background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0,0,0,0.08) 3px, rgba(0,0,0,0.08) 4px);
}

/* ── NOISE GRAIN ── */
body::before {
  content:''; position:fixed; inset:0; pointer-events:none; z-index:998; opacity:0.025;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
  background-size:128px 128px;
}

/* ── LAYOUT ── */
#app { width:100%; height:100vh; display:flex; flex-direction:column; position:relative; overflow:hidden; }

/* ── BACKGROUND EFFECTS ── */
#bg-layer {
  position:absolute; inset:0; z-index:0;
  background:
    radial-gradient(ellipse 60% 50% at 20% 80%, rgba(0,60,120,0.25) 0%, transparent 70%),
    radial-gradient(ellipse 40% 60% at 80% 20%, rgba(100,0,180,0.15) 0%, transparent 70%),
    radial-gradient(ellipse 80% 40% at 50% 110%, rgba(0,180,255,0.08) 0%, transparent 60%);
}
#bg-grid {
  position:absolute; inset:0; z-index:0; opacity:0.18;
  background-image: linear-gradient(rgba(0,229,255,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(0,229,255,0.15) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
}
#bg-lines {
  position:absolute; inset:0; z-index:0; overflow:hidden;
}
.bg-line {
  position:absolute; width:1px; background:linear-gradient(to bottom, transparent, rgba(0,229,255,0.12), transparent);
  animation: linePulse 4s ease-in-out infinite;
  transform-origin:top;
}
@keyframes linePulse { 0%,100%{opacity:0.3;} 50%{opacity:1;} }

/* ── HEADER ── */
#header {
  position:relative; z-index:10;
  padding:14px 28px; border-bottom:1px solid var(--border);
  background:linear-gradient(to bottom, rgba(0,0,0,0.8), transparent);
  display:flex; align-items:center; justify-content:space-between;
  gap:16px;
}
#header-left { display:flex; align-items:center; gap:12px; flex-shrink:0; }
.header-back-btn {
  display:inline-flex; align-items:center; justify-content:center;
  font-family:'Orbitron',monospace; font-size:8px; font-weight:700; letter-spacing:2px;
  color:var(--cyan); text-decoration:none;
  padding:8px 14px; border:1px solid var(--border);
  background:rgba(0,20,40,0.65);
  clip-path:polygon(4px 0%,100% 0%,100% calc(100% - 4px),calc(100% - 4px) 100%,0% 100%,0% 4px);
  transition:border-color .2s, box-shadow .2s, color .2s;
  white-space:nowrap;
}
.header-back-btn:hover { border-color:var(--border-hot); color:#fff; box-shadow:0 0 12px rgba(0,229,255,0.12); }
.header-back-btn:focus-visible { outline:2px solid var(--cyan); outline-offset:3px; }
#logo { font-family:'Orbitron',monospace; font-size:11px; font-weight:700; letter-spacing:4px; color:var(--cyan); }
#title-block { text-align:center; }
#page-title { font-family:'Orbitron',monospace; font-size:16px; font-weight:900; letter-spacing:6px; color:#fff; }
#page-sub { font-size:8px; letter-spacing:3px; color:var(--text-dim); margin-top:3px; }
#header-right { font-size:9px; letter-spacing:2px; color:var(--text-dim); text-align:right; }
#header-right span { color:var(--cyan); }

/* ── STEP INDICATOR ── */
#steps {
  position:relative; z-index:10;
  display:flex; align-items:center; justify-content:center; gap:0;
  padding:10px 0; border-bottom:1px solid var(--border);
  background:rgba(0,0,0,0.3);
}
.step-item { display:flex; align-items:center; gap:0; }
.step-dot {
  width:28px; height:28px;
  border:1px solid rgba(0,229,255,0.25);
  background:rgba(0,10,30,0.9);
  display:flex; align-items:center; justify-content:center;
  font-family:'Orbitron',monospace; font-size:9px; font-weight:700;
  color:var(--text-dim);
  clip-path:polygon(4px 0%,100% 0%,100% calc(100% - 4px),calc(100% - 4px) 100%,0% 100%,0% 4px);
  transition:all .3s;
}
.step-dot.active { border-color:var(--cyan); color:var(--cyan); background:rgba(0,40,80,0.9); box-shadow:0 0 12px rgba(0,229,255,0.2); }
.step-dot.done { border-color:var(--green); color:var(--green); background:rgba(0,30,15,0.9); }
.step-label { font-size:8px; letter-spacing:1.5px; color:var(--text-dim); margin:0 10px; white-space:nowrap; }
.step-label.active { color:var(--cyan); }
.step-label.done { color:var(--green); }
.step-connector { width:40px; height:1px; background:var(--border); margin:0; }
.step-connector.done { background:rgba(0,255,136,0.4); }

/* ── MAIN CONTENT ── */
#main { flex:1; position:relative; z-index:10; display:flex; overflow:hidden; }

/* ── ROSTER PANEL (left) ── */
#roster-panel {
  width:300px; min-width:300px;
  border-right:1px solid var(--border);
  background:var(--panel);
  display:flex; flex-direction:column;
  min-height:0;
}
#roster-header {
  padding:10px 14px; border-bottom:1px solid var(--border);
  display:flex; justify-content:space-between; align-items:center;
}
#roster-title { font-size:8px; letter-spacing:2px; color:var(--cyan); }
#roster-count { font-size:8px; letter-spacing:1px; color:var(--text-dim); }
#roster-search {
  margin:8px 10px;
  background:rgba(0,229,255,0.04); border:1px solid var(--border);
  padding:5px 10px; font-family:'Share Tech Mono',monospace; font-size:9px;
  color:var(--text); letter-spacing:1px; width:calc(100% - 20px);
  outline:none; transition:border-color .2s;
}
#roster-search:focus { border-color:var(--border-hot); }
#roster-search::placeholder { color:var(--text-dim); }
#filter-row { display:flex; gap:4px; padding:0 10px 8px; flex-wrap:wrap; }
.filter-btn {
  font-size:7px; letter-spacing:1px; padding:3px 7px;
  border:1px solid rgba(0,229,255,0.2); background:transparent; color:var(--text-dim);
  font-family:'Share Tech Mono',monospace; cursor:pointer; transition:all .15s;
}
.filter-btn.active { border-color:var(--cyan); color:var(--cyan); background:rgba(0,229,255,0.08); }
/* Block flow avoids flex-item shrink/overlap bugs inside the scroll region */
#roster-list { flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; padding:6px 8px; display:block; }
#roster-list::-webkit-scrollbar { width:3px; }
#roster-list::-webkit-scrollbar-thumb { background:rgba(0,229,255,0.2); }

#roster-list .roster-entry {
  border:1px solid rgba(0,229,255,0.15);
  background:rgba(0,8,22,0.8);
  padding:10px 10px; margin-bottom:8px; cursor:pointer;
  display:flex; flex-direction:column; align-items:stretch; gap:8px;
  flex:none;
  clip-path:polygon(5px 0%,100% 0%,100% calc(100% - 5px),calc(100% - 5px) 100%,0% 100%,0% 5px);
  transition:all .18s; position:relative; overflow:hidden;
}
#roster-list .roster-entry:last-child { margin-bottom:0; }
#roster-list .roster-entry::before {
  content:''; position:absolute; left:0; top:0; bottom:0; width:2px;
  background:var(--rarity-color, var(--cyan)); opacity:0.6; transition:opacity .2s;
}
#roster-list .roster-entry:hover { border-color:rgba(0,229,255,0.45); background:rgba(0,20,50,0.9); }
#roster-list .roster-entry:hover::before { opacity:1; }
#roster-list .roster-entry.selected { border-color:var(--cyan); background:rgba(0,30,65,0.95); }
#roster-list .roster-entry.in-squad { opacity:0.4; pointer-events:none; }
#roster-list .roster-entry[draggable="true"] { cursor:grab; }
#roster-list .roster-entry.dragging-source { opacity:0.45; cursor:grabbing; touch-action:none; }
#roster-list .roster-entry[draggable="true"]{ touch-action:manipulation; }
.squad-slot-column.slot-drag-over .squad-slot-card-wrap {
  outline:2px solid var(--cyan);
  outline-offset:4px;
  background:rgba(0,229,255,0.06);
  border-radius:4px;
}
.squad-slot-column.slot-drag-over { transition:background .15s; }
#roster-list .re-thumb {
  width:100%; height:84px; min-height:84px; max-height:84px; background:rgba(0,229,255,0.05);
  border:1px solid rgba(0,229,255,0.12); flex:0 0 auto;
  display:flex; align-items:center; justify-content:center;
  font-size:28px; color:rgba(0,229,255,0.4);
  overflow:hidden;
  border-radius:2px;
  position:relative;
}
/* contain = personaje completo dentro del recuadro (cover recortaba solo la cabeza) */
#roster-list .re-thumb img {
  position:absolute; left:0; top:0; right:0; bottom:0;
  display:block; width:100%; height:100%;
  object-fit:contain; object-position:center center;
}
#roster-list .re-info { flex:0 0 auto; min-width:0; width:100%; }
#roster-list .re-name { font-family:'Orbitron',monospace; font-size:9px; font-weight:700; letter-spacing:1px; color:#e8f4ff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#roster-list .re-meta { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-top:0; }
#roster-list .re-rar { font-size:6px; letter-spacing:1px; padding:1px 4px; border:1px solid; }
#roster-list .re-class { font-size:7px; letter-spacing:.5px; color:var(--text-dim); }
#roster-list .re-stats { display:flex; gap:8px; flex-wrap:wrap; margin-top:2px; }
#roster-list .re-cstat { font-size:7px; color:var(--text-dim); letter-spacing:.5px; }
#roster-list .re-cstat span { color:var(--text); }

/* Rarity colors */
.rar-legendary { color:#ffd600; border-color:rgba(255,214,0,0.4); background:rgba(255,214,0,0.07); --rarity-color:#ffd600; }
.rar-epic      { color:#c158ff; border-color:rgba(193,88,255,0.4); background:rgba(193,88,255,0.07); --rarity-color:#c158ff; }
.rar-rare      { color:#4488ff; border-color:rgba(68,136,255,0.4);  background:rgba(68,136,255,0.07);  --rarity-color:#4488ff; }
.rar-common    { color:#44aaaa; border-color:rgba(68,170,170,0.3);  background:rgba(68,170,170,0.05);  --rarity-color:#44aaaa; }
.rar-special    { color:#00ffcc; border-color:rgba(0,255,204,0.35); background:rgba(0,255,204,0.06); --rarity-color:#00ffcc; }

/* ── CENTER PANEL ── */
#center-panel {
  flex:1; display:flex; flex-direction:column;
  min-height:0; align-items:stretch;
  padding:8px 12px 12px; gap:10px; overflow:hidden;
}

.mw-squad-select .squad-slot-column.active .squad-slot-card-inner {
  box-shadow:0 0 0 1px rgba(0,229,255,0.45), 0 0 28px rgba(0,229,255,0.12);
  border-radius:4px;
}

.mw-squad-select .squad-slot-card-inner .avatar-card:not(.is-placeholder) .inspect-btn {
  pointer-events:auto;
}

/* ── FORMATION HINT ── */
#formation-hint {
  width:100%; background:rgba(0,5,18,0.7);
  border:1px solid rgba(0,229,255,0.1); padding:8px 14px;
  display:flex; gap:16px; align-items:center; flex-wrap:wrap;
}
.fh-item { display:flex; align-items:center; gap:6px; }
.fh-dot { width:6px; height:6px; border-radius:0; transform:rotate(45deg); }
.fh-text { font-size:8px; letter-spacing:1px; color:var(--text-dim); }

/* ── RIGHT PANEL — Preview ── */
#preview-panel {
  width:270px; min-width:270px;
  border-left:1px solid var(--border);
  background:var(--panel);
  display:flex; flex-direction:column;
}
#preview-header { padding:10px 14px; border-bottom:1px solid var(--border); }
#preview-title { font-size:8px; letter-spacing:2px; color:var(--cyan); }

#big-viewer-wrap {
  height:220px; position:relative; overflow:hidden;
  background:radial-gradient(ellipse at 50% 100%, rgba(0,40,100,0.5) 0%, transparent 70%);
}
#big-canvas { width:100%; height:100%; display:block; }
#preview-empty {
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:8px;
}
#preview-empty-icon { font-size:32px; color:rgba(0,229,255,0.15); }
#preview-empty-text { font-size:8px; letter-spacing:2px; color:var(--text-dim); text-align:center; }

#preview-info { padding:12px 14px; flex:1; overflow-y:auto; }
#preview-info::-webkit-scrollbar { width:2px; }
#preview-info::-webkit-scrollbar-thumb { background:rgba(0,229,255,0.2); }

#pv-name { font-family:'Orbitron',monospace; font-size:14px; font-weight:900; letter-spacing:3px; color:#fff; margin-bottom:2px; }
#pv-rar { display:inline-block; font-size:7px; letter-spacing:1px; padding:2px 6px; border:1px solid; margin-bottom:10px; }
#pv-stats { display:grid; grid-template-columns:1fr 1fr; gap:5px; margin-bottom:10px; }
.pv-stat { background:rgba(0,229,255,0.04); border:1px solid rgba(0,229,255,0.1); padding:5px 8px; }
.pv-stat-label { font-size:7px; letter-spacing:1.5px; color:var(--text-dim); }
.pv-stat-bar { height:2px; background:rgba(255,255,255,0.06); margin:3px 0 2px; position:relative; overflow:hidden; }
.pv-stat-fill { height:100%; background:var(--cyan); position:absolute; left:0; top:0; transition:width .4s ease; }
.pv-stat-val { font-size:10px; font-family:'Orbitron',monospace; font-weight:700; color:var(--text); }

#pv-divider { height:1px; background:var(--border); margin:8px 0; }
#pv-abilities-title { font-size:7px; letter-spacing:2px; color:var(--text-dim); margin-bottom:6px; }
.pv-ability {
  border:1px solid rgba(0,229,255,0.12); background:rgba(0,8,25,0.7);
  padding:6px 8px; margin-bottom:4px;
}
.pv-abl-type { font-size:6px; letter-spacing:1.5px; color:var(--text-dim); margin-bottom:2px; }
.pv-abl-name { font-size:9px; letter-spacing:1px; color:var(--text); margin-bottom:2px; }
.pv-abl-desc { font-size:7px; color:var(--text-dim); line-height:1.5; }

/* ── BOTTOM BAR ── */
#bottom-bar {
  position:relative; z-index:10;
  border-top:1px solid var(--border);
  background:rgba(0,0,0,0.85);
  padding:10px 24px;
  display:flex; align-items:center; justify-content:space-between; gap:16px;
}
.mode-section { display:flex; flex-direction:column; gap:6px; }
.mode-label { font-size:7px; letter-spacing:2px; color:var(--text-dim); }
.mode-btns { display:flex; gap:6px; }
.mode-btn {
  font-family:'Share Tech Mono',monospace; font-size:9px; letter-spacing:2px;
  padding:6px 16px; border:1px solid rgba(0,229,255,0.25);
  background:rgba(0,8,25,0.9); color:var(--text-dim);
  cursor:pointer; transition:all .2s;
  clip-path:polygon(5px 0%,100% 0%,100% calc(100% - 5px),calc(100% - 5px) 100%,0% 100%,0% 5px);
}
.mode-btn:hover { border-color:rgba(0,229,255,0.55); color:var(--text); }
.mode-btn.active { border-color:var(--cyan); color:var(--cyan); background:rgba(0,30,65,0.9); }
.mode-btn.locked { opacity:0.35; pointer-events:none; }
.mode-btn.pve { }
.mode-btn.pvp { border-color:rgba(255,100,60,0.25); }
.mode-btn.pvp:hover { border-color:rgba(255,100,60,0.6); color:#ff8866; }
.mode-btn.pvp.active { border-color:var(--red); color:var(--red); background:rgba(40,0,0,0.9); }
.mode-btn.ranked { border-color:rgba(255,214,0,0.2); }
.mode-btn.ranked:hover { border-color:rgba(255,214,0,0.6); color:var(--gold); }
.mode-btn.ranked.active { border-color:var(--gold); color:var(--gold); background:rgba(30,20,0,0.9); }

.link-btn {
  font-family:'Share Tech Mono',monospace; font-size:9px; letter-spacing:2px;
  padding:6px 14px; border:1px solid rgba(255,255,255,0.1);
  background:rgba(10,10,25,0.9); color:rgba(200,210,230,0.3);
  clip-path:polygon(5px 0%,100% 0%,100% calc(100% - 5px),calc(100% - 5px) 100%,0% 100%,0% 5px);
  pointer-events:none; transition:all .3s;
}
.link-btn.enabled {
  border-color:rgba(120,80,255,0.5); color:rgba(160,120,255,0.8);
  pointer-events:auto; cursor:pointer;
}
.link-btn.enabled:hover { border-color:#c158ff; color:#c158ff; background:rgba(30,0,60,0.9); }

/* Separator */
.bar-sep { width:1px; height:40px; background:var(--border); }

/* ENGAGE button */
#engage-btn {
  font-family:'Orbitron',monospace; font-size:12px; font-weight:900; letter-spacing:4px;
  padding:12px 36px; border:1px solid rgba(0,229,255,0.4);
  background:rgba(0,20,55,0.95); color:rgba(0,229,255,0.4);
  clip-path:polygon(8px 0%,100% 0%,100% calc(100% - 8px),calc(100% - 8px) 100%,0% 100%,0% 8px);
  cursor:not-allowed; transition:all .3s; position:relative; overflow:hidden;
}
#engage-btn.ready {
  border-color:var(--cyan); color:var(--cyan);
  cursor:pointer; box-shadow:0 0 20px rgba(0,229,255,0.15);
}
#engage-btn.ready::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(90deg, transparent, rgba(0,229,255,0.08), transparent);
  animation:shimmer 2s ease infinite;
}
@keyframes shimmer { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
#engage-btn.ready:hover { background:rgba(0,40,100,0.95); box-shadow:0 0 30px rgba(0,229,255,0.25); }
#engage-btn:active { transform:scale(0.98); }
#squad-complete-hint { font-size:7px; letter-spacing:1.5px; color:var(--text-dim); text-align:center; margin-top:3px; }

/* ── ANIMATIONS ── */
@keyframes fadeSlideIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@keyframes glowPulse { 0%,100%{box-shadow:0 0 8px rgba(0,229,255,0.1)} 50%{box-shadow:0 0 18px rgba(0,229,255,0.3)} }
@keyframes rotateSlow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

/* notification toast */
#toast {
  position:fixed; bottom:80px; left:50%; transform:translateX(-50%);
  background:rgba(0,5,20,0.95); border:1px solid var(--border);
  padding:8px 20px; font-size:9px; letter-spacing:2px;
  color:var(--cyan); z-index:200; opacity:0;
  clip-path:polygon(6px 0%,100% 0%,100% calc(100% - 6px),calc(100% - 6px) 100%,0% 100%,0% 6px);
  transition:opacity .3s; pointer-events:none;
}
#toast.show { opacity:1; }

/* Portrait: full-screen hint (same idea as Mind Wars lobby) */
.mw-portrait-gate{
  display:none; position:fixed; inset:0; z-index:30000;
  flex-direction:column; align-items:center; justify-content:center;
  padding:max(24px, env(safe-area-inset-top,0)) max(20px, env(safe-area-inset-right,0)) max(24px, env(safe-area-inset-bottom,0)) max(20px, env(safe-area-inset-left,0));
  background:radial-gradient(ellipse 80% 60% at 50% 20%, rgba(0,229,255,.12) 0%, transparent 55%), radial-gradient(ellipse 70% 50% at 80% 80%, rgba(193,88,255,.08) 0%, transparent 50%), rgba(1,4,12,.97);
  backdrop-filter:blur(12px); text-align:center; box-sizing:border-box;
}
.mw-portrait-gate-inner{ max-width:340px; }
.mw-portrait-gate-phone{ display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:18px; font-size:40px; line-height:1; filter:drop-shadow(0 0 18px rgba(0,229,255,.35)); }
.mw-portrait-gate-arrow{ display:inline-block; animation:squadPortraitSpin 2.2s ease-in-out infinite; font-size:34px; color:var(--cyan); }
@keyframes squadPortraitSpin{ 0%,100%{ transform:rotate(0); } 35%,65%{ transform:rotate(-90deg); } }
.mw-portrait-gate-title{ font-family:'Orbitron',monospace; font-size:14px; font-weight:800; letter-spacing:3px; color:#e8f4ff; margin-bottom:8px; text-transform:uppercase; }
.mw-portrait-gate-sub{ font-size:12px; letter-spacing:1px; color:var(--text-dim); line-height:1.5; }
.mw-portrait-gate-skip{ margin-top:18px; padding:9px 16px; font-family:'Share Tech Mono',monospace; font-size:10px; letter-spacing:1px; color:var(--text-dim); background:rgba(0,20,40,.8); border:1px solid var(--border); cursor:pointer; clip-path:polygon(4px 0,100% 0,100% calc(100% - 4px),calc(100% - 4px) 100%,0 100%,0 4px); }
.mw-portrait-gate-skip:hover{ color:var(--cyan); border-color:var(--border-hot); }
html.mw-portrait-dismissed .mw-portrait-gate{ display:none !important; }
@media (orientation:portrait) and (max-width:926px){ .mw-portrait-gate{ display:flex; } }

/* Mobile / narrow: stack columns, scroll main */
@media (max-width:900px){
  #app{ min-height:100vh; min-height:100dvh; height:auto; max-height:none; }
  #header{ flex-wrap:wrap; padding:10px 14px; gap:8px; }
  #header-right{ display:none; }
  #title-block{ order:3; width:100%; text-align:center; }
  #page-title{ font-size:12px; letter-spacing:3px; }
  #steps{ padding:8px 6px; flex-wrap:wrap; justify-content:center; }
  .step-label{ font-size:7px; margin:0 6px; }
  .step-connector{ width:24px; }
  #main{
    flex:1 1 auto;
    flex-direction:column;
    overflow-x:hidden;
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    min-height:0;
  }
  #center-panel{ order:-1; flex:0 0 auto; padding:6px 10px 8px; overflow:visible; }
  #squad-slots{ padding:4px 6px 8px; }
  #roster-panel{
    width:100%; min-width:0; max-height:min(38vh,320px);
    border-right:none; border-bottom:1px solid var(--border);
    flex-shrink:0;
  }
  #preview-panel{
    width:100%; min-width:0; max-height:min(36vh,280px);
    border-left:none; border-top:1px solid var(--border);
    flex-direction:column; min-height:0;
  }
  #big-viewer-wrap{ height:min(28vh,160px); flex-shrink:0; }
  #preview-info{ flex:1; min-height:80px; overflow-y:auto; }
  #formation-hint{ flex-wrap:wrap; gap:8px; padding:6px 10px; }
  #bottom-bar{
    flex-wrap:wrap; justify-content:center; gap:10px;
    padding:max(10px, env(safe-area-inset-bottom,0)) 12px 12px;
  }
  .bar-sep{ display:none; }
  #engage-btn{ padding:10px 22px; font-size:10px; letter-spacing:2px; }
}
</style>
</head>
<body>
<div id="mw-portrait-gate" class="mw-portrait-gate" role="dialog" aria-modal="true" aria-labelledby="squad-portrait-title">
  <div class="mw-portrait-gate-inner">
    <div class="mw-portrait-gate-phone" aria-hidden="true"><span>📱</span><span class="mw-portrait-gate-arrow">↻</span></div>
    <p id="squad-portrait-title" class="mw-portrait-gate-title">Rotate for squad select</p>
    <p class="mw-portrait-gate-sub">Formation and roster are easier in landscape. Turn your device, or continue below with scrolling.</p>
    <button type="button" class="mw-portrait-gate-skip" onclick="document.documentElement.classList.add('mw-portrait-dismissed')">Continue in portrait</button>
  </div>
</div>
<div id="app" class="mw-squad-select">
  <div id="bg-layer"></div>
  <div id="bg-grid"></div>
  <div id="bg-lines"></div>

  <!-- HEADER -->
  <div id="header">
    <div id="header-left">
      <a class="header-back-btn" href="/games/mind-wars/lobby.php" title="Mind Wars lobby">← BACK TO LOBBY</a>
      <div id="logo">◈ KND GAMES</div>
    </div>
    <div id="title-block">
      <div id="page-title">SQUAD SELECT</div>
      <div id="page-sub">MIND WARS // 3V3 FORMATION</div>
    </div>
    <div id="header-right">
      <div>SEASON <span>01</span></div>
      <div>RANK: <span>GOLD IV</span></div>
      <div>KND: <span>44,752</span></div>
    </div>
  </div>

  <!-- STEPS -->
  <div id="steps">
    <div class="step-item">
      <div class="step-dot active" id="step1">1</div>
      <div class="step-label active">SELECT SQUAD</div>
    </div>
    <div class="step-connector" id="conn1"></div>
    <div class="step-item">
      <div class="step-dot" id="step2">2</div>
      <div class="step-label" id="sl2">CHOOSE MODE</div>
    </div>
    <div class="step-connector" id="conn2"></div>
    <div class="step-item">
      <div class="step-dot" id="step3">3</div>
      <div class="step-label" id="sl3">ENGAGE</div>
    </div>
  </div>

  <!-- MAIN -->
  <div id="main">

    <!-- ROSTER -->
    <div id="roster-panel">
      <div id="roster-header">
        <div id="roster-title">YOUR ROSTER</div>
        <div id="roster-count">12 LINKED</div>
      </div>
      <input id="roster-search" type="text" placeholder="SEARCH UNIT..." oninput="filterRoster(this.value)">
      <div id="filter-row">
        <button class="filter-btn active" onclick="setFilter(this,'all')">ALL</button>
        <button class="filter-btn" onclick="setFilter(this,'legendary')">LEG</button>
        <button class="filter-btn" onclick="setFilter(this,'epic')">EPIC</button>
        <button class="filter-btn" onclick="setFilter(this,'rare')">RARE</button>
        <button class="filter-btn" onclick="setFilter(this,'Tank')">TANK</button>
        <button class="filter-btn" onclick="setFilter(this,'Striker')">STR</button>
      </div>
      <div id="roster-list"></div>
    </div>

    <!-- CENTER -->
    <div id="center-panel">

      <!-- Squad slots: KND avatar cards (see mw-avatar-cards.css .mw-squad-select) -->
      <div id="squad-slots">
        <div class="squad-slot-column" id="slot-column-0" data-slot-index="0">
          <div class="squad-slot-pos pos-front">FRONT</div>
          <div class="squad-slot-card-wrap">
            <div id="slot-card-wrap-0" class="slot-card-root"></div>
          </div>
        </div>
        <div class="squad-slot-column" id="slot-column-1" data-slot-index="1">
          <div class="squad-slot-pos pos-mid">MID</div>
          <div class="squad-slot-card-wrap">
            <div id="slot-card-wrap-1" class="slot-card-root"></div>
          </div>
        </div>
        <div class="squad-slot-column" id="slot-column-2" data-slot-index="2">
          <div class="squad-slot-pos pos-back">BACK</div>
          <div class="squad-slot-card-wrap">
            <div id="slot-card-wrap-2" class="slot-card-root"></div>
          </div>
        </div>
      </div>

      <!-- Formation hint -->
      <div id="formation-hint">
        <div class="fh-item">
          <div class="fh-dot" style="background:#ff6644"></div>
          <div class="fh-text">FRONT: HIGH RISK / HIGH REWARD</div>
        </div>
        <div class="fh-item">
          <div class="fh-dot" style="background:#ffcc00"></div>
          <div class="fh-text">MID: BALANCED</div>
        </div>
        <div class="fh-item">
          <div class="fh-dot" style="background:#44ccff"></div>
          <div class="fh-text">BACK: PROTECTED</div>
        </div>
        <div style="margin-left:auto;font-size:7px;letter-spacing:1px;color:var(--text-dim)">
          BACK UNITS STILL STAND WHEN FRONT FALLS
        </div>
      </div>
    </div>

    <!-- PREVIEW PANEL -->
    <div id="preview-panel">
      <div id="preview-header"><div id="preview-title">HOLOGRAPHIC INSPECTION</div></div>
      <div id="big-viewer-wrap">
        <canvas id="big-canvas"></canvas>
        <div id="preview-empty">
          <div id="preview-empty-icon">⬡</div>
          <div id="preview-empty-text">SELECT A UNIT<br>TO INSPECT</div>
        </div>
      </div>
      <div id="preview-info">
        <div id="pv-name" style="color:rgba(150,180,210,0.3);font-size:10px">— NO UNIT SELECTED —</div>
        <div id="pv-rar" class="pv-rar rar-common" style="display:none"></div>
      </div>
    </div>

  </div><!-- /main -->

  <!-- BOTTOM BAR -->
  <div id="bottom-bar">
    <div class="mode-section">
      <div class="mode-label">BATTLE MODE</div>
      <div class="mode-btns">
        <button class="mode-btn pve active" onclick="setMode(this,'pve')">PVE</button>
        <button class="mode-btn pvp" onclick="setMode(this,'pvp')">PVP</button>
        <button class="mode-btn ranked" onclick="setMode(this,'ranked')">RANKED</button>
      </div>
    </div>

    <div class="bar-sep"></div>

    <div class="mode-section">
      <div class="mode-label">DIRECT LINK</div>
      <div class="mode-btns">
        <button class="link-btn" id="link-btn">⬡ NEURAL LINK</button>
      </div>
    </div>

    <div class="bar-sep"></div>

    <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
      <button id="engage-btn" onclick="engageSquad()">ENGAGE SQUAD LINK</button>
      <div id="squad-complete-hint">SELECT 3 UNITS TO ENGAGE</div>
    </div>
  </div>

  <div id="toast"></div>
</div><!-- /app -->

<script src="/games/mind-wars/mw-avatar-card.js?v=<?php echo (int) $mwCardJsV; ?>"></script>
<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.157.0/build/three.module.js",
    "three/addons/": "https://unpkg.com/three@0.157.0/examples/jsm/"
  }
}
</script>

<script type="module">
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

// ═══ DATA (loaded from API) ══════════════════════
let AVATARS = [];

const RARITY_EMISSIVE = { legendary:0xffd600, epic:0xcc44ff, rare:0x4488ff, common:0x44aaaa, special:0x00ffcc };
const RARITY_HEX      = { legendary:'#ffd600', epic:'#c158ff', rare:'#4488ff', common:'#44aaaa', special:'#00ffcc' };
/** Lower = higher tier. Orden roster: rareza → nivel (desc) → nombre */
const RARITY_SORT_RANK = { legendary: 0, epic: 1, rare: 2, special: 3, common: 4 };

function rosterRarityRank(r) {
  const k = String(r || 'common').toLowerCase();
  return RARITY_SORT_RANK[k] !== undefined ? RARITY_SORT_RANK[k] : 50;
}

function compareRosterAvatars(a, b) {
  const rr = rosterRarityRank(a.rarity) - rosterRarityRank(b.rarity);
  if (rr !== 0) return rr;
  const la = Math.max(1, Number(a.avatar_level) || 1);
  const lb = Math.max(1, Number(b.avatar_level) || 1);
  if (la !== lb) return lb - la;
  return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base', numeric: true });
}

// ═══ STATE ═════════════════════════════════════
let squad = [null, null, null]; // slot 0,1,2
let selectedCard = null;
let activeSlot = null;
let currentFilter = 'all';
let previewUnit = null;
let selectedMode = 'pve';

// Three.js — big preview only (squad slots use KND avatar-card + portrait)
let bigScene, bigCamera, bigRenderer, bigModel, bigClock;
let bigAnimFrame = null;

const loader = new GLTFLoader();
const modelCache = {};

function mwApiUrl(path) {
  const rel = path.replace(/^\//, '');
  return new URL('../' + rel, window.location.href).href;
}

async function loadUserAvatars() {
  const res = await fetch(mwApiUrl('api/mindwars/get_user_avatars.php'), { credentials: 'same-origin' });
  if (res.status === 401) {
    window.location.href = '/auth.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
    return;
  }
  if (!res.ok) {
    showToast('FAILED TO LOAD ROSTER');
    AVATARS = [];
    buildRoster();
    const rc = document.getElementById('roster-count');
    if (rc) rc.textContent = '0 LINKED';
    return;
  }
  const data = await res.json();
  if (Array.isArray(data)) {
    AVATARS = data;
  } else if (data && Array.isArray(data.data)) {
    AVATARS = data.data;
  } else {
    AVATARS = [];
  }
  buildRoster();
  const rc = document.getElementById('roster-count');
  if (rc) rc.textContent = `${AVATARS.length} LINKED`;
}

// ═══ INIT ═══════════════════════════════════════
async function init() {
  buildBgLines();
  await loadUserAvatars();
  try {
    const p = new URLSearchParams(window.location.search);
    const er = p.get('err');
    if (er) {
      const msg = ({ no_engagement: 'OPEN SELECTOR THEN ENGAGE', session_mismatch: 'SESSION EXPIRED — ENGAGE AGAIN', no_battle_token: 'BATTLE TOKEN LOST — ENGAGE AGAIN', encode: 'DATA ERROR', NOT_OWNED: 'INVALID SQUAD', DUPLICATE_AVATAR: 'DUPLICATE UNIT', INVALID_SQUAD_SIZE: 'NEED 3 UNITS', AVATAR_NOT_FOUND: 'AVATAR MISSING', BUILD_FAILED: 'LOAD FAILED' })[er] || er.replace(/_/g, ' ');
      showToast(msg);
    }
  } catch (e) {}
  initBigViewer();
  renderAllSquadSlots();
  initSquadSlotDnD();
  initRosterPointerDnD();
  // Expose globals for HTML onclick handlers
  window.handleSlotClick = handleSlotClick;
  window.removeFromSlot = removeFromSlot;
  window.setMode = setMode;
  window.filterRoster = filterRoster;
  window.setFilter = setFilter;
}

function buildBgLines() {
  const container = document.getElementById('bg-lines');
  for (let i = 0; i < 8; i++) {
    const line = document.createElement('div');
    line.className = 'bg-line';
    line.style.cssText = `left:${10 + i*12}%;top:0;height:${40+Math.random()*60}%;animation-delay:${i*0.5}s;animation-duration:${3+Math.random()*3}s;`;
    container.appendChild(line);
  }
}

// ═══ ROSTER ═════════════════════════════════════
function buildRoster(filter = 'all', search = '') {
  const list = document.getElementById('roster-list');
  list.innerHTML = '';
  const filtered = AVATARS.filter(a => {
    const rar = (a.rarity || '').toLowerCase();
    const matchFilter = filter === 'all' || rar === filter || a.class === filter;
    const matchSearch = !search || a.name.toLowerCase().includes(search.toLowerCase());
    return matchFilter && matchSearch;
  }).sort(compareRosterAvatars);
  filtered.forEach((av, i) => {
    const rar = (av.rarity || 'common').toLowerCase();
    const inSquad = squad.some(s => s?.id === av.id);
    const d = document.createElement('div');
    d.className = 'roster-entry' + (inSquad ? ' in-squad' : '') + (selectedCard?.id === av.id ? ' selected' : '');
    d.id = 'acard-' + av.id;
    d.style.cssText = `--rarity-color:${RARITY_HEX[rar] || RARITY_HEX.common};animation:fadeSlideIn .3s ease both;animation-delay:${i*0.04}s`;
    d.dataset.avatarId = String(av.id);
    d.onclick = () => selectCard(av);
    if (!inSquad) {
      d.setAttribute('draggable', 'true');
      d.addEventListener('dragstart', function (e) {
        const payload = JSON.stringify({ avatarId: av.id });
        try {
          e.dataTransfer.setData('application/json', payload);
        } catch (err) { /* ignore */ }
        e.dataTransfer.setData('text/plain', payload);
        e.dataTransfer.effectAllowed = 'copyMove';
        d.classList.add('dragging-source');
      });
      d.addEventListener('dragend', function () {
        d.classList.remove('dragging-source');
        document.querySelectorAll('.squad-slot-column.slot-drag-over').forEach(function (c) {
          c.classList.remove('slot-drag-over');
        });
      });
    } else {
      d.setAttribute('draggable', 'false');
    }
    const thumbHtml = av.image ? `<img src="${av.image}" alt="" draggable="false">` : '◆';
    d.innerHTML = `
      <div class="re-thumb">${thumbHtml}</div>
      <div class="re-info">
        <div class="re-name">${av.name}</div>
        <div class="re-meta">
          <span class="re-rar rar-${rar}">${rar.toUpperCase()}</span>
          <span class="re-class">${av.class}</span>
        </div>
        <div class="re-stats">
          <span class="re-cstat">MND <span>${av.mind}</span></span>
          <span class="re-cstat">SPD <span>${av.speed}</span></span>
          <span class="re-cstat">LCK <span>${av.luck}</span></span>
        </div>
      </div>
    `;
    list.appendChild(d);
  });
}

window.filterRoster = function(val) { buildRoster(currentFilter, val); };
window.setFilter = function(btn, f) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentFilter = f;
  buildRoster(f, document.getElementById('roster-search').value);
};

// ═══ CARD SELECTION ══════════════════════════════
function selectCard(av) {
  selectedCard = av;
  document.querySelectorAll('#roster-list .roster-entry').forEach(c => c.classList.remove('selected'));
  const el = document.getElementById('acard-' + av.id);
  if (el) el.classList.add('selected');
  showBigPreview(av);
  // If a slot is waiting for assignment, assign directly
  if (activeSlot !== null) {
    assignToSlot(activeSlot, av);
    activeSlot = null;
  }
  showToast(`${av.name} SELECTED — TAP SLOT, DRAG, OR LONG-PRESS & DRAG ON TOUCH`);
}

// ═══ SLOT LOGIC ══════════════════════════════════
function handleSlotClick(slotIdx) {
  if (squad[slotIdx]) {
    document.querySelectorAll('.squad-slot-column').forEach(c => c.classList.remove('active'));
    showBigPreview(squad[slotIdx]);
    return;
  }
  if (selectedCard && !squad.some(s => s?.id === selectedCard.id)) {
    assignToSlot(slotIdx, selectedCard);
  } else if (selectedCard && squad.some(s => s?.id === selectedCard.id)) {
    showToast('UNIT ALREADY IN SQUAD');
  } else {
    activeSlot = slotIdx;
    document.querySelectorAll('.squad-slot-column').forEach((col, i) => col.classList.toggle('active', i === slotIdx));
    showToast('SELECT A UNIT FROM THE ROSTER');
  }
}

function apiAvatarToCardPayload(av) {
  return {
    id: av.id,
    name: av.name,
    rarity: av.rarity,
    class: av.class,
    image: av.image,
    avatar_level: Math.max(1, Number(av.avatar_level) || 1),
    stats: {
      mnd: av.mind,
      fcs: av.focus,
      spd: av.speed,
      lck: av.luck
    }
  };
}

const SLOT_POS = ['FRONT', 'MID', 'BACK'];

function renderSquadSlot(slotIdx) {
  const root = document.getElementById('slot-card-wrap-' + slotIdx);
  if (!root || typeof window.createMwAvatarCard !== 'function') return;
  root.innerHTML = '';
  const av = squad[slotIdx];
  const posLabel = SLOT_POS[slotIdx] + ' — FORMATION';
  let card;
  if (av) {
    card = window.createMwAvatarCard(apiAvatarToCardPayload(av), {
      buttonLabel: 'REMOVE',
      tagline: posLabel
    });
  } else {
    card = window.createMwAvatarCard(null, {
      placeholder: true,
      slotIndex: slotIdx,
      buttonLabel: 'SELECT'
    });
  }
  const inner = document.createElement('div');
  inner.className = 'squad-slot-card-inner';
  inner.appendChild(card);
  inner.addEventListener('click', (e) => {
    if (e.target.closest('.inspect-btn')) {
      e.preventDefault();
      e.stopPropagation();
      if (squad[slotIdx]) {
        window.removeFromSlot(e, slotIdx);
      }
      return;
    }
    handleSlotClick(slotIdx);
  });
  root.appendChild(inner);
}

function renderAllSquadSlots() {
  for (let i = 0; i < 3; i++) renderSquadSlot(i);
}

function assignToSlot(slotIdx, av) {
  // Clear this avatar from any other slot (move / avoids duplicate IDs)
  for (let i = 0; i < squad.length; i++) {
    if (squad[i] && squad[i].id === av.id) {
      squad[i] = null;
    }
  }
  // Drop on a filled slot replaces occupant (previous unit returns to roster list)
  squad[slotIdx] = av;
  document.querySelectorAll('.squad-slot-column').forEach(s => s.classList.remove('active'));
  renderAllSquadSlots();
  updateEngageButton();
  buildRoster(currentFilter, document.getElementById('roster-search').value);
  showToast(`${av.name} → ${SLOT_POS[slotIdx]} POSITION`);
}

const ROSTER_DRAG_SLOP_PX = 14;
let rosterPointerState = null;
let rosterSuppressClickUntil = 0;

function clearSlotDragHighlights() {
  document.querySelectorAll('.squad-slot-column.slot-drag-over').forEach(function (c) {
    c.classList.remove('slot-drag-over');
  });
}

function rosterDocPointerMove(e) {
  if (!rosterPointerState || e.pointerId !== rosterPointerState.pointerId) return;
  const st = rosterPointerState;
  const dx = e.clientX - st.startX;
  const dy = e.clientY - st.startY;
  if (!st.activated) {
    if (dx * dx + dy * dy < ROSTER_DRAG_SLOP_PX * ROSTER_DRAG_SLOP_PX) return;
    st.activated = true;
    st.row.classList.add('dragging-source');
  }
  e.preventDefault();
  clearSlotDragHighlights();
  const under = document.elementFromPoint(e.clientX, e.clientY);
  const col = under && under.closest('.squad-slot-column');
  if (col) col.classList.add('slot-drag-over');
}

function rosterDocPointerEnd(e) {
  if (!rosterPointerState || e.pointerId !== rosterPointerState.pointerId) return;
  const st = rosterPointerState;
  rosterPointerState = null;
  document.removeEventListener('pointermove', rosterDocPointerMove);
  document.removeEventListener('pointerup', rosterDocPointerEnd);
  document.removeEventListener('pointercancel', rosterDocPointerEnd);

  st.row.classList.remove('dragging-source');
  clearSlotDragHighlights();

  if (!st.activated) return;

  const under = document.elementFromPoint(e.clientX, e.clientY);
  const col = under && under.closest('.squad-slot-column');
  if (!col) return;
  const slotIdx = parseInt(col.getAttribute('data-slot-index'), 10);
  if (Number.isNaN(slotIdx)) return;
  const av = AVATARS.find(function (a) { return String(a.id) === String(st.avatarId); });
  if (!av) return;
  assignToSlot(slotIdx, av);
  showBigPreview(av);
  rosterSuppressClickUntil = Date.now() + 450;
}

function rosterListPointerDown(e) {
  if (e.pointerType === 'mouse' && e.button !== 0) return;
  const row = e.target.closest('.roster-entry');
  if (!row || row.classList.contains('in-squad')) return;
  const aid = row.dataset.avatarId;
  if (!aid) return;
  rosterPointerState = {
    pointerId: e.pointerId,
    avatarId: aid,
    row: row,
    startX: e.clientX,
    startY: e.clientY,
    activated: false
  };
  document.addEventListener('pointermove', rosterDocPointerMove, { passive: false });
  document.addEventListener('pointerup', rosterDocPointerEnd);
  document.addEventListener('pointercancel', rosterDocPointerEnd);
}

function initRosterPointerDnD() {
  const list = document.getElementById('roster-list');
  if (!list || list.dataset.kndRosterPointer === '1') return;
  list.dataset.kndRosterPointer = '1';
  list.addEventListener('pointerdown', rosterListPointerDown, { passive: true });
  document.addEventListener('click', function rosterClickSuppress(ev) {
    if (Date.now() >= rosterSuppressClickUntil) return;
    if (!ev.target.closest('#roster-list .roster-entry')) return;
    ev.preventDefault();
    ev.stopPropagation();
  }, true);
}

function initSquadSlotDnD() {
  document.querySelectorAll('.squad-slot-column').forEach((col) => {
    if (col.dataset.kndDndBound === '1') return;
    col.dataset.kndDndBound = '1';
    const slotIdx = parseInt(col.getAttribute('data-slot-index'), 10);
    if (Number.isNaN(slotIdx)) return;

    col.addEventListener('dragenter', function (e) {
      e.preventDefault();
      col.classList.add('slot-drag-over');
    });
    col.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
      col.classList.add('slot-drag-over');
    });
    col.addEventListener('dragleave', function (e) {
      const rel = e.relatedTarget;
      if (!rel || !col.contains(rel)) {
        col.classList.remove('slot-drag-over');
      }
    });
    col.addEventListener('drop', function (e) {
      e.preventDefault();
      col.classList.remove('slot-drag-over');
      let payload = {};
      try {
        const raw = e.dataTransfer.getData('application/json') || e.dataTransfer.getData('text/plain');
        if (raw) payload = JSON.parse(raw);
      } catch (err) {
        return;
      }
      const aid = payload.avatarId != null ? payload.avatarId : payload.id;
      if (aid == null) return;
      const av = AVATARS.find(a => String(a.id) === String(aid));
      if (!av) return;
      assignToSlot(slotIdx, av);
      showBigPreview(av);
    });
  });
}

window.removeFromSlot = function(e, slotIdx) {
  if (e && e.stopPropagation) e.stopPropagation();
  squad[slotIdx] = null;
  renderSquadSlot(slotIdx);
  updateEngageButton();
  buildRoster(currentFilter, document.getElementById('roster-search').value);
};

function requireEl(id) {
  const el = document.getElementById(id);
  if (!el) {
    console.error('Elemento no encontrado:', id);
  }
  return el;
}

// ═══ BIG PREVIEW ════════════════════════════════
function showBigPreview(av) {
  if (!av) {
    console.error('Elemento no encontrado:', 'showBigPreview(av) — av es null');
    return;
  }
  const previewEmpty = requireEl('preview-empty');
  const previewInfo = requireEl('preview-info');
  if (!previewEmpty || !previewInfo) return;

  const rar = (av.rarity || 'common').toLowerCase();
  previewUnit = av;
  previewEmpty.style.display = 'none';

  const stats = [
    { label:'MIND',  val:av.mind,  color:'#c158ff' },
    { label:'FOCUS', val:av.focus, color:'#00e5ff' },
    { label:'SPEED', val:av.speed, color:'#ffd600' },
    { label:'LUCK',  val:av.luck,  color:'#00ff88' },
  ];

  const abls = [
    av.passive && { type:'PASSIVE', name:av.passive.split(':')[0], desc:av.passive.split(':')[1]?.trim() },
    av.ability && { type:'ABILITY', name:av.ability.split(':')[0], desc:av.ability.split(':')[1]?.trim() },
    av.special && { type:'SPECIAL', name:av.special.split(':')[0], desc:av.special.split(':')[1]?.trim() },
    av.heal    && { type:'HEAL',    name:av.heal.split(':')[0],    desc:av.heal.split(':')[1]?.trim() },
  ].filter(Boolean);

  previewInfo.innerHTML = `
    <div id="pv-name" style="font-family:Orbitron,monospace;font-size:14px;font-weight:900;letter-spacing:3px;color:#fff;margin-bottom:2px">${av.name}</div>
    <div id="pv-rar" class="pv-rar rar-${rar}" style="display:inline-block;font-size:7px;letter-spacing:1px;padding:2px 6px;border:1px solid;margin-bottom:10px">${rar.toUpperCase()} // ${(av.class || '').toUpperCase()}</div>
    <div id="pv-stats" style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:10px">
      ${stats.map(s => `<div class="pv-stat"><div class="pv-stat-label">${s.label}</div><div class="pv-stat-bar"><div class="pv-stat-fill" style="width:${s.val}%;background:${s.color};transition:width .4s ease"></div></div><div class="pv-stat-val" style="color:${s.color}">${s.val}</div></div>`).join('')}
    </div>
    <div id="pv-divider" style="height:1px;background:var(--border);margin:8px 0"></div>
    <div id="pv-abilities-title" style="font-size:7px;letter-spacing:2px;color:var(--text-dim);margin-bottom:6px">COMBAT ABILITIES</div>
    ${abls.map(a => `<div class="pv-ability"><div class="pv-abl-type">${a.type}</div><div class="pv-abl-name">${a.name}</div><div class="pv-abl-desc">${a.desc || ''}</div></div>`).join('')}
  `;

  loadModelBig(av);
}

function initBigViewer() {
  const canvas = requireEl('big-canvas');
  if (!canvas || !canvas.parentElement) return;
  const W = canvas.parentElement.clientWidth;
  const H = canvas.parentElement.clientHeight;
  bigRenderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  bigRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  bigRenderer.setSize(W, H);
  bigRenderer.setClearColor(0x000000, 0);
  bigRenderer.shadowMap.enabled = true;
  bigRenderer.toneMapping = THREE.ACESFilmicToneMapping;
  bigRenderer.toneMappingExposure = 1.3;

  bigScene = new THREE.Scene();
  bigCamera = new THREE.PerspectiveCamera(42, W / H, 0.1, 100);
  bigCamera.position.set(0, 1.5, 3.8);
  bigCamera.lookAt(0, 1.1, 0);

  bigScene.add(new THREE.AmbientLight(0x0a1530, 1.8));
  const key = new THREE.DirectionalLight(0x7799ff, 3.0);
  key.position.set(3, 8, 4); key.castShadow = true; bigScene.add(key);
  const rim = new THREE.DirectionalLight(0xff2244, 0.5);
  rim.position.set(-4, 3, -3); bigScene.add(rim);
  const fill = new THREE.DirectionalLight(0x0044aa, 1.2);
  fill.position.set(0, -2, 5); bigScene.add(fill);
  // Accent from below
  const under = new THREE.PointLight(0x003366, 2, 6);
  under.position.set(0, -1, 1); bigScene.add(under);

  bigClock = new THREE.Clock();

  function loopBig() {
    bigAnimFrame = requestAnimationFrame(loopBig);
    const t = bigClock.getDelta();
    if (bigModel) {
      bigModel.rotation.y += t * 0.4;
      bigModel.position.y = bigModel.userData.baseY + Math.sin(Date.now() * 0.0008) * 0.04;
    }
    bigRenderer.render(bigScene, bigCamera);
  }
  loopBig();
}

// ═══ MODEL LOADING ═══════════════════════════════
function loadModel(path, cb) {
  if (modelCache[path]) { cb(modelCache[path].clone()); return; }
  loader.load(path, gltf => {
    const model = gltf.scene;
    // Normalize
    const box = new THREE.Box3().setFromObject(model);
    const size = new THREE.Vector3(); box.getSize(size);
    const scale = 1.8 / size.y;
    model.scale.setScalar(scale);
    box.setFromObject(model);
    model.position.y = -box.min.y;
    modelCache[path] = model;
    cb(model.clone());
  }, undefined, () => cb(null));
}

function applyModelMaterials(model, av) {
  const rar = (av.rarity || 'common').toLowerCase();
  const emissive = RARITY_EMISSIVE[rar] || 0x224466;
  model.traverse(child => {
    if (child.isMesh) {
      child.castShadow = true;
      if (child.material) {
        child.material = child.material.clone();
        child.material.emissive = new THREE.Color(emissive);
        child.material.emissiveIntensity = 0.06;
        child.material.envMapIntensity = 1.5;
      }
    }
  });
}

function loadModelBig(av) {
  if (bigModel) { bigScene.remove(bigModel); bigModel = null; }
  loadModel(av.model, model => {
    if (!model) return;
    applyModelMaterials(model, av);
    model.material?.emissiveIntensity && (model.material.emissiveIntensity = 0.1);
    const box = new THREE.Box3().setFromObject(model);
    model.position.y = -box.min.y + 0.04;
    model.userData.baseY = model.position.y;
    bigModel = model;
    bigScene.add(model);
  });
}

// ═══ MODE & ENGAGE ═══════════════════════════════
window.setMode = function(btn, mode) {
  document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  selectedMode = mode;
  const linkBtn = document.getElementById('link-btn');
  if (mode === 'pvp' || mode === 'ranked') {
    linkBtn.classList.add('enabled');
  } else {
    linkBtn.classList.remove('enabled');
  }
};

function updateEngageButton() {
  const filled = squad.filter(Boolean).length;
  const btn = document.getElementById('engage-btn');
  const hint = document.getElementById('squad-complete-hint');
  const step2 = document.getElementById('step2');
  const sl2 = document.getElementById('sl2');

  if (filled === 3) {
    btn.classList.add('ready');
    hint.textContent = 'SQUAD COMPLETE — CHOOSE YOUR MODE';
    step2.classList.add('active');
    sl2.classList.add('active');
    document.getElementById('conn1').classList.add('done');
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step1').classList.add('done');
  } else {
    btn.classList.remove('ready');
    hint.textContent = `SELECT ${3 - filled} MORE UNIT${3-filled>1?'S':''} TO ENGAGE`;
  }
}

window.engageSquad = async function() {
  if (squad.filter(Boolean).length !== 3) {
    showToast('SELECT 3 UNITS TO ENGAGE');
    return;
  }
  try {
    localStorage.setItem('mw_squad', JSON.stringify(squad));
  } catch (e) {
    showToast('STORAGE ERROR — CANNOT SAVE SQUAD');
    return;
  }
  const engageUrl = new URL('api/engage_squad.php', window.location.href).href;
  try {
    const res = await fetch(engageUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids: squad.map(s => s.id), mode: selectedMode })
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok || !j.ok) {
      showToast((j.error && String(j.error)) || 'ENGAGE FAILED');
      return;
    }
  } catch (e) {
    showToast('NETWORK ERROR');
    return;
  }
  window.location.href = new URL('battlefield.php', window.location.href).href;
};

// ═══ TOAST ═══════════════════════════════════════
let toastTimer;
window.showToast = function(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2200);
};
function showToast(msg) { window.showToast(msg); }

// ═══ RESIZE ══════════════════════════════════════
window.addEventListener('resize', () => {
  const bc = document.getElementById('big-canvas');
  if (!bc || !bigRenderer || !bigCamera) return;
  bigRenderer.setSize(bc.parentElement.clientWidth, bc.parentElement.clientHeight);
  bigCamera.aspect = bc.parentElement.clientWidth / bc.parentElement.clientHeight;
  bigCamera.updateProjectionMatrix();
});

function startSquadSelectorApp() {
  init();
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startSquadSelectorApp);
} else {
  startSquadSelectorApp();
}
</script>
</body>
</html>

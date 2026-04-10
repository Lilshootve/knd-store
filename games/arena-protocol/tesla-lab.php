<?php
/**
 * TESLA LAB — District Room
 * Isometric 3D lab with patrolling NPC scientists. Click to interact → enter game.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/mw_avatar_models.php';

if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// ── Resolve player hero model ────────────────────────────────────────────────
$_heroModelUrl = null;
$_playerName   = 'AGENT';
try {
    $pdo = getDBConnection();
    $uid = (int)(current_user_id() ?? 0);
    if ($uid > 0) {
        $un = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $un->execute([$uid]);
        $_playerName = mb_strtoupper((string)($un->fetchColumn() ?: 'AGENT'), 'UTF-8');

        $s = $pdo->prepare("SELECT fa.id, fa.name, fa.rarity FROM users u
            JOIN knd_avatar_items kai ON kai.id = u.favorite_avatar_id AND kai.mw_avatar_id IS NOT NULL
            JOIN mw_avatars fa ON fa.id = kai.mw_avatar_id WHERE u.id = ?");
        $s->execute([$uid]);
        $av = $s->fetch(PDO::FETCH_ASSOC);
        if ($av) $_heroModelUrl = mw_resolve_avatar_model_url((int)$av['id'], (string)$av['name'], (string)$av['rarity']);

        if (!$_heroModelUrl) {
            $sf = $pdo->prepare("SELECT fa.id, fa.name, fa.rarity FROM knd_user_avatar_inventory ui
                JOIN knd_avatar_items ai ON ai.id = ui.item_id AND ai.mw_avatar_id IS NOT NULL
                JOIN mw_avatars fa ON fa.id = ai.mw_avatar_id WHERE ui.user_id = ? LIMIT 1");
            $sf->execute([$uid]);
            $avf = $sf->fetch(PDO::FETCH_ASSOC);
            if ($avf) $_heroModelUrl = mw_resolve_avatar_model_url((int)$avf['id'], (string)$avf['name'], (string)$avf['rarity']);
        }
        if (!$_heroModelUrl) {
            $sa = $pdo->query("SELECT id, name, rarity FROM mw_avatars ORDER BY id ASC LIMIT 20");
            foreach ($sa->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $url = mw_resolve_avatar_model_url((int)$row['id'], (string)$row['name'], (string)$row['rarity']);
                if ($url) { $_heroModelUrl = $url; break; }
            }
        }
    }
} catch (Throwable $_) {}

// ── NPC definitions (avatar thumbnails + dialogue + game links) ──────────────
$_npcs = [
    [
        'id'        => 'einstein',
        'name'      => 'ALBERT EINSTEIN',
        'title'     => 'THEORETICAL PHYSICIST · LEGENDARY',
        'color'     => '#ffd700',
        'thumb'     => '/assets/avatars/thumbs/albert-einstein.png',
        'waypoints' => [[4,0,5],[4,0,15],[10,0,15],[10,0,5]],
        'dialogue'  => "Remarkable. You've found me mid-calculation. The universe is not only stranger than we suppose — it is stranger than we can suppose. Tell me, do you carry that same curiosity into battle? I've been waiting for a mind sharp enough to challenge mine. Step into the arena. Let us see if your neurons fire faster than your doubts.",
        'game_url'  => '/games/knowledge-duel.php',
        'game_label'=> 'KNOWLEDGE DUEL',
        'coming_soon' => false,
    ],
    [
        'id'        => 'franklin',
        'name'      => 'BENJAMIN FRANKLIN',
        'title'     => 'POLYMATH · LEGENDARY',
        'color'     => '#00e8ff',
        'thumb'     => '/assets/avatars/thumbs/benjamin-franklin.png',
        'waypoints' => [[16,0,5],[16,0,15],[6,0,15],[6,0,8]],
        'dialogue'  => "An investment in knowledge pays the best interest — I said that, and I meant every word. I've survived lightning, diplomacy, and British taxation. But there's one thing I haven't survived: a worthy opponent in the Knowledge Duel. You look promising. Let's find out if you have what it takes.",
        'game_url'  => '/games/knowledge-duel.php',
        'game_label'=> 'KNOWLEDGE DUEL',
        'coming_soon' => false,
    ],
    [
        'id'        => 'sherlock',
        'name'      => 'SHERLOCK HOLMES',
        'title'     => 'CONSULTING DETECTIVE · LEGENDARY',
        'color'     => '#9b30ff',
        'thumb'     => '/assets/avatars/thumbs/sherlock-holmes.png',
        'waypoints' => [[10,0,10],[14,0,6],[14,0,14],[8,0,14]],
        'dialogue'  => "You've come to the lab. Interesting choice. I've already deduced three things about you — none flattering, all accurate. My particular challenge is still being... calibrated. When it opens, I assure you it will require every last neuron. For now, consider this a reconnaissance mission. I'm watching.",
        'game_url'  => null,
        'game_label'=> 'COMING SOON',
        'coming_soon' => true,
    ],
    [
        'id'        => 'newton',
        'name'      => 'ISAAC NEWTON',
        'title'     => 'NATURAL PHILOSOPHER · EPIC',
        'color'     => '#00ff88',
        'thumb'     => '/assets/avatars/thumbs/isaac-newton.png',
        'waypoints' => [[5,0,10],[5,0,16],[16,0,10],[11,0,7]],
        'dialogue'  => "Every action has an equal and opposite reaction. You walked in — I noticed. The question is: what is your next action? I've spent decades reducing the universe to elegant equations. My dueling arena awaits those who believe knowledge is force. Apply sufficient intellectual mass and you shall move mountains.",
        'game_url'  => '/games/knowledge-duel.php',
        'game_label'=> 'KNOWLEDGE DUEL',
        'coming_soon' => false,
    ],
];
$_npcsJson   = json_encode($_npcs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$_heroJson   = json_encode($_heroModelUrl);
$_playerJson = json_encode($_playerName);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TESLA LAB — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#020508;font-family:"Share Tech Mono",monospace;color:#c8e8f0}
canvas{display:block}
/* scanline CRT overlay */
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.03) 3px,rgba(0,0,0,.03) 4px)}

/* CRT transition */
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%);transition:none}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50% 50% 50% 50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#ddf}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0% 0 0% 0);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);opacity:1;background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff;opacity:1}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}

/* TOP BAR */
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(2,5,16,.97);border-bottom:1px solid rgba(0,232,255,.07);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#00e8ff 35%,#ffd700 50%,#00e8ff 65%,transparent 98%);opacity:.2}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(0,232,255,.15);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(0,232,255,.6);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(0,232,255,.4);color:#00e8ff;background:rgba(0,232,255,.06)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(0,232,255,.35);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(255,215,0,.08);border:1px solid rgba(255,215,0,.2);color:#ffd700}

/* CANVAS */
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#020508}
#cv canvas{width:100%!important;height:100%!important}

/* NPC DIALOGUE MODAL */
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.72);backdrop-filter:blur(10px);display:none;align-items:flex-end;justify-content:center;padding:0 0 32px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(4,12,28,.98),rgba(2,8,20,.99));border:1px solid rgba(0,232,255,.18);border-radius:12px;padding:0;overflow:hidden;box-shadow:0 0 80px rgba(0,232,255,.1),0 0 40px rgba(0,0,0,.6);animation:panelUp .35s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:14px;padding:16px 20px 14px;border-bottom:1px solid rgba(0,232,255,.08);position:relative}
.npc-avatar-frame{width:56px;height:56px;border-radius:8px;border:2px solid rgba(0,232,255,.25);overflow:hidden;flex-shrink:0;background:rgba(0,232,255,.05);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-avatar-frame .npc-placeholder{font-size:24px;opacity:.4}
.npc-info{flex:1;min-width:0}
.npc-name{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:900;letter-spacing:.08em;color:#fff;line-height:1.2}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:3px}
.npc-close{position:absolute;right:14px;top:14px;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,.35);transition:all .2s}
.npc-close:hover{background:rgba(255,61,86,.1);border-color:rgba(255,61,86,.3);color:#ff3d56}
.npc-dialogue{padding:18px 22px;min-height:96px}
.npc-text{font-size:12px;line-height:1.75;color:rgba(200,230,245,.88);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#00e8ff;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 18px;border-top:1px solid rgba(0,232,255,.06)}
.npc-btn-enter{flex:1;padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(0,232,255,.2),rgba(255,215,0,.1));border:1px solid rgba(0,232,255,.45);color:#00e8ff;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 28px rgba(0,232,255,.22),0 0 14px rgba(0,232,255,.1);transform:translateY(-1px);color:#fff}
.npc-btn-enter:disabled,.npc-btn-enter.soon{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);color:rgba(155,215,235,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.16);color:rgba(155,215,235,.7)}

/* HINT */
#hint{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.16em;color:rgba(0,232,255,.3);pointer-events:none;transition:opacity .4s}
#hint.fade{opacity:0}

/* LOADING */
#load{position:fixed;inset:0;z-index:8000;background:#020508;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:28px;font-weight:900;letter-spacing:.3em;color:#fff}.load-logo span{color:#00e8ff}
.load-sub{font-size:8px;letter-spacing:.35em;color:rgba(0,232,255,.35)}
.load-bar{width:220px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
.load-fill{height:100%;background:linear-gradient(90deg,#00e8ff,#ffd700);border-radius:1px;width:0%;transition:width .4s ease}
</style>
</head>
<body>

<div id="crt"></div>

<div id="load">
  <div class="load-logo">TESLA <span>LAB</span></div>
  <div class="load-sub">CALIBRATING NEURAL FIELD</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>

<!-- Top Bar -->
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">TESLA LAB</span>
  <span id="tb-sub">DISTRICT · KNOWLEDGE &amp; SCIENCE</span>
  <span class="tb-badge">LIVE</span>
</header>

<!-- Canvas -->
<div id="cv"></div>

<!-- Hint -->
<div id="hint">CLICK ON A SCIENTIST TO INTERACT</div>

<!-- NPC Dialogue Modal -->
<div id="npc-modal">
  <div class="npc-panel" id="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">◈</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title" style="color:#00e8ff">—</div>
      </div>
      <div class="npc-close" onclick="closeNpcModal()">✕</div>
    </div>
    <div class="npc-dialogue">
      <div class="npc-text" id="npc-text"></div>
    </div>
    <div class="npc-actions">
      <a class="npc-btn-enter" id="npc-enter-btn" href="#">
        <span>⚡</span><span id="npc-enter-lbl">ENTER</span>
      </a>
      <button class="npc-btn-skip" onclick="closeNpcModal()">CLOSE</button>
    </div>
  </div>
</div>

<script type="module">
import * as THREE from 'three';
import { EffectComposer }  from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass }      from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { GLTFLoader }      from 'three/addons/loaders/GLTFLoader.js';

// ─── Config ─────────────────────────────────────────────────────────────────
const HERO_MODEL  = <?php echo $_heroJson; ?>;
const PLAYER_NAME = <?php echo $_playerJson; ?>;
const NPCS        = <?php echo $_npcsJson; ?>;
const GRID        = 20;  // room size
const D_CAM       = 13;  // ortho frustum half-size

// ─── Globals ─────────────────────────────────────────────────────────────────
let scene, camera, renderer, composer, bloomPass;
let clock, mixer, heroMesh;
let npcObjects   = []; // {mesh, npc, waypointIdx, t, moving, speed}
let animObjects  = []; // {obj, type, base, range, speed}
let raycaster    = new THREE.Raycaster();
let pointer      = new THREE.Vector2();
let _hintTimer   = 0;

// NPC dialogue state
let _typingInterval = null;

// ─── Boot ────────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', boot);

async function boot() {
    clock = new THREE.Clock();
    setLoad(10);

    initRenderer();
    setLoad(25);
    initCamera();
    initScene();
    setLoad(45);
    initPostFX();
    setLoad(55);
    buildScene();
    setLoad(70);
    spawnNPCs();
    setLoad(82);
    if (HERO_MODEL) await spawnHero();
    setLoad(100);

    // fade out loader
    setTimeout(() => {
        const l = document.getElementById('load');
        l.classList.add('done');
        setTimeout(() => l.remove(), 600);
        document.getElementById('crt').classList.add('on');
    }, 380);

    window.addEventListener('click', onCanvasClick);
    window.addEventListener('resize', onResize);
    tick();
}

function setLoad(pct) {
    const f = document.getElementById('load-fill');
    if (f) f.style.width = pct + '%';
}

// ─── Renderer ────────────────────────────────────────────────────────────────
function initRenderer() {
    const wrap = document.getElementById('cv');
    renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled  = true;
    renderer.shadowMap.type     = THREE.PCFSoftShadowMap;
    renderer.toneMapping        = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure= 1.45;
    renderer.outputColorSpace   = THREE.SRGBColorSpace;
    wrap.appendChild(renderer.domElement);
    const w = wrap.clientWidth, h = wrap.clientHeight;
    renderer.setSize(w, h);
}

// ─── Camera ──────────────────────────────────────────────────────────────────
function initCamera() {
    const wrap = document.getElementById('cv');
    const w = wrap.clientWidth, h = Math.max(1, wrap.clientHeight);
    const aspect = w / h;
    camera = new THREE.OrthographicCamera(
        -D_CAM * aspect, D_CAM * aspect, D_CAM, -D_CAM, 0.1, 300
    );
    camera.position.set(28, 34, 28);
    camera.lookAt(GRID / 2, 0, GRID / 2);
}

function onResize() {
    const wrap = document.getElementById('cv');
    const w = wrap.clientWidth, h = Math.max(1, wrap.clientHeight);
    const aspect = w / h;
    camera.left   = -D_CAM * aspect;
    camera.right  =  D_CAM * aspect;
    camera.top    =  D_CAM;
    camera.bottom = -D_CAM;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
    if (composer) composer.setSize(w, h);
}

// ─── Scene ───────────────────────────────────────────────────────────────────
function initScene() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x020508);
    scene.fog = new THREE.FogExp2(0x030912, 0.018);
}

function initPostFX() {
    const wrap = document.getElementById('cv');
    const w = wrap.clientWidth, h = wrap.clientHeight;
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    bloomPass = new UnrealBloomPass(new THREE.Vector2(w, h), 0.72, 0.48, 0.82);
    composer.addPass(bloomPass);
}

// ─── Lighting ────────────────────────────────────────────────────────────────
function buildScene() {
    // Lighting
    scene.add(new THREE.HemisphereLight(0x3a5a78, 0x06080e, 0.75));
    scene.add(new THREE.AmbientLight(0x182838, 1.1));

    const sun = new THREE.DirectionalLight(0x9eceff, 1.28);
    sun.position.set(14, 28, 14);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = sun.shadow.camera.bottom = -22;
    sun.shadow.camera.right = sun.shadow.camera.top   =  22;
    sun.shadow.camera.near  = 0.5;
    sun.shadow.camera.far   = 80;
    scene.add(sun);

    const fill = new THREE.DirectionalLight(0x2040a0, 0.7);
    fill.position.set(-14, 8, -14);
    scene.add(fill);

    const rim = new THREE.DirectionalLight(0xffd700, 0.35);
    rim.position.set(20, 12, 2);
    scene.add(rim);

    buildFloor();
    buildWalls();
    buildCeiling();
    buildLabProps();
    buildParticles();
}

function buildFloor() {
    // Main floor
    const mat = new THREE.MeshStandardMaterial({
        color: 0x07182a, roughness: 0.72, metalness: 0.35,
        emissive: 0x010812, emissiveIntensity: 0.12
    });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    // Grid overlay
    const grid = new THREE.GridHelper(GRID, GRID, 0x0b2540, 0x0b2540);
    grid.position.set(GRID/2, 0.005, GRID/2);
    scene.add(grid);

    // Glowing floor lines (tesla coil channels)
    const lineMat = new THREE.MeshStandardMaterial({
        color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 1.1,
        transparent: true, opacity: 0.25
    });
    // Horizontal channels
    [5, 10, 15].forEach(z => {
        const strip = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.025, 0.06), lineMat.clone());
        strip.position.set(GRID/2, 0.01, z);
        scene.add(strip);
        animObjects.push({ obj: strip, type: 'opacity', base: 0.2, range: 0.15, speed: 0.4 + z * 0.03 });
    });
    // Vertical channels
    [5, 10, 15].forEach(x => {
        const strip = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.025, GRID), lineMat.clone());
        strip.position.set(x, 0.01, GRID/2);
        scene.add(strip);
        animObjects.push({ obj: strip, type: 'opacity', base: 0.2, range: 0.15, speed: 0.5 + x * 0.025 });
    });

    // Intersection glow nodes
    [5,10,15].forEach(x => {
        [5,10,15].forEach(z => {
            const node = new THREE.Mesh(
                new THREE.CircleGeometry(0.22, 16),
                new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 1.4, transparent: true, opacity: 0.45 })
            );
            node.rotation.x = -Math.PI / 2;
            node.position.set(x, 0.02, z);
            scene.add(node);
            const ptLight = new THREE.PointLight(0x00e8ff, 0.5, 3);
            ptLight.position.set(x, 0.3, z);
            scene.add(ptLight);
            animObjects.push({ obj: ptLight, type: 'pulse', base: 0.4, range: 0.25, speed: 0.7 + (x + z) * 0.02 });
        });
    });
}

function buildWalls() {
    const wallMat = new THREE.MeshStandardMaterial({
        color: 0x061220, roughness: 0.65, metalness: 0.42,
        emissive: 0x020d1a, emissiveIntensity: 0.4
    });

    // Back wall (z=0)
    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 5), wallMat.clone());
    bw.position.set(GRID/2, 2.5, 0);
    scene.add(bw);

    // Left wall (x=0)
    const lw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 5), wallMat.clone());
    lw.position.set(0, 2.5, GRID/2);
    lw.rotation.y = Math.PI / 2;
    scene.add(lw);

    // Circuit lines on walls
    const cMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.4, transparent: true, opacity: 0.12 });
    const cMatY = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.5, transparent: true, opacity: 0.1 });

    // Horizontal rails — back wall
    for (let i = 0; i < 5; i++) {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(19.6, 0.018, 0.03), i % 2 === 0 ? cMat : cMatY);
        bar.position.set(GRID/2, 0.4 + i * 0.95, 0.02);
        scene.add(bar);
    }
    // Vertical ribs
    [2, 5, 8, 11, 14, 17].forEach(x => {
        const vbar = new THREE.Mesh(new THREE.BoxGeometry(0.018, 4.5, 0.03), cMat);
        vbar.position.set(x, 2.25, 0.02);
        scene.add(vbar);
    });

    // Horizontal rails — left wall
    for (let i = 0; i < 5; i++) {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.018, 19.6), i % 2 === 0 ? cMat : cMatY);
        bar.position.set(0.02, 0.4 + i * 0.95, GRID/2);
        scene.add(bar);
    }

    // Large holographic monitor on back wall
    const monMat = new THREE.MeshStandardMaterial({ color: 0x000516, emissive: 0x001a55, emissiveIntensity: 1.3, roughness: 0, metalness: 0.2 });
    const mon = new THREE.Mesh(new THREE.BoxGeometry(6, 3, 0.08), monMat);
    mon.position.set(GRID/2, 3.2, 0.05);
    scene.add(mon);
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.6, transparent: true, opacity: 0.35 });
    const frame = new THREE.Mesh(new THREE.BoxGeometry(6.1, 3.1, 0.03), frameMat);
    frame.position.set(GRID/2, 3.2, 0.03);
    scene.add(frame);
    // Monitor scanline
    const scanMat = new THREE.MeshStandardMaterial({ color: 0x00aaff, emissive: 0x00aaff, emissiveIntensity: 1.2, transparent: true, opacity: 0.25 });
    const scan = new THREE.Mesh(new THREE.BoxGeometry(5.8, 0.035, 0.02), scanMat);
    scan.position.set(GRID/2, 1.7, 0.07);
    scene.add(scan);
    animObjects.push({ obj: scan, type: 'scan_y', base: 1.72, top: 4.66, speed: 0.32 });
    // Monitor glow light
    const mLight = new THREE.PointLight(0x0022ff, 1.6, 10);
    mLight.position.set(GRID/2, 3.2, 0.8);
    scene.add(mLight);
    animObjects.push({ obj: mLight, type: 'pulse', base: 1.4, range: 0.4, speed: 0.22 });

    // Baseboard glow strips
    const gMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 1.0, transparent: true, opacity: 0.55 });
    const s1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.05, 0.04), gMat);
    s1.position.set(GRID/2, 0.025, 0.02);
    scene.add(s1);
    const s2 = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.05, GRID), gMat.clone());
    s2.position.set(0.02, 0.025, GRID/2);
    scene.add(s2);

    // Corner post
    const postMat = gMat.clone();
    const post = new THREE.Mesh(new THREE.BoxGeometry(0.1, 5, 0.1), postMat);
    post.position.set(0.05, 2.5, 0.05);
    scene.add(post);
    const postLight = new THREE.PointLight(0x00e8ff, 1.0, 5);
    postLight.position.set(0.4, 2.5, 0.4);
    scene.add(postLight);
    animObjects.push({ obj: postLight, type: 'pulse', base: 0.8, range: 0.35, speed: 0.85 });
}

function buildCeiling() {
    const cMat = new THREE.MeshStandardMaterial({
        color: 0x040d1a, roughness: 0.6, metalness: 0.5,
        emissive: 0x020a14, emissiveIntensity: 0.3
    });
    const ceiling = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), cMat);
    ceiling.rotation.x = Math.PI / 2;
    ceiling.position.set(GRID/2, 5, GRID/2);
    scene.add(ceiling);

    // Ceiling lights — lab fluorescent strips
    const stripMat = new THREE.MeshStandardMaterial({ color: 0xfff0cc, emissive: 0xffd700, emissiveIntensity: 1.8, transparent: true, opacity: 0.85 });
    [[5,4.98,5],[10,4.98,10],[15,4.98,5],[5,4.98,15],[15,4.98,15]].forEach(([x,,z]) => {
        const strip = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.06, 0.12), stripMat.clone());
        strip.position.set(x, 4.98, z);
        scene.add(strip);
        const ptL = new THREE.PointLight(0xffd07a, 1.5, 8, 1.5);
        ptL.position.set(x, 4.8, z);
        scene.add(ptL);
        animObjects.push({ obj: ptL, type: 'pulse', base: 1.3, range: 0.28, speed: 0.9 + x * 0.02 });
    });
}

function buildLabProps() {
    // Tesla Coils (4 corners)
    [[2,3],[18,3],[2,17],[18,17]].forEach(([x,z]) => {
        buildTeslaCoil(x, z);
    });

    // Lab workbenches
    buildWorkbench(6, 3, 3, 'horizontal');
    buildWorkbench(14, 3, 17, 'horizontal');
    buildWorkbench(3, 3, 10, 'vertical');

    // Holographic display stands
    [[8, 8],[12, 8],[8, 14],[12, 14]].forEach(([x,z]) => {
        buildHoloStand(x, z);
    });

    // Bookshelves/data banks on left wall
    [[2, 4],[2, 9],[2, 14]].forEach(([,,z], i) => {
        const zp = i === 0 ? 4 : i === 1 ? 9 : 14;
        buildDataBank(1, zp);
    });
}

function buildTeslaCoil(x, z) {
    // Base
    const baseMat = new THREE.MeshStandardMaterial({ color: 0x1a2a3a, metalness: 0.9, roughness: 0.2 });
    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.45, 0.25, 8), baseMat);
    base.position.set(x, 0.12, z);
    base.castShadow = true;
    scene.add(base);

    // Shaft
    const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.18, 1.6, 8), baseMat.clone());
    shaft.position.set(x, 1.05, z);
    shaft.castShadow = true;
    scene.add(shaft);

    // Sphere top
    const sphereMat = new THREE.MeshStandardMaterial({ color: 0xc0d8e8, metalness: 0.95, roughness: 0.05, emissive: 0x00e8ff, emissiveIntensity: 0.3 });
    const sphere = new THREE.Mesh(new THREE.SphereGeometry(0.22, 16, 12), sphereMat);
    sphere.position.set(x, 2.0, z);
    sphere.castShadow = true;
    scene.add(sphere);

    // Glow
    const coilLight = new THREE.PointLight(0x00e8ff, 1.8, 4.5, 2);
    coilLight.position.set(x, 2.0, z);
    scene.add(coilLight);
    animObjects.push({ obj: coilLight, type: 'pulse', base: 1.4, range: 0.8, speed: 1.2 + x * 0.04 });

    // Spiral ring (torus)
    const torusMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 1.2, transparent: true, opacity: 0.5 });
    const torus = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.025, 8, 24), torusMat);
    torus.position.set(x, 2.0, z);
    scene.add(torus);
    animObjects.push({ obj: torus, type: 'rotate_y', speed: 1.5 + z * 0.06 });
}

function buildWorkbench(x, z, depth, orient) {
    const deskMat = new THREE.MeshStandardMaterial({ color: 0x0c1f30, roughness: 0.6, metalness: 0.5 });
    const legMat  = new THREE.MeshStandardMaterial({ color: 0x1a2d3e, metalness: 0.9, roughness: 0.15 });

    const W = orient === 'horizontal' ? 3.5 : 0.7;
    const D2 = orient === 'horizontal' ? 0.7 : 3.5;

    const desk = new THREE.Mesh(new THREE.BoxGeometry(W, 0.08, D2), deskMat);
    desk.position.set(x, 0.88, z);
    desk.castShadow = true; desk.receiveShadow = true;
    scene.add(desk);

    // Legs
    [-1,1].forEach(dx => [-1,1].forEach(dz => {
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.88, 0.06), legMat);
        leg.position.set(x + dx*(W/2-0.1), 0.44, z + dz*(D2/2-0.1));
        scene.add(leg);
    }));

    // Surface items (beakers, monitors)
    const screenMat = new THREE.MeshStandardMaterial({ color: 0x000510, emissive: 0x002a55, emissiveIntensity: 1.1 });
    const sm = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.55, 0.04), screenMat);
    sm.position.set(x, 1.2, z + (orient === 'horizontal' ? -0.15 : 0));
    scene.add(sm);
    const smLight = new THREE.PointLight(0x0044ff, 0.5, 2.5);
    smLight.position.set(x, 1.2, z + 0.3);
    scene.add(smLight);
    animObjects.push({ obj: smLight, type: 'pulse', base: 0.4, range: 0.2, speed: 0.55 });
}

function buildHoloStand(x, z) {
    const pedMat = new THREE.MeshStandardMaterial({ color: 0x0e1a26, metalness: 0.8, roughness: 0.25 });
    const ped = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.25, 0.9, 10), pedMat);
    ped.position.set(x, 0.45, z);
    ped.castShadow = true;
    scene.add(ped);

    // Hologram sphere
    const hMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.9, transparent: true, opacity: 0.35, wireframe: true });
    const holo = new THREE.Mesh(new THREE.IcosahedronGeometry(0.28, 1), hMat);
    holo.position.set(x, 1.3, z);
    scene.add(holo);
    animObjects.push({ obj: holo, type: 'rotate_y', speed: 0.6 + x * 0.04 });
    animObjects.push({ obj: holo, type: 'float', base: 1.3, range: 0.08, speed: 0.8 + z * 0.03 });

    const hLight = new THREE.PointLight(0x00e8ff, 0.6, 3);
    hLight.position.set(x, 1.3, z);
    scene.add(hLight);
    animObjects.push({ obj: hLight, type: 'pulse', base: 0.45, range: 0.25, speed: 0.9 + x * 0.03 });
}

function buildDataBank(x, z) {
    const mat = new THREE.MeshStandardMaterial({ color: 0x091825, roughness: 0.6, metalness: 0.6 });
    const bank = new THREE.Mesh(new THREE.BoxGeometry(0.55, 2.4, 1.4), mat);
    bank.position.set(x + 0.28, 1.2, z);
    bank.castShadow = true;
    scene.add(bank);

    // Data LEDs
    for (let r = 0; r < 6; r++) {
        for (let c = 0; c < 3; c++) {
            const col = (r + c) % 3 === 0 ? 0x00ff88 : (r + c) % 3 === 1 ? 0xffd700 : 0xff3d56;
            const ledMat = new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 1.5 });
            const led = new THREE.Mesh(new THREE.BoxGeometry(0.045, 0.045, 0.02), ledMat);
            led.position.set(x + 0.55, 0.5 + r * 0.32, z - 0.5 + c * 0.35);
            scene.add(led);
            animObjects.push({ obj: led.material, type: 'emissive_flicker', base: 1.5, range: 0.8, speed: 1 + (r + c) * 0.3, mat: led.material });
        }
    }
}

function buildParticles() {
    const count = 220;
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        pos[i*3]   = Math.random() * GRID;
        pos[i*3+1] = Math.random() * 4.5;
        pos[i*3+2] = Math.random() * GRID;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.PointsMaterial({ color: 0x00e8ff, size: 0.055, transparent: true, opacity: 0.45, sizeAttenuation: true });
    const pts = new THREE.Points(geo, mat);
    scene.add(pts);
    animObjects.push({ obj: pts, type: 'particles_drift', speed: 0.12 });
}

// ─── HERO SPAWN ───────────────────────────────────────────────────────────────
function normalizeGltf(root) {
    root.traverse(c => {
        if (c.isMesh) {
            if (c.material && c.material.isMeshPhysicalMaterial) {
                const m = c.material;
                c.material = new THREE.MeshStandardMaterial({
                    map: m.map, normalMap: m.normalMap, roughnessMap: m.roughnessMap,
                    metalnessMap: m.metalnessMap, emissiveMap: m.emissiveMap,
                    color: m.color, roughness: m.roughness ?? 0.7,
                    metalness: m.metalness ?? 0.3,
                    emissive: m.emissive ?? new THREE.Color(0),
                    emissiveIntensity: m.emissiveIntensity ?? 1,
                    transparent: m.transparent, opacity: m.opacity, side: m.side
                });
            }
            if (c.material?.map) c.material.map.colorSpace = THREE.SRGBColorSpace;
            if (c.material?.emissiveMap) c.material.emissiveMap.colorSpace = THREE.SRGBColorSpace;
            c.castShadow = c.receiveShadow = true;
        }
    });
}

async function spawnHero() {
    return new Promise(resolve => {
        const loader = new GLTFLoader();
        loader.load(HERO_MODEL, gltf => {
            normalizeGltf(gltf.scene);
            heroMesh = gltf.scene;
            heroMesh.position.set(10, 0, 10);
            heroMesh.scale.setScalar(1.15);
            scene.add(heroMesh);

            mixer = new THREE.AnimationMixer(heroMesh);
            if (gltf.animations.length) {
                const idle = gltf.animations.find(a => /idle/i.test(a.name)) ?? gltf.animations[0];
                mixer.clipAction(idle).play();
            }
            resolve();
        }, undefined, () => resolve());
    });
}

// ─── NPC SPRITES ─────────────────────────────────────────────────────────────
function makeNpcSprite(npc) {
    const SIZE = 256;
    const cv = document.createElement('canvas');
    cv.width = SIZE; cv.height = SIZE * 1.6;
    const ctx = cv.getContext('2d');

    // Draw card background
    const colors = {
        '#ffd700': ['rgba(60,45,0,.92)','rgba(255,215,0,.18)','#ffd700'],
        '#00e8ff': ['rgba(0,18,30,.92)','rgba(0,232,255,.18)','#00e8ff'],
        '#9b30ff': ['rgba(20,8,38,.92)','rgba(155,48,255,.18)','#9b30ff'],
        '#00ff88': ['rgba(0,22,12,.92)','rgba(0,255,136,.18)','#00ff88'],
    };
    const [bg, border, accent] = colors[npc.color] ?? colors['#00e8ff'];

    ctx.fillStyle = bg;
    ctx.roundRect(4, 4, SIZE-8, SIZE*1.6-8, 14);
    ctx.fill();

    ctx.strokeStyle = border;
    ctx.lineWidth = 3;
    ctx.roundRect(4, 4, SIZE-8, SIZE*1.6-8, 14);
    ctx.stroke();

    // Glow at top
    const grad = ctx.createLinearGradient(0,0,0,80);
    grad.addColorStop(0, accent + '44');
    grad.addColorStop(1, 'transparent');
    ctx.fillStyle = grad;
    ctx.roundRect(4, 4, SIZE-8, 80, [14,14,0,0]);
    ctx.fill();

    // Name text
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold 18px "Orbitron", monospace`;
    ctx.textAlign = 'center';
    ctx.fillText(npc.name.split(' ')[0], SIZE/2, SIZE*1.6 - 42);
    ctx.fillStyle = accent;
    ctx.font = `12px "Share Tech Mono", monospace`;
    ctx.fillText('▶ INTERACT', SIZE/2, SIZE*1.6 - 22);

    const tex = new THREE.CanvasTexture(cv);
    tex.colorSpace = THREE.SRGBColorSpace;
    const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
    const sprite = new THREE.Sprite(mat);
    sprite.scale.set(1.0, 1.6, 1);

    // Load actual avatar image on top
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        ctx.drawImage(img, 20, 10, SIZE-40, SIZE*0.85);
        tex.needsUpdate = true;
    };
    img.src = npc.thumb;

    return sprite;
}

function spawnNPCs() {
    NPCS.forEach(npc => {
        const sprite = makeNpcSprite(npc);
        sprite.position.set(npc.waypoints[0][0], 1.6, npc.waypoints[0][2]);
        sprite.userData.npc = npc;
        scene.add(sprite);

        // Shadow disc beneath NPC
        const disc = new THREE.Mesh(
            new THREE.CircleGeometry(0.32, 16),
            new THREE.MeshStandardMaterial({ color: 0x000000, transparent: true, opacity: 0.35, depthWrite: false })
        );
        disc.rotation.x = -Math.PI/2;
        disc.position.set(npc.waypoints[0][0], 0.01, npc.waypoints[0][2]);
        scene.add(disc);

        // Glow ring on floor
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(0.34, 0.44, 24),
            new THREE.MeshStandardMaterial({ color: npc.color, emissive: npc.color, emissiveIntensity: 1.2, transparent: true, opacity: 0.55, depthWrite: false, side: THREE.DoubleSide })
        );
        ring.rotation.x = -Math.PI/2;
        ring.position.set(npc.waypoints[0][0], 0.015, npc.waypoints[0][2]);
        scene.add(ring);
        animObjects.push({ obj: ring, type: 'ring_pulse', base: 0.45, range: 0.25, speed: 0.7 + Math.random() * 0.3 });

        npcObjects.push({
            sprite, disc, ring, npc,
            waypointIdx: 0,
            t: 0,
            speed: 0.55 + Math.random() * 0.3,
            bob: Math.random() * Math.PI * 2,
        });
    });
}

// ─── INTERACTION ──────────────────────────────────────────────────────────────
function onCanvasClick(e) {
    const wrap = document.getElementById('cv');
    const rect = wrap.getBoundingClientRect();
    pointer.x =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
    pointer.y = -((e.clientY - rect.top)  / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);

    const targets = npcObjects.map(n => n.sprite);
    const hits = raycaster.intersectObjects(targets, false);
    if (!hits.length) return;

    const hitSprite = hits[0].object;
    const npcObj = npcObjects.find(n => n.sprite === hitSprite);
    if (npcObj) openNpcModal(npcObj.npc);
}

// ─── NPC MODAL ────────────────────────────────────────────────────────────────
window.closeNpcModal = function () {
    document.getElementById('npc-modal').classList.remove('open');
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval = null; }
};

function openNpcModal(npc) {
    // Set header
    document.getElementById('npc-name').textContent  = npc.name;
    document.getElementById('npc-title').textContent = npc.title;
    document.getElementById('npc-title').style.color = npc.color;

    // Avatar image
    const img = document.getElementById('npc-avatar-img');
    const ph  = document.getElementById('npc-placeholder');
    img.src = npc.thumb;
    img.style.display = 'block';
    ph.style.display  = 'none';

    // Enter button
    const btn = document.getElementById('npc-enter-btn');
    const lbl = document.getElementById('npc-enter-lbl');
    if (npc.coming_soon || !npc.game_url) {
        btn.classList.add('soon');
        btn.removeAttribute('href');
        lbl.textContent = 'COMING SOON';
    } else {
        btn.classList.remove('soon');
        btn.href = npc.game_url;
        lbl.textContent = npc.game_label ?? 'ENTER';
        btn.style.borderColor = npc.color.replace('ff', '99');
    }

    // Clear previous
    const textEl = document.getElementById('npc-text');
    textEl.innerHTML = '';
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval = null; }

    document.getElementById('npc-modal').classList.add('open');

    // Typed effect
    let i = 0;
    const dialogue = npc.dialogue;
    const cursor = document.createElement('span');
    cursor.className = 'npc-cursor';
    textEl.appendChild(cursor);

    _typingInterval = setInterval(() => {
        if (i < dialogue.length) {
            textEl.insertBefore(document.createTextNode(dialogue[i]), cursor);
            i++;
        } else {
            cursor.remove();
            clearInterval(_typingInterval);
            _typingInterval = null;
        }
    }, 22);
}

// ─── ANIMATION LOOP ───────────────────────────────────────────────────────────
let _t = 0;
function tick() {
    requestAnimationFrame(tick);
    const dt = Math.min(clock.getDelta(), 0.05);
    _t += dt;

    if (mixer) mixer.update(dt);
    updateNPCs(dt);
    updateAnimObjects(_t, dt);

    // hide hint after 5s
    _hintTimer += dt;
    if (_hintTimer > 5) {
        document.getElementById('hint')?.classList.add('fade');
    }

    if (composer) composer.render();
    else renderer.render(scene, camera);
}

function updateNPCs(dt) {
    npcObjects.forEach(no => {
        const wp = no.npc.waypoints;
        const from = wp[no.waypointIdx];
        const next = (no.waypointIdx + 1) % wp.length;
        const to   = wp[next];

        no.t += dt * no.speed;
        const dx = to[0] - from[0], dz = to[2] - from[2];
        const dist = Math.sqrt(dx*dx + dz*dz);

        if (no.t >= 1.0 || dist < 0.01) {
            no.waypointIdx = next;
            no.t = 0;
        } else {
            const px = from[0] + dx * no.t;
            const pz = from[2] + dz * no.t;

            // Bob animation
            no.bob += dt * 2.2;
            const bob = Math.sin(no.bob) * 0.06;

            no.sprite.position.set(px, 1.6 + bob, pz);
            no.disc.position.set(px, 0.01, pz);
            no.ring.position.set(px, 0.015, pz);

            // Face camera (billboard already, but rotate ring toward movement)
            if (dist > 0.1) {
                const angle = Math.atan2(dx, dz);
                no.ring.rotation.z = 0; // keep flat
            }
        }
    });
}

function updateAnimObjects(t, dt) {
    animObjects.forEach(ao => {
        if (!ao.obj) return;
        switch (ao.type) {
            case 'pulse':
                ao.obj.intensity = ao.base + Math.sin(t * ao.speed) * ao.range;
                break;
            case 'opacity':
                if (ao.obj.material) ao.obj.material.opacity = ao.base + Math.sin(t * ao.speed) * ao.range;
                break;
            case 'scan_y':
                ao.obj.position.y = ao.base + ((((t * ao.speed) % 1) + 1) % 1) * (ao.top - ao.base);
                break;
            case 'rotate_y':
                ao.obj.rotation.y += dt * ao.speed;
                break;
            case 'float':
                ao.obj.position.y = ao.base + Math.sin(t * ao.speed) * ao.range;
                break;
            case 'ring_pulse':
                if (ao.obj.material) ao.obj.material.opacity = ao.base + Math.sin(t * ao.speed) * ao.range;
                ao.obj.scale.setScalar(1 + Math.sin(t * ao.speed * 0.5) * 0.08);
                break;
            case 'particles_drift':
                const pos = ao.obj.geometry.attributes.position;
                for (let i = 0; i < pos.count; i++) {
                    pos.array[i*3+1] += dt * ao.speed * (0.6 + Math.sin(t + i) * 0.4);
                    if (pos.array[i*3+1] > 4.8) pos.array[i*3+1] = 0;
                }
                pos.needsUpdate = true;
                break;
            case 'emissive_flicker':
                if (ao.mat) ao.mat.emissiveIntensity = ao.base + Math.sin(t * ao.speed + ao.speed) * ao.range;
                break;
        }
    });
}
</script>
</body>
</html>

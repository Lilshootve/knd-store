<?php
/**
 * SOCIAL AGORA — District Room v2
 * Real GLB NPCs, WASD movement, E-key interaction.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/mw_avatar_models.php';

if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$_heroModelUrl = null;
$_playerName   = 'CITIZEN';
try {
    $pdo = getDBConnection();
    $uid = (int)(current_user_id() ?? 0);
    if ($uid > 0) {
        $un = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $un->execute([$uid]);
        $_playerName = mb_strtoupper((string)($un->fetchColumn() ?: 'CITIZEN'), 'UTF-8');
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

$_npcs = [
    [
        'id'        => 'caesar',
        'name'      => 'JULIUS CAESAR',
        'title'     => 'ROMAN GENERAL · EPIC TANK',
        'color'     => '#ffd700',
        'glb'       => '/assets/avatars/models/rare/julio_csar.glb',
        'waypoints' => [[4,0,4],[4,0,16],[10,0,10],[14,0,5]],
        'dialogue'  => "Veni, vidi, vici — I came, I saw, I conquered. But conquest without community is hollow. Rome was not built by one man. The Social Agora is where alliances form, where guilds rise, where legends begin. When this district opens fully, come back ready. A new Rome is being assembled.",
        'game_url'  => null,
        'game_label'=> 'GUILDS',
        'coming_soon' => true,
    ],
    [
        'id'        => 'confucius',
        'name'      => 'CONFUCIUS',
        'title'     => 'THE MASTER · RARE STRATEGIST',
        'color'     => '#00e8ff',
        'glb'       => '/assets/avatars/models/rare/confucius.glb',
        'waypoints' => [[16,0,5],[16,0,15],[10,0,18],[6,0,10]],
        'dialogue'  => "The man who asks a question is a fool for a minute. The man who does not ask is a fool for life. This Agora is being built on wisdom — a place where warriors share knowledge, form squads, and grow together. I have waited centuries for such a forum. Patience, student. It comes soon.",
        'game_url'  => null,
        'game_label'=> 'WISDOM HUB',
        'coming_soon' => true,
    ],
    [
        'id'        => 'chanakya',
        'name'      => 'CHANAKYA',
        'title'     => 'THE KINGMAKER · RARE STRATEGIST',
        'color'     => '#00ff88',
        'glb'       => '/assets/avatars/models/rare/chanakya.glb',
        'waypoints' => [[5,0,15],[14,0,15],[14,0,10],[7,0,6]],
        'dialogue'  => "A person should not be too honest. Straight trees are cut first and honest people are screwed first. The Agora demands political cunning. I built empires from shadows. When the Social Hub opens, I will teach you how to build alliances that last — and how to know when to break them.",
        'game_url'  => null,
        'game_label'=> 'ALLIANCES',
        'coming_soon' => true,
    ],
    [
        'id'        => 'napoleon',
        'name'      => 'NAPOLEON',
        'title'     => 'EMPEROR · EPIC TANK',
        'color'     => '#ff9800',
        'glb'       => '/assets/avatars/models/epic/napoleon.glb',
        'waypoints' => [[10,0,12],[16,0,12],[14,0,17],[8,0,16]],
        'dialogue'  => "Impossible is a word found only in the dictionary of fools. I reshaped continents. The Social Agora will reshape how KND players connect — squads, tournaments, shared glory. I am planning the campaign now. Every great conquest starts here, in the marketplace of ambition. Come back soon. We march.",
        'game_url'  => null,
        'game_label'=> 'SQUADRONS',
        'coming_soon' => true,
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
<title>SOCIAL AGORA — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#030a05;font-family:"Share Tech Mono",monospace;color:#c0f0d0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.04) 3px,rgba(0,0,0,.04) 4px)}
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%)}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#d0ffe8}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0%);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(3,10,5,.97);border-bottom:1px solid rgba(0,255,136,.08);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#00ff88 35%,#00e8ff 50%,#00ff88 65%,transparent 98%);opacity:.25}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(0,255,136,.2);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(0,255,136,.75);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(0,255,136,.5);color:#00ff88;background:rgba(0,255,136,.08)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(0,255,136,.3);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(255,152,0,.08);border:1px solid rgba(255,152,0,.3);color:#ff9800}
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#030a05}
#cv canvas{width:100%!important;height:100%!important}
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.78);backdrop-filter:blur(14px);display:none;align-items:flex-end;justify-content:center;padding:0 0 36px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(4,12,7,.99),rgba(2,8,4,.99));border:1px solid rgba(0,255,136,.2);border-radius:14px;overflow:hidden;box-shadow:0 0 100px rgba(0,255,136,.1),0 0 50px rgba(0,0,0,.8);animation:panelUp .38s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:16px;padding:18px 22px 16px;border-bottom:1px solid rgba(0,255,136,.1);position:relative}
.npc-avatar-frame{width:64px;height:64px;border-radius:10px;border:2px solid rgba(0,255,136,.35);overflow:hidden;flex-shrink:0;background:rgba(0,255,136,.06);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-placeholder{font-size:28px;opacity:.4}
.npc-info{flex:1;min-width:0}
.npc-name{font-family:"Orbitron",sans-serif;font-size:14px;font-weight:900;letter-spacing:.08em;color:#fff}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:4px;opacity:.5}
.npc-close{position:absolute;right:14px;top:14px;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:rgba(255,255,255,.3);transition:all .2s}
.npc-close:hover{background:rgba(255,152,0,.12);border-color:rgba(255,152,0,.4);color:#ff9800}
.npc-dialogue{padding:20px 24px;min-height:100px}
.npc-text{font-size:12.5px;line-height:1.8;color:rgba(192,240,208,.9);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#00ff88;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 20px;border-top:1px solid rgba(0,255,136,.07)}
.npc-btn-enter{flex:1;padding:13px 18px;border-radius:7px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(0,255,136,.15),rgba(0,232,255,.08));border:1px solid rgba(0,255,136,.4);color:#00ff88;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 32px rgba(0,255,136,.25);transform:translateY(-2px);color:#fff}
.npc-btn-enter.soon{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:13px 18px;border-radius:7px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);color:rgba(192,240,208,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.18);color:rgba(192,240,208,.75)}
#hint{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:100;font-size:8px;letter-spacing:.18em;color:rgba(0,255,136,.45);pointer-events:none;transition:opacity .4s;white-space:nowrap;text-shadow:0 0 10px rgba(0,255,136,.3)}
#hint.active{color:#00ff88;font-size:9px;text-shadow:0 0 20px rgba(0,255,136,.7)}
#wasd-hint{position:fixed;bottom:44px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.14em;color:rgba(0,255,136,.22);pointer-events:none}
#load{position:fixed;inset:0;z-index:8000;background:#030a05;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:28px;font-weight:900;letter-spacing:.28em;color:#fff}.load-logo span{color:#00ff88}
.load-sub{font-size:8px;letter-spacing:.38em;color:rgba(0,255,136,.4)}
.load-bar{width:240px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
.load-fill{height:100%;background:linear-gradient(90deg,#00ff88,#00e8ff);border-radius:1px;width:0%;transition:width .4s ease}
</style>
</head>
<body>
<div id="crt"></div>
<div id="load">
  <div class="load-logo">SOCIAL <span>AGORA</span></div>
  <div class="load-sub">THE MARKETPLACE OF CIVILIZATION</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">SOCIAL AGORA</span>
  <span id="tb-sub">DISTRICT · COMMUNITY &amp; GUILDS</span>
  <span class="tb-badge">SOON</span>
</header>
<div id="cv"></div>
<div id="hint">APPROACH A FIGURE — PRESS E TO SPEAK</div>
<div id="wasd-hint">WASD / ARROWS — MOVE</div>
<div id="npc-modal">
  <div class="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">🏛</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title" style="color:#00ff88">—</div>
      </div>
      <div class="npc-close" onclick="closeNpcModal()">✕</div>
    </div>
    <div class="npc-dialogue"><div class="npc-text" id="npc-text"></div></div>
    <div class="npc-actions">
      <a class="npc-btn-enter soon" id="npc-enter-btn" href="#"><span>🔒</span><span id="npc-enter-lbl">COMING SOON</span></a>
      <button class="npc-btn-skip" onclick="closeNpcModal()">LEAVE</button>
    </div>
  </div>
</div>

<script type="module">
import * as THREE from 'three';
import { EffectComposer }  from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass }      from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { GLTFLoader }      from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls }   from 'three/addons/controls/OrbitControls.js';

const HERO_MODEL  = <?php echo $_heroJson; ?>;
const PLAYER_NAME = <?php echo $_playerJson; ?>;
const NPCS        = <?php echo $_npcsJson; ?>;
const GRID = 20;
const D_CAM = 13;
const MOVE_SPEED = 5.5;
const INTERACT_DIST = 2.6;
const ISO_FWD  = new THREE.Vector3(-0.707, 0, -0.707);
const ISO_BACK = new THREE.Vector3( 0.707, 0,  0.707);
const ISO_LEFT = new THREE.Vector3(-0.707, 0,  0.707);
const ISO_RIGHT= new THREE.Vector3( 0.707, 0, -0.707);

let scene, camera, renderer, composer, controls;
let clock, heroMesh, heroMixer;
let heroPos = new THREE.Vector3(GRID/2, 0, GRID/2);
let npcObjects = [], animObjects = [];
let keys = {}, activeNpcIdx = -1;
let _typingInterval = null, _t = 0;
const loader = new GLTFLoader();
const raycaster = new THREE.Raycaster(), pointer = new THREE.Vector2();

window.addEventListener('DOMContentLoaded', boot);

async function boot() {
    clock = new THREE.Clock();
    setLoad(10);
    initRenderer(); setLoad(25);
    initCamera(); setLoad(35);
    initScene(); setLoad(50);
    buildScene(); setLoad(65);
    await spawnNPCs(); setLoad(84);
    if (HERO_MODEL) await spawnHero(); setLoad(100);
    setTimeout(() => {
        document.getElementById('load').classList.add('done');
        setTimeout(() => document.getElementById('load').remove(), 600);
        document.getElementById('crt').classList.add('on');
    }, 350);
    window.addEventListener('click', onCanvasClick);
    window.addEventListener('resize', onResize);
    window.addEventListener('keydown', e => {
        keys[e.code] = true;
        if (e.code === 'KeyE' && activeNpcIdx >= 0 && !document.getElementById('npc-modal').classList.contains('open'))
            openNpcModal(npcObjects[activeNpcIdx].npc);
        if (e.code === 'Escape') closeNpcModal();
    });
    window.addEventListener('keyup', e => { keys[e.code] = false; });
    tick();
}

function setLoad(p) { const f=document.getElementById('load-fill'); if(f) f.style.width=p+'%'; }

function initRenderer() {
    const wrap = document.getElementById('cv');
    renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.45;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    wrap.appendChild(renderer.domElement);
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
}

function initCamera() {
    const wrap = document.getElementById('cv');
    camera = new THREE.PerspectiveCamera(55, wrap.clientWidth / Math.max(1, wrap.clientHeight), 0.1, 200);
    camera.position.set(GRID/2 + 14, 22, GRID/2 + 14);
    camera.lookAt(GRID/2, 0, GRID/2);
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping   = true;
    controls.dampingFactor   = 0.07;
    controls.minDistance     = 6;
    controls.maxDistance     = 55;
    controls.maxPolarAngle   = Math.PI / 2.1;
    controls.target.set(GRID/2, 0, GRID/2);
    controls.update();
}

function onResize() {
    const wrap = document.getElementById('cv');
    const w = wrap.clientWidth, h = wrap.clientHeight;
    camera.aspect = w / Math.max(1, h);
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
    if (composer) composer.setSize(w, h);
}

function initScene() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x030a05);
    scene.fog = new THREE.FogExp2(0x040e06, 0.016);
    const wrap = document.getElementById('cv');
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(wrap.clientWidth, wrap.clientHeight), 0.72, 0.48, 0.82));
}

function buildScene() {
    scene.add(new THREE.HemisphereLight(0x205030, 0x080e08, 0.75));
    scene.add(new THREE.AmbientLight(0x0a1a0c, 1.1));
    const sun = new THREE.DirectionalLight(0xd0ffaa, 1.0);
    sun.position.set(12, 28, 14);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = sun.shadow.camera.bottom = -28;
    sun.shadow.camera.right = sun.shadow.camera.top = 28;
    sun.shadow.camera.far = 90;
    scene.add(sun);
    const fill = new THREE.DirectionalLight(0x00e8ff, 0.4);
    fill.position.set(-12, 10, -8);
    scene.add(fill);
    const rim = new THREE.DirectionalLight(0xffd080, 0.25);
    rim.position.set(20, 8, 4);
    scene.add(rim);

    buildAgoraFloor();
    buildAgoraStructure();
    buildMarketProps();
    buildFountain();
    buildParticles();
}

function buildAgoraFloor() {
    const mat = new THREE.MeshStandardMaterial({ color: 0x0d1e0f, roughness: 0.85, metalness: 0.05 });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI/2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);
    // Stone tile pattern
    const tileMat = new THREE.MeshStandardMaterial({ color: 0x152218, roughness: 0.9 });
    const grid = new THREE.GridHelper(GRID, 10, 0x1a3020, 0x152218);
    grid.position.set(GRID/2, 0.003, GRID/2);
    scene.add(grid);
    // Green glow paths
    const pathMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.6, transparent: true, opacity: 0.15 });
    [[GRID/2, 0, 0, 0, GRID, 0.04], [0, 0, GRID/2, GRID, 0, 0.04]].forEach(([x,_,z,w,d]) => {
        const p = new THREE.Mesh(new THREE.BoxGeometry(w||0.04, 0.018, d||0.04), pathMat.clone());
        p.position.set(x, 0.009, z);
        scene.add(p);
    });
    // Mosaic center rings
    const rMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.5, transparent: true, opacity: 0.25, side: THREE.DoubleSide, depthWrite: false });
    [1.2, 2.8, 4.6].forEach((r, i) => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(r, r+0.06, 40), rMat.clone());
        ring.rotation.x = -Math.PI/2;
        ring.position.set(GRID/2, 0.015, GRID/2);
        scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.18, range: 0.15, speed: 0.3 + i*0.08 });
    });
}

function buildAgoraStructure() {
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x0e1a10, roughness: 0.95 });
    [[GRID/2, 1.5, 0, 0,0,0, GRID, 3],
     [GRID/2, 1.5, GRID, 0,0,0, GRID, 3],
     [0, 1.5, GRID/2, 0,Math.PI/2,0, GRID, 3],
     [GRID, 1.5, GRID/2, 0,Math.PI/2,0, GRID, 3]].forEach(([x,y,z,rx,ry,rz,w,h]) => {
        const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), wallMat);
        m.position.set(x,y,z); m.rotation.set(rx,ry,rz); m.receiveShadow = true; scene.add(m);
    });
    // Columns — 8 around perimeter
    const colGeo = new THREE.CylinderGeometry(0.22, 0.26, 3.2, 10);
    const colMat = new THREE.MeshStandardMaterial({ color: 0x1a3020, roughness: 0.75, metalness: 0.05 });
    [[1,1],[1,19],[19,1],[19,19],[1,10],[19,10],[10,1],[10,19]].forEach(([x,z]) => {
        const col = new THREE.Mesh(colGeo, colMat);
        col.position.set(x, 1.6, z); col.castShadow = true; scene.add(col);
        // Cap glow
        const capMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.5, roughness: 0.5 });
        const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, 0.12, 10), capMat);
        cap.position.set(x, 3.26, z); scene.add(cap);
        const pl = new THREE.PointLight(0x00ff88, 0.35, 3.5);
        pl.position.set(x, 3.5, z); scene.add(pl);
        animObjects.push({ obj: pl, type: 'pulse', base: 0.25, range: 0.25, speed: 0.6 + Math.random()*0.4 });
    });
    // Hanging lanterns
    for (let i = 0; i < 4; i++) {
        const lx = [5,15,5,15][i], lz = [5,5,15,15][i];
        const chainMat = new THREE.MeshStandardMaterial({ color: 0x2a4a30, metalness: 0.6 });
        const chain = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.9, 4), chainMat);
        chain.position.set(lx, 3.55, lz); scene.add(chain);
        const lanternMat = new THREE.MeshStandardMaterial({ color: 0x1a4a22, emissive: 0x00ff88, emissiveIntensity: 0.4, roughness: 0.3 });
        const lantern = new THREE.Mesh(new THREE.OctahedronGeometry(0.22, 0), lanternMat);
        lantern.position.set(lx, 3.0, lz); scene.add(lantern);
        animObjects.push({ obj: lantern, type: 'float', base: 3.0, range: 0.1, speed: 0.7 + i*0.18 });
        animObjects.push({ obj: lanternMat, type: 'emissive_flicker', color: new THREE.Color(0x00ff88), base: 0.35, range: 0.35, speed: 1.5+i*0.3 });
        const lpl = new THREE.PointLight(0x00ff88, 0.45, 5);
        lpl.position.set(lx, 2.9, lz); scene.add(lpl);
        animObjects.push({ obj: lpl, type: 'pulse', base: 0.3, range: 0.35, speed: 0.9+i*0.25 });
    }
}

function buildMarketProps() {
    const stallColors = [0x1a4a22, 0x4a1a22, 0x1a224a, 0x4a3a0a, 0x0a3a3a, 0x3a0a4a];
    [[2,2],[18,2],[2,18],[18,18],[2,10],[18,10]].forEach(([x,z], i) => {
        const col = stallColors[i % stallColors.length];
        const roofMat = new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.15, roughness: 0.6 });
        const roof = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.08, 1.6), roofMat);
        roof.position.set(x, 1.7, z); roof.castShadow = true; scene.add(roof);
        const tableMat = new THREE.MeshStandardMaterial({ color: 0x1a2e1c, roughness: 0.8 });
        const table = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.06, 1.2), tableMat);
        table.position.set(x, 0.82, z); table.castShadow = true; scene.add(table);
        const legMat = new THREE.MeshStandardMaterial({ color: 0x0e1a10 });
        [[-0.7,0.7],[-0.7,-0.7],[0.7,0.7],[0.7,-0.7]].forEach(([dx,dz]) => {
            const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.82, 5), legMat);
            leg.position.set(x+dx, 0.41, z+dz); scene.add(leg);
        });
    });
    // Banner poles
    [[GRID/2-3, 0, GRID/2-3],[GRID/2+3, 0, GRID/2-3],[GRID/2-3, 0, GRID/2+3],[GRID/2+3, 0, GRID/2+3]].forEach(([x,y,z], i) => {
        const poleMat = new THREE.MeshStandardMaterial({ color: 0x2a4030, metalness: 0.4 });
        const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 3.5, 6), poleMat);
        pole.position.set(x, 1.75, z); scene.add(pole);
        const bColors = [0x00ff88, 0x00e8ff, 0xffd700, 0xff9800];
        const bannerMat = new THREE.MeshStandardMaterial({ color: bColors[i], emissive: bColors[i], emissiveIntensity: 0.3, side: THREE.DoubleSide, transparent: true, opacity: 0.85 });
        const banner = new THREE.Mesh(new THREE.PlaneGeometry(0.7, 1.2), bannerMat);
        banner.position.set(x + 0.35, 2.8, z); scene.add(banner);
        animObjects.push({ obj: banner, type: 'wave', base: 0, range: 0.06, speed: 1.2 + i*0.25 });
    });
}

function buildFountain() {
    const cx = GRID/2, cz = GRID/2;
    const stoneMat = new THREE.MeshStandardMaterial({ color: 0x1e3824, roughness: 0.85, metalness: 0.05 });
    const basin = new THREE.Mesh(new THREE.CylinderGeometry(1.8, 2.0, 0.35, 20), stoneMat);
    basin.position.set(cx, 0.175, cz); basin.castShadow = true; scene.add(basin);
    const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.25, 1.4, 8), stoneMat);
    pillar.position.set(cx, 0.7, cz); scene.add(pillar);
    const bowl = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.25, 0.2, 12), stoneMat);
    bowl.position.set(cx, 1.5, cz); scene.add(bowl);
    const waterMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.5, transparent: true, opacity: 0.55, roughness: 0.0, metalness: 0.3 });
    const water = new THREE.Mesh(new THREE.CylinderGeometry(1.6, 1.6, 0.05, 20), waterMat);
    water.position.set(cx, 0.39, cz); scene.add(water);
    animObjects.push({ obj: waterMat, type: 'om', base: 0.45, range: 0.2, speed: 0.8 });
    const fpl = new THREE.PointLight(0x00ff88, 1.0, 7);
    fpl.position.set(cx, 1.6, cz); scene.add(fpl);
    animObjects.push({ obj: fpl, type: 'pulse', base: 0.7, range: 0.5, speed: 1.1 });
    // Spray particles
    const sprayCnt = 24;
    const sprayData = [];
    for (let i = 0; i < sprayCnt; i++) {
        const angle = (i / sprayCnt) * Math.PI * 2;
        const geo = new THREE.SphereGeometry(0.03, 4, 4);
        const mat = new THREE.MeshBasicMaterial({ color: 0x88ffcc, transparent: true, opacity: 0.7 });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.position.set(cx, 1.5, cz);
        scene.add(mesh);
        sprayData.push({ mesh, mat, angle, t: (i / sprayCnt), speed: 0.7 + Math.random()*0.4 });
    }
    animObjects.push({ obj: null, type: 'fountain_spray', cx, cz, particles: sprayData });
}

function buildParticles() {
    const geo = new THREE.BufferGeometry();
    const cnt = 100;
    const pos = new Float32Array(cnt * 3);
    for (let i = 0; i < cnt; i++) {
        pos[i*3] = Math.random()*GRID; pos[i*3+1] = Math.random()*3.2+0.2; pos[i*3+2] = Math.random()*GRID;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const pts = new THREE.Points(geo, new THREE.PointsMaterial({ color: 0x00ff88, size: 0.06, transparent: true, opacity: 0.45, sizeAttenuation: true }));
    scene.add(pts);
    animObjects.push({ obj: pts, type: 'particles_drift', positions: pos, count: cnt, speed: 0.05 });
}

function normalizeGltf(gltf) {
    gltf.scene.traverse(o => {
        if (!o.isMesh) return;
        const m = o.material;
        if (!m) return;
        if (m.isMeshPhysicalMaterial) {
            o.material = new THREE.MeshStandardMaterial({
                color: m.color, map: m.map, normalMap: m.normalMap,
                roughnessMap: m.roughnessMap, metalnessMap: m.metalnessMap,
                emissiveMap: m.emissiveMap, emissive: m.emissive,
                roughness: m.roughness ?? 0.7, metalness: m.metalness ?? 0.0,
                transparent: m.transparent, opacity: m.opacity, side: m.side
            });
        }
        ['map','emissiveMap'].forEach(k => { if (m[k]) m[k].colorSpace = THREE.SRGBColorSpace; });
        o.castShadow = o.receiveShadow = true;
    });
}

function makeNameLabel(name, color) {
    const c = document.createElement('canvas');
    c.width = 256; c.height = 52;
    const ctx = c.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.fill();
    ctx.strokeStyle = color; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 13px Orbitron, monospace';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(name, 128, 26);
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
    sprite.scale.set(2.4, 0.45, 1);
    return sprite;
}

async function loadGroundedGLB(url, targetHeight) {
    const gltf = await loader.loadAsync(url);
    normalizeGltf(gltf);
    const model = gltf.scene;
    const rawBox = new THREE.Box3().setFromObject(model);
    const rawH   = rawBox.max.y - rawBox.min.y;
    model.scale.setScalar(targetHeight / Math.max(rawH, 0.001));
    const scaledBox = new THREE.Box3().setFromObject(model);
    model.position.y = -scaledBox.min.y;
    const wrapper = new THREE.Group();
    wrapper.add(model);
    const mixer = gltf.animations.length > 0 ? new THREE.AnimationMixer(model) : null;
    if (mixer) mixer.clipAction(gltf.animations[0]).play();
    return { wrapper, mixer };
}

async function spawnNPCs() {
    for (const npc of NPCS) {
        const wp0 = npc.waypoints[0];
        let obj, mixer = null;
        try {
            const result = await loadGroundedGLB(npc.glb, 1.9);
            obj   = result.wrapper;
            mixer = result.mixer;
            obj.position.set(wp0[0], 0, wp0[2]);
            const dir = new THREE.Vector3(GRID/2 - wp0[0], 0, GRID/2 - wp0[2]).normalize();
            obj.rotation.y = Math.atan2(dir.x, dir.z);
            scene.add(obj);
        } catch(e) {
            console.warn('NPC GLB fail:', npc.glb, e);
            const col = parseInt(npc.color.replace('#',''), 16);
            obj = new THREE.Mesh(new THREE.CapsuleGeometry(0.35, 1.1, 4, 8),
                new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.3, roughness: 0.6 }));
            obj.position.set(wp0[0], 0, wp0[2]);
            scene.add(obj);
        }
        const label = makeNameLabel(npc.name, npc.color);
        label.position.set(0, 2.5, 0);
        obj.add(label);
        const disc = new THREE.Mesh(new THREE.CircleGeometry(0.7, 24),
            new THREE.MeshBasicMaterial({ color: new THREE.Color(npc.color), transparent: true, opacity: 0.2, depthWrite: false, side: THREE.DoubleSide }));
        disc.rotation.x = -Math.PI/2;
        disc.position.set(wp0[0], 0.02, wp0[2]);
        scene.add(disc);
        const ringMat = new THREE.MeshBasicMaterial({ color: new THREE.Color(npc.color), transparent: true, opacity: 0.0, side: THREE.DoubleSide });
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.85, 1.05, 32), ringMat);
        ring.rotation.x = -Math.PI/2;
        ring.position.set(wp0[0], 0.03, wp0[2]);
        scene.add(ring);
        npcObjects.push({ npc, obj, disc, ring, ringMat, wpIdx: 0, mixer, isNear: false });
    }
}

async function spawnHero() {
    try {
        const result = await loadGroundedGLB(HERO_MODEL, 1.8);
        heroMesh  = result.wrapper;
        heroMixer = result.mixer;
        heroMesh.position.copy(heroPos);
        scene.add(heroMesh);
        const lbl = makeNameLabel(PLAYER_NAME, '#00e8ff');
        lbl.position.set(0, 2.5, 0);
        heroMesh.add(lbl);
    } catch(e) { console.warn('Hero GLB fail:', e); }
}

function tick() {
    requestAnimationFrame(tick);
    const dt = Math.min(clock.getDelta(), 0.05);
    _t += dt;
    updateHero(dt);
    updateNPCs(dt);
    updateAnimObjects(dt);
    if (heroMixer) heroMixer.update(dt);
    composer.render();
}

function updateHero(dt) {
    if (document.getElementById('npc-modal').classList.contains('open')) return;
    const v = new THREE.Vector3();
    if (keys['KeyW']||keys['ArrowUp'])    v.add(ISO_FWD);
    if (keys['KeyS']||keys['ArrowDown'])  v.add(ISO_BACK);
    if (keys['KeyA']||keys['ArrowLeft'])  v.add(ISO_LEFT);
    if (keys['KeyD']||keys['ArrowRight']) v.add(ISO_RIGHT);
    if (v.lengthSq() > 0) {
        v.normalize().multiplyScalar(MOVE_SPEED * dt);
        heroPos.x = Math.max(0.8, Math.min(GRID-0.8, heroPos.x + v.x));
        heroPos.z = Math.max(0.8, Math.min(GRID-0.8, heroPos.z + v.z));
        if (heroMesh) { heroMesh.position.copy(heroPos); heroMesh.rotation.y = Math.atan2(v.x, v.z); }
    }
    controls.target.lerp(heroPos, 0.08);
    controls.update();
}

function updateNPCs(dt) {
    let nearestIdx = -1, nearestDist = Infinity;
    npcObjects.forEach((no, i) => {
        const wps = no.npc.waypoints;
        const target = new THREE.Vector3(wps[no.wpIdx][0], 0, wps[no.wpIdx][2]);
        const diff = target.clone().sub(new THREE.Vector3(no.obj.position.x, 0, no.obj.position.z));
        if (diff.length() < 0.12) { no.wpIdx = (no.wpIdx + 1) % wps.length; }
        else {
            const step = diff.normalize().multiplyScalar(0.75 * dt);
            no.obj.position.x += step.x; no.obj.position.z += step.z;
            no.obj.rotation.y = Math.atan2(step.x, step.z);
            if (no.disc) { no.disc.position.x = no.obj.position.x; no.disc.position.z = no.obj.position.z; }
            if (no.ring) { no.ring.position.x = no.obj.position.x; no.ring.position.z = no.obj.position.z; }
        }
        if (no.mixer) no.mixer.update(dt);
        const d2h = new THREE.Vector3(no.obj.position.x, 0, no.obj.position.z).distanceTo(heroPos);
        if (d2h < nearestDist) { nearestDist = d2h; nearestIdx = i; }
        if (d2h < INTERACT_DIST) { no.ringMat.opacity = 0.5 + 0.3*Math.sin(_t*4); no.isNear = true; }
        else { no.ringMat.opacity = 0; no.isNear = false; }
    });
    activeNpcIdx = (nearestDist < INTERACT_DIST) ? nearestIdx : -1;
    const hint = document.getElementById('hint');
    if (activeNpcIdx >= 0) {
        hint.textContent = `[ E ] SPEAK WITH ${npcObjects[activeNpcIdx].npc.name}`;
        hint.className = 'active';
    } else {
        hint.textContent = 'APPROACH A FIGURE — PRESS E TO SPEAK';
        hint.className = '';
    }
}

function updateAnimObjects(dt) {
    animObjects.forEach(ao => {
        if (!ao.obj && ao.type !== 'fountain_spray') return;
        switch(ao.type) {
            case 'pulse': ao.obj.intensity = ao.base + Math.sin(_t*ao.speed*Math.PI*2)*ao.range; break;
            case 'om': ao.obj.opacity = ao.base + Math.sin(_t*ao.speed*Math.PI*2)*ao.range; break;
            case 'rotate_y': ao.obj.rotation.y += ao.speed*dt; break;
            case 'float': ao.obj.position.y = ao.base + Math.sin(_t*ao.speed*Math.PI*2)*ao.range; break;
            case 'emissive_flicker': ao.obj.emissiveIntensity = ao.base + Math.random()*ao.range; break;
            case 'wave': ao.obj.rotation.z = Math.sin(_t*ao.speed*Math.PI*2)*ao.range; break;
            case 'particles_drift': {
                const p = ao.positions;
                for (let i=0;i<ao.count;i++) {
                    p[i*3+1] += ao.speed*dt;
                    if(p[i*3+1]>3.5){p[i*3+1]=0.1;p[i*3]=Math.random()*GRID;p[i*3+2]=Math.random()*GRID;}
                }
                ao.obj.geometry.attributes.position.needsUpdate=true;
                break;
            }
            case 'fountain_spray': {
                ao.particles.forEach(sp => {
                    sp.t = (sp.t + dt * sp.speed) % 1.0;
                    const t = sp.t;
                    const r = Math.sin(t * Math.PI) * 0.8;
                    sp.mesh.position.set(
                        ao.cx + Math.cos(sp.angle) * r,
                        1.55 + Math.sin(t * Math.PI) * 1.1 - t * 0.4,
                        ao.cz + Math.sin(sp.angle) * r
                    );
                    sp.mat.opacity = t < 0.85 ? 0.65*(1-t*0.6) : 0;
                });
                break;
            }
        }
    });
}

function onCanvasClick(e) {
    const modal = document.getElementById('npc-modal');
    if (modal.classList.contains('open')) return;
    const wrap = document.getElementById('cv');
    const rect = wrap.getBoundingClientRect();
    pointer.set(((e.clientX-rect.left)/rect.width)*2-1, -((e.clientY-rect.top)/rect.height)*2+1);
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(npcObjects.map(n=>n.obj), true);
    if (hits.length) {
        let root = hits[0].object;
        while(root.parent && !npcObjects.find(n=>n.obj===root)) root=root.parent;
        const no = npcObjects.find(n=>n.obj===root);
        if(no) openNpcModal(no.npc);
    }
}

function openNpcModal(npc) {
    if(_typingInterval) clearInterval(_typingInterval);
    document.getElementById('npc-name').textContent = npc.name;
    document.getElementById('npc-title').textContent = npc.title;
    document.getElementById('npc-title').style.color = npc.color;
    const textEl = document.getElementById('npc-text');
    textEl.textContent = '';
    const cursor = document.createElement('span'); cursor.className='npc-cursor'; textEl.appendChild(cursor);
    let ci=0; const txt=npc.dialogue||'';
    _typingInterval = setInterval(()=>{ if(ci<txt.length){cursor.before(txt[ci++]);}else{clearInterval(_typingInterval);} },20);
    const btn = document.getElementById('npc-enter-btn');
    btn.classList.add('soon');
    btn.removeAttribute('href');
    document.getElementById('npc-enter-lbl').textContent='COMING SOON';
    btn.querySelector('span').textContent='🔒';
    document.getElementById('npc-modal').classList.add('open');
}

window.closeNpcModal = function() {
    if(_typingInterval){clearInterval(_typingInterval);_typingInterval=null;}
    document.getElementById('npc-modal').classList.remove('open');
};
</script>
</body>
</html>

<?php
/**
 * FATE CASINO — District Room v2
 * Real GLB NPCs, WASD movement, camera follow, E-key interaction.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/mw_avatar_models.php';

// CSP: allow wasm-unsafe-eval for DRACO/Three.js
header("Content-Security-Policy", "default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval' blob: https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://code.jquery.com https://www.paypal.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; img-src 'self' data: blob: https: https://www.paypalobjects.com; media-src 'self' data: blob:; frame-src 'self' https://www.paypal.com https://www.sandbox.paypal.com; connect-src 'self' blob: wss://knd-store-production.up.railway.app https://knd-store-production.up.railway.app http://127.0.0.1:3000 http://localhost:3000 ws://127.0.0.1:8765 ws://localhost:8765 ws://kndstore.com:8765 wss://kndstore.com:8765 ws://www.kndstore.com:8765 wss://www.kndstore.com:8765 https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com https://www.gstatic.com https://www.paypal.com https://www.paypalobjects.com https://api-m.paypal.com;");


if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$nexusRtUid = (int)(current_user_id() ?? 0);

$_heroModelUrl = null;
$_playerName   = 'GAMBLER';
try {
    $pdo = getDBConnection();
    $uid = (int)(current_user_id() ?? 0);
    if ($uid > 0) {
        $un = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $un->execute([$uid]);
        $_playerName = mb_strtoupper((string)($un->fetchColumn() ?: 'GAMBLER'), 'UTF-8');
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
        'id'        => 'loki',
        'name'      => 'CORRUPTED LOKI',
        'title'     => 'GOD OF MISCHIEF · LEGENDARY TRICKSTER',
        'color'     => '#9b30ff',
        'glb'       => '/assets/avatars/models/epic/corrupted_loki.glb',
        'waypoints' => [[4,0,4],[4,0,10],[8,0,6],[6,0,14]],
        'dialogue'  => "Ohhh, a visitor. How... predictable. Or is it? I've been known to shuffle fate itself like a deck of cards. KND Insight is MY game — above or under? Simple? Don't be fooled. Even a coin flip hides infinite chaos. I've never lost a wager. But then again... I lie. Go ahead. Place your bet.",
        'game_url'  => '/above-under.php',
        'game_label'=> 'KND INSIGHT',
        'coming_soon' => false,
    ],
    [
        'id'        => 'silver',
        'name'      => 'LONG JOHN SILVER',
        'title'     => 'PIRATE CAPTAIN · SPECIAL STRATEGIST',
        'color'     => '#ffd700',
        'glb'       => '/assets/avatars/models/special/long_john_silver.glb',
        'waypoints' => [[16,0,4],[16,0,10],[13,0,7],[14,0,14]],
        'dialogue'  => "Arr, shiver me timbers — a fresh face at the table! I've sailed seven seas and cheated death thrice over. The Death Roll is my kind of game — pure nerve, pure skull-and-crossbones. You roll the dice. Fate decides. But between you and me... luck always favors the bold. Are ye bold enough?",
        'game_url'  => '/death-roll-lobby.php',
        'game_label'=> 'DEATH ROLL',
        'coming_soon' => false,
    ],
    [
        'id'        => 'dracula',
        'name'      => 'DRACULA',
        'title'     => 'LORD OF DARKNESS · EPIC CONTROLLER',
        'color'     => '#ff3d56',
        'glb'       => '/assets/avatars/models/epic/dracula.glb',
        'waypoints' => [[4,0,17],[10,0,15],[14,0,18],[8,0,16]],
        'dialogue'  => "Come in. I insist. I've waited centuries for someone interesting to walk through these doors. The Death Roll is exquisite — each throw of the dice could be your last... financially speaking. I've watched empires fall on a single roll. Tonight, we see what you're made of. Blood type: winner or loser?",
        'game_url'  => '/death-roll-game.php',
        'game_label'=> 'QUICK ROLL',
        'coming_soon' => false,
    ],
    [
        'id'        => 'medusa',
        'name'      => 'MEDUSA',
        'title'     => 'GORGON QUEEN · LEGENDARY SEER',
        'color'     => '#00ff88',
        'glb'       => '/assets/avatars/models/legendary/medusa.glb',
        'waypoints' => [[10,0,10],[15,0,12],[12,0,16],[7,0,12]],
        'dialogue'  => "Look me in the eyes. I dare you. Most turn to stone... but perhaps you're different. KND Insight is my prediction — will you go above, or crawl under? Every glance I cast freezes the future in place. You see, prophecy and gambling are the same art. I've already seen your choice. Have you?",
        'game_url'  => '/above-under.php',
        'game_label'=> 'KND INSIGHT',
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
<meta name="nexus-ws-url" content="wss://knd-store-production.up.railway.app">
<title>FATE CASINO — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#060208;font-family:"Share Tech Mono",monospace;color:#e0c0f0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.04) 3px,rgba(0,0,0,.04) 4px)}
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%)}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#f0d0ff}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0%);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(6,2,8,.97);border-bottom:1px solid rgba(155,48,255,.1);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#9b30ff 35%,#ff3d56 50%,#9b30ff 65%,transparent 98%);opacity:.3}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(155,48,255,.25);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(155,48,255,.8);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(155,48,255,.6);color:#c06aff;background:rgba(155,48,255,.1)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(155,48,255,.35);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(255,61,86,.1);border:1px solid rgba(255,61,86,.3);color:#ff3d56}
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#060208}
#cv canvas{width:100%!important;height:100%!important}
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.78);backdrop-filter:blur(14px);display:none;align-items:flex-end;justify-content:center;padding:0 0 36px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(10,4,18,.99),rgba(6,2,12,.99));border:1px solid rgba(155,48,255,.25);border-radius:14px;overflow:hidden;box-shadow:0 0 100px rgba(155,48,255,.15),0 0 50px rgba(0,0,0,.8);animation:panelUp .38s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:16px;padding:18px 22px 16px;border-bottom:1px solid rgba(155,48,255,.12);position:relative}
.npc-avatar-frame{width:64px;height:64px;border-radius:10px;border:2px solid rgba(155,48,255,.4);overflow:hidden;flex-shrink:0;background:rgba(155,48,255,.08);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-placeholder{font-size:28px;opacity:.4}
.npc-info{flex:1;min-width:0}
.npc-name{font-family:"Orbitron",sans-serif;font-size:14px;font-weight:900;letter-spacing:.08em;color:#fff;line-height:1.2}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:4px;opacity:.5}
.npc-close{position:absolute;right:14px;top:14px;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:rgba(255,255,255,.3);transition:all .2s}
.npc-close:hover{background:rgba(255,61,86,.12);border-color:rgba(255,61,86,.4);color:#ff3d56}
.npc-dialogue{padding:20px 24px;min-height:100px}
.npc-text{font-size:12.5px;line-height:1.8;color:rgba(220,195,245,.9);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#9b30ff;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 20px;border-top:1px solid rgba(155,48,255,.08)}
.npc-btn-enter{flex:1;padding:13px 18px;border-radius:7px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(155,48,255,.22),rgba(255,61,86,.12));border:1px solid rgba(155,48,255,.55);color:#c06aff;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 32px rgba(155,48,255,.3),0 0 16px rgba(155,48,255,.12);transform:translateY(-2px);color:#fff}
.npc-btn-enter.soon{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:13px 18px;border-radius:7px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);color:rgba(200,170,230,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.18);color:rgba(200,170,230,.75)}
#hint{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:100;font-size:8px;letter-spacing:.18em;color:rgba(155,48,255,.5);pointer-events:none;transition:opacity .4s;white-space:nowrap;text-shadow:0 0 10px rgba(155,48,255,.4)}
#hint.fade{opacity:0}
#hint.active{color:#c06aff;font-size:9px;text-shadow:0 0 20px rgba(155,48,255,.8)}
#wasd-hint{position:fixed;bottom:44px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.14em;color:rgba(155,48,255,.28);pointer-events:none}
#load{position:fixed;inset:0;z-index:8000;background:#060208;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:30px;font-weight:900;letter-spacing:.3em;color:#fff}.load-logo span{color:#9b30ff}
.load-sub{font-size:8px;letter-spacing:.38em;color:rgba(155,48,255,.4)}
.load-bar{width:240px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
.load-fill{height:100%;background:linear-gradient(90deg,#9b30ff,#ff3d56);border-radius:1px;width:0%;transition:width .4s ease}
</style>
</head>
<body>
<div id="crt"></div>
<div id="load">
  <div class="load-logo">FATE <span>CASINO</span></div>
  <div class="load-sub">ROLLING THE DICE OF DESTINY</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">FATE CASINO</span>
  <span id="tb-sub">DISTRICT · LUCK &amp; TRICKERY</span>
  <span class="tb-badge">LIVE</span>
</header>
<div id="cv"></div>
<div id="hint">APPROACH A DEALER — PRESS E TO INTERACT</div>
<div id="wasd-hint">WASD / ARROWS — MOVE</div>
<div id="npc-modal">
  <div class="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">🎲</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title" style="color:#9b30ff">—</div>
      </div>
      <div class="npc-close" onclick="closeNpcModal()">✕</div>
    </div>
    <div class="npc-dialogue"><div class="npc-text" id="npc-text"></div></div>
    <div class="npc-actions">
      <a class="npc-btn-enter" id="npc-enter-btn" href="#"><span>🎲</span><span id="npc-enter-lbl">PLAY</span></a>
      <button class="npc-btn-skip" onclick="closeNpcModal()">FOLD</button>
    </div>
  </div>
</div>

<script type="module">
import * as THREE from 'three';
import { EffectComposer }  from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass }      from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { GLTFLoader }      from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader }     from 'three/addons/loaders/DRACOLoader.js';
import { OrbitControls }   from 'three/addons/controls/OrbitControls.js';
import { createNexusDistrictRealtime } from './js/nexus-district-realtime.js';
import { CharacterController } from './js/character-controller.js';

const HERO_MODEL  = <?php echo $_heroJson; ?>;
const PLAYER_NAME = <?php echo $_playerJson; ?>;
const NEXUS_RT_UID = <?php echo (int)$nexusRtUid; ?>;
const NPCS        = <?php echo $_npcsJson; ?>;
const GRID = 20;
const D_CAM = 13;
const MOVE_SPEED = 5.5;
const INTERACT_DIST = 2.6;
// Isometric movement directions relative to 45° camera
const ISO_FWD  = new THREE.Vector3(-0.707, 0, -0.707);
const ISO_BACK = new THREE.Vector3( 0.707, 0,  0.707);
const ISO_LEFT = new THREE.Vector3(-0.707, 0,  0.707);
const ISO_RIGHT= new THREE.Vector3( 0.707, 0, -0.707);

let scene, camera, renderer, composer, controls;
let clock, heroMesh;
let heroPos = new THREE.Vector3(GRID/2, 0, GRID/2);
let npcObjects = [], animObjects = [];
let keys = {}, activeNpcIdx = -1;
let _typingInterval = null, _t = 0;
const _charCtrl = new CharacterController({
    walkSpeed:      16,
    runSpeed:       32,
    crouchSpeed:    6,
    jumpForce:      8,
    gravity:        20,
    isInputBlocked: () => document.getElementById('npc-modal').classList.contains('open') ||
                          ['INPUT','SELECT','TEXTAREA'].includes(document.activeElement?.tagName),
});
const loader = new GLTFLoader();
const _dracoLoader = new DRACOLoader();
_dracoLoader.setDecoderPath('https://www.gstatic.com/draco/v1/decoders/');
_dracoLoader.setDecoderConfig({ type: 'js' });
loader.setDRACOLoader(_dracoLoader);
let nexusRt = null;

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
        if (e.code === 'KeyE' && activeNpcIdx >= 0 && !document.getElementById('npc-modal').classList.contains('open')) {
            openNpcModal(npcObjects[activeNpcIdx].npc);
        }
        if (e.code === 'Escape') closeNpcModal();
    });
    window.addEventListener('keyup', e => { keys[e.code] = false; });
    nexusRt = createNexusDistrictRealtime({
        scene,
        districtId: 'casino',
        userId: NEXUS_RT_UID,
        displayName: PLAYER_NAME,
        colorBody: '#9b30ff',
        colorVisor: '#00e8ff',
        colorEcho: '#ffd600',
        heroModelUrl: HERO_MODEL || null,
        getPosition: () => ({ x: heroPos.x, z: heroPos.z }),
        getRotationY: () => (heroMesh ? heroMesh.rotation.y : 0),
    });
    if (NEXUS_RT_UID > 0) nexusRt.start();
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
    renderer.toneMappingExposure = 1.05; // Oscuro intencional — el neón brilla más contra fondo oscuro
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
    scene.background = new THREE.Color(0x060208);
    scene.fog = new THREE.FogExp2(0x080210, 0.018);
    const wrap = document.getElementById('cv');
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    // Bloom de neón: threshold bajo (0.45) capta los strips de suelo y point lights; radius estrecho para glow nítido de neón real
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(wrap.clientWidth, wrap.clientHeight), 1.1, 0.4, 0.45));
}

function buildScene() {
    // Lighting — NO Object.assign on position
    // Hemisphere: iluminación global de base para legibilidad de modelos low-poly
    scene.add(new THREE.HemisphereLight(0x4a1a70, 0x180828, 1.4));
    // Ambient global — garantiza que los NPCs sean visibles en toda la sala
    scene.add(new THREE.AmbientLight(0x2a1040, 1.8));

    // Key light — araña de cristal central, define dirección y sombras suaves
    const chandelier = new THREE.DirectionalLight(0xd090ff, 1.6);
    chandelier.position.set(GRID/2, 30, GRID/2);
    chandelier.castShadow = true;
    chandelier.shadow.mapSize.set(2048, 2048);
    chandelier.shadow.bias = -0.0003;
    chandelier.shadow.camera.left = chandelier.shadow.camera.bottom = -28;
    chandelier.shadow.camera.right = chandelier.shadow.camera.top = 28;
    chandelier.shadow.camera.far = 90;
    scene.add(chandelier);

    // Fill dramático rojo sangre — luz del caos / Dracula side
    const fill = new THREE.DirectionalLight(0xff4466, 0.85);
    fill.position.set(-16, 6, -12);
    scene.add(fill);

    // Rim dorado — riqueza, tentación, la promesa del jackpot
    const rim = new THREE.DirectionalLight(0xffcc00, 0.65);
    rim.position.set(22, 10, 4);
    scene.add(rim);

    buildCasinoFloor();
    buildCasinoWalls();
    buildCasinoCeiling();
    buildGamingTables();
    buildParticles();
}

function buildCasinoFloor() {
    // Suelo lacado tipo casino de lujo: metalness alto para reflejar los neones en el suelo
    const mat = new THREE.MeshStandardMaterial({ color: 0x080212, roughness: 0.15, metalness: 0.75 });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI/2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);
    scene.add(Object.assign(new THREE.GridHelper(GRID, 20, 0x18083a, 0x10042a), {}).translateX(GRID/2).translateZ(GRID/2));

    // Neon floor strips
    const colors = [0x9b30ff, 0xff3d56, 0xffd700, 0x00e8ff];
    [4, 8, 12, 16].forEach((v, i) => {
        const c = colors[i];
        const sm = new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 1.2, transparent: true, opacity: 0.28 });
        const h = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.022, 0.06), sm);
        h.position.set(GRID/2, 0.01, v); scene.add(h);
        animObjects.push({ obj: h.material, type: 'om', base: 0.2, range: 0.15, speed: 0.4+i*0.12 });
        const vv = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.022, GRID), sm.clone());
        vv.position.set(v, 0.01, GRID/2); scene.add(vv);
    });
    // Center roulette rings
    const cMat = new THREE.MeshStandardMaterial({ color: 0x9b30ff, emissive: 0x9b30ff, emissiveIntensity: 0.9, transparent: true, opacity: 0.35, side: THREE.DoubleSide, depthWrite: false });
    [1.5, 3.0, 4.5].forEach(r => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(r, r+0.07, 42), cMat.clone());
        ring.rotation.x = -Math.PI/2;
        ring.position.set(GRID/2, 0.02, GRID/2);
        scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.25, range: 0.2, speed: 0.45+r*0.07 });
    });
}

function buildCasinoWalls() {
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x0a0314, roughness: 0.9, metalness: 0.1 });
    const walls = [
        { pos: [GRID/2, 1.5, 0], rot: [0,0,0], size: [GRID, 3] },
        { pos: [GRID/2, 1.5, GRID], rot: [0,0,0], size: [GRID, 3] },
        { pos: [0, 1.5, GRID/2], rot: [0,Math.PI/2,0], size: [GRID, 3] },
        { pos: [GRID, 1.5, GRID/2], rot: [0,Math.PI/2,0], size: [GRID, 3] },
    ];
    walls.forEach(w => {
        const m = new THREE.Mesh(new THREE.PlaneGeometry(...w.size), wallMat);
        m.position.set(...w.pos); m.rotation.set(...w.rot); m.receiveShadow = true; scene.add(m);
    });
    // Slot machine silhouettes on back wall
    for (let i = 0; i < 5; i++) {
        const x = 2 + i * 3.8;
        const slotMat = new THREE.MeshStandardMaterial({ color: 0x1a0830, emissive: 0x9b30ff, emissiveIntensity: 0.3, roughness: 0.5 });
        const slot = new THREE.Mesh(new THREE.BoxGeometry(0.7, 1.6, 0.15), slotMat);
        slot.position.set(x, 1.2, 0.1); scene.add(slot);
        const screenMat = new THREE.MeshStandardMaterial({ color: 0x200840, emissive: 0xff3d56, emissiveIntensity: 0.6, transparent: true, opacity: 0.8 });
        const screen = new THREE.Mesh(new THREE.PlaneGeometry(0.45, 0.45), screenMat);
        screen.position.set(x, 1.3, 0.19); scene.add(screen);
        animObjects.push({ obj: screenMat, type: 'emissive_flicker', color: new THREE.Color(0xff3d56), base: 0.5, range: 0.5, speed: 1.2 + i*0.4 });
        const pl = new THREE.PointLight(0x9b30ff, 0.4, 3);
        pl.position.set(x, 1.5, 0.5); scene.add(pl);
        animObjects.push({ obj: pl, type: 'pulse', base: 0.3, range: 0.35, speed: 0.8+i*0.25 });
    }
}

function buildCasinoCeiling() {
    // Disco ball
    const dbGeo = new THREE.SphereGeometry(0.55, 16, 16);
    const dbMat = new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.98, roughness: 0.02 });
    const disco = new THREE.Mesh(dbGeo, dbMat);
    disco.position.set(GRID/2, 3.5, GRID/2);
    scene.add(disco);
    animObjects.push({ obj: disco, type: 'rotate_y', speed: 0.4 });
    // Disco spotlights orbiting
    const spotColors = [0xff3d56, 0x9b30ff, 0xffd700, 0x00e8ff];
    spotColors.forEach((col, i) => {
        const pl = new THREE.PointLight(col, 1.2, 12);
        pl.position.set(GRID/2, 2.0, GRID/2);
        scene.add(pl);
        animObjects.push({ obj: pl, type: 'disco_orbit', cx: GRID/2, cz: GRID/2, radius: 4.5, speed: 0.35, phase: (i/4)*Math.PI*2 });
    });
    // Ceiling plane
    const ceilMat = new THREE.MeshStandardMaterial({ color: 0x080114, roughness: 1 });
    const ceil = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), ceilMat);
    ceil.rotation.x = Math.PI/2;
    ceil.position.set(GRID/2, 4.0, GRID/2);
    scene.add(ceil);
    // Skull dice — floating rotating cubes
    [[5,2.2,5],[15,2.2,15]].forEach((p, i) => {
        const diceMat = new THREE.MeshStandardMaterial({ color: 0x1a0830, emissive: 0xff3d56, emissiveIntensity: 0.5, roughness: 0.3 });
        const dice = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.5, 0.5), diceMat);
        dice.position.set(...p); scene.add(dice);
        animObjects.push({ obj: dice, type: 'rotate_y', speed: 0.6 + i*0.3 });
        animObjects.push({ obj: dice, type: 'float', base: p[1], range: 0.18, speed: 0.9+i*0.3 });
        const dpl = new THREE.PointLight(0xff3d56, 0.6, 5);
        dpl.position.set(...p); scene.add(dpl);
        animObjects.push({ obj: dpl, type: 'pulse', base: 0.4, range: 0.5, speed: 1.2+i*0.4 });
    });
}

function buildGamingTables() {
    const tableColors = [0x0d4a1a, 0x1a0d4a, 0x4a0d1a, 0x1a3a10];
    [[5,5],[15,5],[5,15],[15,15]].forEach(([x,z], i) => {
        const felt = new THREE.MeshStandardMaterial({ color: tableColors[i], roughness: 0.85 });
        const top = new THREE.Mesh(new THREE.CylinderGeometry(1.4, 1.4, 0.06, 24), felt);
        top.position.set(x, 0.78, z); top.castShadow = true; scene.add(top);
        const legMat = new THREE.MeshStandardMaterial({ color: 0x1a0830, metalness: 0.6, roughness: 0.4 });
        const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.1, 0.75, 8), legMat);
        leg.position.set(x, 0.38, z); scene.add(leg);
        // Glow rim
        const rimMat = new THREE.MeshStandardMaterial({ color: tableColors[i], emissive: tableColors[i], emissiveIntensity: 0.6, transparent: true, opacity: 0.5, side: THREE.DoubleSide });
        const rim = new THREE.Mesh(new THREE.RingGeometry(1.35, 1.42, 32), rimMat);
        rim.rotation.x = -Math.PI/2;
        rim.position.set(x, 0.82, z); scene.add(rim);
        const tpl = new THREE.PointLight(tableColors[i], 0.5, 4);
        tpl.position.set(x, 1.5, z); scene.add(tpl);
    });
}

function buildParticles() {
    // Card-like particles floating
    const geo = new THREE.BufferGeometry();
    const cnt = 80;
    const pos = new Float32Array(cnt * 3);
    for (let i = 0; i < cnt; i++) {
        pos[i*3]   = Math.random() * GRID;
        pos[i*3+1] = Math.random() * 3.5 + 0.3;
        pos[i*3+2] = Math.random() * GRID;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const pMat = new THREE.PointsMaterial({ color: 0x9b30ff, size: 0.08, transparent: true, opacity: 0.55, sizeAttenuation: true });
    const pts = new THREE.Points(geo, pMat);
    scene.add(pts);
    animObjects.push({ obj: pts, type: 'particles_drift', positions: pos, count: cnt, speed: 0.06 });
}

function normalizeGltf(gltf) {
    gltf.scene.traverse(o => {
        if (!o.isMesh) return;
        const m = o.material;
        if (!m) return;
        if (m.isMeshPhysicalMaterial) {
            const s = new THREE.MeshStandardMaterial({
                color: m.color, map: m.map, normalMap: m.normalMap,
                roughnessMap: m.roughnessMap, metalnessMap: m.metalnessMap,
                emissiveMap: m.emissiveMap, emissive: m.emissive,
                roughness: m.roughness ?? 0.7, metalness: m.metalness ?? 0.0,
                transparent: m.transparent, opacity: m.opacity, side: m.side
            });
            o.material = s;
        }
        ['map','emissiveMap'].forEach(k => { if (m[k]) m[k].colorSpace = THREE.SRGBColorSpace; });
        o.castShadow = o.receiveShadow = true;
    });
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
    let mixer = null;
    const actions = {};
    if (gltf.animations.length > 0) {
        mixer = new THREE.AnimationMixer(model);
        gltf.animations.forEach(clip => { actions[clip.name] = mixer.clipAction(clip); });
        const idleClip = gltf.animations.find(c => {
            const n = c.name.toLowerCase();
            return n.includes('idle') || n.includes('stand') || n.includes('t-pose') || n.includes('tpose');
        }) || gltf.animations[0];
        actions[idleClip.name].setLoop(THREE.LoopRepeat, Infinity).play();
    }
    return { wrapper, mixer, actions };
}


function makeNameLabel(name, color) {
    const c = document.createElement('canvas');
    c.width = 256; c.height = 52;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 256, 52);
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.fill();
    ctx.strokeStyle = color; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 13px Orbitron, monospace';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(name, 128, 26);
    const tex = new THREE.CanvasTexture(c);
    const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
    const sprite = new THREE.Sprite(mat);
    sprite.scale.set(2.4, 0.45, 1);
    return sprite;
}

function makeGlowDisc(color) {
    const mat = new THREE.MeshBasicMaterial({ color: new THREE.Color(color), transparent: true, opacity: 0.22, depthWrite: false, side: THREE.DoubleSide });
    const disc = new THREE.Mesh(new THREE.CircleGeometry(0.7, 24), mat);
    disc.rotation.x = -Math.PI/2;
    return disc;
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
            console.warn('NPC GLB load fail:', npc.glb, e);
            const col = parseInt(npc.color.replace('#',''), 16);
            obj = new THREE.Mesh(new THREE.CapsuleGeometry(0.35, 1.1, 4, 8),
                new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.35, roughness: 0.6 }));
            obj.position.set(wp0[0], 0, wp0[2]);
            scene.add(obj);
        }

        // Shadow glow disc
        const disc = makeGlowDisc(npc.color);
        disc.position.set(wp0[0], 0.02, wp0[2]);
        scene.add(disc);

        // Floating name label
        const label = makeNameLabel(npc.name, npc.color);
        label.position.set(0, 2.5, 0);
        obj.add(label);

        // Interaction ring (initially hidden)
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
        const gltf  = await loader.loadAsync(HERO_MODEL);
        normalizeGltf(gltf);
        const model = gltf.scene;
        const rawBox = new THREE.Box3().setFromObject(model);
        const rawH   = rawBox.max.y - rawBox.min.y;
        model.scale.setScalar(1.8 / Math.max(rawH, 0.001));
        const scaledBox = new THREE.Box3().setFromObject(model);
        model.position.y = -scaledBox.min.y;
        heroMesh = new THREE.Group();
        heroMesh.add(model);
        heroMesh.position.copy(heroPos);
        scene.add(heroMesh);
        const lbl = makeNameLabel(PLAYER_NAME, '#00e8ff');
        lbl.position.set(0, 2.5, 0);
        heroMesh.add(lbl);
        try {
            _charCtrl.setupAnimations(gltf, model);
        } catch (e) { console.warn('[hero] animation setup:', e); }
    } catch(e) {
        console.warn('Hero GLB fail:', e);
        heroMesh = new THREE.Mesh(
            new THREE.CapsuleGeometry(0.35, 1.1, 4, 8),
            new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.35, roughness: 0.6 })
        );
        heroMesh.position.copy(heroPos);
        scene.add(heroMesh);
    }
}

function tick() {
    requestAnimationFrame(tick);
    const dt = Math.min(clock.getDelta(), 0.05);
    _t += dt;
    updateHero(dt);
    updateNPCs(dt);
    updateAnimObjects(dt);
    if (nexusRt) nexusRt.update(dt);
    composer.render();
}

function updateHero(dt) {
    if (!heroMesh) return;
    const { vx, vz } = _charCtrl.update(dt, heroMesh);
    heroMesh.position.x = Math.max(0.8, Math.min(GRID-0.8, heroMesh.position.x + vx * dt));
    heroMesh.position.z = Math.max(0.8, Math.min(GRID-0.8, heroMesh.position.z + vz * dt));
    heroPos.copy(heroMesh.position);
    controls.target.lerp(heroPos, 0.08);
    controls.update();
}

function updateNPCs(dt) {
    let nearestIdx = -1, nearestDist = Infinity;
    npcObjects.forEach((no, i) => {
        const wps = no.npc.waypoints;
        const target = new THREE.Vector3(wps[no.wpIdx][0], 0, wps[no.wpIdx][2]);
        const diff = target.clone().sub(new THREE.Vector3(no.obj.position.x, 0, no.obj.position.z));
        const dist = diff.length();
        if (dist < 0.12) {
            no.wpIdx = (no.wpIdx + 1) % wps.length;
        } else {
            const step = diff.normalize().multiplyScalar(0.75 * dt);
            no.obj.position.x += step.x;
            no.obj.position.z += step.z;
            no.obj.rotation.y = Math.atan2(step.x, step.z);
            if (no.disc) { no.disc.position.x = no.obj.position.x; no.disc.position.z = no.obj.position.z; }
            if (no.ring) { no.ring.position.x = no.obj.position.x; no.ring.position.z = no.obj.position.z; }
        }
        if (no.mixer) no.mixer.update(dt);
        // Proximity
        const d2h = new THREE.Vector3(no.obj.position.x, 0, no.obj.position.z).distanceTo(heroPos);
        if (d2h < nearestDist) { nearestDist = d2h; nearestIdx = i; }
        const near = d2h < INTERACT_DIST;
        if (near !== no.isNear) {
            no.isNear = near;
            no.ringMat.opacity = near ? 0.65 : 0.0;
        }
        if (near) {
            no.ringMat.opacity = 0.5 + 0.3 * Math.sin(_t * 4);
        }
    });
    activeNpcIdx = (nearestDist < INTERACT_DIST) ? nearestIdx : -1;
    const hint = document.getElementById('hint');
    if (activeNpcIdx >= 0) {
        hint.textContent = `[ E ] INTERACT WITH ${npcObjects[activeNpcIdx].npc.name}`;
        hint.className = 'active';
    } else {
        hint.textContent = 'APPROACH A DEALER — PRESS E TO INTERACT';
        hint.className = '';
    }
}

function updateAnimObjects(dt) {
    animObjects.forEach(ao => {
        if (!ao.obj) return;
        switch (ao.type) {
            case 'pulse':
                ao.obj.intensity = ao.base + Math.sin(_t * ao.speed * Math.PI * 2) * ao.range; break;
            case 'om':
                ao.obj.opacity = ao.base + Math.sin(_t * ao.speed * Math.PI * 2) * ao.range; break;
            case 'rotate_y':
                ao.obj.rotation.y += ao.speed * dt; break;
            case 'float':
                ao.obj.position.y = ao.base + Math.sin(_t * ao.speed * Math.PI * 2) * ao.range; break;
            case 'emissive_flicker':
                ao.obj.emissiveIntensity = ao.base + Math.random() * ao.range; break;
            case 'disco_orbit':
                ao.obj.position.x = ao.cx + Math.cos(_t * ao.speed * Math.PI * 2 + ao.phase) * ao.radius;
                ao.obj.position.z = ao.cz + Math.sin(_t * ao.speed * Math.PI * 2 + ao.phase) * ao.radius;
                ao.obj.position.y = 1.8 + Math.sin(_t * 0.4 + ao.phase) * 0.4;
                break;
            case 'particles_drift': {
                const p = ao.positions;
                for (let i = 0; i < ao.count; i++) {
                    p[i*3+1] += ao.speed * dt;
                    if (p[i*3+1] > 3.8) { p[i*3+1] = 0.1; p[i*3] = Math.random()*GRID; p[i*3+2] = Math.random()*GRID; }
                }
                ao.obj.geometry.attributes.position.needsUpdate = true;
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
    pointer.set(
        ((e.clientX - rect.left) / rect.width) * 2 - 1,
        -((e.clientY - rect.top) / rect.height) * 2 + 1
    );
    raycaster.setFromCamera(pointer, camera);
    const objs = npcObjects.map(n => n.obj);
    const hits = raycaster.intersectObjects(objs, true);
    if (hits.length) {
        let root = hits[0].object;
        while (root.parent && !npcObjects.find(n => n.obj === root)) root = root.parent;
        const no = npcObjects.find(n => n.obj === root);
        if (no) openNpcModal(no.npc);
    }
}

function openNpcModal(npc) {
    if (_typingInterval) clearInterval(_typingInterval);
    document.getElementById('npc-name').textContent = npc.name;
    document.getElementById('npc-title').textContent = npc.title;
    document.getElementById('npc-title').style.color = npc.color;
    const img = document.getElementById('npc-avatar-img');
    const ph = document.getElementById('npc-placeholder');
    img.style.display = 'block'; ph.style.display = 'none';
    img.src = npc.thumb || '';
    const textEl = document.getElementById('npc-text');
    textEl.textContent = '';
    const cursor = document.createElement('span');
    cursor.className = 'npc-cursor';
    textEl.appendChild(cursor);
    const txt = npc.dialogue || '';
    let ci = 0;
    _typingInterval = setInterval(() => {
        if (ci < txt.length) { cursor.before(txt[ci++]); } else { clearInterval(_typingInterval); }
    }, 20);
    const btn = document.getElementById('npc-enter-btn');
    const lbl = document.getElementById('npc-enter-lbl');
    if (npc.coming_soon) {
        btn.classList.add('soon');
        btn.removeAttribute('href');
        lbl.textContent = 'COMING SOON';
        btn.querySelector('span').textContent = '🔒';
    } else {
        btn.classList.remove('soon');
        btn.href = npc.game_url;
        lbl.textContent = npc.game_label;
        btn.querySelector('span').textContent = '🎲';
    }
    document.getElementById('npc-modal').classList.add('open');
}

window.closeNpcModal = function() {
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval = null; }
    document.getElementById('npc-modal').classList.remove('open');
};
</script>
</body>
</html>

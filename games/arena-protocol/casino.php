<?php
/**
 * FATE CASINO — District Room
 * Isometric neon casino with trickster NPC dealers. Click to play.
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
        'title'     => 'GOD OF MISCHIEF · LEGENDARY CONTROLLER',
        'color'     => '#9b30ff',
        'thumb'     => '/assets/avatars/thumbs/corrupted-loki.png',
        'waypoints' => [[5,0,5],[5,0,15],[12,0,10],[5,0,5]],
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
        'thumb'     => '/assets/avatars/thumbs/long-john-silver.png',
        'waypoints' => [[16,0,5],[10,0,5],[10,0,15],[16,0,15]],
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
        'thumb'     => '/assets/avatars/thumbs/dracula.png',
        'waypoints' => [[4,0,16],[14,0,16],[14,0,8],[8,0,12]],
        'dialogue'  => "Come in. I insist. I've waited centuries for someone interesting to walk through these doors. The Death Roll is exquisite — each throw of the dice could be your last... financially speaking. I've watched empires fall on a single roll. Tonight, we see what you're made of. Blood type: winner or loser?",
        'game_url'  => '/death-roll-game.php',
        'game_label'=> 'QUICK ROLL',
        'coming_soon' => false,
    ],
    [
        'id'        => 'medusa',
        'name'      => 'MEDUSA',
        'title'     => 'GORGON QUEEN · LEGENDARY CONTROLLER',
        'color'     => '#00ff88',
        'thumb'     => '/assets/avatars/thumbs/medusa.png',
        'waypoints' => [[10,0,10],[16,0,14],[12,0,16],[6,0,8]],
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
<title>FATE CASINO — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#060208;font-family:"Share Tech Mono",monospace;color:#e0c0f0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.03) 3px,rgba(0,0,0,.03) 4px)}
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%)}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#f0d0ff}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0%);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(6,2,8,.97);border-bottom:1px solid rgba(155,48,255,.07);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#9b30ff 35%,#ff3d56 50%,#9b30ff 65%,transparent 98%);opacity:.22}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(155,48,255,.2);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(155,48,255,.7);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(155,48,255,.5);color:#c06aff;background:rgba(155,48,255,.08)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(155,48,255,.35);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(255,61,86,.08);border:1px solid rgba(255,61,86,.25);color:#ff3d56}
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#060208}
#cv canvas{width:100%!important;height:100%!important}
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.75);backdrop-filter:blur(12px);display:none;align-items:flex-end;justify-content:center;padding:0 0 32px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(10,4,18,.99),rgba(6,2,12,.99));border:1px solid rgba(155,48,255,.22);border-radius:12px;overflow:hidden;box-shadow:0 0 80px rgba(155,48,255,.12),0 0 40px rgba(0,0,0,.7);animation:panelUp .35s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:14px;padding:16px 20px 14px;border-bottom:1px solid rgba(155,48,255,.1);position:relative}
.npc-avatar-frame{width:56px;height:56px;border-radius:8px;border:2px solid rgba(155,48,255,.3);overflow:hidden;flex-shrink:0;background:rgba(155,48,255,.06);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-placeholder{font-size:24px;opacity:.4}
.npc-info{flex:1;min-width:0}
.npc-name{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:900;letter-spacing:.08em;color:#fff;line-height:1.2}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:3px}
.npc-close{position:absolute;right:14px;top:14px;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,.3);transition:all .2s}
.npc-close:hover{background:rgba(255,61,86,.1);border-color:rgba(255,61,86,.3);color:#ff3d56}
.npc-dialogue{padding:18px 22px;min-height:96px}
.npc-text{font-size:12px;line-height:1.75;color:rgba(220,195,245,.9);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#9b30ff;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 18px;border-top:1px solid rgba(155,48,255,.08)}
.npc-btn-enter{flex:1;padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(155,48,255,.2),rgba(255,61,86,.1));border:1px solid rgba(155,48,255,.5);color:#c06aff;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 28px rgba(155,48,255,.25),0 0 14px rgba(155,48,255,.12);transform:translateY(-1px);color:#fff}
.npc-btn-enter.soon{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);color:rgba(200,170,230,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.15);color:rgba(200,170,230,.7)}
#hint{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.16em;color:rgba(155,48,255,.35);pointer-events:none;transition:opacity .4s}
#hint.fade{opacity:0}
#load{position:fixed;inset:0;z-index:8000;background:#060208;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:28px;font-weight:900;letter-spacing:.3em;color:#fff}.load-logo span{color:#9b30ff}
.load-sub{font-size:8px;letter-spacing:.35em;color:rgba(155,48,255,.4)}
.load-bar{width:220px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
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
<div id="hint">APPROACH A DEALER TO PLACE YOUR BETS</div>
<div id="npc-modal">
  <div class="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">🎲</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title">—</div>
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

const HERO_MODEL  = <?php echo $_heroJson; ?>;
const PLAYER_NAME = <?php echo $_playerJson; ?>;
const NPCS        = <?php echo $_npcsJson; ?>;
const GRID = 20;
const D_CAM = 13;

let scene, camera, renderer, composer;
let clock, mixer, heroMesh;
let npcObjects = [], animObjects = [];
let raycaster = new THREE.Raycaster(), pointer = new THREE.Vector2();
let _hintTimer = 0, _typingInterval = null, _t = 0;

window.addEventListener('DOMContentLoaded', boot);

async function boot() {
    clock = new THREE.Clock();
    setLoad(10);
    initRenderer(); setLoad(28);
    initCamera(); initScene(); setLoad(48);
    buildScene(); setLoad(68);
    spawnNPCs(); setLoad(82);
    if (HERO_MODEL) await spawnHero(); setLoad(100);
    setTimeout(() => {
        document.getElementById('load').classList.add('done');
        setTimeout(() => document.getElementById('load').remove(), 600);
        document.getElementById('crt').classList.add('on');
    }, 350);
    window.addEventListener('click', onCanvasClick);
    window.addEventListener('resize', onResize);
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
    renderer.toneMappingExposure = 1.38;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    wrap.appendChild(renderer.domElement);
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
}

function initCamera() {
    const wrap = document.getElementById('cv');
    const a = wrap.clientWidth / Math.max(1, wrap.clientHeight);
    camera = new THREE.OrthographicCamera(-D_CAM*a, D_CAM*a, D_CAM, -D_CAM, 0.1, 300);
    camera.position.set(28, 34, 28);
    camera.lookAt(GRID/2, 0, GRID/2);
}

function onResize() {
    const wrap = document.getElementById('cv');
    const a = wrap.clientWidth / Math.max(1, wrap.clientHeight);
    camera.left=-D_CAM*a; camera.right=D_CAM*a; camera.top=D_CAM; camera.bottom=-D_CAM;
    camera.updateProjectionMatrix();
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    if (composer) composer.setSize(wrap.clientWidth, wrap.clientHeight);
}

function initScene() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x060208);
    scene.fog = new THREE.FogExp2(0x080210, 0.016);
    const wrap = document.getElementById('cv');
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(wrap.clientWidth, wrap.clientHeight), 0.95, 0.52, 0.75));
}

function buildScene() {
    scene.add(new THREE.HemisphereLight(0x3a1050, 0x080210, 0.7));
    scene.add(new THREE.AmbientLight(0x18081a, 1.1));
    const sun = new THREE.DirectionalLight(0xd090ff, 1.1);
    sun.position.set(14, 28, 12); sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = sun.shadow.camera.bottom = -24;
    sun.shadow.camera.right = sun.shadow.camera.top = 24;
    sun.shadow.camera.far = 90;
    scene.add(sun);
    scene.add(Object.assign(new THREE.DirectionalLight(0xff3d56, 0.6), { position: new THREE.Vector3(-14, 8, -10) }));
    scene.add(Object.assign(new THREE.DirectionalLight(0xffd700, 0.3), { position: new THREE.Vector3(22, 12, 2) }));

    buildCasinoFloor();
    buildCasinoWalls();
    buildCasinoCeiling();
    buildGamingTables();
    buildParticles();
}

function buildCasinoFloor() {
    const mat = new THREE.MeshStandardMaterial({ color: 0x0c0414, roughness: 0.6, metalness: 0.3, emissive: 0x06010a, emissiveIntensity: 0.2 });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI/2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    const grid = new THREE.GridHelper(GRID, 20, 0x18083a, 0x10042a);
    grid.position.set(GRID/2, 0.005, GRID/2);
    scene.add(grid);

    // Neon floor strips
    const colors = [0x9b30ff, 0xff3d56, 0xffd700, 0x00e8ff];
    [4, 8, 12, 16].forEach((v, i) => {
        const c = colors[i];
        const sMat = new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 1.1, transparent: true, opacity: 0.28 });
        const h = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.022, 0.06), sMat.clone());
        h.position.set(GRID/2, 0.01, v); scene.add(h);
        animObjects.push({ obj: h.material, type: 'om', base: 0.22, range: 0.15, speed: 0.4 + i*0.1, mat: h.material });
        const vv = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.022, GRID), sMat.clone());
        vv.position.set(v, 0.01, GRID/2); scene.add(vv);
    });

    // Center: glowing roulette pattern
    const cMat = new THREE.MeshStandardMaterial({ color: 0x9b30ff, emissive: 0x9b30ff, emissiveIntensity: 0.8, transparent: true, opacity: 0.35, side: THREE.DoubleSide, depthWrite: false });
    [1.5, 3.0, 4.5].forEach(r => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(r, r+0.07, 40), cMat.clone());
        ring.rotation.x = -Math.PI/2;
        ring.position.set(GRID/2, 0.02, GRID/2);
        scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.25, range: 0.18, speed: 0.45 + r*0.06, mat: ring.material });
    });

    const cLight = new THREE.PointLight(0x9b30ff, 1.5, 6);
    cLight.position.set(GRID/2, 0.6, GRID/2);
    scene.add(cLight);
    animObjects.push({ obj: cLight, type: 'pulse', base: 1.2, range: 0.5, speed: 0.42 });
}

function buildCasinoWalls() {
    const wMat = new THREE.MeshStandardMaterial({ color: 0x080212, roughness: 0.55, metalness: 0.5, emissive: 0x06010f, emissiveIntensity: 0.35 });
    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 6), wMat.clone());
    bw.position.set(GRID/2, 3, 0); scene.add(bw);
    const lw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 6), wMat.clone());
    lw.position.set(0, 3, GRID/2); lw.rotation.y = Math.PI/2; scene.add(lw);

    // Neon wall signs
    const neonColors = [0x9b30ff, 0xff3d56, 0xffd700];
    const neonMat = (c) => new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 1.4, transparent: true, opacity: 0.6 });

    // Horizontal neon lines on back wall
    [1.0, 2.2, 3.4, 4.6].forEach((y, i) => {
        const c = neonColors[i % 3];
        const bar = new THREE.Mesh(new THREE.BoxGeometry(GRID-0.4, 0.02, 0.04), neonMat(c));
        bar.position.set(GRID/2, y, 0.02); scene.add(bar);
        const ptL = new THREE.PointLight(c, 0.7, 4);
        ptL.position.set(GRID/2, y, 0.3); scene.add(ptL);
        animObjects.push({ obj: ptL, type: 'pulse', base: 0.55, range: 0.3, speed: 0.6 + i*0.15 });
    });

    // Slot machine silhouettes on back wall
    [3, 8, 12, 17].forEach(x => {
        const sBody = new THREE.Mesh(new THREE.BoxGeometry(1.4, 2.2, 0.1),
            new THREE.MeshStandardMaterial({ color: 0x18082a, roughness: 0.4, metalness: 0.7 }));
        sBody.position.set(x, 1.2, 0.06); scene.add(sBody);
        const sScreen = new THREE.Mesh(new THREE.BoxGeometry(1.0, 1.1, 0.04),
            new THREE.MeshStandardMaterial({ color: 0x00020a, emissive: 0x2200aa, emissiveIntensity: 1.0 }));
        sScreen.position.set(x, 1.4, 0.12); scene.add(sScreen);
        const sL = new THREE.PointLight(0x5500ff, 0.6, 3);
        sL.position.set(x, 1.4, 0.5); scene.add(sL);
        animObjects.push({ obj: sL, type: 'pulse', base: 0.4, range: 0.3, speed: 2 + x*0.05 });
    });

    // Baseboard strips
    const gMat = new THREE.MeshStandardMaterial({ color: 0x9b30ff, emissive: 0x9b30ff, emissiveIntensity: 1.1, transparent: true, opacity: 0.5 });
    scene.add(Object.assign(new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.05, 0.04), gMat), { position: new THREE.Vector3(GRID/2, 0.025, 0.02) }));
    scene.add(Object.assign(new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.05, GRID), gMat.clone()), { position: new THREE.Vector3(0.02, 0.025, GRID/2) }));
}

function buildCasinoCeiling() {
    const cMat = new THREE.MeshStandardMaterial({ color: 0x050010, roughness: 0.5, metalness: 0.5 });
    const ceil = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), cMat);
    ceil.rotation.x = Math.PI/2;
    ceil.position.set(GRID/2, 6, GRID/2);
    scene.add(ceil);

    // Disco ball (center)
    const dMat = new THREE.MeshStandardMaterial({ color: 0xaaaaaa, metalness: 0.98, roughness: 0.02, emissive: 0x444444, emissiveIntensity: 0.3 });
    const disco = new THREE.Mesh(new THREE.SphereGeometry(0.55, 12, 10), dMat);
    disco.position.set(GRID/2, 5.5, GRID/2);
    scene.add(disco);
    animObjects.push({ obj: disco, type: 'rotate_y', speed: 0.4 });

    const spotColors = [0xff3d56, 0x9b30ff, 0xffd700, 0x00e8ff];
    spotColors.forEach((c, i) => {
        const sl = new THREE.PointLight(c, 2.5, 12);
        const a = (i / spotColors.length) * Math.PI * 2;
        sl.position.set(GRID/2 + Math.cos(a)*4, 4.5, GRID/2 + Math.sin(a)*4);
        scene.add(sl);
        animObjects.push({ obj: sl, type: 'disco_orbit', cx: GRID/2, cz: GRID/2, r: 4, baseA: a, speed: 0.28, light: sl });
    });

    // Hanging chandeliers
    [[6,6],[14,6],[6,14],[14,14]].forEach(([cx,cz], i) => {
        const chainMat = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.5, metalness: 0.9 });
        const chain = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 1.2, 6), chainMat);
        chain.position.set(cx, 5.6, cz); scene.add(chain);
        const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.3, 0.08, 12), chainMat.clone());
        disc.position.set(cx, 5.0, cz); scene.add(disc);
        const cl = new THREE.PointLight(0xffd090, 1.4, 7);
        cl.position.set(cx, 4.85, cz); scene.add(cl);
        animObjects.push({ obj: cl, type: 'pulse', base: 1.1, range: 0.4, speed: 0.6 + i*0.1 });
    });
}

function buildGamingTables() {
    // Roulette tables
    [[6,7],[14,7],[6,13],[14,13]].forEach(([x,z], i) => {
        const tMat = new THREE.MeshStandardMaterial({ color: 0x0a2810, roughness: 0.5, metalness: 0.2 });
        const table = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.1, 1.4), tMat);
        table.position.set(x, 0.82, z); table.castShadow = true; scene.add(table);
        // Legs
        const lMat = new THREE.MeshStandardMaterial({ color: 0x1a1008, metalness: 0.9, roughness: 0.1 });
        [-1,1].forEach(dx => [-1,1].forEach(dz => {
            const leg = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.82, 0.06), lMat);
            leg.position.set(x+dx*0.95, 0.41, z+dz*0.6); scene.add(leg);
        }));
        // Table felt
        const felt = new THREE.Mesh(new THREE.BoxGeometry(2.1, 0.02, 1.3),
            new THREE.MeshStandardMaterial({ color: 0x1a5c28, roughness: 0.9 }));
        felt.position.set(x, 0.88, z); scene.add(felt);
        // Table glow
        const tLight = new THREE.PointLight(i%2===0 ? 0x9b30ff : 0xff3d56, 0.6, 3.5);
        tLight.position.set(x, 1.2, z); scene.add(tLight);
        animObjects.push({ obj: tLight, type: 'pulse', base: 0.45, range: 0.25, speed: 0.7 + i*0.2 });
    });

    // Center stage — skull dice
    buildSkullDice(GRID/2, GRID/2);
}

function buildSkullDice(x, z) {
    const diceMat = new THREE.MeshStandardMaterial({ color: 0x1a0a22, metalness: 0.7, roughness: 0.25, emissive: 0x9b30ff, emissiveIntensity: 0.4 });
    const dice1 = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.65, 0.65), diceMat);
    dice1.position.set(x - 0.4, 0.55, z - 0.4);
    dice1.castShadow = true; scene.add(dice1);
    animObjects.push({ obj: dice1, type: 'rotate_y', speed: 0.35 });
    animObjects.push({ obj: dice1, type: 'float', base: 0.55, range: 0.12, speed: 0.65 });

    const dice2 = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.55, 0.55), diceMat.clone());
    dice2.position.set(x + 0.4, 0.45, z + 0.4);
    dice2.castShadow = true; scene.add(dice2);
    animObjects.push({ obj: dice2, type: 'rotate_y', speed: -0.5 });
    animObjects.push({ obj: dice2, type: 'float', base: 0.45, range: 0.1, speed: 0.85 });

    const glow = new THREE.PointLight(0x9b30ff, 2.0, 5);
    glow.position.set(x, 0.8, z); scene.add(glow);
    animObjects.push({ obj: glow, type: 'pulse', base: 1.6, range: 0.7, speed: 0.55 });
}

function buildParticles() {
    const count = 250;
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    const palHex = [0x9b30ff, 0xff3d56, 0xffd700];
    for (let i = 0; i < count; i++) {
        pos[i*3]=Math.random()*GRID; pos[i*3+1]=Math.random()*5.5; pos[i*3+2]=Math.random()*GRID;
        const c = new THREE.Color(palHex[Math.floor(Math.random()*3)]);
        colors[i*3]=c.r; colors[i*3+1]=c.g; colors[i*3+2]=c.b;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    geo.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    const pts = new THREE.Points(geo, new THREE.PointsMaterial({ size: 0.06, transparent: true, opacity: 0.55, vertexColors: true, sizeAttenuation: true }));
    scene.add(pts);
    animObjects.push({ obj: pts, type: 'particles_drift', speed: 0.1 });
}

function normalizeGltf(root) {
    root.traverse(c => {
        if (c.isMesh) {
            if (c.material?.isMeshPhysicalMaterial) {
                const m = c.material;
                c.material = new THREE.MeshStandardMaterial({ map: m.map, color: m.color, roughness: m.roughness??0.7, metalness: m.metalness??0.3, emissive: m.emissive??new THREE.Color(0), emissiveIntensity: m.emissiveIntensity??1, transparent: m.transparent, opacity: m.opacity });
            }
            if (c.material?.map) c.material.map.colorSpace = THREE.SRGBColorSpace;
            c.castShadow = c.receiveShadow = true;
        }
    });
}

async function spawnHero() {
    return new Promise(resolve => {
        new GLTFLoader().load(HERO_MODEL, gltf => {
            normalizeGltf(gltf.scene);
            heroMesh = gltf.scene;
            heroMesh.position.set(GRID/2, 0, GRID/2);
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

function makeNpcSprite(npc) {
    const SIZE = 256;
    const cv = document.createElement('canvas');
    cv.width = SIZE; cv.height = SIZE * 1.6;
    const ctx = cv.getContext('2d');
    const colorMap = {
        '#9b30ff': ['rgba(16,4,26,.93)','rgba(155,48,255,.24)','#9b30ff'],
        '#ffd700': ['rgba(28,18,0,.93)','rgba(255,215,0,.22)','#ffd700'],
        '#ff3d56': ['rgba(22,4,8,.93)','rgba(255,61,86,.22)','#ff3d56'],
        '#00ff88': ['rgba(0,18,10,.93)','rgba(0,255,136,.2)','#00ff88'],
    };
    const [bg, border, accent] = colorMap[npc.color] ?? colorMap['#9b30ff'];
    ctx.fillStyle = bg; ctx.roundRect(4,4,SIZE-8,SIZE*1.6-8,14); ctx.fill();
    ctx.strokeStyle = border; ctx.lineWidth = 3; ctx.roundRect(4,4,SIZE-8,SIZE*1.6-8,14); ctx.stroke();
    const g = ctx.createLinearGradient(0,0,0,80);
    g.addColorStop(0, accent+'55'); g.addColorStop(1,'transparent');
    ctx.fillStyle = g; ctx.roundRect(4,4,SIZE-8,80,[14,14,0,0]); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.font = `bold 20px Orbitron,monospace`; ctx.textAlign='center';
    ctx.fillText(npc.name.split(' ')[0].slice(0,10), SIZE/2, SIZE*1.6-40);
    ctx.fillStyle = accent; ctx.font = `11px "Share Tech Mono",monospace`;
    ctx.fillText('▶ PLAY', SIZE/2, SIZE*1.6-20);
    const tex = new THREE.CanvasTexture(cv); tex.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false }));
    sprite.scale.set(1.05, 1.68, 1);
    const img = new Image(); img.crossOrigin = 'anonymous';
    img.onload = () => { ctx.drawImage(img, 20, 10, SIZE-40, SIZE*0.85); tex.needsUpdate = true; };
    img.src = npc.thumb;
    return sprite;
}

function spawnNPCs() {
    NPCS.forEach(npc => {
        const sprite = makeNpcSprite(npc);
        sprite.position.set(npc.waypoints[0][0], 1.65, npc.waypoints[0][2]);
        sprite.userData.npc = npc;
        scene.add(sprite);
        const disc = new THREE.Mesh(new THREE.CircleGeometry(0.34,16), new THREE.MeshStandardMaterial({color:0,transparent:true,opacity:0.3,depthWrite:false}));
        disc.rotation.x=-Math.PI/2; disc.position.set(npc.waypoints[0][0],0.01,npc.waypoints[0][2]); scene.add(disc);
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.36,0.48,26), new THREE.MeshStandardMaterial({color:npc.color,emissive:npc.color,emissiveIntensity:1.2,transparent:true,opacity:0.55,depthWrite:false,side:THREE.DoubleSide}));
        ring.rotation.x=-Math.PI/2; ring.position.set(npc.waypoints[0][0],0.015,npc.waypoints[0][2]); scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.4, range: 0.25, speed: 0.75+Math.random()*0.3, mat: ring.material });
        npcObjects.push({ sprite, disc, ring, npc, waypointIdx: 0, t: 0, speed: 0.5+Math.random()*0.3, bob: Math.random()*Math.PI*2 });
    });
}

function onCanvasClick(e) {
    const wrap = document.getElementById('cv');
    const rect = wrap.getBoundingClientRect();
    pointer.x = ((e.clientX-rect.left)/rect.width)*2-1;
    pointer.y = -((e.clientY-rect.top)/rect.height)*2+1;
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(npcObjects.map(n=>n.sprite), false);
    if (!hits.length) return;
    const hit = npcObjects.find(n=>n.sprite===hits[0].object);
    if (hit) openNpcModal(hit.npc);
}

window.closeNpcModal = function() {
    document.getElementById('npc-modal').classList.remove('open');
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval=null; }
};

function openNpcModal(npc) {
    document.getElementById('npc-name').textContent = npc.name;
    document.getElementById('npc-title').textContent = npc.title;
    document.getElementById('npc-title').style.color = npc.color;
    const img = document.getElementById('npc-avatar-img');
    img.src = npc.thumb; img.style.display = 'block';
    document.getElementById('npc-placeholder').style.display = 'none';
    const btn = document.getElementById('npc-enter-btn');
    const lbl = document.getElementById('npc-enter-lbl');
    if (npc.coming_soon || !npc.game_url) {
        btn.classList.add('soon'); btn.removeAttribute('href'); lbl.textContent = 'COMING SOON';
    } else {
        btn.classList.remove('soon'); btn.href = npc.game_url; lbl.textContent = npc.game_label??'PLAY';
    }
    const textEl = document.getElementById('npc-text');
    textEl.innerHTML = '';
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval=null; }
    document.getElementById('npc-modal').classList.add('open');
    let i=0;
    const cursor = document.createElement('span');
    cursor.className = 'npc-cursor';
    cursor.style.background = npc.color;
    textEl.appendChild(cursor);
    _typingInterval = setInterval(() => {
        if (i < npc.dialogue.length) { textEl.insertBefore(document.createTextNode(npc.dialogue[i]), cursor); i++; }
        else { cursor.remove(); clearInterval(_typingInterval); _typingInterval=null; }
    }, 22);
}

function tick() {
    requestAnimationFrame(tick);
    const dt = Math.min(clock.getDelta(), 0.05);
    _t += dt;
    if (mixer) mixer.update(dt);
    updateNPCs(dt);
    updateAnimObjects(_t, dt);
    _hintTimer += dt;
    if (_hintTimer > 5) document.getElementById('hint')?.classList.add('fade');
    if (composer) composer.render();
    else renderer.render(scene, camera);
}

function updateNPCs(dt) {
    npcObjects.forEach(no => {
        const wp = no.npc.waypoints;
        const from = wp[no.waypointIdx];
        const next = (no.waypointIdx+1) % wp.length;
        const to = wp[next];
        no.t += dt * no.speed;
        const dx = to[0]-from[0], dz = to[2]-from[2];
        if (no.t >= 1.0 || Math.sqrt(dx*dx+dz*dz)<0.01) { no.waypointIdx=next; no.t=0; }
        else {
            no.bob += dt * 2.1;
            const px=from[0]+dx*no.t, pz=from[2]+dz*no.t;
            const bob=Math.sin(no.bob)*0.06;
            no.sprite.position.set(px, 1.65+bob, pz);
            no.disc.position.set(px, 0.01, pz);
            no.ring.position.set(px, 0.015, pz);
        }
    });
}

function updateAnimObjects(t, dt) {
    animObjects.forEach(ao => {
        if (!ao.obj) return;
        switch (ao.type) {
            case 'pulse': ao.obj.intensity = ao.base + Math.sin(t*ao.speed)*ao.range; break;
            case 'om': if (ao.mat) ao.mat.opacity = ao.base + Math.sin(t*ao.speed)*ao.range; break;
            case 'rotate_y': ao.obj.rotation.y += dt*ao.speed; break;
            case 'float': ao.obj.position.y = ao.base + Math.sin(t*ao.speed)*ao.range; break;
            case 'disco_orbit':
                ao.light.position.x = ao.cx + Math.cos(ao.baseA + t*ao.speed) * ao.r;
                ao.light.position.z = ao.cz + Math.sin(ao.baseA + t*ao.speed) * ao.r;
                break;
            case 'particles_drift':
                const pos = ao.obj.geometry.attributes.position;
                for (let i=0; i<pos.count; i++) {
                    pos.array[i*3+1] += dt*ao.speed*(0.5+Math.sin(t+i)*0.5);
                    if (pos.array[i*3+1]>5.5) pos.array[i*3+1]=0;
                }
                pos.needsUpdate = true; break;
        }
    });
}
</script>
</body>
</html>

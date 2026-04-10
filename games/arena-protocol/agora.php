<?php
/**
 * SOCIAL AGORA — District Room
 * Isometric open marketplace / social hub. NPCs invite players to upcoming social games.
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
        'id'        => 'cleopatra',
        'name'      => 'CLEOPATRA',
        'title'     => 'QUEEN OF THE NILE · EPIC STRATEGIST',
        'color'     => '#00ff88',
        'thumb'     => '/assets/avatars/thumbs/cleopatra.png',
        'waypoints' => [[5,0,5],[5,0,15],[15,0,10],[8,0,5]],
        'dialogue'  => "Welcome to the Agora — the heart of civilization, where minds meet and alliances are forged. I ruled one of history's greatest empires through diplomacy and intellect. The Social Hub opens soon — a place where warriors become communities. Come back when the doors open. I shall be here, holding court.",
        'game_url'  => null,
        'game_label'=> 'COMING SOON',
        'coming_soon' => true,
    ],
    [
        'id'        => 'socrates',
        'name'      => 'SOCRATES',
        'title'     => 'THE GADFLY · LEGENDARY STRATEGIST',
        'color'     => '#00e8ff',
        'thumb'     => '/assets/avatars/thumbs/socrates.png',
        'waypoints' => [[16,0,6],[10,0,10],[4,0,14],[10,0,6]],
        'dialogue'  => "I know that I know nothing — but I know this place will soon be alive with conversation. The Agora was where Athens breathed. Philosophers, traders, warriors — all equal beneath the open sky. Our Social Hub is being built on those same foundations. What do you wish to know while you wait?",
        'game_url'  => null,
        'game_label'=> 'COMING SOON',
        'coming_soon' => true,
    ],
    [
        'id'        => 'caesar',
        'name'      => 'JULIUS CAESAR',
        'title'     => 'ROMAN GENERAL · EPIC TANK',
        'color'     => '#ffd700',
        'thumb'     => '/assets/avatars/thumbs/julius-caesar.png',
        'waypoints' => [[4,0,16],[15,0,16],[15,0,5],[8,0,12]],
        'dialogue'  => "Veni, vidi, vici — I came, I saw, I conquered. But conquest without community is hollow. Rome was not built by one man. The Social Agora is where alliances form, where guilds rise, where legends begin. When this district opens fully, come back ready. A new Rome is being assembled.",
        'game_url'  => null,
        'game_label'=> 'COMING SOON',
        'coming_soon' => true,
    ],
    [
        'id'        => 'marco-polo',
        'name'      => 'MARCO POLO',
        'title'     => 'EXPLORER · SPECIAL STRATEGIST',
        'color'     => '#ff9800',
        'thumb'     => '/assets/avatars/thumbs/marco-polo.png',
        'waypoints' => [[12,0,15],[12,0,5],[6,0,10],[16,0,12]],
        'dialogue'  => "I traveled 24,000 miles to discover what lay beyond the horizon. Every market I entered — Constantinople, Cathay, Persia — told me the same thing: people want to connect. The Social Agora is the next frontier. Trading stories, forming crews, building something together. The journey begins... soon.",
        'game_url'  => null,
        'game_label'=> 'COMING SOON',
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
html,body{width:100%;height:100%;overflow:hidden;background:#020a06;font-family:"Share Tech Mono",monospace;color:#c0f0d0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.025) 3px,rgba(0,0,0,.025) 4px)}
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50%)}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#d0ffd8}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0%);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(2,10,4,.97);border-bottom:1px solid rgba(0,255,136,.06);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#00ff88 35%,#00e8ff 50%,#00ff88 65%,transparent 98%);opacity:.18}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(0,255,136,.15);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(0,255,136,.6);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(0,255,136,.4);color:#00ff88;background:rgba(0,255,136,.06)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(0,255,136,.3);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(0,255,136,.06);border:1px solid rgba(0,255,136,.2);color:#00ff88}
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#020a06}
#cv canvas{width:100%!important;height:100%!important}
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.72);backdrop-filter:blur(10px);display:none;align-items:flex-end;justify-content:center;padding:0 0 32px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(2,12,6,.98),rgba(2,8,4,.99));border:1px solid rgba(0,255,136,.16);border-radius:12px;overflow:hidden;box-shadow:0 0 80px rgba(0,255,136,.08),0 0 40px rgba(0,0,0,.6);animation:panelUp .35s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:14px;padding:16px 20px 14px;border-bottom:1px solid rgba(0,255,136,.07);position:relative}
.npc-avatar-frame{width:56px;height:56px;border-radius:8px;border:2px solid rgba(0,255,136,.25);overflow:hidden;flex-shrink:0;background:rgba(0,255,136,.04);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-placeholder{font-size:24px;opacity:.4}
.npc-info{flex:1}
.npc-name{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:900;letter-spacing:.08em;color:#fff;line-height:1.2}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:3px}
.npc-close{position:absolute;right:14px;top:14px;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,.3);transition:all .2s}
.npc-close:hover{background:rgba(255,61,86,.1);border-color:rgba(255,61,86,.3);color:#ff3d56}
.npc-dialogue{padding:18px 22px;min-height:96px}
.npc-text{font-size:12px;line-height:1.75;color:rgba(192,240,208,.9);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#00ff88;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 18px;border-top:1px solid rgba(0,255,136,.06)}
.npc-btn-enter{flex:1;padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(0,255,136,.15),rgba(0,232,255,.08));border:1px solid rgba(0,255,136,.35);color:#00ff88;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 28px rgba(0,255,136,.18);transform:translateY(-1px);color:#fff}
.npc-btn-enter.soon{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);color:rgba(192,240,208,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.15);color:rgba(192,240,208,.7)}
.cs-tag{display:inline-block;padding:2px 7px;border-radius:3px;font-size:7px;letter-spacing:.12em;background:rgba(255,152,0,.08);border:1px solid rgba(255,152,0,.2);color:#ff9800;margin-left:8px;vertical-align:middle}
#hint{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.16em;color:rgba(0,255,136,.28);pointer-events:none;transition:opacity .4s}
#hint.fade{opacity:0}
#load{position:fixed;inset:0;z-index:8000;background:#020a06;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:28px;font-weight:900;letter-spacing:.3em;color:#fff}.load-logo span{color:#00ff88}
.load-sub{font-size:8px;letter-spacing:.35em;color:rgba(0,255,136,.35)}
.load-bar{width:220px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
.load-fill{height:100%;background:linear-gradient(90deg,#00ff88,#00e8ff);border-radius:1px;width:0%;transition:width .4s ease}
</style>
</head>
<body>
<div id="crt"></div>
<div id="load">
  <div class="load-logo">SOCIAL <span>AGORA</span></div>
  <div class="load-sub">OPENING THE MARKETPLACE OF MINDS</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">SOCIAL AGORA</span>
  <span id="tb-sub">DISTRICT · COMMUNITY HUB</span>
  <span class="tb-badge">SOON</span>
</header>
<div id="cv"></div>
<div id="hint">MEET THE RESIDENTS OF THE AGORA</div>
<div id="npc-modal">
  <div class="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">🌐</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title">—</div>
      </div>
      <div class="npc-close" onclick="closeNpcModal()">✕</div>
    </div>
    <div class="npc-dialogue"><div class="npc-text" id="npc-text"></div></div>
    <div class="npc-actions">
      <a class="npc-btn-enter soon" id="npc-enter-btn" href="#"><span>🌐</span><span id="npc-enter-lbl">COMING SOON</span></a>
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
    clock = new THREE.Clock(); setLoad(10);
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
    renderer.toneMappingExposure = 1.4;
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
    scene.background = new THREE.Color(0x020a06);
    scene.fog = new THREE.FogExp2(0x030c05, 0.014);
    const wrap = document.getElementById('cv');
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(wrap.clientWidth, wrap.clientHeight), 0.75, 0.5, 0.82));
}

function buildScene() {
    scene.add(new THREE.HemisphereLight(0x204a30, 0x080e08, 0.72));
    scene.add(new THREE.AmbientLight(0x0c1c10, 1.1));
    const sun = new THREE.DirectionalLight(0xc0ffcc, 1.25);
    sun.position.set(14, 28, 10); sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = sun.shadow.camera.bottom = -24;
    sun.shadow.camera.right = sun.shadow.camera.top = 24;
    sun.shadow.camera.far = 90;
    scene.add(sun);
    scene.add(Object.assign(new THREE.DirectionalLight(0x00e8ff, 0.45), { position: new THREE.Vector3(-14, 8, -10) }));
    scene.add(Object.assign(new THREE.DirectionalLight(0xffd700, 0.28), { position: new THREE.Vector3(20, 12, 4) }));

    buildAgoraFloor();
    buildAgoraStructure();
    buildMarketProps();
    buildCeiling();
    buildParticles();
}

function buildAgoraFloor() {
    const mat = new THREE.MeshStandardMaterial({ color: 0x0a1a0c, roughness: 0.65, metalness: 0.22, emissive: 0x050e06, emissiveIntensity: 0.15 });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI/2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    const grid = new THREE.GridHelper(GRID, 10, 0x0c2010, 0x081808);
    grid.position.set(GRID/2, 0.005, GRID/2);
    scene.add(grid);

    // Mosaic path (center cross)
    const pMat = new THREE.MeshStandardMaterial({ color: 0x182818, roughness: 0.5, metalness: 0.25 });
    const hPath = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.04, 2.5), pMat);
    hPath.position.set(GRID/2, 0.02, GRID/2); scene.add(hPath);
    const vPath = new THREE.Mesh(new THREE.BoxGeometry(2.5, 0.04, GRID), pMat.clone());
    vPath.position.set(GRID/2, 0.02, GRID/2); scene.add(vPath);

    // Path glow edges
    const gMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.8, transparent: true, opacity: 0.22 });
    [-1.3, 1.3].forEach(offset => {
        const hs = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.02, 0.05), gMat.clone());
        hs.position.set(GRID/2, 0.03, GRID/2 + offset); scene.add(hs);
        animObjects.push({ obj: hs.material, type: 'om', base: 0.18, range: 0.12, speed: 0.4+offset*0.1, mat: hs.material });
        const vs = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.02, GRID), gMat.clone());
        vs.position.set(GRID/2 + offset, 0.03, GRID/2); scene.add(vs);
    });

    // Center fountain light
    [1.2, 2.4, 3.6].forEach(r => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(r, r+0.06, 40),
            new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.7, transparent: true, opacity: 0.2, side: THREE.DoubleSide, depthWrite: false }));
        ring.rotation.x = -Math.PI/2;
        ring.position.set(GRID/2, 0.02, GRID/2); scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.12, range: 0.1, speed: 0.38+r*0.06, mat: ring.material });
    });

    const cLight = new THREE.PointLight(0x00ff88, 1.2, 6);
    cLight.position.set(GRID/2, 0.5, GRID/2); scene.add(cLight);
    animObjects.push({ obj: cLight, type: 'pulse', base: 0.9, range: 0.45, speed: 0.48 });
}

function buildAgoraStructure() {
    const wMat = new THREE.MeshStandardMaterial({ color: 0x0a1a0c, roughness: 0.6, metalness: 0.25, emissive: 0x060e08, emissiveIntensity: 0.3 });

    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 6.5), wMat.clone());
    bw.position.set(GRID/2, 3.25, 0); scene.add(bw);
    const lw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 6.5), wMat.clone());
    lw.position.set(0, 3.25, GRID/2); lw.rotation.y = Math.PI/2; scene.add(lw);

    // Vine / circuit lines on walls (green)
    const vineMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.5, transparent: true, opacity: 0.15 });
    [1.0, 2.4, 3.8].forEach(y => {
        const hBar = new THREE.Mesh(new THREE.BoxGeometry(19.5, 0.02, 0.03), vineMat.clone());
        hBar.position.set(GRID/2, y, 0.02); scene.add(hBar);
        const vBar = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.02, 19.5), vineMat.clone());
        vBar.position.set(0.02, y, GRID/2); scene.add(vBar);
    });

    // Arched doorways (back wall)
    [5, 10, 15].forEach(x => {
        const archMat = new THREE.MeshStandardMaterial({ color: 0x0c200e, roughness: 0.45, metalness: 0.3, emissive: 0x00ff88, emissiveIntensity: 0.25 });
        const arch = new THREE.Mesh(new THREE.BoxGeometry(2.0, 3.5, 0.1), archMat);
        arch.position.set(x, 1.75, 0.05); scene.add(arch);
        const archLight = new THREE.PointLight(0x00ff88, 0.5, 4);
        archLight.position.set(x, 1.8, 0.5); scene.add(archLight);
        animObjects.push({ obj: archLight, type: 'pulse', base: 0.38, range: 0.2, speed: 0.6+x*0.02 });
    });

    // Columns (green-lit)
    const colMat = new THREE.MeshStandardMaterial({ color: 0x0e1e10, roughness: 0.5, metalness: 0.2 });
    const colCapMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.4, transparent: true, opacity: 0.6 });
    [[1.5,1.5],[1.5,GRID-1.5],[GRID-1.5,1.5],[GRID-1.5,GRID-1.5],[1.5,GRID/2],[GRID/2,1.5],[GRID-1.5,GRID/2],[GRID/2,GRID-1.5]].forEach(([cx,cz]) => {
        const col = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.28, 5.5, 9), colMat.clone());
        col.position.set(cx, 2.75, cz); col.castShadow = true; scene.add(col);
        const cap = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.2, 0.65), colCapMat.clone());
        cap.position.set(cx, 5.6, cz); scene.add(cap);
        const cLight2 = new THREE.PointLight(0x00ff88, 0.35, 3.5);
        cLight2.position.set(cx, 3, cz); scene.add(cLight2);
        animObjects.push({ obj: cLight2, type: 'pulse', base: 0.25, range: 0.15, speed: 0.65+cx*0.02 });
    });

    // Baseboard
    const gMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.9, transparent: true, opacity: 0.45 });
    scene.add(Object.assign(new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.05, 0.04), gMat), { position: new THREE.Vector3(GRID/2, 0.025, 0.02) }));
    scene.add(Object.assign(new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.05, GRID), gMat.clone()), { position: new THREE.Vector3(0.02, 0.025, GRID/2) }));
}

function buildMarketProps() {
    // Market stalls
    [[5,4],[15,4],[5,16],[15,16],[3,10],[17,10]].forEach(([x,z], i) => buildStall(x,z,i));

    // Center fountain
    buildFountain(GRID/2, GRID/2);

    // Banners
    [[4,0],[10,0],[16,0],[0,5],[0,10],[0,15]].forEach(([bx,bz]) => buildBanner(bx,bz));
}

function buildStall(x, z, idx) {
    const stallColors = [0x00ff88, 0xffd700, 0x00e8ff, 0xff9800, 0x9b30ff, 0xff3d56];
    const c = stallColors[idx % stallColors.length];
    const mat = new THREE.MeshStandardMaterial({ color: 0x0a1e0c, roughness: 0.6, metalness: 0.3 });
    const table = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.08, 1.0), mat);
    table.position.set(x, 0.85, z); table.castShadow = true; scene.add(table);

    const legMat = new THREE.MeshStandardMaterial({ color: 0x0e180e, metalness: 0.8, roughness: 0.15 });
    [-0.85,0.85].forEach(dx => [-0.42,0.42].forEach(dz2 => {
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.85, 0.05), legMat);
        leg.position.set(x+dx, 0.42, z+dz2); scene.add(leg);
    }));

    // Canopy
    const awMat = new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 0.22, transparent: true, opacity: 0.7 });
    const awning = new THREE.Mesh(new THREE.BoxGeometry(2.1, 0.04, 1.1), awMat);
    awning.position.set(x, 1.45, z); scene.add(awning);

    // Stall light
    const sLight = new THREE.PointLight(c, 0.6, 3.5);
    sLight.position.set(x, 1.6, z); scene.add(sLight);
    animObjects.push({ obj: sLight, type: 'pulse', base: 0.45, range: 0.25, speed: 0.7+idx*0.12 });
}

function buildFountain(x, z) {
    const stoneMat = new THREE.MeshStandardMaterial({ color: 0x0e1e10, roughness: 0.6, metalness: 0.3 });
    // Basin
    const basin = new THREE.Mesh(new THREE.CylinderGeometry(1.5, 1.6, 0.3, 16), stoneMat);
    basin.position.set(x, 0.15, z); basin.castShadow = true; scene.add(basin);
    // Pillar
    const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.2, 1.4, 10), stoneMat.clone());
    pillar.position.set(x, 1.0, z); scene.add(pillar);
    // Top bowl
    const topBowl = new THREE.Mesh(new THREE.CylinderGeometry(0.5, 0.5, 0.2, 12), stoneMat.clone());
    topBowl.position.set(x, 1.7, z); scene.add(topBowl);

    // Water (glowing plane)
    const waterMat = new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00aa88, emissiveIntensity: 0.9, transparent: true, opacity: 0.55, roughness: 0, metalness: 0.4 });
    const water = new THREE.Mesh(new THREE.CircleGeometry(1.35, 20), waterMat);
    water.rotation.x = -Math.PI/2;
    water.position.set(x, 0.31, z); scene.add(water);
    animObjects.push({ obj: waterMat, type: 'om', base: 0.45, range: 0.2, speed: 0.55, mat: waterMat });

    const fLight = new THREE.PointLight(0x00ff88, 1.5, 6);
    fLight.position.set(x, 1.2, z); scene.add(fLight);
    animObjects.push({ obj: fLight, type: 'pulse', base: 1.1, range: 0.55, speed: 0.52 });

    // Spray particles
    for (let i = 0; i < 30; i++) {
        const a = (i / 30) * Math.PI * 2;
        const spray = new THREE.Mesh(
            new THREE.SphereGeometry(0.04, 4, 3),
            new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 1.2, transparent: true, opacity: 0.6 })
        );
        spray.position.set(x + Math.cos(a)*0.2, 1.8, z + Math.sin(a)*0.2);
        scene.add(spray);
        animObjects.push({ obj: spray, type: 'fountain_spray', cx: x, cz: z, a, speed: 0.8+Math.random()*0.4, h: 0.4+Math.random()*0.3 });
    }
}

function buildBanner(x, z) {
    const poleMat = new THREE.MeshStandardMaterial({ color: 0x1a2a1a, metalness: 0.8 });
    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.07, 3.5, 7), poleMat);
    const px = x === 0 ? 1.0 : x, pz = z === 0 ? 1.0 : z;
    pole.position.set(px, 1.75, pz); scene.add(pole);
    const banMat = new THREE.MeshStandardMaterial({ color: 0x00ff88, emissive: 0x00ff88, emissiveIntensity: 0.35, transparent: true, opacity: 0.65 });
    const banner = new THREE.Mesh(new THREE.BoxGeometry(0.8, 1.5, 0.02), banMat);
    banner.position.set(px + 0.4, 3.0, pz); scene.add(banner);
    animObjects.push({ obj: banner, type: 'wave', speed: 1.2+Math.random()*0.5 });
}

function buildCeiling() {
    const cMat = new THREE.MeshStandardMaterial({ color: 0x050e06, roughness: 0.6, metalness: 0.3 });
    const ceil = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), cMat);
    ceil.rotation.x = Math.PI/2;
    ceil.position.set(GRID/2, 6.2, GRID/2); scene.add(ceil);

    // Hanging lanterns
    [[GRID/2,GRID/2],[5,5],[15,5],[5,15],[15,15],[10,5],[10,15],[5,10],[15,10]].forEach(([cx,cz],i) => {
        const chainMat = new THREE.MeshStandardMaterial({ color: 0x2a3a28, metalness: 0.7 });
        const chain = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 1.5, 5), chainMat);
        chain.position.set(cx, 5.5, cz); scene.add(chain);
        const lantern = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.4, 0.3),
            new THREE.MeshStandardMaterial({ color: 0x102010, roughness: 0.4, metalness: 0.5 }));
        lantern.position.set(cx, 4.75, cz); scene.add(lantern);
        const glow = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.32, 0.22),
            new THREE.MeshStandardMaterial({ color: 0xffd090, emissive: 0xffd090, emissiveIntensity: 1.6, transparent: true, opacity: 0.8 }));
        glow.position.set(cx, 4.75, cz); scene.add(glow);
        const lLight = new THREE.PointLight(0xffd090, 1.3, 7);
        lLight.position.set(cx, 4.6, cz); scene.add(lLight);
        animObjects.push({ obj: lLight, type: 'pulse', base: 1.0, range: 0.4, speed: 0.55+i*0.1 });
        animObjects.push({ obj: lantern, type: 'float', base: 4.75, range: 0.04, speed: 0.6+i*0.08 });
    });
}

function buildParticles() {
    const count = 200;
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        pos[i*3]=Math.random()*GRID; pos[i*3+1]=Math.random()*5.5; pos[i*3+2]=Math.random()*GRID;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const pts = new THREE.Points(geo, new THREE.PointsMaterial({ color: 0x00ff88, size: 0.05, transparent: true, opacity: 0.45, sizeAttenuation: true }));
    scene.add(pts);
    animObjects.push({ obj: pts, type: 'particles_drift', speed: 0.09 });
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
            heroMesh.position.set(GRID/2+2, 0, GRID/2);
            heroMesh.scale.setScalar(1.15);
            scene.add(heroMesh);
            mixer = new THREE.AnimationMixer(heroMesh);
            if (gltf.animations.length) {
                const idle = gltf.animations.find(a=>/idle/i.test(a.name))??gltf.animations[0];
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
        '#00ff88': ['rgba(0,16,8,.93)','rgba(0,255,136,.22)','#00ff88'],
        '#00e8ff': ['rgba(0,14,22,.93)','rgba(0,232,255,.2)','#00e8ff'],
        '#ffd700': ['rgba(28,18,0,.93)','rgba(255,215,0,.2)','#ffd700'],
        '#ff9800': ['rgba(24,10,0,.93)','rgba(255,152,0,.2)','#ff9800'],
    };
    const [bg, border, accent] = colorMap[npc.color] ?? colorMap['#00ff88'];
    ctx.fillStyle = bg; ctx.roundRect(4,4,SIZE-8,SIZE*1.6-8,14); ctx.fill();
    ctx.strokeStyle = border; ctx.lineWidth = 3; ctx.roundRect(4,4,SIZE-8,SIZE*1.6-8,14); ctx.stroke();
    const g = ctx.createLinearGradient(0,0,0,80);
    g.addColorStop(0, accent+'55'); g.addColorStop(1,'transparent');
    ctx.fillStyle = g; ctx.roundRect(4,4,SIZE-8,80,[14,14,0,0]); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.font = `bold 18px Orbitron,monospace`; ctx.textAlign = 'center';
    ctx.fillText(npc.name.split(' ')[0].slice(0,10), SIZE/2, SIZE*1.6-40);
    ctx.fillStyle = accent; ctx.font = `10px "Share Tech Mono",monospace`;
    ctx.fillText('▶ SPEAK', SIZE/2, SIZE*1.6-20);
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
        const disc = new THREE.Mesh(new THREE.CircleGeometry(0.33,16), new THREE.MeshStandardMaterial({color:0,transparent:true,opacity:0.28,depthWrite:false}));
        disc.rotation.x=-Math.PI/2; disc.position.set(npc.waypoints[0][0],0.01,npc.waypoints[0][2]); scene.add(disc);
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.35,0.46,26), new THREE.MeshStandardMaterial({color:npc.color,emissive:npc.color,emissiveIntensity:1.1,transparent:true,opacity:0.5,depthWrite:false,side:THREE.DoubleSide}));
        ring.rotation.x=-Math.PI/2; ring.position.set(npc.waypoints[0][0],0.015,npc.waypoints[0][2]); scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'om', base: 0.38, range: 0.22, speed: 0.72+Math.random()*0.28, mat: ring.material });
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

let _spray = [];
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
        const dx=to[0]-from[0], dz=to[2]-from[2];
        if (no.t >= 1.0 || Math.sqrt(dx*dx+dz*dz)<0.01) { no.waypointIdx=next; no.t=0; }
        else {
            no.bob += dt*2.0;
            const px=from[0]+dx*no.t, pz=from[2]+dz*no.t, bob=Math.sin(no.bob)*0.055;
            no.sprite.position.set(px,1.65+bob,pz);
            no.disc.position.set(px,0.01,pz);
            no.ring.position.set(px,0.015,pz);
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
            case 'wave': ao.obj.rotation.z = Math.sin(t*ao.speed)*0.06; break;
            case 'fountain_spray':
                const phase = ((t * ao.speed) % 1);
                ao.obj.position.x = ao.cx + Math.cos(ao.a) * (0.2 + phase * 0.8);
                ao.obj.position.y = 1.8 + phase * ao.h - phase * phase * ao.h * 1.8;
                ao.obj.position.z = ao.cz + Math.sin(ao.a) * (0.2 + phase * 0.8);
                ao.obj.material.opacity = 0.6 * (1 - phase);
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

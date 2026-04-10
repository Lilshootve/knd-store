<?php
/**
 * MOUNT OLYMPUS — District Room
 * Isometric Greek temple with patrolling god NPCs. Click to interact → enter game.
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
$_playerName   = 'MORTAL';
try {
    $pdo = getDBConnection();
    $uid = (int)(current_user_id() ?? 0);
    if ($uid > 0) {
        $un = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $un->execute([$uid]);
        $_playerName = mb_strtoupper((string)($un->fetchColumn() ?: 'MORTAL'), 'UTF-8');

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
        'id'        => 'zeus',
        'name'      => 'ZEUS',
        'title'     => 'GOD OF THUNDER · EPIC STRIKER',
        'color'     => '#ffd700',
        'thumb'     => '/assets/avatars/thumbs/zeus.png',
        'waypoints' => [[5,0,5],[5,0,15],[15,0,15],[15,0,5]],
        'dialogue'  => "Mortal. You dare approach the throne of Olympus. I have watched civilizations rise and crumble like dust in a storm. Now I watch YOU. The arena calls. My thunderbolts have struck down arrogance before — today they may crown glory instead. Will you face a 1v1 duel for supremacy? Or form a squad and test your mettle in team combat?",
        'game_url'  => '/games/mind-wars-arena.php',
        'game_label'=> 'MIND WARS ARENA',
        'coming_soon' => false,
    ],
    [
        'id'        => 'odin',
        'name'      => 'ODIN',
        'title'     => 'ALLFATHER · EPIC STRATEGIST',
        'color'     => '#00e8ff',
        'thumb'     => '/assets/avatars/thumbs/odin.png',
        'waypoints' => [[16,0,6],[10,0,6],[4,0,12],[10,0,16]],
        'dialogue'  => "I sacrificed an eye for wisdom. Two ravens bring me every secret in the nine realms. Every. Single. One. I know your strengths and I know your weaknesses. The Squad Arena waits — a battlefield where strategy devours raw strength. Assemble your allies, or fall alone. Either way, Odin watches.",
        'game_url'  => '/squad-arena-v2/squad-selector.php',
        'game_label'=> 'SQUAD ARENA',
        'coming_soon' => false,
    ],
    [
        'id'        => 'hercules',
        'name'      => 'HERCULES',
        'title'     => 'DEMIGOD · EPIC TANK',
        'color'     => '#ff6b35',
        'thumb'     => '/assets/avatars/thumbs/hercules.png',
        'waypoints' => [[4,0,16],[14,0,16],[14,0,8],[6,0,8]],
        'dialogue'  => "HA! Finally, someone with the nerve to walk into Olympus without an invitation. I respect that. I've wrestled the Nemean Lion, cleaned the Augean stables in a day — trust me, I've had worse mornings than you. The arena is my home. Come. Let's see if your mind is as strong as your spine.",
        'game_url'  => '/games/mind-wars-arena.php',
        'game_label'=> 'MIND WARS ARENA',
        'coming_soon' => false,
    ],
    [
        'id'        => 'leonidas',
        'name'      => 'LEONIDAS',
        'title'     => 'SPARTAN KING · COMMON TANK',
        'color'     => '#c84000',
        'thumb'     => '/assets/avatars/thumbs/leonidas.png',
        'waypoints' => [[10,0,10],[16,0,14],[10,0,4],[4,0,10]],
        'dialogue'  => "Three hundred Spartans held a mountain pass against a million. Morale is everything. Discipline is everything. Do you have both? The Squad Arena forges warriors — not from steel, but from the decisions made under pressure. Form your squad. March into battle. Sparta does not retreat.",
        'game_url'  => '/squad-arena-v2/squad-selector.php',
        'game_label'=> 'SQUAD ARENA',
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
<title>MOUNT OLYMPUS — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#070408;font-family:"Share Tech Mono",monospace;color:#f0e8c0}
canvas{display:block}
body::after{content:"";position:fixed;inset:0;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.025) 3px,rgba(0,0,0,.025) 4px)}
#crt{position:fixed;inset:0;z-index:10000;pointer-events:none;background:#000;clip-path:inset(50% 50% 50% 50%);transition:none}
#crt.on{animation:crt-in .85s cubic-bezier(.16,1,.3,1) forwards}
#crt.off{animation:crt-out .6s ease-in forwards;pointer-events:all}
@keyframes crt-in{0%{clip-path:inset(50% 50% 50% 50%);background:#fff}25%{clip-path:inset(49% 0 49% 0);background:#ffd}70%{clip-path:inset(2% 0 2% 0);background:#111}100%{clip-path:inset(0% 0 0% 0);background:transparent}}
@keyframes crt-out{0%{clip-path:inset(0%);opacity:1;background:transparent}40%{clip-path:inset(46% 0 46% 0);background:#fff;opacity:1}75%{clip-path:inset(49.5% 0 49.5% 0);background:#fff}100%{clip-path:inset(50%);background:#000}}
#tb{position:fixed;top:0;left:0;right:0;height:48px;z-index:200;background:rgba(10,6,2,.97);border-bottom:1px solid rgba(255,215,0,.07);display:flex;align-items:center;padding:0 16px;gap:10px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 2%,#ffd700 35%,#ff6b35 50%,#ffd700 65%,transparent 98%);opacity:.2}
.back-btn{display:flex;align-items:center;gap:5px;padding:4px 10px 4px 7px;border-radius:4px;border:1px solid rgba(255,215,0,.15);cursor:pointer;font-size:9px;letter-spacing:.14em;color:rgba(255,215,0,.6);transition:all .2s;text-decoration:none}
.back-btn:hover{border-color:rgba(255,215,0,.4);color:#ffd700;background:rgba(255,215,0,.06)}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
#tb-title{font-family:"Orbitron",sans-serif;font-size:11px;font-weight:900;letter-spacing:.2em;color:#fff}
#tb-sub{font-size:7.5px;letter-spacing:.18em;color:rgba(255,215,0,.35);margin-left:auto}
.tb-badge{padding:3px 8px;border-radius:3px;font-family:"Orbitron",sans-serif;font-size:7px;font-weight:700;letter-spacing:.12em;background:rgba(255,107,53,.1);border:1px solid rgba(255,107,53,.3);color:#ff6b35}
#cv{position:fixed;top:48px;left:0;right:0;bottom:0;z-index:0;background:#070408}
#cv canvas{width:100%!important;height:100%!important}
#npc-modal{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.72);backdrop-filter:blur(10px);display:none;align-items:flex-end;justify-content:center;padding:0 0 32px}
#npc-modal.open{display:flex}
.npc-panel{width:min(680px,96vw);background:linear-gradient(160deg,rgba(18,10,2,.98),rgba(10,6,2,.99));border:1px solid rgba(255,215,0,.2);border-radius:12px;padding:0;overflow:hidden;box-shadow:0 0 80px rgba(255,215,0,.1),0 0 40px rgba(0,0,0,.6);animation:panelUp .35s cubic-bezier(.2,.8,.2,1)}
@keyframes panelUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.npc-header{display:flex;align-items:center;gap:14px;padding:16px 20px 14px;border-bottom:1px solid rgba(255,215,0,.08);position:relative}
.npc-avatar-frame{width:56px;height:56px;border-radius:8px;border:2px solid rgba(255,215,0,.3);overflow:hidden;flex-shrink:0;background:rgba(255,215,0,.05);display:flex;align-items:center;justify-content:center}
.npc-avatar-frame img{width:100%;height:100%;object-fit:cover}
.npc-placeholder{font-size:24px;opacity:.4}
.npc-info{flex:1;min-width:0}
.npc-name{font-family:"Orbitron",sans-serif;font-size:13px;font-weight:900;letter-spacing:.08em;color:#fff;line-height:1.2}
.npc-title{font-size:8px;letter-spacing:.14em;margin-top:3px}
.npc-close{position:absolute;right:14px;top:14px;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,.35);transition:all .2s}
.npc-close:hover{background:rgba(255,61,86,.1);border-color:rgba(255,61,86,.3);color:#ff3d56}
.npc-dialogue{padding:18px 22px;min-height:96px}
.npc-text{font-size:12px;line-height:1.75;color:rgba(240,232,200,.9);letter-spacing:.02em}
.npc-cursor{display:inline-block;width:2px;height:14px;background:#ffd700;animation:blink .75s step-end infinite;vertical-align:middle;margin-left:2px}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.npc-actions{display:flex;gap:10px;padding:14px 22px 18px;border-top:1px solid rgba(255,215,0,.06)}
.npc-btn-enter{flex:1;padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:900;letter-spacing:.18em;cursor:pointer;background:linear-gradient(135deg,rgba(255,215,0,.18),rgba(255,107,53,.1));border:1px solid rgba(255,215,0,.45);color:#ffd700;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
.npc-btn-enter:hover{box-shadow:0 0 28px rgba(255,215,0,.22),0 0 14px rgba(255,215,0,.1);transform:translateY(-1px);color:#fff}
.npc-btn-enter.soon{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.npc-btn-skip{padding:12px 18px;border-radius:6px;font-family:"Orbitron",sans-serif;font-size:9px;font-weight:700;letter-spacing:.14em;cursor:pointer;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);color:rgba(240,220,140,.4);transition:all .2s}
.npc-btn-skip:hover{border-color:rgba(255,255,255,.16);color:rgba(240,220,140,.7)}
#hint{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:100;font-size:7.5px;letter-spacing:.16em;color:rgba(255,215,0,.3);pointer-events:none;transition:opacity .4s}
#hint.fade{opacity:0}
#load{position:fixed;inset:0;z-index:8000;background:#070408;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px}
#load.done{animation:loadOut .5s ease forwards}
@keyframes loadOut{to{opacity:0;pointer-events:none}}
.load-logo{font-family:"Orbitron",sans-serif;font-size:28px;font-weight:900;letter-spacing:.3em;color:#fff}.load-logo span{color:#ffd700}
.load-sub{font-size:8px;letter-spacing:.35em;color:rgba(255,215,0,.35)}
.load-bar{width:220px;height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden;margin-top:8px}
.load-fill{height:100%;background:linear-gradient(90deg,#ffd700,#ff6b35);border-radius:1px;width:0%;transition:width .4s ease}
</style>
</head>
<body>
<div id="crt"></div>
<div id="load">
  <div class="load-logo">MOUNT <span>OLYMPUS</span></div>
  <div class="load-sub">ASCENDING TO THE DIVINE PLANE</div>
  <div class="load-bar"><div class="load-fill" id="load-fill"></div></div>
</div>
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">MOUNT OLYMPUS</span>
  <span id="tb-sub">DISTRICT · GODS &amp; COMBAT</span>
  <span class="tb-badge">ARENA</span>
</header>
<div id="cv"></div>
<div id="hint">CLICK ON A GOD TO CHALLENGE THEM</div>
<div id="npc-modal">
  <div class="npc-panel" id="npc-panel">
    <div class="npc-header">
      <div class="npc-avatar-frame">
        <img id="npc-avatar-img" src="" alt="" onerror="this.style.display='none';document.getElementById('npc-placeholder').style.display='block'">
        <span class="npc-placeholder" id="npc-placeholder" style="display:none">⚡</span>
      </div>
      <div class="npc-info">
        <div class="npc-name" id="npc-name">—</div>
        <div class="npc-title" id="npc-title">—</div>
      </div>
      <div class="npc-close" onclick="closeNpcModal()">✕</div>
    </div>
    <div class="npc-dialogue"><div class="npc-text" id="npc-text"></div></div>
    <div class="npc-actions">
      <a class="npc-btn-enter" id="npc-enter-btn" href="#">
        <span>⚔</span><span id="npc-enter-lbl">ENTER</span>
      </a>
      <button class="npc-btn-skip" onclick="closeNpcModal()">RETREAT</button>
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
let npcObjects = [];
let animObjects = [];
let raycaster = new THREE.Raycaster();
let pointer   = new THREE.Vector2();
let _hintTimer = 0;
let _typingInterval = null;

window.addEventListener('DOMContentLoaded', boot);

async function boot() {
    clock = new THREE.Clock();
    setLoad(10);
    initRenderer(); setLoad(28);
    initCamera();
    initScene(); setLoad(48);
    buildScene(); setLoad(68);
    spawnNPCs(); setLoad(82);
    if (HERO_MODEL) await spawnHero(); setLoad(100);

    setTimeout(() => {
        const l = document.getElementById('load');
        l.classList.add('done');
        setTimeout(() => l.remove(), 600);
        document.getElementById('crt').classList.add('on');
    }, 350);

    window.addEventListener('click', onCanvasClick);
    window.addEventListener('resize', onResize);
    tick();
}

function setLoad(p) { const f = document.getElementById('load-fill'); if (f) f.style.width = p + '%'; }

function initRenderer() {
    const wrap = document.getElementById('cv');
    renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.5;
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
    camera.left = -D_CAM*a; camera.right = D_CAM*a;
    camera.top = D_CAM; camera.bottom = -D_CAM;
    camera.updateProjectionMatrix();
    renderer.setSize(wrap.clientWidth, wrap.clientHeight);
    if (composer) composer.setSize(wrap.clientWidth, wrap.clientHeight);
}

function initScene() {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x070408);
    scene.fog = new THREE.FogExp2(0x0a0504, 0.016);
    const wrap = document.getElementById('cv');
    const w = wrap.clientWidth, h = wrap.clientHeight;
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(w, h), 0.88, 0.55, 0.78));
}

function buildScene() {
    // Lighting — warm divine gold
    scene.add(new THREE.HemisphereLight(0x7a5a20, 0x0c0408, 0.8));
    scene.add(new THREE.AmbientLight(0x2a1a08, 1.15));
    const sun = new THREE.DirectionalLight(0xffd090, 1.45);
    sun.position.set(16, 30, 10);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.camera.left = sun.shadow.camera.bottom = -24;
    sun.shadow.camera.right = sun.shadow.camera.top = 24;
    sun.shadow.camera.far = 90;
    scene.add(sun);
    const fill = new THREE.DirectionalLight(0xff6b35, 0.55);
    fill.position.set(-14, 8, -10);
    scene.add(fill);
    const rim = new THREE.DirectionalLight(0x80a0ff, 0.3);
    rim.position.set(22, 14, 0);
    scene.add(rim);

    buildTempleFloor();
    buildTempleStructure();
    buildSkyElements();
    buildParticles();
}

function buildTempleFloor() {
    // White marble floor
    const mat = new THREE.MeshStandardMaterial({
        color: 0x1a1208, roughness: 0.55, metalness: 0.18,
        emissive: 0x100800, emissiveIntensity: 0.15
    });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), mat);
    floor.rotation.x = -Math.PI/2;
    floor.position.set(GRID/2, -0.01, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    // Marble tile grid
    const grid = new THREE.GridHelper(GRID, 10, 0x2a1a08, 0x1e1206);
    grid.position.set(GRID/2, 0.005, GRID/2);
    scene.add(grid);

    // Central raised platform (altar)
    const platMat = new THREE.MeshStandardMaterial({ color: 0x1a1006, roughness: 0.4, metalness: 0.3 });
    const plat = new THREE.Mesh(new THREE.BoxGeometry(4, 0.18, 4), platMat);
    plat.position.set(GRID/2, 0.09, GRID/2);
    plat.receiveShadow = true;
    scene.add(plat);
    // Altar glow
    const altarLight = new THREE.PointLight(0xffd700, 1.5, 7);
    altarLight.position.set(GRID/2, 0.5, GRID/2);
    scene.add(altarLight);
    animObjects.push({ obj: altarLight, type: 'pulse', base: 1.2, range: 0.5, speed: 0.45 });

    // Floor rune lines (golden)
    const runeMat = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.9, transparent: true, opacity: 0.3 });
    // Diagonal rune channels to altar
    [[GRID/2,5],[5,GRID/2],[GRID/2,GRID-5],[GRID-5,GRID/2]].forEach(([px,pz]) => {
        const dx = GRID/2 - px, dz = GRID/2 - pz;
        const len = Math.sqrt(dx*dx+dz*dz);
        const strip = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.02, len), runeMat.clone());
        strip.position.set((px+GRID/2)/2, 0.01, (pz+GRID/2)/2);
        strip.rotation.y = Math.atan2(dx, dz);
        scene.add(strip);
        animObjects.push({ obj: strip.material, type: 'opacity_mat', base: 0.25, range: 0.18, speed: 0.5 + Math.random()*0.3, mat: strip.material });
    });

    // Glow floor rings
    [3, 5.5, 8].forEach(r => {
        const ring = new THREE.Mesh(new THREE.RingGeometry(r, r+0.06, 40),
            new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.7, transparent: true, opacity: 0.18, side: THREE.DoubleSide, depthWrite: false })
        );
        ring.rotation.x = -Math.PI/2;
        ring.position.set(GRID/2, 0.02, GRID/2);
        scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'opacity_mat', base: 0.12, range: 0.1, speed: 0.35 + r*0.04, mat: ring.material });
    });
}

function buildTempleStructure() {
    const marbleMat = new THREE.MeshStandardMaterial({ color: 0x1a1208, roughness: 0.45, metalness: 0.2, emissive: 0x0a0806, emissiveIntensity: 0.3 });
    const goldMat   = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.55, metalness: 0.9, roughness: 0.1, transparent: true, opacity: 0.7 });

    // Back wall (z=0)
    const bw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 7), marbleMat.clone());
    bw.position.set(GRID/2, 3.5, 0);
    scene.add(bw);

    // Left wall (x=0)
    const lw = new THREE.Mesh(new THREE.PlaneGeometry(GRID, 7), marbleMat.clone());
    lw.position.set(0, 3.5, GRID/2);
    lw.rotation.y = Math.PI/2;
    scene.add(lw);

    // Columns — Greek Doric columns around the room
    const colMat = new THREE.MeshStandardMaterial({ color: 0x1e1610, roughness: 0.42, metalness: 0.15 });
    const colCapMat = new THREE.MeshStandardMaterial({ color: 0xffd090, roughness: 0.3, metalness: 0.4, emissive: 0xffd700, emissiveIntensity: 0.25 });

    const colPositions = [
        [1.2,1.2],[1.2,GRID-1.2],[GRID-1.2,1.2],[GRID-1.2,GRID-1.2],
        [1.2,GRID/2],[GRID-1.2,GRID/2],[GRID/2,1.2],[GRID/2,GRID-1.2]
    ];

    colPositions.forEach(([cx, cz]) => {
        // Column shaft
        const col = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.32, 5.5, 10), colMat.clone());
        col.position.set(cx, 2.75, cz);
        col.castShadow = true;
        scene.add(col);
        // Capital (top)
        const cap = new THREE.Mesh(new THREE.BoxGeometry(0.72, 0.22, 0.72), colCapMat.clone());
        cap.position.set(cx, 5.61, cz);
        scene.add(cap);
        // Base
        const base = new THREE.Mesh(new THREE.BoxGeometry(0.68, 0.18, 0.68), colCapMat.clone());
        base.position.set(cx, 0.09, cz);
        scene.add(base);
        // Column glow
        const cLight = new THREE.PointLight(0xffd090, 0.4, 4);
        cLight.position.set(cx, 3.5, cz);
        scene.add(cLight);
        animObjects.push({ obj: cLight, type: 'pulse', base: 0.3, range: 0.18, speed: 0.6 + cx * 0.02 });
    });

    // Entablature (top horizontal beams)
    const beamMat = new THREE.MeshStandardMaterial({ color: 0x181006, roughness: 0.38, metalness: 0.25, emissive: 0x100800, emissiveIntensity: 0.2 });
    const beam1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.35, 0.55), beamMat);
    beam1.position.set(GRID/2, 5.8, 0.28);
    scene.add(beam1);
    const beam2 = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.35, GRID), beamMat.clone());
    beam2.position.set(0.28, 5.8, GRID/2);
    scene.add(beam2);

    // Gold trim on beams
    const trim1 = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.06, 0.04), goldMat.clone());
    trim1.position.set(GRID/2, 5.65, 0.02);
    scene.add(trim1);
    const trim2 = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.06, GRID), goldMat.clone());
    trim2.position.set(0.02, 5.65, GRID/2);
    scene.add(trim2);

    // Wall gold circuit lines
    const wgMat = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.4, transparent: true, opacity: 0.15 });
    [1.2, 2.5, 4.0, 5.5].forEach(y => {
        const hBar = new THREE.Mesh(new THREE.BoxGeometry(19.6, 0.02, 0.03), wgMat.clone());
        hBar.position.set(GRID/2, y, 0.02);
        scene.add(hBar);
        const vBar = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.02, 19.6), wgMat.clone());
        vBar.position.set(0.02, y, GRID/2);
        scene.add(vBar);
    });

    // Large flame torches (corner accents)
    [[2, 3],[18, 3],[2, 17],[18, 17]].forEach(([tx, tz]) => {
        buildTorch(tx, tz);
    });
}

function buildTorch(x, z) {
    const torchMat = new THREE.MeshStandardMaterial({ color: 0x3a2010, metalness: 0.8, roughness: 0.2 });
    const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.1, 1.8, 8), torchMat);
    shaft.position.set(x, 0.9, z);
    shaft.castShadow = true;
    scene.add(shaft);

    const bowl = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.14, 0.28, 10), torchMat.clone());
    bowl.position.set(x, 1.92, z);
    scene.add(bowl);

    // Flame glow
    const flameLight = new THREE.PointLight(0xff6b35, 2.2, 5, 1.5);
    flameLight.position.set(x, 2.2, z);
    scene.add(flameLight);
    animObjects.push({ obj: flameLight, type: 'pulse', base: 1.8, range: 0.7, speed: 2.5 + x * 0.06 });

    // Flame mesh (cone)
    const flameMat = new THREE.MeshStandardMaterial({ color: 0xff8800, emissive: 0xff4400, emissiveIntensity: 2.0, transparent: true, opacity: 0.75 });
    const flame = new THREE.Mesh(new THREE.ConeGeometry(0.14, 0.45, 8), flameMat);
    flame.position.set(x, 2.4, z);
    scene.add(flame);
    animObjects.push({ obj: flame, type: 'flame', baseY: 2.4, speed: 3.0 + Math.random() });
    animObjects.push({ obj: flame.material, type: 'opacity_mat', base: 0.65, range: 0.2, speed: 3.5 + x * 0.1, mat: flame.material });
}

function buildSkyElements() {
    // Ceiling — dark stone with golden trim
    const cMat = new THREE.MeshStandardMaterial({ color: 0x0e0a04, roughness: 0.6, metalness: 0.3 });
    const ceil = new THREE.Mesh(new THREE.PlaneGeometry(GRID, GRID), cMat);
    ceil.rotation.x = Math.PI/2;
    ceil.position.set(GRID/2, 6.2, GRID/2);
    scene.add(ceil);

    // Hanging chandeliers
    [[GRID/2, GRID/2],[6, 6],[14, 6],[6, 14],[14, 14]].forEach(([cx, cz], i) => {
        const chainMat = new THREE.MeshStandardMaterial({ color: 0xffd700, emissive: 0xffd700, emissiveIntensity: 0.6, metalness: 0.9 });
        const chain = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 1.5, 6), chainMat);
        chain.position.set(cx, 5.5, cz);
        scene.add(chain);

        const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.1, 12), chainMat.clone());
        disc.position.set(cx, 4.75, cz);
        scene.add(disc);

        const chanLight = new THREE.PointLight(0xffd090, 1.8, 8, 1.5);
        chanLight.position.set(cx, 4.6, cz);
        scene.add(chanLight);
        animObjects.push({ obj: chanLight, type: 'pulse', base: 1.5, range: 0.4, speed: 0.55 + i * 0.12 });
    });
}

function buildParticles() {
    const count = 180;
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        pos[i*3]   = Math.random() * GRID;
        pos[i*3+1] = Math.random() * 5.5;
        pos[i*3+2] = Math.random() * GRID;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.PointsMaterial({ color: 0xffd700, size: 0.05, transparent: true, opacity: 0.5, sizeAttenuation: true });
    scene.add(new THREE.Points(geo, mat));
    animObjects.push({ obj: scene.children[scene.children.length-1], type: 'particles_drift', speed: 0.1 });
}

function normalizeGltf(root) {
    root.traverse(c => {
        if (c.isMesh) {
            if (c.material?.isMeshPhysicalMaterial) {
                const m = c.material;
                c.material = new THREE.MeshStandardMaterial({
                    map: m.map, normalMap: m.normalMap, color: m.color,
                    roughness: m.roughness??0.7, metalness: m.metalness??0.3,
                    emissive: m.emissive??new THREE.Color(0), emissiveIntensity: m.emissiveIntensity??1,
                    transparent: m.transparent, opacity: m.opacity
                });
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
        '#ffd700': ['rgba(40,28,0,.92)','rgba(255,215,0,.22)','#ffd700'],
        '#00e8ff': ['rgba(0,14,24,.92)','rgba(0,232,255,.2)','#00e8ff'],
        '#ff6b35': ['rgba(30,10,0,.92)','rgba(255,107,53,.2)','#ff6b35'],
        '#c84000': ['rgba(25,8,0,.92)','rgba(200,64,0,.2)','#c84000'],
    };
    const [bg, border, accent] = colorMap[npc.color] ?? colorMap['#ffd700'];

    ctx.fillStyle = bg;
    ctx.roundRect(4, 4, SIZE-8, SIZE*1.6-8, 14);
    ctx.fill();
    ctx.strokeStyle = border;
    ctx.lineWidth = 3;
    ctx.roundRect(4, 4, SIZE-8, SIZE*1.6-8, 14);
    ctx.stroke();

    const grad = ctx.createLinearGradient(0,0,0,80);
    grad.addColorStop(0, accent + '55');
    grad.addColorStop(1, 'transparent');
    ctx.fillStyle = grad;
    ctx.roundRect(4, 4, SIZE-8, 80, [14,14,0,0]);
    ctx.fill();

    ctx.fillStyle = '#fff';
    ctx.font = `bold 20px Orbitron, monospace`;
    ctx.textAlign = 'center';
    ctx.fillText(npc.name.split(' ')[0].slice(0,10), SIZE/2, SIZE*1.6 - 40);
    ctx.fillStyle = accent;
    ctx.font = `11px "Share Tech Mono", monospace`;
    ctx.fillText('▶ CHALLENGE', SIZE/2, SIZE*1.6 - 20);

    const tex = new THREE.CanvasTexture(cv);
    tex.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false }));
    sprite.scale.set(1.05, 1.68, 1);

    const img = new Image();
    img.crossOrigin = 'anonymous';
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

        const disc = new THREE.Mesh(
            new THREE.CircleGeometry(0.35, 16),
            new THREE.MeshStandardMaterial({ color: 0, transparent: true, opacity: 0.3, depthWrite: false })
        );
        disc.rotation.x = -Math.PI/2;
        disc.position.set(npc.waypoints[0][0], 0.01, npc.waypoints[0][2]);
        scene.add(disc);

        const ring = new THREE.Mesh(
            new THREE.RingGeometry(0.36, 0.48, 26),
            new THREE.MeshStandardMaterial({ color: npc.color, emissive: npc.color, emissiveIntensity: 1.2, transparent: true, opacity: 0.55, depthWrite: false, side: THREE.DoubleSide })
        );
        ring.rotation.x = -Math.PI/2;
        ring.position.set(npc.waypoints[0][0], 0.015, npc.waypoints[0][2]);
        scene.add(ring);
        animObjects.push({ obj: ring.material, type: 'opacity_mat', base: 0.4, range: 0.25, speed: 0.75 + Math.random()*0.3, mat: ring.material });

        npcObjects.push({ sprite, disc, ring, npc, waypointIdx: 0, t: 0, speed: 0.5 + Math.random()*0.3, bob: Math.random()*Math.PI*2 });
    });
}

function onCanvasClick(e) {
    const wrap = document.getElementById('cv');
    const rect = wrap.getBoundingClientRect();
    pointer.x =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
    pointer.y = -((e.clientY - rect.top)  / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(npcObjects.map(n => n.sprite), false);
    if (!hits.length) return;
    const hit = npcObjects.find(n => n.sprite === hits[0].object);
    if (hit) openNpcModal(hit.npc);
}

window.closeNpcModal = function () {
    document.getElementById('npc-modal').classList.remove('open');
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval = null; }
};

function openNpcModal(npc) {
    document.getElementById('npc-name').textContent  = npc.name;
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
        btn.classList.remove('soon'); btn.href = npc.game_url; lbl.textContent = npc.game_label ?? 'ENTER';
    }

    const textEl = document.getElementById('npc-text');
    textEl.innerHTML = '';
    if (_typingInterval) { clearInterval(_typingInterval); _typingInterval = null; }
    document.getElementById('npc-modal').classList.add('open');

    let i = 0;
    const cursor = document.createElement('span');
    cursor.className = 'npc-cursor';
    cursor.style.background = npc.color;
    textEl.appendChild(cursor);
    _typingInterval = setInterval(() => {
        if (i < npc.dialogue.length) {
            textEl.insertBefore(document.createTextNode(npc.dialogue[i]), cursor);
            i++;
        } else {
            cursor.remove();
            clearInterval(_typingInterval); _typingInterval = null;
        }
    }, 22);
}

let _t = 0;
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
        const next = (no.waypointIdx + 1) % wp.length;
        const to = wp[next];
        no.t += dt * no.speed;
        const dx = to[0] - from[0], dz = to[2] - from[2];
        if (no.t >= 1.0 || Math.sqrt(dx*dx+dz*dz) < 0.01) {
            no.waypointIdx = next; no.t = 0;
        } else {
            no.bob += dt * 2.0;
            const px = from[0] + dx * no.t;
            const pz = from[2] + dz * no.t;
            const bob = Math.sin(no.bob) * 0.055;
            no.sprite.position.set(px, 1.65 + bob, pz);
            no.disc.position.set(px, 0.01, pz);
            no.ring.position.set(px, 0.015, pz);
        }
    });
}

function updateAnimObjects(t, dt) {
    animObjects.forEach(ao => {
        if (!ao.obj) return;
        switch (ao.type) {
            case 'pulse':
                ao.obj.intensity = ao.base + Math.sin(t * ao.speed) * ao.range; break;
            case 'opacity_mat':
                if (ao.mat) ao.mat.opacity = ao.base + Math.sin(t * ao.speed) * ao.range; break;
            case 'scan_y':
                ao.obj.position.y = ao.base + ((((t * ao.speed) % 1) + 1) % 1) * (ao.top - ao.base); break;
            case 'rotate_y':
                ao.obj.rotation.y += dt * ao.speed; break;
            case 'flame':
                ao.obj.position.y = ao.baseY + Math.sin(t * ao.speed) * 0.06;
                ao.obj.scale.x = 1 + Math.sin(t * ao.speed * 1.3) * 0.15;
                ao.obj.scale.z = 1 + Math.cos(t * ao.speed * 1.1) * 0.12;
                break;
            case 'particles_drift':
                const pos = ao.obj.geometry.attributes.position;
                for (let i = 0; i < pos.count; i++) {
                    pos.array[i*3+1] += dt * ao.speed * (0.5 + Math.sin(t + i) * 0.5);
                    if (pos.array[i*3+1] > 5.5) pos.array[i*3+1] = 0;
                }
                pos.needsUpdate = true; break;
        }
    });
}
</script>
</body>
</html>

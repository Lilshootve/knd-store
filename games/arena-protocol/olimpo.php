<?php
/**
 * MOUNT OLYMPUS — District Room v2
 * Isometric 3D temple: WASD movement, OrbitControls camera, real GLB NPCs, E-key interaction.
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
        $av = $s->fetch(\PDO::FETCH_ASSOC);
        if ($av) $_heroModelUrl = mw_resolve_avatar_model_url((int)$av['id'], (string)$av['name'], (string)$av['rarity']);
        if (!$_heroModelUrl) {
            $sf = $pdo->prepare("SELECT fa.id, fa.name, fa.rarity FROM knd_user_avatar_inventory ui
                JOIN knd_avatar_items ai ON ai.id = ui.item_id AND ai.mw_avatar_id IS NOT NULL
                JOIN mw_avatars fa ON fa.id = ai.mw_avatar_id WHERE ui.user_id = ? LIMIT 1");
            $sf->execute([$uid]);
            $avf = $sf->fetch(\PDO::FETCH_ASSOC);
            if ($avf) $_heroModelUrl = mw_resolve_avatar_model_url((int)$avf['id'], (string)$avf['name'], (string)$avf['rarity']);
        }
        if (!$_heroModelUrl) {
            $sa = $pdo->query("SELECT id, name, rarity FROM mw_avatars ORDER BY id ASC LIMIT 20");
            foreach ($sa->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $url = mw_resolve_avatar_model_url((int)$row['id'], (string)$row['name'], (string)$row['rarity']);
                if ($url) { $_heroModelUrl = $url; break; }
            }
        }
    }
} catch (\Throwable $e) { error_log('olimpo hero: ' . $e->getMessage()); }

$NPCS_PHP = [
    [
        'id'         => 'zeus',
        'name'       => 'Zeus',
        'title'      => 'King of Olympus',
        'color'      => '#ffd600',
        'blurb'      => 'Only the worthy enter the arena. Prove your mind is lightning-forged. Face me in Mind Wars.',
        'glb'        => '/assets/avatars/models/epic/corrupted_zeus.glb',
        'game_url'   => '/games/mind-wars/lobby.php',
        'game_label' => 'Mind Wars',
        'coming_soon'=> false,
        'waypoints'  => [[5, 0, 5], [8, 0, 5], [8, 0, 8], [5, 0, 8]],
    ],
    [
        'id'         => 'hercules',
        'name'       => 'Hercules',
        'title'      => 'Demigod · Champion',
        'color'      => '#ff6b00',
        'blurb'      => 'Twelve labors forged my name. One game will forge yours. The arena awaits, mortal.',
        'glb'        => '/assets/avatars/models/epic/hercules.glb',
        'game_url'   => '/games/mind-wars/lobby.php',
        'game_label' => 'Mind Wars',
        'coming_soon'=> false,
        'waypoints'  => [[14, 0, 5], [14, 0, 8], [11, 0, 8], [11, 0, 5]],
    ],
    [
        'id'         => 'thor',
        'name'       => 'Thor',
        'title'      => 'God of Thunder',
        'color'      => '#66ccff',
        'blurb'      => 'Mjolnir strikes fast and true. In the 1v1 arena, so shall you — if you dare.',
        'glb'        => '/assets/avatars/models/epic/thor.glb',
        'game_url'   => null,
        'game_label' => 'Duel 1v1',
        'coming_soon'=> true,
        'waypoints'  => [[5, 0, 14], [8, 0, 14], [8, 0, 11], [5, 0, 11]],
    ],
    [
        'id'         => 'odin',
        'name'       => 'Odin',
        'title'      => 'Allfather · Wisdom',
        'color'      => '#c084fc',
        'blurb'      => 'I sacrificed an eye for wisdom. What will you sacrifice for victory?',
        'glb'        => '/assets/avatars/models/epic/odin.glb',
        'game_url'   => null,
        'game_label' => 'Wisdom Trial',
        'coming_soon'=> true,
        'waypoints'  => [[14, 0, 14], [11, 0, 14], [11, 0, 11], [14, 0, 11]],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="nexus-ws-url" content="wss://knd-store-production.up.railway.app">
<title>MOUNT OLYMPUS · NEXUS CITY</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#000;font-family:'Rajdhani',sans-serif}
#c{position:fixed;top:48px;left:0;right:0;bottom:0;display:block;width:100%}
/* Topbar */
#tb{position:fixed;top:0;left:0;right:0;height:48px;background:rgba(0,0,0,.88);border-bottom:1px solid rgba(255,214,0,.18);display:flex;align-items:center;gap:12px;padding:0 16px;z-index:30;backdrop-filter:blur(8px)}
.back-btn{display:flex;align-items:center;gap:6px;color:#ffd600;text-decoration:none;font-family:'Orbitron',monospace;font-size:.65rem;letter-spacing:.12em;opacity:.8;transition:opacity .2s}
.back-btn:hover{opacity:1}
.back-btn svg{width:16px;height:16px;stroke:#ffd600;stroke-width:2.5;fill:none}
#tb-title{font-family:'Orbitron',monospace;font-size:.7rem;font-weight:700;letter-spacing:.15em;color:#ffd600;text-shadow:0 0 10px rgba(255,214,0,.5),0 0 30px rgba(255,214,0,.2)}
#tb-sub{font-family:'Share Tech Mono',monospace;font-size:.6rem;letter-spacing:.12em;color:rgba(255,214,0,.45);margin-left:4px}
#loading{position:fixed;inset:0;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:50;transition:opacity .6s}
#loading.hidden{opacity:0;pointer-events:none}
.ld-logo{font-family:'Orbitron',monospace;font-size:2rem;font-weight:900;color:#ffd600;text-shadow:0 0 24px #ffd600,0 0 60px rgba(255,214,0,.4);letter-spacing:.2em;margin-bottom:8px}
.ld-sub{font-family:'Share Tech Mono',monospace;font-size:.75rem;letter-spacing:.2em;color:rgba(255,214,0,.5);margin-bottom:40px}
.ld-bar{width:280px;height:3px;background:rgba(255,214,0,.15);border-radius:2px;overflow:hidden}
.ld-fill{height:100%;width:0;background:linear-gradient(90deg,#ffd600,#ff6b00);transition:width .3s}
.ld-msg{font-family:'Share Tech Mono',monospace;font-size:.65rem;color:rgba(255,214,0,.4);letter-spacing:.15em;margin-top:10px}
#interact-hint{position:fixed;bottom:120px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.82);border:1px solid rgba(255,214,0,.45);border-radius:10px;padding:9px 22px;font-family:'Orbitron',monospace;font-size:.65rem;letter-spacing:.12em;color:#ffd600;text-shadow:0 0 8px #ffd600;pointer-events:none;z-index:20;opacity:0;transition:opacity .25s;white-space:nowrap}
#interact-hint.show{opacity:1}
#ctrl-hint{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);opacity:.45;pointer-events:none;z-index:10}
#ctrl-hint span{display:inline-block;background:rgba(255,214,0,.08);border:1px solid rgba(255,214,0,.25);border-radius:5px;padding:3px 9px;font-family:'Share Tech Mono',monospace;font-size:.6rem;letter-spacing:.1em;color:#ffd600;margin:0 3px}
#cam-hint{position:fixed;bottom:46px;left:50%;transform:translateX(-50%);opacity:.35;pointer-events:none;z-index:10;font-family:'Share Tech Mono',monospace;font-size:.58rem;letter-spacing:.1em;color:#ffd600}
#npc-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:40;pointer-events:none;opacity:0;transition:opacity .3s}
#npc-modal.open{pointer-events:all;opacity:1}
.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(6px)}
.modal-card{position:relative;background:linear-gradient(160deg,rgba(20,15,0,.97),rgba(10,8,0,.97));border:1px solid rgba(255,214,0,.3);border-radius:16px;padding:32px 36px 28px;width:min(480px,92vw);box-shadow:0 0 60px rgba(255,214,0,.15),inset 0 0 40px rgba(255,214,0,.03)}
.modal-accent{position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0;background:linear-gradient(90deg,transparent,#ffd600,transparent)}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;color:rgba(255,214,0,.5);font-size:1.3rem;cursor:pointer;line-height:1;transition:color .2s}
.modal-close:hover{color:#ffd600}
.modal-badge{display:inline-block;font-family:'Share Tech Mono',monospace;font-size:.58rem;letter-spacing:.18em;padding:3px 10px;border-radius:4px;margin-bottom:12px;background:rgba(255,214,0,.12);border:1px solid rgba(255,214,0,.3);color:#ffd600}
.modal-name{font-family:'Orbitron',monospace;font-size:1.35rem;font-weight:700;color:#fff;text-shadow:0 0 14px rgba(255,214,0,.5);margin-bottom:4px}
.modal-title{font-size:.8rem;letter-spacing:.1em;color:rgba(255,214,0,.7);margin-bottom:18px}
.modal-blurb{font-size:.9rem;line-height:1.6;color:rgba(255,255,255,.75);margin-bottom:24px;font-style:italic}
.modal-btn{display:block;width:100%;padding:13px;font-family:'Orbitron',monospace;font-size:.75rem;font-weight:700;letter-spacing:.12em;border:none;border-radius:10px;cursor:pointer;transition:all .2s;text-decoration:none;text-align:center}
.modal-btn-primary{background:linear-gradient(135deg,#ffd600,#cc9900);color:#000;box-shadow:0 0 20px rgba(255,214,0,.4)}
.modal-btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 28px rgba(255,214,0,.6)}
.modal-btn-soon{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.3);cursor:not-allowed}
@keyframes crt-in{0%{clip-path:inset(50% 0)}100%{clip-path:inset(0% 0)}}
.crt-enter{animation:crt-in .35s ease-out}
</style>
</head>
<body class="crt-enter">
<header id="tb">
  <a class="back-btn" href="/games/arena-protocol/nexus-city.html">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>NEXUS</span>
  </a>
  <span style="width:1px;height:18px;background:rgba(255,255,255,.07)"></span>
  <span id="tb-title">MONTE OLIMPO</span>
  <span id="tb-sub">DISTRITO · COMBATE &amp; HONOR</span>
</header>
<canvas id="c"></canvas>
<div id="loading">
    <div class="ld-logo">MONTE OLIMPO</div>
    <div class="ld-sub">COMBATE · HONOR · ARENA</div>
    <div class="ld-bar"><div class="ld-fill" id="ld-fill"></div></div>
    <div class="ld-msg" id="ld-msg">INITIALIZING…</div>
</div>
<div id="interact-hint">[ E ] TALK TO <span id="hint-name"></span></div>
<div id="ctrl-hint">
    <span>W</span><span>A</span><span>S</span><span>D</span> MOVE &nbsp;
    <span>E</span> INTERACT
</div>
<div id="cam-hint">🖱 DRAG to rotate · SCROLL to zoom</div>
<div id="npc-modal">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-card">
        <div class="modal-accent" id="modal-accent"></div>
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-badge" id="modal-badge">DEITY</div>
        <div class="modal-name" id="modal-name">—</div>
        <div class="modal-title" id="modal-title">—</div>
        <div class="modal-blurb" id="modal-blurb">…</div>
        <a id="modal-btn" class="modal-btn modal-btn-primary" href="#">ENTER GAME</a>
    </div>
</div>

<script>
window.__KND_DISTRICT_BOOT = {
    HERO_MODEL_URL: <?php echo json_encode($_heroModelUrl); ?>,
    PLAYER_NAME: <?php echo json_encode($_playerName); ?>,
    NPCS_DATA: <?php echo json_encode($NPCS_PHP, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,
    NEXUS_RT_UID: <?php echo (int)(current_user_id() ?? 0); ?>,
};
</script>

<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"
  }
}
</script>
<script type="module">
import * as THREE from 'three';
import { GLTFLoader }       from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader }      from 'three/addons/loaders/DRACOLoader.js';
import { OrbitControls }    from 'three/addons/controls/OrbitControls.js';
import { EffectComposer }   from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass }       from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass }  from 'three/addons/postprocessing/UnrealBloomPass.js';
import { createNexusDistrictRealtime } from './js/nexus-district-realtime.js';

const { HERO_MODEL_URL, PLAYER_NAME, NPCS_DATA, NEXUS_RT_UID } = window.__KND_DISTRICT_BOOT;

// ── Constants ────────────────────────────────────────────────────────────────
const GRID        = 20;
const MOVE_SPEED  = 7;
const INTERACT_DIST = 2.6;
const ISO_FWD   = new THREE.Vector3(-0.707, 0, -0.707);
const ISO_BACK  = new THREE.Vector3( 0.707, 0,  0.707);
const ISO_LEFT  = new THREE.Vector3(-0.707, 0,  0.707);
const ISO_RIGHT = new THREE.Vector3( 0.707, 0, -0.707);

// ── Renderer ─────────────────────────────────────────────────────────────────
const canvas   = document.getElementById('c');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.35; // Sol mediterráneo pleno sobre el Monte Olimpo
renderer.outputColorSpace = THREE.SRGBColorSpace;

const scene = new THREE.Scene();
// Fog más suave: templo abierto al cielo — no cortar la perspectiva a media distancia
scene.fog   = new THREE.FogExp2(0x0d0800, 0.018);

// ── Camera ────────────────────────────────────────────────────────────────────
const camera = new THREE.PerspectiveCamera(55, canvas.clientWidth / canvas.clientHeight, 0.1, 200);
camera.position.set(GRID/2 + 14, 22, GRID/2 + 14);
camera.lookAt(GRID/2, 0, GRID/2);

// ── OrbitControls ────────────────────────────────────────────────────────────
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping    = true;
controls.dampingFactor    = 0.07;
controls.minDistance      = 6;
controls.maxDistance      = 55;
controls.maxPolarAngle    = Math.PI / 2.1;
controls.target.set(GRID/2, 0, GRID/2);
controls.update();

// ── Post-processing ───────────────────────────────────────────────────────────
const W = canvas.clientWidth, H = canvas.clientHeight;
const composer = new EffectComposer(renderer);
composer.addPass(new RenderPass(scene, camera));
// Bloom calibrado para deidades: threshold bajo capta brillo de fuego/oro, radius amplio para halo divino
composer.addPass(new UnrealBloomPass(new THREE.Vector2(W, H), 0.75, 0.55, 0.60));

function onResize() {
    const w = canvas.clientWidth, h = canvas.clientHeight;
    renderer.setSize(w, h, false);
    composer.setSize(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
}
window.addEventListener('resize', onResize);
onResize();

// ── State ─────────────────────────────────────────────────────────────────────
const _dracoLoader = new DRACOLoader();
_dracoLoader.setDecoderPath('https://www.gstatic.com/draco/v1/decoders/');
const loader     = new GLTFLoader();
loader.setDRACOLoader(_dracoLoader);
const clock      = new THREE.Clock();
const keys       = {};
const heroPos    = new THREE.Vector3(GRID/2, 0, GRID/2);
let heroMesh     = null;
let heroMixer    = null;
/** Diccionario de acciones de animación del héroe { [clipName]: AnimationAction } */
let heroActions  = {};
const npcObjects = [];
const animObjects= [];
let activeNpcIdx = -1;
const hintEl     = document.getElementById('interact-hint');
const hintName   = document.getElementById('hint-name');
let nexusRt      = null;

window.addEventListener('keydown', e => {
    keys[e.code] = true;
    if (e.code === 'KeyE') tryInteract();
    if (e.code === 'Escape') closeModal();
});
window.addEventListener('keyup', e => { keys[e.code] = false; });

// ── Progress ──────────────────────────────────────────────────────────────────
let loadSteps = 0, loadDone = 0;
const ldFill = document.getElementById('ld-fill');
const ldMsg  = document.getElementById('ld-msg');
function progStep(msg) {
    loadDone++;
    ldFill.style.width = Math.min(100, (loadDone / Math.max(loadSteps,1)) * 100) + '%';
    if (msg) ldMsg.textContent = msg;
}
function hideLoading() {
    const el = document.getElementById('loading');
    el.classList.add('hidden');
    setTimeout(() => el.remove(), 700);
}

// ── GLB Helpers ───────────────────────────────────────────────────────────────
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

/** Load a GLB and return a wrapper Group with the model grounded (feet at y=0). */
async function loadGroundedGLB(url, targetHeight) {
    const gltf = await loader.loadAsync(url);
    normalizeGltf(gltf);
    const model = gltf.scene;
    // Scale to target height
    const rawBox = new THREE.Box3().setFromObject(model);
    const rawH   = rawBox.max.y - rawBox.min.y;
    const scale  = targetHeight / Math.max(rawH, 0.001);
    model.scale.setScalar(scale);
    // Offset so feet sit on y=0
    const scaledBox = new THREE.Box3().setFromObject(model);
    model.position.y = -scaledBox.min.y;
    const wrapper = new THREE.Group();
    wrapper.add(model);
    let mixer = null;
    const actions = {};
    if (gltf.animations.length > 0) {
        mixer = new THREE.AnimationMixer(model);
        gltf.animations.forEach(clip => { actions[clip.name] = mixer.clipAction(clip); });
        // Detección inteligente: prefiere 'idle', luego primer clip
        const idleClip = gltf.animations.find(c => {
            const n = c.name.toLowerCase();
            return n.includes('idle') || n.includes('stand') || n.includes('t-pose') || n.includes('tpose');
        }) || gltf.animations[0];
        actions[idleClip.name].setLoop(THREE.LoopRepeat, Infinity).play();
    }
    return { wrapper, mixer, actions };
}

/**
 * Cambia la animación del héroe con cross-fade suave.
 * @param {string} name  Nombre exacto del clip (como aparece en consola)
 */
function playAnimation(name) {
    if (!heroActions[name]) {
        console.warn('[hero] animation not found:', name, '| available:', Object.keys(heroActions));
        return;
    }
    Object.values(heroActions).forEach(a => a.fadeOut(0.2));
    heroActions[name].reset().fadeIn(0.2).play();
}

function makeNameLabel(name, color) {
    const c2 = document.createElement('canvas');
    c2.width = 256; c2.height = 52;
    const ctx = c2.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0.6)';
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.fill();
    ctx.strokeStyle = color; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.roundRect(6, 6, 244, 40, 8); ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 13px Orbitron, monospace';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(name, 128, 26);
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c2), transparent: true, depthTest: false }));
    sp.scale.set(2.4, 0.45, 1);
    return sp;
}

// ── Scene ─────────────────────────────────────────────────────────────────────
function buildScene() {
    // Ambient global — sol mediterráneo fuerte, iluminación base tipo día pleno
    scene.add(new THREE.AmbientLight(0x3a2800, 1.8));
    // Hemisphere: golden sky / deep shadow ground — defines the divine atmosphere
    scene.add(new THREE.HemisphereLight(0xffd060, 0x2a1400, 1.5));

    // Key light — high-angle Greek sun, harsh and directional for hard shadows
    const sun = new THREE.DirectionalLight(0xffcc66, 2.2);
    sun.position.set(15, 30, 10);
    sun.castShadow = true;
    sun.shadow.mapSize.setScalar(2048);
    sun.shadow.bias = -0.0004;
    sun.shadow.camera.near = 0.5; sun.shadow.camera.far = 80;
    sun.shadow.camera.left = -22; sun.shadow.camera.right = 22;
    sun.shadow.camera.top = 22; sun.shadow.camera.bottom = -22;
    scene.add(sun);

    // Rim light — backlit orange glow from fire bowls / torches
    const rim1 = new THREE.DirectionalLight(0xff6600, 0.85);
    rim1.position.set(-12, 6, -10);
    scene.add(rim1);

    // Secondary rim — electric Zeus blue-gold, adds divine highlight on NPCs
    const rim2 = new THREE.DirectionalLight(0xffe060, 0.6);
    rim2.position.set(12, 4, -16);
    scene.add(rim2);

    // Underlight — subtle bounced light from marble floor
    const bounce = new THREE.DirectionalLight(0xeedd99, 0.18);
    bounce.position.set(GRID/2, -3, GRID/2);
    scene.add(bounce);

    buildTempleFloor();
    buildColumns();
    buildAltar();
    buildFireBowls();
    buildStatues();
    buildParticles();
    buildSky();
}

function buildTempleFloor() {
    // Stone floor
    // Mármol Pentélico: roughness 0.35 = superficie pulida con reflexión difusa visible
    const mat = new THREE.MeshStandardMaterial({ color: 0xddd0b0, roughness: 0.35, metalness: 0.08 });
    const floor = new THREE.Mesh(new THREE.BoxGeometry(GRID, 0.2, GRID), mat);
    floor.position.set(GRID/2, -0.1, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    // Grid of stone tiles
    const tileMat = new THREE.LineBasicMaterial({ color: 0xaa9966, transparent: true, opacity: 0.25 });
    for (let i = 0; i <= GRID; i += 2) {
        const h = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(i, 0.01, 0), new THREE.Vector3(i, 0.01, GRID)]);
        const v = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, 0.01, i), new THREE.Vector3(GRID, 0.01, i)]);
        scene.add(new THREE.Line(h, tileMat));
        scene.add(new THREE.Line(v, tileMat));
    }

    // Raised platform in center
    // Plataforma elevada con más lustre — punto focal de los jugadores
    const platMat = new THREE.MeshStandardMaterial({ color: 0xf0e4c4, roughness: 0.28, metalness: 0.12 });
    const platform = new THREE.Mesh(new THREE.BoxGeometry(8, 0.3, 8), platMat);
    platform.position.set(GRID/2, 0.15, GRID/2);
    platform.receiveShadow = true;
    scene.add(platform);
}

function buildColumns() {
    const colPositions = [
        [2.5, 0, 2.5], [17.5, 0, 2.5], [2.5, 0, 17.5], [17.5, 0, 17.5],
        [2.5, 0, 10], [17.5, 0, 10], [10, 0, 2.5], [10, 0, 17.5],
    ];
    const colMat = new THREE.MeshStandardMaterial({ color: 0xf0e6c8, roughness: 0.6, metalness: 0.1 });
    const capMat = new THREE.MeshStandardMaterial({ color: 0xe8d5a8, roughness: 0.5, metalness: 0.15 });

    colPositions.forEach(([x,,z]) => {
        // Shaft
        const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.35, 4.5, 14), colMat);
        shaft.position.set(x, 2.25, z);
        shaft.castShadow = shaft.receiveShadow = true;
        scene.add(shaft);
        // Base & capital
        [0.12, 4.6].forEach(y => {
            const cap = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.22, 0.9), capMat);
            cap.position.set(x, y, z);
            scene.add(cap);
        });
    });
}

function buildAltar() {
    const altMat = new THREE.MeshStandardMaterial({ color: 0x9a7a3a, roughness: 0.6, metalness: 0.4 });
    const altar = new THREE.Mesh(new THREE.BoxGeometry(2, 1.2, 1.2), altMat);
    altar.position.set(GRID/2, 0.6, GRID/2 - 3);
    altar.castShadow = altar.receiveShadow = true;
    scene.add(altar);

    // Gold trim
    const trimMat = new THREE.MeshStandardMaterial({ color: 0xffd600, emissive: 0xffd600, emissiveIntensity: 0.4, metalness: 0.9, roughness: 0.2 });
    const trim = new THREE.Mesh(new THREE.BoxGeometry(2.1, 0.08, 1.3), trimMat);
    trim.position.set(GRID/2, 1.24, GRID/2 - 3);
    scene.add(trim);
    animObjects.push({ type: 'emissive_flicker', mat: trimMat, base: 0.4, speed: 1.5 });
}

function buildFireBowls() {
    const bowlPos = [[4, 0, 4], [16, 0, 4], [4, 0, 16], [16, 0, 16]];
    bowlPos.forEach(([x,,z], i) => {
        // Stand
        const stand = new THREE.Mesh(
            new THREE.CylinderGeometry(0.08, 0.12, 2, 8),
            new THREE.MeshStandardMaterial({ color: 0x554422, roughness: 0.5, metalness: 0.8 })
        );
        stand.position.set(x, 1, z);
        scene.add(stand);
        // Bowl
        const bowl = new THREE.Mesh(
            new THREE.CylinderGeometry(0.4, 0.2, 0.3, 12, 1, true),
            new THREE.MeshStandardMaterial({ color: 0x885522, roughness: 0.4, metalness: 0.7 })
        );
        bowl.position.set(x, 2.15, z);
        scene.add(bowl);
        // Flame glow
        const flamePt = new THREE.PointLight(0xff8800, 1.4, 6);
        flamePt.position.set(x, 2.5, z);
        scene.add(flamePt);
        animObjects.push({ type: 'emissive_flicker', light: flamePt, base: 1.4, speed: 3 + i * 0.5 });

        // Fire particles sprite
        const fireMat = new THREE.MeshBasicMaterial({ color: 0xff6600, transparent: true, opacity: 0.8 });
        const fire = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.55, 8), fireMat);
        fire.position.set(x, 2.55, z);
        scene.add(fire);
        animObjects.push({ type: 'float', mesh: fire, base: 2.55, amp: 0.08, speed: 4 + i * 0.3 });
        animObjects.push({ type: 'emissive_flicker_basic', mat: fireMat, base: 0.8, speed: 5 + Math.random() * 2 });
    });
}

function buildStatues() {
    // Decorative obelisks
    const obeliskPos = [[3, 0, 10], [17, 0, 10], [10, 0, 3], [10, 0, 17]];
    const stoneMat = new THREE.MeshStandardMaterial({ color: 0xd0c090, roughness: 0.7, metalness: 0.1 });
    obeliskPos.forEach(([x,,z]) => {
        const ob = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.25, 3.5, 4), stoneMat);
        ob.position.set(x, 1.75, z);
        ob.castShadow = true;
        scene.add(ob);
        // Gold tip
        const tip = new THREE.Mesh(new THREE.ConeGeometry(0.1, 0.35, 4), new THREE.MeshStandardMaterial({ color: 0xffd600, emissive: 0xffd600, emissiveIntensity: 0.6, metalness: 0.9 }));
        tip.position.set(x, 3.7, z);
        scene.add(tip);
        animObjects.push({ type: 'emissive_flicker', mat: tip.material, base: 0.6, speed: 1.2 });
    });
}

function buildParticles() {
    const N = 80;
    const positions = new Float32Array(N * 3);
    const vel = [];
    for (let i = 0; i < N; i++) {
        positions[i*3]   = Math.random() * GRID;
        positions[i*3+1] = Math.random() * 5;
        positions[i*3+2] = Math.random() * GRID;
        vel.push(new THREE.Vector3((Math.random()-.5)*.2, Math.random()*.12+.03, (Math.random()-.5)*.2));
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const mat = new THREE.PointsMaterial({ color: 0xffd600, size: 0.07, transparent: true, opacity: 0.4, depthWrite: false });
    scene.add(new THREE.Points(geo, mat));
    animObjects.push({ type: 'particles_drift', geo, positions, vel, N, bounds: GRID });
}

function buildSky() {
    const sky = new THREE.Mesh(
        new THREE.SphereGeometry(90, 16, 16),
        new THREE.MeshBasicMaterial({ color: 0x0a0500, side: THREE.BackSide })
    );
    scene.add(sky);
}

// ── Hero ──────────────────────────────────────────────────────────────────────
async function spawnHero() {
    progStep('LOADING CHAMPION…');
    if (HERO_MODEL_URL) {
        try {
            const { wrapper, mixer, actions } = await loadGroundedGLB(HERO_MODEL_URL, 1.8);
            heroMesh    = wrapper;
            heroMixer   = mixer;
            heroActions = actions;
            console.log('[hero] animations disponibles:', Object.keys(heroActions));
            heroMesh.position.copy(heroPos);
            scene.add(heroMesh);
        } catch(e) { console.warn('Hero fail:', e); spawnHeroFallback(); }
    } else { spawnHeroFallback(); }
    progStep('CHAMPION READY');
}
function spawnHeroFallback() {
    heroMesh = new THREE.Mesh(
        new THREE.CapsuleGeometry(0.3, 1.0, 4, 8),
        new THREE.MeshStandardMaterial({ color: 0xffd600, emissive: 0xffd600, emissiveIntensity: 0.3, roughness: 0.5 })
    );
    heroMesh.position.copy(heroPos);
    scene.add(heroMesh);
}

// ── NPCs ──────────────────────────────────────────────────────────────────────
async function spawnNPCs() {
    for (const npc of NPCS_DATA) {
        progStep('SUMMONING ' + npc.name.toUpperCase() + '…');
        const wp0 = npc.waypoints[0];
        let obj, mixer = null;

        try {
            const result = await loadGroundedGLB(npc.glb, 1.9);
            obj   = result.wrapper;
            mixer = result.mixer;
            obj.position.set(wp0[0], 0, wp0[2]);
            scene.add(obj);
        } catch(e) {
            console.warn('NPC fail:', npc.glb, e);
            const col = parseInt(npc.color.replace('#',''), 16);
            obj = new THREE.Mesh(
                new THREE.CapsuleGeometry(0.35, 1.1, 4, 8),
                new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.35, roughness: 0.6 })
            );
            obj.position.set(wp0[0], 0.9, wp0[2]);
            scene.add(obj);
        }

        const label = makeNameLabel(npc.name, npc.color);
        label.position.set(0, 2.8, 0);
        obj.add(label);

        const col = parseInt(npc.color.replace('#',''), 16);
        const disc = new THREE.Mesh(
            new THREE.CircleGeometry(0.55, 24),
            new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.35, side: THREE.DoubleSide, depthWrite: false })
        );
        disc.rotation.x = -Math.PI / 2;
        disc.position.set(wp0[0], 0.01, wp0[2]);
        scene.add(disc);
        animObjects.push({ type: 'pulse', mat: disc.material, base: 0.35, amp: 0.2, speed: 1.6 });

        const ringMat = new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.65, 0.78, 32), ringMat);
        ring.rotation.x = -Math.PI / 2;
        ring.position.set(wp0[0], 0.02, wp0[2]);
        scene.add(ring);

        npcObjects.push({ npc, obj, disc, ring, ringMat, wpIdx: 0, wpT: 0, mixer, isNear: false });
    }
}

// ── Update ────────────────────────────────────────────────────────────────────
function updateHero(dt) {
    if (document.getElementById('npc-modal').classList.contains('open')) return;
    const v = new THREE.Vector3();
    if (keys['KeyW'] || keys['ArrowUp'])    v.add(ISO_FWD);
    if (keys['KeyS'] || keys['ArrowDown'])  v.add(ISO_BACK);
    if (keys['KeyA'] || keys['ArrowLeft'])  v.add(ISO_LEFT);
    if (keys['KeyD'] || keys['ArrowRight']) v.add(ISO_RIGHT);
    if (v.lengthSq() > 0) {
        v.normalize().multiplyScalar(MOVE_SPEED * dt);
        heroPos.x = Math.max(0.8, Math.min(GRID - 0.8, heroPos.x + v.x));
        heroPos.z = Math.max(0.8, Math.min(GRID - 0.8, heroPos.z + v.z));
        if (heroMesh) {
            heroMesh.position.copy(heroPos);
            heroMesh.rotation.y = Math.atan2(v.x, v.z);
        }
    }
    // Smoothly follow hero with orbit target
    controls.target.lerp(heroPos, 0.08);
    controls.update();
}

const _tmpV = new THREE.Vector3();
function updateNPCs(dt) {
    let nearestDist = Infinity, nearestIdx = -1;
    npcObjects.forEach((n, i) => {
        if (n.mixer) n.mixer.update(dt);
        const wps  = n.npc.waypoints;
        const next = wps[(n.wpIdx + 1) % wps.length];
        const cur  = wps[n.wpIdx];
        n.wpT = (n.wpT || 0) + dt * 0.8;
        const t  = Math.min(n.wpT, 1);
        const px = cur[0] + (next[0] - cur[0]) * t;
        const pz = cur[2] + (next[2] - cur[2]) * t;
        n.obj.position.set(px, 0, pz);
        n.disc.position.set(px, 0.01, pz);
        n.ring.position.set(px, 0.02, pz);
        const dx = next[0]-cur[0], dz = next[2]-cur[2];
        if (Math.abs(dx)+Math.abs(dz)>0.01) n.obj.rotation.y = Math.atan2(dx, dz);
        if (t >= 1) { n.wpIdx = (n.wpIdx + 1) % wps.length; n.wpT = 0; }
        _tmpV.set(px, 0, pz);
        const dist = _tmpV.distanceTo(heroPos);
        if (dist < INTERACT_DIST && dist < nearestDist) { nearestDist = dist; nearestIdx = i; }
        n.isNear = dist < INTERACT_DIST;
    });
    npcObjects.forEach(n => {
        n.ringMat.opacity = n.isNear ? (0.5 + 0.3 * Math.sin(Date.now() * 0.004)) : 0;
    });
    activeNpcIdx = nearestIdx;
    if (nearestIdx >= 0 && !document.getElementById('npc-modal').classList.contains('open')) {
        hintName.textContent = npcObjects[nearestIdx].npc.name.toUpperCase();
        hintEl.classList.add('show');
    } else {
        hintEl.classList.remove('show');
    }
}

function updateAnimObjects(t, dt) {
    animObjects.forEach(a => {
        switch (a.type) {
            case 'pulse':
                a.mat.opacity = a.base + a.amp * Math.sin(t * a.speed);
                break;
            case 'rotate_y':
                a.mesh.rotation.y += a.speed * dt;
                break;
            case 'float':
                a.mesh.position.y = a.base + a.amp * Math.sin(t * a.speed);
                break;
            case 'emissive_flicker':
                if (a.mat)   a.mat.emissiveIntensity = a.base * (0.7 + 0.3 * Math.abs(Math.sin(t * a.speed)));
                if (a.light) a.light.intensity = a.base * (0.6 + 0.4 * Math.abs(Math.sin(t * a.speed)));
                break;
            case 'emissive_flicker_basic':
                a.mat.opacity = a.base * (0.6 + 0.4 * Math.abs(Math.sin(t * a.speed)));
                break;
            case 'particles_drift': {
                const pos = a.positions;
                for (let i = 0; i < a.N; i++) {
                    pos[i*3]   += a.vel[i].x * dt;
                    pos[i*3+1] += a.vel[i].y * dt;
                    pos[i*3+2] += a.vel[i].z * dt;
                    if (pos[i*3+1] > 5) { pos[i*3+1] = 0; pos[i*3] = Math.random()*a.bounds; pos[i*3+2] = Math.random()*a.bounds; }
                    if (pos[i*3]<0||pos[i*3]>a.bounds) a.vel[i].x*=-1;
                    if (pos[i*3+2]<0||pos[i*3+2]>a.bounds) a.vel[i].z*=-1;
                }
                a.geo.attributes.position.needsUpdate = true;
                break;
            }
        }
    });
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function tryInteract() {
    if (activeNpcIdx < 0 || document.getElementById('npc-modal').classList.contains('open')) return;
    openModal(npcObjects[activeNpcIdx].npc);
}
function openModal(npc) {
    document.getElementById('modal-accent').style.background = `linear-gradient(90deg,transparent,${npc.color},transparent)`;
    document.getElementById('modal-badge').textContent = npc.title;
    document.getElementById('modal-badge').style.cssText += `;border-color:${npc.color}55;color:${npc.color}`;
    document.getElementById('modal-name').textContent = npc.name;
    document.getElementById('modal-name').style.textShadow = `0 0 14px ${npc.color}80`;
    document.getElementById('modal-title').textContent = npc.title;
    document.getElementById('modal-title').style.color = npc.color + 'cc';
    document.getElementById('modal-blurb').textContent = npc.blurb;
    const btn = document.getElementById('modal-btn');
    if (npc.coming_soon || !npc.game_url) {
        btn.textContent = '⏳ COMING SOON';
        btn.className = 'modal-btn modal-btn-soon';
        btn.removeAttribute('href'); btn.onclick = null;
    } else {
        btn.textContent = '▶ ' + npc.game_label.toUpperCase();
        btn.className = 'modal-btn modal-btn-primary';
        btn.href = npc.game_url;
        btn.style.background = `linear-gradient(135deg,${npc.color},${npc.color}99)`;
    }
    document.getElementById('npc-modal').classList.add('open');
    hintEl.classList.remove('show');
}
window.closeModal = () => document.getElementById('npc-modal').classList.remove('open');

// ── Loop ──────────────────────────────────────────────────────────────────────
function animate() {
    requestAnimationFrame(animate);
    const dt = Math.min(clock.getDelta(), 0.05);
    const t  = clock.getElapsedTime();
    updateHero(dt);
    updateNPCs(dt);
    updateAnimObjects(t, dt);
    if (heroMixer) heroMixer.update(dt);
    if (nexusRt) nexusRt.update(dt);
    composer.render();
}

// ── Boot ──────────────────────────────────────────────────────────────────────
(async function boot() {
    loadSteps = 2 + NPCS_DATA.length + 4;
    progStep('BUILDING OLYMPUS…');
    buildScene();
    progStep('OLYMPUS READY');
    await spawnHero();
    await spawnNPCs();
    progStep('STARTING…');
    nexusRt = createNexusDistrictRealtime({
        scene,
        districtId: 'olimpo',
        userId: NEXUS_RT_UID,
        displayName: PLAYER_NAME,
        colorBody: '#ffd600',
        colorVisor: '#ff6b00',
        colorEcho: '#66ccff',
        heroModelUrl: HERO_MODEL_URL || null,
        getPosition: () => ({ x: heroPos.x, z: heroPos.z }),
        getRotationY: () => (heroMesh ? heroMesh.rotation.y : 0),
    });
    if (NEXUS_RT_UID > 0) nexusRt.start();
    animate();
    hideLoading();
})();
</script>
</body>
</html>

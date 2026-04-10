<?php
/**
 * TESLA LAB — District Room v2
 * Isometric 3D lab: WASD movement, real GLB NPCs, E-key interaction.
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
} catch (\Throwable $e) { error_log('tesla-lab hero: ' . $e->getMessage()); }

$NPCS_PHP = [
    [
        'id'         => 'albert_einstein',
        'name'       => 'Albert Einstein',
        'title'      => 'Physicist · Relativity',
        'color'      => '#00e8ff',
        'blurb'      => 'The universe has secrets — and I have equations. Are you brave enough to duel with knowledge?',
        'glb'        => '/assets/avatars/models/legendary/albert_einstein.glb',
        'game_url'   => '/games/knowledge-duel.php',
        'game_label' => 'Knowledge Duel',
        'coming_soon'=> false,
        'waypoints'  => [[5, 0, 5], [5, 0, 8], [8, 0, 8], [8, 0, 5]],
    ],
    [
        'id'         => 'nicola_tesla',
        'name'       => 'Nicola Tesla',
        'title'      => 'Engineer · Electricity',
        'color'      => '#9b30ff',
        'blurb'      => 'Alternating currents, alternating minds. My lab welcomes those who dare to think differently.',
        'glb'        => '/assets/avatars/models/rare/nicola_tesla.glb',
        'game_url'   => '/games/knowledge-duel.php',
        'game_label' => 'Knowledge Duel',
        'coming_soon'=> false,
        'waypoints'  => [[14, 0, 5], [14, 0, 8], [11, 0, 8], [11, 0, 5]],
    ],
    [
        'id'         => 'benjamin_franklin',
        'name'       => 'Benjamin Franklin',
        'title'      => 'Inventor · Statesman',
        'color'      => '#ffd600',
        'blurb'      => 'Lightning once obeyed me. Perhaps knowledge shall obey you.',
        'glb'        => '/assets/avatars/models/legendary/benjamin_franklin.glb',
        'game_url'   => '/games/knowledge-duel.php',
        'game_label' => 'Knowledge Duel',
        'coming_soon'=> false,
        'waypoints'  => [[5, 0, 14], [8, 0, 14], [8, 0, 11], [5, 0, 11]],
    ],
    [
        'id'         => 'alan_turing',
        'name'       => 'Alan Turing',
        'title'      => 'Mathematician · Codebreaker',
        'color'      => '#00ff88',
        'blurb'      => 'Every code can be broken. Every mystery, solved. Show me what your mind can decode.',
        'glb'        => '/assets/avatars/models/rare/alang_turing.glb',
        'game_url'   => null,
        'game_label' => 'Code Breaker',
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
<title>TESLA LAB · NEXUS CITY</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#000;font-family:'Rajdhani',sans-serif}
#c{position:fixed;top:48px;left:0;right:0;bottom:0;display:block;width:100%}
/* Topbar */
#tb{position:fixed;top:0;left:0;right:0;height:48px;background:rgba(0,0,0,.88);border-bottom:1px solid rgba(0,232,255,.18);display:flex;align-items:center;gap:12px;padding:0 16px;z-index:30;backdrop-filter:blur(8px)}
.back-btn{display:flex;align-items:center;gap:6px;color:#00e8ff;text-decoration:none;font-family:'Orbitron',monospace;font-size:.65rem;letter-spacing:.12em;opacity:.8;transition:opacity .2s}
.back-btn:hover{opacity:1}
.back-btn svg{width:16px;height:16px;stroke:#00e8ff;stroke-width:2.5;fill:none}
#tb-title{font-family:'Orbitron',monospace;font-size:.7rem;font-weight:700;letter-spacing:.15em;color:#00e8ff;text-shadow:0 0 10px rgba(0,232,255,.5)}
#tb-sub{font-family:'Share Tech Mono',monospace;font-size:.6rem;letter-spacing:.12em;color:rgba(0,232,255,.45);margin-left:4px}

/* Loading */
#loading{position:fixed;inset:0;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:50;transition:opacity .6s}
#loading.hidden{opacity:0;pointer-events:none}
.ld-logo{font-family:'Orbitron',monospace;font-size:2rem;font-weight:900;color:#00e8ff;text-shadow:0 0 24px #00e8ff,0 0 60px rgba(0,232,255,.4);letter-spacing:.2em;margin-bottom:8px}
.ld-sub{font-family:'Share Tech Mono',monospace;font-size:.75rem;letter-spacing:.2em;color:rgba(0,232,255,.5);margin-bottom:40px}
.ld-bar{width:280px;height:3px;background:rgba(0,232,255,.15);border-radius:2px;overflow:hidden}
.ld-fill{height:100%;width:0;background:linear-gradient(90deg,#00e8ff,#9b30ff);transition:width .3s}
.ld-msg{font-family:'Share Tech Mono',monospace;font-size:.65rem;color:rgba(0,232,255,.4);letter-spacing:.15em;margin-top:10px}

/* Interact hint */
#interact-hint{position:fixed;bottom:120px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.82);border:1px solid rgba(0,232,255,.45);border-radius:10px;padding:9px 22px;font-family:'Orbitron',monospace;font-size:.65rem;letter-spacing:.12em;color:#00e8ff;text-shadow:0 0 8px #00e8ff;pointer-events:none;z-index:20;opacity:0;transition:opacity .25s;white-space:nowrap}
#interact-hint.show{opacity:1}

/* Controls hint */
#ctrl-hint{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);opacity:.45;pointer-events:none;z-index:10}
#ctrl-hint span{display:inline-block;background:rgba(0,232,255,.08);border:1px solid rgba(0,232,255,.25);border-radius:5px;padding:3px 9px;font-family:'Share Tech Mono',monospace;font-size:.6rem;letter-spacing:.1em;color:#00e8ff;margin:0 3px}

/* NPC Modal */
#npc-modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:40;pointer-events:none;opacity:0;transition:opacity .3s}
#npc-modal.open{pointer-events:all;opacity:1}
.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(6px)}
.modal-card{position:relative;background:linear-gradient(160deg,rgba(0,20,35,.97),rgba(0,5,18,.97));border:1px solid rgba(0,232,255,.3);border-radius:16px;padding:32px 36px 28px;width:min(480px,92vw);box-shadow:0 0 60px rgba(0,232,255,.15),inset 0 0 40px rgba(0,232,255,.03)}
.modal-accent{position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0;background:linear-gradient(90deg,transparent,#00e8ff,transparent)}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;color:rgba(0,232,255,.5);font-size:1.3rem;cursor:pointer;line-height:1;transition:color .2s}
.modal-close:hover{color:#00e8ff}
.modal-badge{display:inline-block;font-family:'Share Tech Mono',monospace;font-size:.58rem;letter-spacing:.18em;padding:3px 10px;border-radius:4px;margin-bottom:12px;background:rgba(0,232,255,.12);border:1px solid rgba(0,232,255,.3);color:#00e8ff}
.modal-name{font-family:'Orbitron',monospace;font-size:1.35rem;font-weight:700;color:#fff;text-shadow:0 0 14px rgba(0,232,255,.5);margin-bottom:4px}
.modal-title{font-size:.8rem;letter-spacing:.1em;color:rgba(0,232,255,.7);margin-bottom:18px}
.modal-blurb{font-size:.9rem;line-height:1.6;color:rgba(255,255,255,.75);margin-bottom:24px;font-style:italic}
.modal-btn{display:block;width:100%;padding:13px;font-family:'Orbitron',monospace;font-size:.75rem;font-weight:700;letter-spacing:.12em;border:none;border-radius:10px;cursor:pointer;transition:all .2s;text-decoration:none;text-align:center}
.modal-btn-primary{background:linear-gradient(135deg,#00e8ff,#0099bb);color:#000;box-shadow:0 0 20px rgba(0,232,255,.4)}
.modal-btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 28px rgba(0,232,255,.6)}
.modal-btn-soon{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.3);cursor:not-allowed}

/* CRT transition */
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
  <span id="tb-title">TESLA LAB</span>
  <span id="tb-sub">DISTRICT · CIENCIA &amp; CONOCIMIENTO</span>
</header>
<canvas id="c"></canvas>

<!-- Loading -->
<div id="loading">
    <div class="ld-logo">TESLA LAB</div>
    <div class="ld-sub">KNOWLEDGE · DISCOVERY · INNOVATION</div>
    <div class="ld-bar"><div class="ld-fill" id="ld-fill"></div></div>
    <div class="ld-msg" id="ld-msg">INITIALIZING…</div>
</div>

<!-- Interact hint -->
<div id="interact-hint" id="interact-hint">[ E ] TALK TO <span id="hint-name"></span></div>

<!-- Controls -->
<div id="ctrl-hint">
    <span>W</span><span>A</span><span>S</span><span>D</span> MOVE &nbsp;
    <span>E</span> INTERACT
</div>

<!-- NPC Modal -->
<div id="npc-modal">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-card">
        <div class="modal-accent" id="modal-accent"></div>
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-badge" id="modal-badge">SCIENTIST</div>
        <div class="modal-name" id="modal-name">—</div>
        <div class="modal-title" id="modal-title">—</div>
        <div class="modal-blurb" id="modal-blurb">…</div>
        <a id="modal-btn" class="modal-btn modal-btn-primary" href="#">ENTER GAME</a>
    </div>
</div>

<script>
// PHP → JS (global): el bloque type=module no comparte scope con const de este script
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
const ISO_FWD     = new THREE.Vector3(-0.707, 0, -0.707);
const ISO_BACK    = new THREE.Vector3( 0.707, 0,  0.707);
const ISO_LEFT    = new THREE.Vector3(-0.707, 0,  0.707);
const ISO_RIGHT   = new THREE.Vector3( 0.707, 0, -0.707);

// ── Globals ──────────────────────────────────────────────────────────────────
const canvas   = document.getElementById('c');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.2; // Lab con luz fluorescente fuerte y neón
renderer.outputColorSpace = THREE.SRGBColorSpace;

const scene  = new THREE.Scene();
// Fog lab: color azul-petróleo, densidad moderada — nebulosa de experimentos
scene.fog    = new THREE.FogExp2(0x000c1a, 0.022);

const camera = new THREE.PerspectiveCamera(55, canvas.clientWidth / canvas.clientHeight, 0.1, 200);
camera.position.set(GRID/2 + 14, 22, GRID/2 + 14);
camera.lookAt(GRID/2, 0, GRID/2);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping  = true;
controls.dampingFactor  = 0.07;
controls.minDistance    = 6;
controls.maxDistance    = 55;
controls.maxPolarAngle  = Math.PI / 2.1;
controls.target.set(GRID/2, 0, GRID/2);
controls.update();

const W = canvas.clientWidth, H = canvas.clientHeight;
const composer = new EffectComposer(renderer);
composer.addPass(new RenderPass(scene, camera));
// Bloom eléctrico: threshold 0.5 capta arcos de bobina y grid lines, radius estrecho = chispas nítidas
composer.addPass(new UnrealBloomPass(new THREE.Vector2(W, H), 0.85, 0.35, 0.50));

const loader    = new GLTFLoader();
const clock     = new THREE.Clock();
const keys      = {};
const heroPos   = new THREE.Vector3(10, 0, 10);
let heroMesh    = null;
let heroMixer   = null;
const npcObjects= [];
const animObjects=[];
let activeNpcIdx = -1;
const hintEl    = document.getElementById('interact-hint');
const hintName  = document.getElementById('hint-name');
let nexusRt     = null;

// ── Resize ───────────────────────────────────────────────────────────────────
function onResize() {
    const w = canvas.clientWidth, h = canvas.clientHeight;
    renderer.setSize(w, h, false);
    composer.setSize(w, h);
    camera.aspect = w / Math.max(1, h);
    camera.updateProjectionMatrix();
}
window.addEventListener('resize', onResize);
onResize();

// ── Input ────────────────────────────────────────────────────────────────────
window.addEventListener('keydown', e => {
    keys[e.code] = true;
    if (e.code === 'KeyE') tryInteract();
    if (e.code === 'Escape') closeModal();
});
window.addEventListener('keyup', e => { keys[e.code] = false; });

// ── Progress ─────────────────────────────────────────────────────────────────
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

// ── Helpers ──────────────────────────────────────────────────────────────────
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

// ── Scene Building ────────────────────────────────────────────────────────────
function buildScene() {
    // Ambient + directional lights
    // Ambient global — base iluminación para laboratorio, luz artificial de neón
    scene.add(new THREE.AmbientLight(0x102030, 1.8));
    // Hemisphere: techo metálico frío azul / suelo de laboratorio oscuro
    scene.add(new THREE.HemisphereLight(0x1a4060, 0x0a1828, 1.4));

    // Key light — luz de fluorescente overhead, blanca-azulada y dura
    const overhead = new THREE.DirectionalLight(0xc8eeff, 1.8);
    overhead.position.set(GRID/2, 28, GRID/2);
    overhead.castShadow = true;
    overhead.shadow.mapSize.setScalar(2048);
    overhead.shadow.bias = -0.0003;
    overhead.shadow.camera.near = 0.5;
    overhead.shadow.camera.far = 80;
    overhead.shadow.camera.left = -20;
    overhead.shadow.camera.right = 20;
    overhead.shadow.camera.top = 20;
    overhead.shadow.camera.bottom = -20;
    scene.add(overhead);

    // Fill eléctrico cian — luz de las bobinas de Tesla
    const fill1 = new THREE.DirectionalLight(0x00ddff, 1.0);
    fill1.position.set(-12, 10, -8);
    scene.add(fill1);

    // Rim violeta — descarga de plasma / Einstein energy
    const fill2 = new THREE.DirectionalLight(0x8820ff, 0.6);
    fill2.position.set(12, 5, -14);
    scene.add(fill2);

    // Point light central — la bobina principal ilumina hacia abajo
    const mainCoil = new THREE.PointLight(0x00c8ff, 2.5, 12);
    mainCoil.position.set(GRID/2, 4, GRID/2);
    scene.add(mainCoil);

    buildLabFloor();
    buildLabWalls();
    buildTeslaCoils();
    buildWorkbenches();
    buildHoloStands();
    buildParticles();
    buildSkybox();
}

function buildLabFloor() {
    // Checker floor with emissive grid lines
    const floorGeo = new THREE.PlaneGeometry(GRID, GRID, GRID, GRID);
    const floorMat = new THREE.MeshStandardMaterial({
        // Suelo de laboratorio: resina epoxi oscura — rugosidad baja para reflexión de neones
        color: 0x010b14,
        roughness: 0.22,
        metalness: 0.55,
    });
    const floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(GRID/2, 0, GRID/2);
    floor.receiveShadow = true;
    scene.add(floor);

    // Glowing grid lines
    const gridMat = new THREE.LineBasicMaterial({ color: 0x00e8ff, transparent: true, opacity: 0.12 });
    for (let i = 0; i <= GRID; i++) {
        const h = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(i, 0.01, 0), new THREE.Vector3(i, 0.01, GRID)
        ]);
        const v = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(0, 0.01, i), new THREE.Vector3(GRID, 0.01, i)
        ]);
        scene.add(new THREE.Line(h, gridMat));
        scene.add(new THREE.Line(v, gridMat));
    }

    // Scan line — animated in animObjects
    const scanGeo = new THREE.PlaneGeometry(GRID, 0.06);
    const scanMat = new THREE.MeshBasicMaterial({ color: 0x00e8ff, transparent: true, opacity: 0.55, depthWrite: false });
    const scan = new THREE.Mesh(scanGeo, scanMat);
    scan.rotation.x = -Math.PI / 2;
    scan.position.set(GRID/2, 0.02, 0);
    scene.add(scan);
    animObjects.push({ type: 'scan_line', mesh: scan, speed: 4.5, min: 0, max: GRID, axis: 'z' });
}

function buildLabWalls() {
    // Back wall (z=0 side)
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x040e1c, roughness: 0.9, metalness: 0.1 });
    const backWall = new THREE.Mesh(new THREE.BoxGeometry(GRID, 5, 0.3), wallMat);
    backWall.position.set(GRID/2, 2.5, 0);
    scene.add(backWall);
    backWall.receiveShadow = true;

    const leftWall = new THREE.Mesh(new THREE.BoxGeometry(0.3, 5, GRID), wallMat);
    leftWall.position.set(0, 2.5, GRID/2);
    scene.add(leftWall);

    // LED data banks on back wall
    const LED_COUNT = 5;
    for (let i = 0; i < LED_COUNT; i++) {
        const bx = 2 + i * 3.5;
        // Panel
        const panel = new THREE.Mesh(
            new THREE.BoxGeometry(2.6, 3.2, 0.12),
            new THREE.MeshStandardMaterial({ color: 0x020810, roughness: 0.5, metalness: 0.6 })
        );
        panel.position.set(bx, 2.5, 0.25);
        scene.add(panel);

        // LED strips
        const colors = [0x00e8ff, 0x9b30ff, 0x00ff88, 0xffd600, 0xff3d56];
        const c = colors[i % colors.length];
        for (let row = 0; row < 6; row++) {
            const strip = new THREE.Mesh(
                new THREE.BoxGeometry(2.2, 0.08, 0.05),
                new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 1.2 })
            );
            strip.position.set(bx, 1.1 + row * 0.48, 0.32);
            scene.add(strip);
            animObjects.push({ type: 'emissive_flicker', mesh: strip, mat: strip.material, base: 1.2, speed: 1.5 + Math.random() * 2 });
        }
        // Point light from bank
        const pt = new THREE.PointLight(c, 0.6, 4);
        pt.position.set(bx, 2.5, 0.6);
        scene.add(pt);
        animObjects.push({ type: 'emissive_flicker', mesh: { material: { emissiveIntensity: 0 } }, mat: null, light: pt, base: 0.6, speed: 0.8 + Math.random() });
    }
}

function buildTeslaCoils() {
    const positions = [[2, 0, 2], [18, 0, 2], [2, 0, 18], [18, 0, 18]];
    const coilColor = 0x00e8ff;

    positions.forEach((pos, i) => {
        // Base cylinder
        const base = new THREE.Mesh(
            new THREE.CylinderGeometry(0.4, 0.5, 0.3, 12),
            new THREE.MeshStandardMaterial({ color: 0x111824, roughness: 0.4, metalness: 0.9 })
        );
        base.position.set(pos[0], 0.15, pos[2]);
        scene.add(base);

        // Main coil shaft
        const shaft = new THREE.Mesh(
            new THREE.CylinderGeometry(0.12, 0.18, 2.5, 12),
            new THREE.MeshStandardMaterial({ color: 0x1a2840, roughness: 0.3, metalness: 0.95 })
        );
        shaft.position.set(pos[0], 1.55, pos[2]);
        scene.add(shaft);

        // Spiral wire (torus stack)
        for (let t = 0; t < 6; t++) {
            const tor = new THREE.Mesh(
                new THREE.TorusGeometry(0.22, 0.025, 6, 16),
                new THREE.MeshStandardMaterial({ color: coilColor, emissive: coilColor, emissiveIntensity: 0.8 })
            );
            tor.position.set(pos[0], 0.5 + t * 0.35, pos[2]);
            tor.rotation.x = Math.PI / 2;
            scene.add(tor);
            animObjects.push({ type: 'emissive_flicker', mesh: tor, mat: tor.material, base: 0.8, speed: 3 + i * 0.4 });
        }

        // Top sphere
        const top = new THREE.Mesh(
            new THREE.SphereGeometry(0.28, 16, 16),
            new THREE.MeshStandardMaterial({ color: 0x001f33, roughness: 0.2, metalness: 1, emissive: coilColor, emissiveIntensity: 0.6 })
        );
        top.position.set(pos[0], 3.0, pos[2]);
        scene.add(top);
        animObjects.push({ type: 'emissive_flicker', mesh: top, mat: top.material, base: 0.6, speed: 2 + i * 0.3 });

        // Point light crackle
        const pt = new THREE.PointLight(coilColor, 1.2, 6);
        pt.position.set(pos[0], 3.2, pos[2]);
        scene.add(pt);
        animObjects.push({ type: 'emissive_flicker', mesh: null, mat: null, light: pt, base: 1.2, speed: 4 + Math.random() * 2 });
    });
}

function buildWorkbenches() {
    const benchData = [
        { x: 5, z: 3 }, { x: 10, z: 3 }, { x: 15, z: 3 },
        { x: 3, z: 8 }, { x: 17, z: 8 },
    ];
    benchData.forEach(b => {
        // Bench surface
        const surf = new THREE.Mesh(
            new THREE.BoxGeometry(2.8, 0.12, 1.2),
            new THREE.MeshStandardMaterial({ color: 0x111820, roughness: 0.5, metalness: 0.7 })
        );
        surf.position.set(b.x, 0.88, b.z);
        surf.castShadow = surf.receiveShadow = true;
        scene.add(surf);

        // Legs
        [[1.2,0,0.4],[1.2,0,-0.4],[-1.2,0,0.4],[-1.2,0,-0.4]].forEach(([dx,dy,dz]) => {
            const leg = new THREE.Mesh(
                new THREE.BoxGeometry(0.08, 0.9, 0.08),
                new THREE.MeshStandardMaterial({ color: 0x1a2840, roughness: 0.4, metalness: 0.9 })
            );
            leg.position.set(b.x + dx, 0.45, b.z + dz);
            scene.add(leg);
        });

        // Glowing screen on bench
        const sc = new THREE.Mesh(
            new THREE.BoxGeometry(0.8, 0.55, 0.06),
            new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.8, roughness: 0.3 })
        );
        sc.position.set(b.x + 0.5, 1.2, b.z - 0.4);
        sc.rotation.x = -0.35;
        scene.add(sc);
        animObjects.push({ type: 'emissive_flicker', mesh: sc, mat: sc.material, base: 0.8, speed: 1.2 + Math.random() });
    });
}

function buildHoloStands() {
    const standPos = [[9, 0, 9], [11, 0, 9], [9, 0, 11], [11, 0, 11]];
    const colors   = [0x00e8ff, 0x9b30ff, 0x00ff88, 0xffd600];
    standPos.forEach((pos, i) => {
        // Pedestal
        const ped = new THREE.Mesh(
            new THREE.CylinderGeometry(0.2, 0.25, 0.6, 10),
            new THREE.MeshStandardMaterial({ color: 0x0a1828, roughness: 0.4, metalness: 0.8 })
        );
        ped.position.set(pos[0], 0.3, pos[2]);
        scene.add(ped);

        // Holographic icosahedron
        const ico = new THREE.Mesh(
            new THREE.IcosahedronGeometry(0.38, 0),
            new THREE.MeshStandardMaterial({ color: colors[i], emissive: colors[i], emissiveIntensity: 0.9, wireframe: true, transparent: true, opacity: 0.8 })
        );
        ico.position.set(pos[0], 1.2, pos[2]);
        scene.add(ico);
        animObjects.push({ type: 'rotate_y', mesh: ico, speed: 0.8 + i * 0.2 });
        animObjects.push({ type: 'float', mesh: ico, base: 1.2, amp: 0.15, speed: 1.4 + i * 0.3 });

        // Holo base glow disc
        const disc = new THREE.Mesh(
            new THREE.CircleGeometry(0.25, 20),
            new THREE.MeshBasicMaterial({ color: colors[i], transparent: true, opacity: 0.4, side: THREE.DoubleSide, depthWrite: false })
        );
        disc.rotation.x = -Math.PI / 2;
        disc.position.set(pos[0], 0.02, pos[2]);
        scene.add(disc);
        animObjects.push({ type: 'pulse', mesh: disc, mat: disc.material, base: 0.4, amp: 0.25, speed: 1.8 + i * 0.2 });
    });
}

function buildParticles() {
    const N = 120;
    const positions = new Float32Array(N * 3);
    const vel = [];
    for (let i = 0; i < N; i++) {
        positions[i*3]   = Math.random() * GRID;
        positions[i*3+1] = Math.random() * 4;
        positions[i*3+2] = Math.random() * GRID;
        vel.push(new THREE.Vector3((Math.random()-.5)*.3, Math.random()*.15+.05, (Math.random()-.5)*.3));
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const mat = new THREE.PointsMaterial({ color: 0x00e8ff, size: 0.06, transparent: true, opacity: 0.55, depthWrite: false });
    const pts = new THREE.Points(geo, mat);
    scene.add(pts);
    animObjects.push({ type: 'particles_drift', mesh: pts, geo, positions, vel, N, bounds: GRID });
}

function buildSkybox() {
    const sky = new THREE.Mesh(
        new THREE.SphereGeometry(90, 16, 16),
        new THREE.MeshBasicMaterial({ color: 0x00050d, side: THREE.BackSide })
    );
    scene.add(sky);
}

// ── Hero spawn ───────────────────────────────────────────────────────────────
async function spawnHero() {
    progStep('LOADING AGENT…');
    if (HERO_MODEL_URL) {
        try {
            const result = await loadGroundedGLB(HERO_MODEL_URL, 1.8);
            heroMesh  = result.wrapper;
            heroMixer = result.mixer;
            heroMesh.position.copy(heroPos);
            scene.add(heroMesh);
        } catch(e) { console.warn('Hero GLB fail:', e); spawnHeroFallback(); }
    } else { spawnHeroFallback(); }
    progStep('AGENT READY');
}
function spawnHeroFallback() {
    heroMesh = new THREE.Mesh(
        new THREE.CapsuleGeometry(0.3, 1.0, 4, 8),
        new THREE.MeshStandardMaterial({ color: 0x00e8ff, emissive: 0x00e8ff, emissiveIntensity: 0.3, roughness: 0.5 })
    );
    heroMesh.position.copy(heroPos);
    scene.add(heroMesh);
}

// ── NPC spawn ────────────────────────────────────────────────────────────────
async function spawnNPCs() {
    for (const npc of NPCS_DATA) {
        progStep('LOADING ' + npc.name.toUpperCase() + '…');
        const wp0 = npc.waypoints[0];
        let obj, mixer = null;
        try {
            const result = await loadGroundedGLB(npc.glb, 1.9);
            obj   = result.wrapper;
            mixer = result.mixer;
            obj.position.set(wp0[0], 0, wp0[2]);
            scene.add(obj);
        } catch(e) {
            console.warn('NPC GLB fail:', npc.glb, e);
            const col = parseInt(npc.color.replace('#',''), 16);
            obj = new THREE.Mesh(
                new THREE.CapsuleGeometry(0.35, 1.1, 4, 8),
                new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.35, roughness: 0.6 })
            );
            obj.position.set(wp0[0], 0.9, wp0[2]);
            scene.add(obj);
        }

        // Label
        const label = makeNameLabel(npc.name, npc.color);
        label.position.set(0, 2.6, 0);
        obj.add(label);

        // Glow disc
        const disc = new THREE.Mesh(
            new THREE.CircleGeometry(0.55, 24),
            new THREE.MeshBasicMaterial({ color: parseInt(npc.color.replace('#',''),16), transparent: true, opacity: 0.35, side: THREE.DoubleSide, depthWrite: false })
        );
        disc.rotation.x = -Math.PI / 2;
        disc.position.set(wp0[0], 0.01, wp0[2]);
        scene.add(disc);
        animObjects.push({ type: 'pulse', mesh: disc, mat: disc.material, base: 0.35, amp: 0.2, speed: 1.6 });

        // Interaction ring
        const ringMat = new THREE.MeshBasicMaterial({ color: parseInt(npc.color.replace('#',''),16), transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.65, 0.78, 32), ringMat);
        ring.rotation.x = -Math.PI / 2;
        ring.position.set(wp0[0], 0.02, wp0[2]);
        scene.add(ring);

        npcObjects.push({ npc, obj, disc, ring, ringMat, wpIdx: 0, wpT: 0, mixer, isNear: false });
    }
}

// ── Movement & Camera ────────────────────────────────────────────────────────
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
    controls.target.lerp(heroPos, 0.08);
    controls.update();
}

// ── NPC patrol ───────────────────────────────────────────────────────────────
const _tmpV = new THREE.Vector3();
function updateNPCs(dt) {
    let nearestDist = Infinity, nearestIdx = -1;
    npcObjects.forEach((n, i) => {
        if (n.mixer) n.mixer.update(dt);

        // Waypoint patrol
        const wps = n.npc.waypoints;
        const next = wps[(n.wpIdx + 1) % wps.length];
        const cur  = wps[n.wpIdx];
        n.wpT = (n.wpT || 0) + dt * 0.8;
        const t = Math.min(n.wpT, 1);
        const px = cur[0] + (next[0] - cur[0]) * t;
        const pz = cur[2] + (next[2] - cur[2]) * t;
        n.obj.position.set(px, 0, pz);
        n.disc.position.set(px, 0.01, pz);
        n.ring.position.set(px, 0.02, pz);

        // Face direction
        const dx = next[0] - cur[0], dz = next[2] - cur[2];
        if (Math.abs(dx)+Math.abs(dz)>0.01) n.obj.rotation.y = Math.atan2(dx, dz);

        if (t >= 1) { n.wpIdx = (n.wpIdx + 1) % wps.length; n.wpT = 0; }

        // Distance from hero
        _tmpV.set(px, 0, pz);
        const dist = _tmpV.distanceTo(heroPos);
        if (dist < INTERACT_DIST && dist < nearestDist) {
            nearestDist = dist;
            nearestIdx = i;
        }
        n.isNear = dist < INTERACT_DIST;
    });

    // Interaction rings
    npcObjects.forEach((n, i) => {
        n.ringMat.opacity = n.isNear ? (0.5 + 0.3 * Math.sin(Date.now() * 0.004)) : 0;
    });

    // Hint
    activeNpcIdx = nearestIdx;
    if (nearestIdx >= 0 && !document.getElementById('npc-modal').classList.contains('open')) {
        hintName.textContent = npcObjects[nearestIdx].npc.name.toUpperCase();
        hintEl.classList.add('show');
    } else {
        hintEl.classList.remove('show');
    }
}

// ── Anim objects ─────────────────────────────────────────────────────────────
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
                if (a.mat) a.mat.emissiveIntensity = a.base * (0.7 + 0.3 * Math.abs(Math.sin(t * a.speed + Math.random() * 0.02)));
                if (a.light) a.light.intensity = a.base * (0.6 + 0.4 * Math.abs(Math.sin(t * a.speed)));
                break;
            case 'scan_line': {
                const pos = (t * a.speed) % a.max;
                if (a.axis === 'z') a.mesh.position.z = a.min + pos;
                else a.mesh.position.x = a.min + pos;
                break;
            }
            case 'particles_drift': {
                const pos = a.positions;
                for (let i = 0; i < a.N; i++) {
                    pos[i*3]   += a.vel[i].x * dt;
                    pos[i*3+1] += a.vel[i].y * dt;
                    pos[i*3+2] += a.vel[i].z * dt;
                    if (pos[i*3+1] > 4.5) { pos[i*3+1] = 0; pos[i*3] = Math.random() * a.bounds; pos[i*3+2] = Math.random() * a.bounds; }
                    if (pos[i*3]   < 0 || pos[i*3]   > a.bounds) a.vel[i].x *= -1;
                    if (pos[i*3+2] < 0 || pos[i*3+2] > a.bounds) a.vel[i].z *= -1;
                }
                a.geo.attributes.position.needsUpdate = true;
                break;
            }
        }
    });
}

// ── Interaction ───────────────────────────────────────────────────────────────
function tryInteract() {
    if (activeNpcIdx < 0) return;
    if (document.getElementById('npc-modal').classList.contains('open')) return;
    const n = npcObjects[activeNpcIdx].npc;
    openModal(n);
}

function openModal(npc) {
    document.getElementById('modal-accent').style.background = `linear-gradient(90deg,transparent,${npc.color},transparent)`;
    document.getElementById('modal-badge').textContent = npc.title;
    document.getElementById('modal-badge').style.borderColor = npc.color + '55';
    document.getElementById('modal-badge').style.color = npc.color;
    document.getElementById('modal-name').textContent = npc.name;
    document.getElementById('modal-name').style.textShadow = `0 0 14px ${npc.color}80`;
    document.getElementById('modal-title').textContent = npc.title;
    document.getElementById('modal-title').style.color = npc.color + 'cc';
    document.getElementById('modal-blurb').textContent = npc.blurb;
    const btn = document.getElementById('modal-btn');
    if (npc.coming_soon || !npc.game_url) {
        btn.textContent = '⏳ COMING SOON';
        btn.className = 'modal-btn modal-btn-soon';
        btn.removeAttribute('href');
        btn.onclick = null;
    } else {
        btn.textContent = '▶ ' + npc.game_label.toUpperCase();
        btn.className = 'modal-btn modal-btn-primary';
        btn.href = npc.game_url;
        btn.style.background = `linear-gradient(135deg,${npc.color},${npc.color}99)`;
    }
    document.getElementById('npc-modal').classList.add('open');
    hintEl.classList.remove('show');
}

window.closeModal = function() {
    document.getElementById('npc-modal').classList.remove('open');
};

// ── Render loop ───────────────────────────────────────────────────────────────
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
    progStep('BUILDING LAB…');
    buildScene();
    progStep('LAB READY');
    await spawnHero();
    await spawnNPCs();
    progStep('STARTING…');
    nexusRt = createNexusDistrictRealtime({
        scene,
        districtId: 'tesla',
        userId: NEXUS_RT_UID,
        displayName: PLAYER_NAME,
        colorBody: '#00e8ff',
        colorVisor: '#9b30ff',
        colorEcho: '#ffd600',
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

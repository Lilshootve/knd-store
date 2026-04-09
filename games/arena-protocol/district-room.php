<?php
/**
 * Sala 3D por distrito — cámara orbital, NPCs con diálogo y enlace a minijuegos.
 * Entrada: ?district=tesla|olimpo|casino|agora
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/nexus_district_room_registry.php';

if (!is_logged_in()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$district = isset($_GET['district']) ? strtolower(trim((string)$_GET['district'])) : '';
if (!in_array($district, nexus_district_room_layer_ids(), true)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Distrito</title></head><body style="background:#020508;color:#8ac;font-family:monospace;padding:2rem">Distrito no disponible. <a href="/games/arena-protocol/nexus-city.html" style="color:#0ef">Volver al NEXUS</a></body></html>';
    exit;
}

$room = nexus_district_room_get($district);
if (!$room) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Distrito</title></head><body style="background:#020508;color:#8ac;font-family:monospace;padding:2rem">Sala no configurada. <a href="/games/arena-protocol/nexus-city.html" style="color:#0ef">Volver al NEXUS</a></body></html>';
    exit;
}

$roomJson = json_encode($room, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$districtJson = json_encode($district, JSON_UNESCAPED_UNICODE);
$nexusUrl = '/games/arena-protocol/nexus-city.html';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($room['title'], ENT_QUOTES, 'UTF-8') ?> — KND NEXUS</title>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"}}</script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#020508;font-family:"Share Tech Mono",monospace;color:#c8e8f0}
#cv{position:fixed;top:52px;left:0;right:0;bottom:0;z-index:0;background:#020508}
#cv canvas{display:block;width:100%!important;height:100%!important}
#tb{position:fixed;top:0;left:0;right:0;height:52px;z-index:50;background:rgba(2,5,16,.97);border-bottom:1px solid rgba(0,232,255,.1);display:flex;align-items:center;padding:0 14px;gap:12px}
#tb::after{content:"";position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,#00e8ff55,transparent);opacity:.35}
.back-btn{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:4px;border:1px solid rgba(0,232,255,.2);cursor:pointer;font-size:9px;letter-spacing:.12em;color:rgba(0,232,255,.75);background:rgba(0,232,255,.05);transition:.2s;text-decoration:none}
.back-btn:hover{border-color:#00e8ff;color:#00e8ff}
#tb-title{font-family:Orbitron,sans-serif;font-size:11px;font-weight:800;letter-spacing:.14em;color:#fff;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#tb-sub{font-size:8px;letter-spacing:.1em;color:rgba(155,215,235,.45);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#hint{position:fixed;bottom:14px;left:50%;transform:translateX(-50%);z-index:40;font-size:8px;letter-spacing:.12em;color:rgba(155,215,235,.35);pointer-events:none;text-align:center}
#modal{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:16px}
#modal.open{display:flex}
.modal-box{width:min(400px,94vw);background:linear-gradient(160deg,rgba(8,18,32,.98),rgba(4,10,20,.99));border:1px solid rgba(0,232,255,.2);border-radius:10px;padding:22px 22px 18px;box-shadow:0 0 50px rgba(0,232,255,.08)}
.modal-box h2{font-family:Orbitron,sans-serif;font-size:13px;letter-spacing:.1em;color:#fff;margin-bottom:4px}
.modal-box .sub{font-size:9px;color:rgba(155,215,235,.5);letter-spacing:.08em;margin-bottom:14px}
.modal-box p{font-size:11px;line-height:1.55;color:rgba(200,220,235,.88);margin-bottom:18px}
.modal-actions{display:flex;gap:10px;flex-wrap:wrap}
.modal-actions button,.modal-actions a{flex:1;min-width:120px;padding:11px 14px;border-radius:6px;font-family:Orbitron,sans-serif;font-size:8px;font-weight:700;letter-spacing:.12em;cursor:pointer;border:1px solid;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.btn-go{background:linear-gradient(135deg,rgba(0,232,255,.2),rgba(155,48,255,.12));border-color:rgba(0,232,255,.45);color:#00e8ff}
.btn-go:hover{box-shadow:0 0 20px rgba(0,232,255,.2)}
.btn-go:disabled{opacity:.35;cursor:not-allowed;box-shadow:none}
.btn-x{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.12);color:rgba(155,215,235,.55)}
.btn-x:hover{border-color:rgba(255,255,255,.22);color:#fff}
</style>
</head>
<body>
<header id="tb">
  <a class="back-btn" href="<?= htmlspecialchars($nexusUrl, ENT_QUOTES, 'UTF-8') ?>">◀ NEXUS</a>
  <div style="flex:1;min-width:0">
    <div id="tb-title"><?= htmlspecialchars($room['title'], ENT_QUOTES, 'UTF-8') ?></div>
    <div id="tb-sub"><?= htmlspecialchars($room['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</header>
<div id="cv"></div>
<div id="hint">Arrastra · rueda zoom · click en personaje para interactuar</div>
<div id="modal" aria-hidden="true">
  <div class="modal-box">
    <h2 id="m-name"></h2>
    <div class="sub" id="m-title"></div>
    <p id="m-blurb"></p>
    <div class="modal-actions">
      <button type="button" class="btn-x" id="m-close">Cerrar</button>
      <a class="btn-go" id="m-play" href="#" style="display:none">Jugar</a>
    </div>
  </div>
</div>

<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const DISTRICT = <?php echo $districtJson; ?>;
const ROOM = <?php echo $roomJson; ?>;

const nexusUrl = <?= json_encode($nexusUrl, JSON_UNESCAPED_UNICODE) ?>;
const returnUrl = window.location.pathname + window.location.search;

function withReturn(gamePath) {
  if (!gamePath || gamePath === '#') return '#';
  const u = new URL(gamePath, window.location.origin);
  u.searchParams.set('return', returnUrl);
  return u.pathname + u.search + u.hash;
}

const wrap = document.getElementById('cv');
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x020508);
scene.fog = new THREE.FogExp2(0x020508, 0.018);

const camera = new THREE.PerspectiveCamera(50, 1, 0.1, 220);
camera.position.set(12, 9, 14);

const renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
wrap.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.target.set(0, 1.2, 0);
controls.enableDamping = true;
controls.dampingFactor = 0.06;
controls.minDistance = 4;
controls.maxDistance = 42;
controls.maxPolarAngle = Math.PI / 2 - 0.06;

const accent = new THREE.Color(ROOM.accent_hex || '#00e8ff');

const hemi = new THREE.HemisphereLight(0x446688, 0x080410, 0.85);
scene.add(hemi);
const sun = new THREE.DirectionalLight(0xffffff, 1.15);
sun.position.set(10, 22, 8);
sun.castShadow = true;
sun.shadow.mapSize.set(2048, 2048);
sun.shadow.camera.near = 2;
sun.shadow.camera.far = 60;
sun.shadow.camera.left = sun.shadow.camera.bottom = -22;
sun.shadow.camera.right = sun.shadow.camera.top = 22;
scene.add(sun);
const fill = new THREE.PointLight(accent, 0.6, 40, 2);
fill.position.set(-6, 8, -4);
scene.add(fill);

const floor = new THREE.Mesh(
  new THREE.PlaneGeometry(48, 48),
  new THREE.MeshStandardMaterial({
    color: 0x050a12,
    metalness: 0.2,
    roughness: 0.85,
    emissive: accent,
    emissiveIntensity: 0.04,
  })
);
floor.rotation.x = -Math.PI / 2;
floor.receiveShadow = true;
scene.add(floor);

const grid = new THREE.GridHelper(48, 48, accent.getHex(), 0x112233);
grid.material.opacity = 0.22;
grid.material.transparent = true;
scene.add(grid);

function wallPlane(w, h, x, z, ry) {
  const g = new THREE.Mesh(
    new THREE.PlaneGeometry(w, h),
    new THREE.MeshStandardMaterial({
      color: 0x060d18,
      metalness: 0.15,
      roughness: 0.92,
      emissive: accent,
      emissiveIntensity: 0.03,
      side: THREE.DoubleSide,
    })
  );
  g.position.set(x, h / 2, z);
  g.rotation.y = ry;
  g.receiveShadow = true;
  scene.add(g);
}
const W = 22, H = 10;
wallPlane(W, H, 0, -W, 0);
wallPlane(W, H, W, 0, -Math.PI / 2);
wallPlane(W, H, -W, 0, Math.PI / 2);

function mkSpriteLabel(text, color) {
  const cv = document.createElement('canvas');
  cv.width = 512;
  cv.height = 96;
  const cx = cv.getContext('2d');
  cx.fillStyle = 'rgba(0,0,0,.45)';
  cx.fillRect(0, 0, 512, 96);
  cx.strokeStyle = color;
  cx.lineWidth = 2;
  cx.strokeRect(2, 2, 508, 92);
  cx.fillStyle = color;
  cx.font = 'bold 28px Orbitron, Share Tech Mono, monospace';
  cx.textAlign = 'center';
  cx.textBaseline = 'middle';
  cx.fillText(text.slice(0, 18), 256, 48);
  const tex = new THREE.CanvasTexture(cv);
  tex.colorSpace = THREE.SRGBColorSpace;
  const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
  const sp = new THREE.Sprite(mat);
  sp.scale.set(4.2, 0.78, 1);
  return sp;
}

function mkNpcFigure(hex) {
  const g = new THREE.Group();
  const c = new THREE.Color(hex);
  const body = new THREE.Mesh(
    new THREE.CapsuleGeometry(0.38, 1.0, 4, 10),
    new THREE.MeshStandardMaterial({ color: c, metalness: 0.35, roughness: 0.55, emissive: c, emissiveIntensity: 0.12 })
  );
  body.position.y = 0.85;
  body.castShadow = true;
  g.add(body);
  const head = new THREE.Mesh(
    new THREE.SphereGeometry(0.32, 14, 12),
    new THREE.MeshStandardMaterial({ color: 0xd8c8b8, metalness: 0.1, roughness: 0.75 })
  );
  head.position.y = 1.65;
  head.castShadow = true;
  g.add(head);
  return g;
}

const npcMeshes = [];
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();

for (const n of ROOM.npcs || []) {
  const fig = mkNpcFigure(n.color_hex || '#00e8ff');
  const [px, py, pz] = n.pos || [0, 0, 0];
  fig.position.set(px, py, pz);
  fig.rotation.y = n.rot_y || 0;
  fig.userData.npc = n;
  const lbl = mkSpriteLabel(n.name || 'NPC', n.color_hex || '#00e8ff');
  lbl.position.y = 2.35;
  fig.add(lbl);
  scene.add(fig);
  npcMeshes.push(fig);
}

function resize() {
  const w = wrap.clientWidth;
  const h = wrap.clientHeight;
  camera.aspect = w / Math.max(h, 1);
  camera.updateProjectionMatrix();
  renderer.setSize(w, h, false);
}
window.addEventListener('resize', resize);
resize();

const modal = document.getElementById('modal');
const mName = document.getElementById('m-name');
const mTitle = document.getElementById('m-title');
const mBlurb = document.getElementById('m-blurb');
const mPlay = document.getElementById('m-play');
const mClose = document.getElementById('m-close');

function openModal(npc) {
  mName.textContent = (npc.name || '').toUpperCase();
  mTitle.textContent = npc.title || '';
  mBlurb.textContent = npc.blurb || '';
  const coming = npc.coming_soon === true || !npc.game_url;
  mPlay.style.display = coming ? 'none' : 'inline-flex';
  if (!coming) {
    mPlay.href = withReturn(npc.game_url);
    mPlay.textContent = (npc.game_label || 'Jugar').toUpperCase();
  }
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
}
function closeModal() {
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
}
mClose.addEventListener('click', closeModal);
modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

wrap.addEventListener('click', (e) => {
  const rect = wrap.getBoundingClientRect();
  pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
  pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const hits = raycaster.intersectObjects(npcMeshes, true);
  if (!hits.length) return;
  let o = hits[0].object;
  while (o && !o.userData.npc) o = o.parent;
  if (o && o.userData.npc) openModal(o.userData.npc);
});

function tick() {
  requestAnimationFrame(tick);
  controls.update();
  renderer.render(scene, camera);
}
tick();
</script>
</body>
</html>

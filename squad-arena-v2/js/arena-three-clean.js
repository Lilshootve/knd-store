/**
 * Squad Arena v2 — minimal Three.js arena (GLB from G / __MW_BATTLE_INIT__).
 * Expects global THREE + THREE.GLTFLoader (no Draco/meshopt).
 * Visual tone aligned with squad-arena/squad-arena.php (dark arena, cyan accents).
 */
'use strict';

var scene;
var camera;
var renderer;
var clock;
var containerEl;
var canvas;
var unitsGroup;
var arenaGroup;
var booted = false;
var rafId = null;
var renderLogged = false;

function getG() {
  return window.G || window.__MW_BATTLE_INIT__ || null;
}

function sortByPos(units) {
  if (!Array.isArray(units)) return [];
  var order = { front: 0, mid: 1, back: 2 };
  return units.slice().sort(function (a, b) {
    return (order[a.pos] || 9) - (order[b.pos] || 9);
  });
}

function disposeObject3D(obj) {
  if (!obj) return;
  obj.traverse(function (o) {
    if (o.isMesh) {
      if (o.geometry) o.geometry.dispose();
      if (o.material) {
        if (Array.isArray(o.material)) o.material.forEach(function (m) { m.dispose(); });
        else o.material.dispose();
      }
    }
  });
}

function clearUnits() {
  if (!unitsGroup) return;
  while (unitsGroup.children.length) {
    var ch = unitsGroup.children[0];
    disposeObject3D(ch);
    unitsGroup.remove(ch);
  }
}

function onResize() {
  if (!containerEl || !camera || !renderer) return;
  var W = containerEl.clientWidth;
  var H = containerEl.clientHeight;
  if (W < 1 || H < 1) return;
  camera.aspect = W / H;
  camera.updateProjectionMatrix();
  renderer.setSize(W, H);
}

function buildArenaVisual() {
  arenaGroup = new THREE.Group();
  var floorGeo = new THREE.PlaneGeometry(28, 28, 1, 1);
  var floorMat = new THREE.MeshStandardMaterial({
    color: 0x02060c,
    roughness: 0.94,
    metalness: 0.12,
    emissive: 0x000812,
    emissiveIntensity: 0.035
  });
  var plane = new THREE.Mesh(floorGeo, floorMat);
  plane.rotation.x = -Math.PI / 2;
  plane.receiveShadow = true;
  arenaGroup.add(plane);
  var grid = new THREE.GridHelper(28, 14, 0x0a2540, 0x081a30);
  grid.material.opacity = 0.26;
  grid.material.transparent = true;
  arenaGroup.add(grid);
  scene.add(arenaGroup);

  var starsGeo = new THREE.BufferGeometry();
  var n = 600;
  var pos = new Float32Array(n * 3);
  for (var i = 0; i < n * 3; i++) pos[i] = (Math.random() - 0.5) * 80;
  starsGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  var starsMat = new THREE.PointsMaterial({ color: 0x8899cc, size: 0.12, transparent: true, opacity: 0.55 });
  scene.add(new THREE.Points(starsGeo, starsMat));
  var skyGeo = new THREE.SphereGeometry(45, 8, 8);
  var skyMat = new THREE.MeshBasicMaterial({ color: 0x000308, side: THREE.BackSide });
  scene.add(new THREE.Mesh(skyGeo, skyMat));
}

function buildLights() {
  scene.add(new THREE.AmbientLight(0x112233, 1.15));
  var dir = new THREE.DirectionalLight(0x4488ff, 1.45);
  dir.position.set(5, 10, 5);
  dir.castShadow = true;
  dir.shadow.mapSize.width = 512;
  dir.shadow.mapSize.height = 512;
  scene.add(dir);
  var rim = new THREE.DirectionalLight(0xff2244, 0.35);
  rim.position.set(-8, 3, -5);
  scene.add(rim);
  scene.add(new THREE.HemisphereLight(0x001133, 0x000000, 0.55));
}

function fitModelToHeight(root, targetH) {
  var box = new THREE.Box3().setFromObject(root);
  var size = new THREE.Vector3();
  box.getSize(size);
  var h = Math.max(size.y, 0.001);
  var s = targetH / h;
  root.scale.setScalar(s);
  root.updateMatrixWorld(true);
  box.setFromObject(root);
  root.position.y = -box.min.y;
}

function addFallbackCube(parent, isEnemy) {
  var geo = new THREE.BoxGeometry(0.45, 1.4, 0.32);
  var mat = new THREE.MeshStandardMaterial({
    color: isEnemy ? 0x553333 : 0x1a3355,
    roughness: 0.72,
    metalness: 0.22
  });
  var mesh = new THREE.Mesh(geo, mat);
  mesh.position.y = 0.7;
  mesh.castShadow = true;
  parent.add(mesh);
}

function createUnit(state, x, z, isEnemy) {
  if (state == null) return;
  var hp = state.hp != null ? Number(state.hp) : 0;
  if (hp <= 0) return;

  var group = new THREE.Group();
  group.position.set(x, 0, z);
  if (isEnemy) group.rotation.y = Math.PI;

  var url = state.modelGlb != null ? String(state.modelGlb).trim() : '';
  if (!url) {
    addFallbackCube(group, isEnemy);
    unitsGroup.add(group);
    return;
  }

  if (typeof THREE.GLTFLoader !== 'function') {
    addFallbackCube(group, isEnemy);
    unitsGroup.add(group);
    return;
  }

  console.log('LOADING GLB:', url);
  var loader = new THREE.GLTFLoader();
  loader.load(
    url,
    function (gltf) {
      console.log('GLB OK');
      var root = gltf.scene;
      root.traverse(function (o) {
        if (o.isMesh) {
          o.castShadow = true;
          o.receiveShadow = true;
        }
      });
      fitModelToHeight(root, 1.5);
      group.add(root);
    },
    undefined,
    function () {
      console.log('GLB ERROR');
      addFallbackCube(group, isEnemy);
    }
  );

  unitsGroup.add(group);
}

function animate() {
  rafId = requestAnimationFrame(animate);
  if (!renderLogged) {
    console.log('RENDER OK');
    renderLogged = true;
  }
  renderer.render(scene, camera);
}

function initThree(container) {
  containerEl = container;
  canvas = container.querySelector('#three-canvas') || container.querySelector('canvas') || document.getElementById('three-canvas');
  if (!canvas || typeof THREE === 'undefined') return false;

  renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.setClearColor(0x000008, 1);

  var W = container.clientWidth;
  var H = container.clientHeight;
  renderer.setSize(W, H);

  scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x000510, 0.08);

  camera = new THREE.PerspectiveCamera(55, W / Math.max(H, 1), 0.1, 100);
  camera.position.set(0, 4.5, 10);
  camera.lookAt(0, 1, 0);

  clock = new THREE.Clock();
  buildLights();
  buildArenaVisual();

  unitsGroup = new THREE.Group();
  scene.add(unitsGroup);

  window.addEventListener('resize', onResize);
  animate();
  return true;
}

function rebuild() {
  var g = getG();
  clearUnits();
  if (!g || !unitsGroup) return;

  var pa = sortByPos(g.allies || []);
  var pe = sortByPos(g.enemies || []);
  var xs = [-2, 0, 2];
  var zAlly = [3.2, 3.8, 4.4];
  var zEn = [-3.2, -3.8, -4.4];

  for (var i = 0; i < 3; i++) {
    createUnit(pa[i] || null, xs[i], zAlly[i], false);
    createUnit(pe[i] || null, xs[i], zEn[i], true);
  }
}

function boot(container) {
  if (!container) return;
  if (!booted) {
    if (!initThree(container)) return;
    booted = true;
    console.log('BOOT OK');
  } else {
    onResize();
  }
  rebuild();
}

window.MWArenaThree = {
  boot: boot,
  rebuild: rebuild
};

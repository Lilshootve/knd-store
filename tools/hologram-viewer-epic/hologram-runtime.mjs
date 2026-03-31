/**
 * Shared hologram GLB pipeline (same shaders / presets as index.html).
 * Use embed:true for card-sized viewports (Mind Wars lobby / squad cards).
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

const THREE_VERSION = '0.160.0';
const DRACO_DECODER = `https://unpkg.com/three@${THREE_VERSION}/examples/jsm/libs/draco/`;

const holoVertexShader = `
  varying vec3 vNormal;
  varying vec3 vViewDir;
  varying vec3 vWorldPos;
  varying vec2 vUv;

  void main() {
    vUv = uv;
    vec4 worldPos = modelMatrix * vec4(position, 1.0);
    vWorldPos = worldPos.xyz;
    vNormal = normalize(normalMatrix * normal);
    vec4 mvPos = modelViewMatrix * vec4(position, 1.0);
    vViewDir = normalize(-mvPos.xyz);
    gl_Position = projectionMatrix * mvPos;
  }
`;

const holoFragmentShader = `
  uniform float uTime;
  uniform vec3  uColor;
  uniform float uOpacity;
  uniform float uFresnelStrength;
  uniform float uInnerGlow;
  uniform float uRimStrength;
  uniform float uPulseSpeed;
  uniform float uPulseStrength;
  uniform float uScanSpeed;
  uniform float uScanDensity;
  uniform float uFlickerIntensity;

  varying vec3 vNormal;
  varying vec3 vViewDir;
  varying vec3 vWorldPos;
  varying vec2 vUv;

  void main() {
    float fresnel = 1.0 - max(dot(vNormal, vViewDir), 0.0);
    fresnel = pow(fresnel, uFresnelStrength);

    float scanLine = sin(vWorldPos.y * uScanDensity + uTime * uScanSpeed) * 0.5 + 0.5;
    scanLine = pow(scanLine, 3.0) * 0.35 + 0.65;

    float pulse  = sin(uTime * uPulseSpeed) * 0.5 + 0.5;
    float pulse2 = sin(uTime * uPulseSpeed * 0.4 + vWorldPos.y * 3.0) * 0.5 + 0.5;
    float energy = mix(pulse, pulse2, 0.4);

    float inner = 1.0 - fresnel;
    float core  = pow(inner, 3.5) * uInnerGlow;

    float rim = pow(fresnel, 1.2) * uRimStrength;

    float alpha = (rim + core) * scanLine * (1.0 - uPulseStrength + energy * uPulseStrength);
    alpha = clamp(alpha, 0.0, 1.0);

    float flicker = 1.0 - uFlickerIntensity + uFlickerIntensity * sin(uTime * 17.3 + vUv.y * 100.0);
    alpha *= flicker;

    vec3 col = uColor * (0.9 + rim * 0.4 + core * 0.5);
    col = clamp(col, 0.0, 3.0);

    gl_FragColor = vec4(col, alpha * uOpacity);
  }
`;

const DEFAULTS = {
  colorR: 0.0, colorG: 2.0, colorB: 2.5,
  opacity: 0.82,
  fresnelStrength: 1.8,
  innerGlow: 0.45,
  rimStrength: 1.4,
  pulseSpeed: 1.8,
  pulseStrength: 0.25,
  scanSpeed: 2.5,
  scanDensity: 80.0,
  flickerIntensity: 0.1,
  baseOpacity: 0.15,
  texOpacity: 0.38,
  holoOpacityMul: 1.0,
  ringOpacity: 0.55,
  ringPulseSpeed: 2.2,
  beamIntensity: 0.18,
};

export function getPresetUrl(modelUrl) {
  const u = String(modelUrl || '');
  if (u.includes('/assets/avatars/models/legendary/')) return '/workflows/legendary-preset.json';
  return '/workflows/epic-preset.json';
}

async function loadPreset(url, targetP) {
  try {
    const res = await fetch(url);
    if (!res.ok) return;
    const data = await res.json();
    Object.assign(targetP, data);
  } catch (_) { /* ignore */ }
}

/**
 * @param {HTMLElement} container — host element (cleared; canvas appended)
 * @param {{ modelUrl: string, embed?: boolean, enableControls?: boolean }} options
 * @returns {Promise<{ dispose: Function, setSize: Function, canvas: HTMLCanvasElement }>}
 */
export function mountHologramInContainer(container, options) {
  const modelUrl = options.modelUrl;
  const embed = !!options.embed;
  const enableControls = options.enableControls !== false;

  if (!container || !modelUrl) {
    return Promise.reject(new Error('mountHologramInContainer: missing container or modelUrl'));
  }

  const P = { ...DEFAULTS };
  const shaderUniforms = [];
  const baseMeshes = [];
  const texMeshes = [];
  let modelGroup = null;
  let disposed = false;

  function applyP() {
    for (const u of shaderUniforms) {
      u.uColor.value.set(P.colorR, P.colorG, P.colorB);
      u.uOpacity.value = P.opacity * P.holoOpacityMul;
      u.uFresnelStrength.value = P.fresnelStrength;
      u.uInnerGlow.value = P.innerGlow;
      u.uRimStrength.value = P.rimStrength;
      u.uPulseSpeed.value = P.pulseSpeed;
      u.uPulseStrength.value = P.pulseStrength;
      u.uScanSpeed.value = P.scanSpeed;
      u.uScanDensity.value = P.scanDensity;
      u.uFlickerIntensity.value = P.flickerIntensity;
    }
    for (const m of baseMeshes) m.material.opacity = P.baseOpacity;
    for (const m of texMeshes) m.material.opacity = P.texOpacity;
  }

  const LOBBY_VOID_HEX = 0x010508;
  const w0 = Math.max(1, container.clientWidth || 300);
  const h0 = Math.max(1, container.clientHeight || 300);

  const canvas = document.createElement('canvas');
  canvas.style.cssText = 'display:block;width:100%;height:100%;touch-action:none;';
  container.innerHTML = '';
  container.appendChild(canvas);

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(w0, h0);
  renderer.sortObjects = true;
  renderer.toneMapping = THREE.NoToneMapping;

  if (embed) {
    renderer.setClearColor(LOBBY_VOID_HEX, 1);
  } else {
    renderer.setClearColor(0x000000, 0);
  }

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, w0 / h0, 0.01, 100);
  camera.position.set(0, 0, 2);

  let controls = null;
  if (enableControls) {
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.minDistance = 0.35;
    controls.maxDistance = 10;
    controls.target.set(0, 0, 0);
  }

  const ambient = new THREE.AmbientLight(0x00ffff, 0.15);
  scene.add(ambient);
  const dirLight = new THREE.DirectionalLight(0x00ffff, 0.3);
  dirLight.position.set(1, 2, 2);
  scene.add(dirLight);

  function buildHologramLayers(gltf) {
    if (modelGroup) {
      scene.remove(modelGroup);
      modelGroup.traverse((o) => {
        if (o.isMesh) {
          o.geometry.dispose();
          if (Array.isArray(o.material)) o.material.forEach((m) => m.dispose());
          else o.material.dispose();
        }
      });
    }

    shaderUniforms.length = 0;
    baseMeshes.length = 0;
    texMeshes.length = 0;

    modelGroup = new THREE.Group();

    const box = new THREE.Box3().setFromObject(gltf.scene);
    const center = new THREE.Vector3();
    box.getCenter(center);
    const size = new THREE.Vector3();
    box.getSize(size);
    const maxDim = Math.max(size.x, size.y, size.z);
    const scale = 1.6 / maxDim;
    gltf.scene.position.sub(center);
    gltf.scene.scale.setScalar(scale);

    const box2 = new THREE.Box3().setFromObject(gltf.scene);
    gltf.scene.position.y -= box2.min.y + 1.05 / scale;

    gltf.scene.updateMatrixWorld(true);

    gltf.scene.traverse((child) => {
      if (!child.isMesh) return;

      const origMat = Array.isArray(child.material) ? child.material[0] : child.material;
      const geo = child.geometry;

      const baseMat = new THREE.MeshBasicMaterial({
        color: 0x003333,
        transparent: true,
        opacity: P.baseOpacity,
        depthWrite: false,
      });
      const baseMesh = new THREE.Mesh(geo, baseMat);
      baseMesh.renderOrder = 0;
      baseMesh.applyMatrix4(child.matrixWorld);
      modelGroup.add(baseMesh);
      baseMeshes.push(baseMesh);

      const texMap = origMat?.map ?? null;
      const texMat = new THREE.MeshBasicMaterial({
        map: texMap,
        color: texMap ? 0xffffff : 0x007777,
        transparent: true,
        opacity: texMap ? P.texOpacity : 0.18,
        depthWrite: false,
      });
      const texMesh = new THREE.Mesh(geo, texMat);
      texMesh.renderOrder = 1;
      texMesh.applyMatrix4(child.matrixWorld);
      modelGroup.add(texMesh);
      texMeshes.push(texMesh);

      const uniforms = {
        uTime: { value: 0 },
        uColor: { value: new THREE.Vector3(P.colorR, P.colorG, P.colorB) },
        uOpacity: { value: P.opacity * P.holoOpacityMul },
        uFresnelStrength: { value: P.fresnelStrength },
        uInnerGlow: { value: P.innerGlow },
        uRimStrength: { value: P.rimStrength },
        uPulseSpeed: { value: P.pulseSpeed },
        uPulseStrength: { value: P.pulseStrength },
        uScanSpeed: { value: P.scanSpeed },
        uScanDensity: { value: P.scanDensity },
        uFlickerIntensity: { value: P.flickerIntensity },
      };
      shaderUniforms.push(uniforms);

      const holoMat = new THREE.ShaderMaterial({
        uniforms,
        vertexShader: holoVertexShader,
        fragmentShader: holoFragmentShader,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        side: THREE.FrontSide,
      });
      const holoMesh = new THREE.Mesh(geo, holoMat);
      holoMesh.scale.setScalar(1.002);
      holoMesh.renderOrder = 2;
      holoMesh.applyMatrix4(child.matrixWorld);
      modelGroup.add(holoMesh);
    });

    scene.add(modelGroup);
    scene.updateMatrixWorld(true);
    frameCameraToObject(modelGroup);
  }

  function frameCameraToObject(object) {
    const box = new THREE.Box3().setFromObject(object);
    const sz = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());

    const maxDim = Math.max(sz.x, sz.y, sz.z);
    const fov = camera.fov * (Math.PI / 180);
    let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
    cameraZ *= 1.6;

    camera.position.set(center.x, center.y + maxDim * 0.2, cameraZ);
    if (controls) {
      controls.target.copy(center);
      controls.update();
    } else {
      camera.lookAt(center);
    }

    camera.near = 0.01;
    camera.far = cameraZ * 10;
    camera.updateProjectionMatrix();
  }

  const clock = new THREE.Clock();
  let rafId = 0;

  function animate() {
    if (disposed) return;
    rafId = requestAnimationFrame(animate);
    const elapsed = clock.getElapsedTime();
    for (const u of shaderUniforms) {
      u.uTime.value = elapsed;
    }
    if (controls) controls.update();
    renderer.render(scene, camera);
  }

  function setSize(w, h) {
    const W = Math.max(1, Math.floor(w));
    const H = Math.max(1, Math.floor(h));
    camera.aspect = W / H;
    camera.updateProjectionMatrix();
    renderer.setSize(W, H);
  }

  function disposeRendererAndScene() {
    disposed = true;
    if (rafId) cancelAnimationFrame(rafId);
    if (controls) {
      controls.dispose();
      controls = null;
    }
    if (modelGroup) {
      scene.remove(modelGroup);
      modelGroup.traverse((o) => {
        if (o.isMesh) {
          o.geometry.dispose();
          if (Array.isArray(o.material)) o.material.forEach((m) => m.dispose());
          else o.material.dispose();
        }
      });
      modelGroup = null;
    }
    scene.clear();
    renderer.dispose();
    if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
  }

  const stopClickBubble = (e) => e.stopPropagation();
  canvas.addEventListener('click', stopClickBubble);
  canvas.addEventListener('pointerdown', stopClickBubble);

  return (async () => {
    const presetUrl = getPresetUrl(modelUrl);
    await loadPreset(presetUrl, P);
    applyP();

    const dracoLoader = new DRACOLoader();
    dracoLoader.setDecoderPath(DRACO_DECODER);

    const loader = new GLTFLoader();
    loader.setDRACOLoader(dracoLoader);

    await new Promise((resolve, reject) => {
      loader.load(
        modelUrl,
        (gltf) => {
          buildHologramLayers(gltf);
          resolve();
        },
        undefined,
        reject
      );
    });

    animate();

    let ro = null;
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(() => {
        if (disposed) return;
        const r = container.getBoundingClientRect();
        setSize(r.width, r.height);
      });
      ro.observe(container);
    }

    const api = {
      canvas,
      setSize,
      dispose() {
        if (ro) ro.disconnect();
        canvas.removeEventListener('click', stopClickBubble);
        canvas.removeEventListener('pointerdown', stopClickBubble);
        disposeRendererAndScene();
      },
    };
    return api;
  })();
}

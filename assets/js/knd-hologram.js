/**
 * KND Hologram Viewer — reusable Three.js hologram module
 * Requires import map for Three.js. No UI — canvas-only rendering.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

const DEFAULTS = {
  colorR: 0,
  colorG: 2,
  colorB: 2.5,
  opacity: 0.82,
  fresnelStrength: 1.8,
  innerGlow: 0.45,
  rimStrength: 1.4,
  pulseSpeed: 1.8,
  pulseStrength: 0.25,
  scanSpeed: 2.5,
  scanDensity: 80,
  flickerIntensity: 0.1,
  baseOpacity: 0.15,
  texOpacity: 0.38,
  holoOpacityMul: 1,
  ringOpacity: 0.55,
  ringPulseSpeed: 2.2,
  beamIntensity: 0.18,
};

const HOLO_VERTEX_SHADER = `
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

const HOLO_FRAGMENT_SHADER = `
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

const DRACO_PATH = 'https://unpkg.com/three@0.160.0/examples/jsm/libs/draco/';

/**
 * Initialize hologram viewer.
 * @param {Object} options
 * @param {string|HTMLCanvasElement} options.canvas - Selector or canvas element
 * @param {string} options.model - URL path to GLB model
 * @param {Object} [options.preset] - Hologram parameters (merged with defaults)
 * @returns {{ destroy: () => void, setPreset: (partialPreset: Object) => void, setLayerVisibility: (visible: Object) => void }}
 */
export function initHologramViewer(options) {
  const { canvas: canvasOpt, model, preset = {} } = options;

  const canvas = typeof canvasOpt === 'string'
    ? document.querySelector(canvasOpt)
    : canvasOpt;

  if (!canvas || !(canvas instanceof HTMLCanvasElement)) {
    throw new Error('knd-hologram: canvas must be a selector or HTMLCanvasElement');
  }

  const P = { ...DEFAULTS, ...preset };

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setClearColor(0x111111, 1);
  renderer.sortObjects = true;
  renderer.toneMapping = THREE.NoToneMapping;

  const el = renderer.domElement;
  el.style.position = 'fixed';
  el.style.top = '0';
  el.style.left = '0';
  el.style.width = '100vw';
  el.style.height = '100vh';
  el.style.minWidth = '100vw';
  el.style.minHeight = '100vh';
  el.style.display = 'block';
  el.style.zIndex = '0';

  const scene = new THREE.Scene();

  const debugGeo = new THREE.BoxGeometry(0.5, 0.5, 0.5);
  const debugMat = new THREE.MeshBasicMaterial({ color: 0xff0000 });
  const debugCube = new THREE.Mesh(debugGeo, debugMat);
  scene.add(debugCube);

  const bgGeo = new THREE.PlaneGeometry(2, 2);
  const bgMat = new THREE.ShaderMaterial({
    depthWrite: false,
    depthTest: false,
    uniforms: {
      uResolution: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
    },
    vertexShader: `
      void main() {
        gl_Position = vec4(position.xy, 1.0, 1.0);
      }
    `,
    fragmentShader: `
      uniform vec2 uResolution;
      void main() {
        vec2 uv = gl_FragCoord.xy / uResolution;
        float dist = length(uv - 0.5) * 1.6;
        vec3 center = vec3(0.0, 0.04, 0.07);
        vec3 edge   = vec3(0.0, 0.0, 0.0);
        gl_FragColor = vec4(mix(center, edge, dist), 1.0);
      }
    `,
  });
  const bgMesh = new THREE.Mesh(bgGeo, bgMat);
  bgMesh.renderOrder = -1000;
  bgMesh.frustumCulled = false;
  scene.add(bgMesh);

  const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.01, 100);
  camera.position.set(0, 0, 2);
  camera.lookAt(0, 0, 0);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.minDistance = 0.5;
  controls.maxDistance = 10;
  controls.target.set(0, 0, 0);

  function resizeRenderer() {
    const width = window.innerWidth;
    const height = window.innerHeight;
    renderer.setSize(width, height, false);
    renderer.setPixelRatio(window.devicePixelRatio);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    bgMat.uniforms.uResolution.value.set(width, height);
  }
  resizeRenderer();
  window.addEventListener('resize', resizeRenderer);

  const ambient = new THREE.AmbientLight(0x00ffff, 0.15);
  scene.add(ambient);
  const dirLight = new THREE.DirectionalLight(0x00ffff, 0.3);
  dirLight.position.set(1, 2, 2);
  scene.add(dirLight);

  const ringGeo = new THREE.RingGeometry(0.55, 0.85, 64);
  const ringMat = new THREE.MeshBasicMaterial({
    color: 0x00ffff,
    transparent: true,
    opacity: P.ringOpacity,
    side: THREE.DoubleSide,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
  });
  const ringMesh = new THREE.Mesh(ringGeo, ringMat);
  ringMesh.rotation.x = -Math.PI / 2;
  ringMesh.position.y = -1.05;
  ringMesh.renderOrder = 5;
  scene.add(ringMesh);

  const dotGeo = new THREE.CircleGeometry(0.3, 48);
  const dotMat = new THREE.MeshBasicMaterial({
    color: 0x00ffff,
    transparent: true,
    opacity: 0.08,
    side: THREE.DoubleSide,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
  });
  const dotMesh = new THREE.Mesh(dotGeo, dotMat);
  dotMesh.rotation.x = -Math.PI / 2;
  dotMesh.position.y = -1.06;
  dotMesh.renderOrder = 4;
  scene.add(dotMesh);

  const beamGeo = new THREE.CylinderGeometry(0.01, 0.4, 1.1, 32, 1, true);
  const beamMat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    side: THREE.DoubleSide,
    blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uBeamIntensity: { value: P.beamIntensity } },
    vertexShader: `
      varying vec2 vUv;
      void main() {
        vUv = uv;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0);
      }
    `,
    fragmentShader: `
      uniform float uTime;
      uniform float uBeamIntensity;
      varying vec2 vUv;
      void main() {
        float alpha = (1.0 - vUv.y) * uBeamIntensity;
        alpha *= 0.7 + 0.3 * sin(uTime * 2.0);
        gl_FragColor = vec4(0.0, 1.0, 1.0, alpha);
      }
    `,
  });
  const beamMesh = new THREE.Mesh(beamGeo, beamMat);
  beamMesh.position.y = -0.5;
  beamMesh.renderOrder = 3;
  scene.add(beamMesh);

  const shaderUniforms = [];
  const baseMeshes = [];
  const texMeshes = [];
  const holoMeshes = [];
  let modelGroup = null;

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
    holoMeshes.length = 0;
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
        vertexShader: HOLO_VERTEX_SHADER,
        fragmentShader: HOLO_FRAGMENT_SHADER,
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
      holoMeshes.push(holoMesh);
    });

    scene.add(modelGroup);
  }

  const dracoLoader = new DRACOLoader();
  dracoLoader.setDecoderPath(DRACO_PATH);

  const loader = new GLTFLoader();
  loader.setDRACOLoader(dracoLoader);
  console.log('Loading model:', model);
  loader.load(
    model,
    (gltf) => {
      console.log('GLB loaded', gltf);
      try {
        buildHologramLayers(gltf);
      } catch (e) {
        console.error('knd-hologram: error building hologram layers:', e);
      }
    },
    (progress) => {
      const pct = progress.total ? (progress.loaded / progress.total) : progress.loaded;
      console.log('knd-hologram: loading progress', pct);
    },
    (err) => {
      console.error('GLB ERROR', err);
    }
  );

  const clock = new THREE.Clock();
  let rafId = 0;

  function animate() {
    rafId = requestAnimationFrame(animate);
    const elapsed = clock.getElapsedTime();

    for (const u of shaderUniforms) {
      u.uTime.value = elapsed;
    }

    ringMat.opacity = P.ringOpacity * (0.8 + 0.2 * Math.sin(elapsed * P.ringPulseSpeed));
    beamMat.uniforms.uTime.value = elapsed;
    beamMat.uniforms.uBeamIntensity.value = P.beamIntensity;

    controls.update();
    renderer.render(scene, camera);
  }
  animate();


  function setPreset(partialPreset) {
    Object.assign(P, partialPreset);
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
    beamMat.uniforms.uBeamIntensity.value = P.beamIntensity;
  }

  function setLayerVisibility(visible) {
    const v = visible ?? {};
    if ('base' in v) baseMeshes.forEach((m) => (m.visible = v.base));
    if ('tex' in v) texMeshes.forEach((m) => (m.visible = v.tex));
    if ('holo' in v) holoMeshes.forEach((m) => (m.visible = v.holo));
    if ('beam' in v) beamMesh.visible = v.beam;
    if ('ring' in v) {
      ringMesh.visible = v.ring;
      dotMesh.visible = v.ring;
    }
  }

  function destroy() {
    cancelAnimationFrame(rafId);
    window.removeEventListener('resize', resizeRenderer);
    controls.dispose();

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

    [bgGeo, bgMat, ringGeo, ringMat, dotGeo, dotMat, beamGeo, beamMat].forEach((obj) => {
      if (obj?.dispose) obj.dispose();
    });

    renderer.dispose();
  }

  return { destroy, setPreset, setLayerVisibility };
}

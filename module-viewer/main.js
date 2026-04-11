import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { createHologramMaterial } from './hologramShader.js';

let scene = new THREE.Scene();
let camera = new THREE.PerspectiveCamera(60, window.innerWidth/window.innerHeight, 0.1, 100);
camera.position.set(0, 1.5, 3);

let renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setSize(window.innerWidth, window.innerHeight);
document.body.appendChild(renderer.domElement);

// Luz base
const light = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
scene.add(light);

let mixer;
let clock = new THREE.Clock();

// Cargar preset
let preset;

fetch('./preset.json')
  .then(res => res.json())
  .then(data => {
    preset = data;
    loadModel();
  });

function loadModel() {
  const loader = new GLTFLoader();

  loader.load('./model.glb', (gltf) => {

    const material = createHologramMaterial(preset);

    gltf.scene.traverse((child) => {
      if (child.isMesh) {
        child.material = material;
      }
    });

    scene.add(gltf.scene);

    // 🎬 ANIMACIONES (esto evita T-pose)
    if (gltf.animations.length > 0) {
      mixer = new THREE.AnimationMixer(gltf.scene);

      gltf.animations.forEach((clip) => {
        mixer.clipAction(clip).play();
      });
    }
  });
}

function animate() {
  requestAnimationFrame(animate);

  let delta = clock.getDelta();

  if (mixer) mixer.update(delta);

  // actualizar shader
  scene.traverse(obj => {
    if (obj.material && obj.material.uniforms) {
      obj.material.uniforms.time.value += delta;
    }
  });

  renderer.render(scene, camera);
}

animate();
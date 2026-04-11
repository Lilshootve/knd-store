/**
 * Squad Arena v2 — Three.js arena (ported from squad-arena/squad-arena.php inline script).
 * Expects global G (battle state), optional global lg() for combat log.
 *
 * GLB URLs: only explicit paths from PHP (`state.modelGlb`); no client-side name/slug heuristics.
 * Verbose GLB: __MW_ARENA_DEBUG_GLB__, ?debugGlb=1, or localStorage mw_debug_glb=1.
 */
(function () {
  'use strict';



  var scene, camera, renderer, clock;
  var arenaGroup;
  var playerUnits = [];
  var enemyUnits = [];
  var originalCamPos, originalCamTarget;
  var particleSystems = [];
  var containerEl;
  var canvas;
  var raycaster;
  var pointer;
  var pickEnemyMode = false;
  var booted = false;
  /** Hologram preset (merged with tools/presets/preset-default.json when fetch succeeds) */
  var holoP = {};
  var RARITY_COLORS = { legendary: 0xffcc00, epic: 0xcc44ff, rare: 0x4488ff, common: 0x44aaaa, special: 0x00ffcc };

  /** Known file in repo (legendary/wukong.glb). `test.glb` is not in the repo — smoke test compares both. */
  var MW_KNOWN_GOOD_GLB = '/assets/avatars/models/legendary/wukong.glb';
  var MW_HARDCODE_MISSING_TEST_GLB = '/assets/avatars/models/legendary/test.glb';
  var _glbSmokeTestDone = false;

  var _mwArenaGlbBannerLogged = false;

  /**
   * Verbose GLB logs: __MW_ARENA_DEBUG_GLB__, ?debugGlb=1, or localStorage/sessionStorage mw_debug_glb=1
   * (persisted storage survives redirects from squad-selector; query string often does not).
   */
  function mwGlbDebugEnabled() {
    if (typeof window === 'undefined') return false;
    if (window.__MW_ARENA_DEBUG_GLB__ === true) return true;
    try {
      if (window.localStorage && window.localStorage.getItem('mw_debug_glb') === '1') return true;
      if (window.sessionStorage && window.sessionStorage.getItem('mw_debug_glb') === '1') return true;
    } catch (e) {
      /* private mode / blocked storage */
    }
    try {
      return /(?:\?|&)debugGlb=1(?:&|$)/.test(window.location && window.location.search ? window.location.search : '');
    } catch (e2) {
      return false;
    }
  }

  function logMwArenaGlbBannerOnce() {
    if (_mwArenaGlbBannerLogged || typeof console === 'undefined' || !console.info) return;
    _mwArenaGlbBannerLogged = true;
    console.info(
      '[MWArena] Three.js arena listo. Logs detallados de GLB: ejecuta localStorage.setItem("mw_debug_glb","1") y recarga, o abre la batalla con ?debugGlb=1 (si vienes del selector, el query suele perderse). Silenciar: localStorage.removeItem("mw_debug_glb").'
    );
  }

  function probeFetchGlb(modelPath, label) {
    if (!mwGlbDebugEnabled()) {
      return Promise.resolve();
    }
    console.log('Loading model from:', modelPath);
    if (label) {
      console.log('[MWArena GLB] attempt:', label);
    }
    if (typeof fetch === 'undefined') {
      console.warn('[MWArena GLB] fetch() not available');
      return Promise.resolve();
    }
    return fetch(modelPath, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
      .then(function (res) {
        console.log('[MWArena GLB] fetch probe Status:', res.status, 'for', modelPath);
        return res;
      })
      .catch(function (err) {
        console.error('Fetch error:', err);
      });
  }

  function runGlbSmokeTestOnce() {
    if (_glbSmokeTestDone || !mwGlbDebugEnabled()) return;
    _glbSmokeTestDone = true;
    var Ctor =
      typeof THREE !== 'undefined' && THREE.GLTFLoader
        ? THREE.GLTFLoader
        : typeof window !== 'undefined' && window.THREE && window.THREE.GLTFLoader
          ? window.THREE.GLTFLoader
          : null;
    console.log(
      '[MWArena GLB smoke] This PHP app serves assets from site root /assets/... (not Vite /public). Comparing missing vs known-good file.'
    );
    fetch(MW_HARDCODE_MISSING_TEST_GLB)
      .then(function (res) {
        console.log('[MWArena GLB smoke] fetch (expected 404 if test.glb absent):', MW_HARDCODE_MISSING_TEST_GLB, 'Status:', res.status);
      })
      .catch(function (err) {
        console.error('[MWArena GLB smoke] Fetch error:', MW_HARDCODE_MISSING_TEST_GLB, err);
      });
    fetch(MW_KNOWN_GOOD_GLB)
      .then(function (res) {
        console.log('[MWArena GLB smoke] fetch (known-good in repo):', MW_KNOWN_GOOD_GLB, 'Status:', res.status);
        if (!Ctor || !res.ok) {
          if (!Ctor) console.warn('[MWArena GLB smoke] THREE.GLTFLoader missing; skip loader probe');
          return;
        }
        var loader = new Ctor();
        loader.load(
          MW_KNOWN_GOOD_GLB,
          function () {
            console.log('Modelo cargado correctamente [smoke test file, not added to arena]', MW_KNOWN_GOOD_GLB);
          },
          undefined,
          function (err) {
            console.error('Error cargando modelo:', MW_KNOWN_GOOD_GLB, err);
          }
        );
      })
      .catch(function (err) {
        console.error('[MWArena GLB smoke] Fetch error:', MW_KNOWN_GOOD_GLB, err);
      });
  }

  /**
   * Log de candidatos + comprobación HTTP (HEAD) para ver si el recurso existe en /assets/...
   */
  function logModelUrlCandidatesAndProbe(meta, modelUrlCandidates) {
    if (typeof console !== 'undefined' && console.log) {
      console.log('modelUrlCandidates', {
        unitId: meta.unitId,
        name: meta.name,
        isEnemy: meta.isEnemy,
        stateModelGlb: meta.stateModelGlb,
        modelUrlCandidates: modelUrlCandidates.slice()
      });
    }
    if (typeof fetch === 'undefined') return;
    modelUrlCandidates.forEach(function (url) {
      if (!url) return;
      var isLocalAssets = url.indexOf('/assets/avatars/models/') === 0;
      var isAbsolute = /^https?:\/\//i.test(url);
      if (!isLocalAssets && !isAbsolute) {
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('[MWArena GLB] modelGlb no es ruta /assets/... ni URL absoluta:', url);
        }
      }
      fetch(url, { method: 'HEAD', cache: 'no-store', credentials: isAbsolute ? 'omit' : 'same-origin' })
        .then(function (res) {
          if (typeof console !== 'undefined' && console.log) {
            console.log(
              '[MWArena GLB] existe?',
              url,
              '→ HTTP',
              res.status,
              res.ok ? '(recurso accesible)' : '(no OK — revisar ruta en disco / mayúsculas)'
            );
          }
        })
        .catch(function (err) {
          if (typeof console !== 'undefined' && console.error) {
            console.error('[MWArena GLB] HEAD falló (red/CORS/servidor):', url, err);
          }
        });
    });
  }

  function getG() {
    return window.G || window.__MW_BATTLE_INIT__ || null;
  }

  /** DRACOLoader compartido — alineado con tools/hologram-viewer-epic (decoders en /squad-arena-v2/js/draco/). */
  var _sharedDracoLoader = null;
  function getSharedDracoLoader() {
    if (_sharedDracoLoader) return _sharedDracoLoader;
    if (typeof THREE === 'undefined' || typeof THREE.DRACOLoader !== 'function') {
      return null;
    }
    _sharedDracoLoader = new THREE.DRACOLoader();
    _sharedDracoLoader.setDecoderPath('/squad-arena-v2/js/draco/');
    return _sharedDracoLoader;
  }

  /**
   * EXT_meshopt_compression: el loader debe tener el decoder antes de parsear.
   * Cached: only attempts once to avoid spamming CSP errors.
   */
  var _meshoptAttempted = false;
  var _meshoptOk = false;
  function ensureMeshoptOnLoader(loader, callback) {
    if (!loader || typeof callback !== 'function') return;
    if (_meshoptAttempted) {
      callback();
      return;
    }
    if (typeof loader.setMeshoptDecoder !== 'function') {
      _meshoptAttempted = true;
      callback();
      return;
    }
    var md = typeof window !== 'undefined' ? window.MeshoptDecoder : null;
    if (!md || md.supported === false) {
      _meshoptAttempted = true;
      callback();
      return;
    }
    function attach() {
      _meshoptAttempted = true;
      try {
        loader.setMeshoptDecoder(md);
        _meshoptOk = true;
      } catch (e) { /* ignore */ }
      callback();
    }
    if (md.ready && typeof md.ready.then === 'function') {
      md.ready.then(attach).catch(function () {
        _meshoptAttempted = true;
        callback();
      });
    } else {
      attach();
    }
  }

  /**
   * Escala por la dimensión mayor del AABB (no solo Y): evita avatares invisibles
   * si el export usa unidades enormes o el personaje está muy “plano” en Y.
   */
  function applyGltfScaleAndGround(root, targetMaxDim) {
    var T = targetMaxDim != null && isFinite(targetMaxDim) && targetMaxDim > 0 ? targetMaxDim : 1.2;
    root.updateMatrixWorld(true);
    root.traverse(function (o) {
      if (o.isSkinnedMesh && o.skeleton) {
        try {
          o.skeleton.update();
        } catch (e) {
          /* ignore */
        }
      }
    });
    var box = new THREE.Box3().setFromObject(root);
    if (typeof box.isEmpty === 'function' && box.isEmpty()) {
      root.scale.setScalar(1);
      root.position.y = 0;
      return;
    }
    var size = new THREE.Vector3();
    box.getSize(size);
    var maxDim = Math.max(size.x, size.y, size.z, 1e-6);
    if (!isFinite(maxDim) || maxDim <= 0) {
      root.scale.setScalar(1);
      root.position.y = 0;
      return;
    }
    var scale = T / maxDim;
    var MIN_SCALE = 1e-5;
    var MAX_SCALE = 1e6;
    scale = Math.min(Math.max(scale, MIN_SCALE), MAX_SCALE);
    root.scale.setScalar(scale);
    root.updateMatrixWorld(true);
    box.setFromObject(root);
    root.position.y = -box.min.y;
  }

  function sortedByPos(units) {
    var order = { front: 0, mid: 1, back: 2 };
    return units.slice().sort(function (a, b) {
      return (order[a.pos] || 9) - (order[b.pos] || 9);
    });
  }

  function escTxt(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function disposeModelSubtree(root) {
    if (!root) return;
    root.traverse(function (o) {
      if (o.isMesh) {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(function (m) { m.dispose(); });
          else o.material.dispose();
        }
      }
    });
    while (root.children.length) root.remove(root.children[0]);
  }

  /** Scale/center loaded scene when hologram path is unavailable or throws. */
  function mountPlainGltf(gltf, modelRoot) {
    disposeModelSubtree(modelRoot);
    var root = gltf.scene.clone(true);
    root.traverse(function (o) {
      if (o.isMesh) {
        o.castShadow = true;
        o.receiveShadow = true;
      }
    });
    applyGltfScaleAndGround(root, 1.12);
    modelRoot.add(root);
  }

  function addFallbackRig(modelRoot, col, isEnemy) {
    disposeModelSubtree(modelRoot);
    var bodyCol = isEnemy ? 0x331111 : 0x0a1a2a;
    var bodyMat = new THREE.MeshStandardMaterial({ color: bodyCol, roughness: 0.7, metalness: 0.3 });
    var torso = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.65, 0.28), bodyMat);
    torso.position.y = 1.0;
    torso.castShadow = true;
    torso.userData.isTorso = true;
    modelRoot.add(torso);
    var headMat = new THREE.MeshStandardMaterial({ color: isEnemy ? 0x442222 : 0x1a2a3a, roughness: 0.5, metalness: 0.2 });
    var head = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.38, 0.32), headMat);
    head.position.y = 1.58;
    head.castShadow = true;
    modelRoot.add(head);
    var legMat = new THREE.MeshStandardMaterial({ color: isEnemy ? 0x221111 : 0x061018, roughness: 0.8 });
    var legL = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.5, 0.2), legMat);
    legL.position.set(-0.13, 0.48, 0);
    modelRoot.add(legL);
    var legR = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.5, 0.2), legMat);
    legR.position.set(0.13, 0.48, 0);
    modelRoot.add(legR);
    var armMat = new THREE.MeshStandardMaterial({ color: bodyCol, roughness: 0.7, metalness: 0.2 });
    var armL = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.5, 0.18), armMat);
    armL.position.set(-0.37, 1.0, 0);
    modelRoot.add(armL);
    var armR = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.5, 0.18), armMat);
    armR.position.set(0.37, 1.0, 0);
    armR.userData.isArmR = true;
    modelRoot.add(armR);
  }

  function bindFallbackRefs(group) {
    var torso = null;
    var armR = null;
    if (group.userData.modelRoot) {
      group.userData.modelRoot.traverse(function (c) {
        if (c.userData && c.userData.isTorso) torso = c;
        if (c.userData && c.userData.isArmR) armR = c;
      });
    }
    group.userData.torso = torso;
    group.userData.armR = armR;
  }

  /** GLTF skeletal clips: prefer name matching "idle/stand/t-pose", else first clip.
   *  Optional `group` param: if provided, attaches `group.userData.playAnimation(name)` with cross-fade. */
  function startGltfIdleMixer(root, animations, group) {
    if (!root || !animations || !animations.length || typeof THREE.AnimationMixer !== 'function') {
      return null;
    }
    console.log('[arena] animations:', animations.map(function(c){ return c.name; }));
    var clip = null;
    var ci;
    for (ci = 0; ci < animations.length; ci++) {
      var n = (animations[ci].name || '').toLowerCase();
      if (/idle|stand|t-pose|tpose/.test(n)) { clip = animations[ci]; break; }
    }
    if (!clip) clip = animations[0];
    var mixer = new THREE.AnimationMixer(root);
    var allActions = {};
    for (ci = 0; ci < animations.length; ci++) {
      allActions[animations[ci].name] = mixer.clipAction(animations[ci]);
    }
    mixer._kndActions = allActions;
    var act = allActions[clip.name];
    act.reset();
    act.setLoop(THREE.LoopRepeat, Infinity);
    act.play();
    if (group) {
      group.userData.playAnimation = function(name) {
        if (!allActions[name]) {
          console.warn('[arena] anim not found:', name, '| available:', Object.keys(allActions));
          return;
        }
        Object.keys(allActions).forEach(function(k){ allActions[k].fadeOut(0.2); });
        allActions[name].reset().fadeIn(0.2).play();
      };
    }
    return mixer;
  }

  /**
   * @param {object|null} state — G ally/enemy (name, rarity, id, hp, modelGlb, …)
   */
  function createUnitFromState(state, x, z, isEnemy) {
    var rawName = state && state.name != null ? String(state.name).trim() : '';
    var name = rawName || (isEnemy ? 'ENEMY' : 'ALLY');
    var rarity = state && state.rarity ? String(state.rarity).toLowerCase().trim() : 'common';
    if (!rarity) rarity = 'common';
  
    var unitId = state && state.id != null ? state.id : null;
    var hp = state && state.hp != null ? Number(state.hp) : 1;
    var dead = !state || hp <= 0;
  
    var col = RARITY_COLORS[rarity] || 0x4488ff;
  
    var group = new THREE.Group();
  
    // plataforma
    var plat = new THREE.Mesh(
      new THREE.CylinderGeometry(0.5, 0.6, 0.06, 12),
      new THREE.MeshStandardMaterial({
        color: col,
        roughness: 0.3,
        metalness: 0.8,
        emissive: col,
        emissiveIntensity: 0.15
      })
    );
    plat.position.y = 0.03;
    group.add(plat);
  
    var ring = new THREE.Mesh(
      new THREE.RingGeometry(0.55, 0.62, 32),
      new THREE.MeshBasicMaterial({
        color: col,
        transparent: true,
        opacity: 0.4,
        side: THREE.DoubleSide
      })
    );
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.04;
    group.add(ring);
  
    var modelRoot = new THREE.Group();
    group.add(modelRoot);

    var modelUrl = state && state.modelGlb ? String(state.modelGlb).trim() : '';

    if (!modelUrl) {
      addFallbackRig(modelRoot, col, isEnemy);
    } else if (typeof THREE.GLTFLoader === 'function') {
      var loader = new THREE.GLTFLoader();
      var draco = getSharedDracoLoader();
      if (draco) {
        loader.setDRACOLoader(draco);
      }
      if (mwGlbDebugEnabled() && typeof console !== 'undefined' && console.log) {
        // console.log('[MWArena GLB] cargando:', modelUrl);
      }
      ensureMeshoptOnLoader(loader, function () {
        loader.load(
          modelUrl,
          function (gltf) {
            if (mwGlbDebugEnabled() && typeof console !== 'undefined' && console.log) {
              // console.log('[MWArena GLB] OK:', modelUrl);
            }
            /* Try hologram layers first */
            if (window.MWHologramLayers && typeof MWHologramLayers.buildIntoGroup === 'function') {
              var holoUnis = [];
              MWHologramLayers.buildIntoGroup(gltf, modelRoot, holoP, {
                shaderUniforms: holoUnis,
                targetHeight: 1.2,
                enemyTint: isEnemy
              });
              group.userData.holoUniforms = holoUnis;
              group.userData._usingHolo = true;
              group.userData.animMixer = modelRoot.userData.mwAnimationMixer || null;
              if (mwGlbDebugEnabled()) {
                // console.log('[MWArena GLB] hologram applied:', modelUrl, holoUnis.length, 'layers');
              }
            } else {
              /* Plain GLB (no hologram shader available) */
              var root = gltf.scene;
              root.traverse(function (o) {
                if (o.isMesh) {
                  o.castShadow = true;
                  o.receiveShadow = true;
                }
              });
              applyGltfScaleAndGround(root, 1.2);
              modelRoot.add(root);
              group.userData.animMixer = startGltfIdleMixer(root, gltf.animations, group);
            }
          },
          undefined,
          function (error) {
            if (typeof console !== 'undefined' && console.error) {
              console.error('[MWArena GLB] error:', modelUrl, error);
            }
            addFallbackRig(modelRoot, col, isEnemy);
            bindFallbackRefs(group);
          }
        );
      });
    } else {
      addFallbackRig(modelRoot, col, isEnemy);
    }

    /* Allies face enemies (away from camera toward Z-), enemies face allies (toward camera, Z+) */
    if (!isEnemy) group.rotation.y = Math.PI;
    group.position.set(x, 0, z);

    if (dead) group.scale.set(0.001, 0.001, 0.001);

    group.userData = {
      isUnitRoot: true,
      name: String(name || 'UNIT').toUpperCase(),
      rarity: rarity,
      isEnemy: isEnemy,
      isDead: !!dead,
      basePos: { x: x, z: z },
      ring: ring,
      torso: null,
      armR: null,
      unitId: unitId == null ? null : unitId,
      modelRoot: modelRoot,
      holoUniforms: [],
      _usingHolo: false,
      animMixer: null
    };

    bindFallbackRefs(group);
    scene.add(group);

    return group;
  }

  function buildLighting() {
    /* Ambient: brighter, slightly warm white so models are always visible */
    var amb = new THREE.AmbientLight(0x667788, 2.0);
    scene.add(amb);

    /* Key light: strong white-blue from above-front, casts shadows */
    var dir = new THREE.DirectionalLight(0xccddff, 2.5);
    dir.position.set(3, 12, 8);
    dir.castShadow = true;
    dir.shadow.mapSize.width = 1024;
    dir.shadow.mapSize.height = 1024;
    dir.shadow.camera.near = 0.5;
    dir.shadow.camera.far = 30;
    dir.shadow.camera.left = -8;
    dir.shadow.camera.right = 8;
    dir.shadow.camera.top = 8;
    dir.shadow.camera.bottom = -8;
    scene.add(dir);

    /* Fill light: softer, from the opposite side to reduce harsh shadows */
    var fill = new THREE.DirectionalLight(0x8899bb, 1.2);
    fill.position.set(-5, 6, -3);
    scene.add(fill);

    /* Rim / accent: subtle colored backlight for depth */
    var rim = new THREE.DirectionalLight(0xff4466, 0.6);
    rim.position.set(-6, 4, -8);
    scene.add(rim);

    /* Hemisphere: sky/ground bounce — lifts dark undersides of models */
    var hemi = new THREE.HemisphereLight(0x88aacc, 0x222244, 1.0);
    scene.add(hemi);
  }

  function buildArena() {
    arenaGroup = new THREE.Group();

    /* ── FLOOR — dark reflective surface ── */
    var floorGeo = new THREE.CircleGeometry(12, 64);
    var floorMat = new THREE.MeshStandardMaterial({
      color: 0x020810,
      roughness: 0.4,
      metalness: 0.6,
      emissive: 0x000a18,
      emissiveIntensity: 0.08
    });
    var plane = new THREE.Mesh(floorGeo, floorMat);
    plane.rotation.x = -Math.PI / 2;
    plane.receiveShadow = true;
    arenaGroup.add(plane);

    /* ── HEX GRID — sci-fi honeycomb pattern ── */
    var gridHelper = new THREE.GridHelper(24, 24, 0x0a3050, 0x061828);
    gridHelper.material.opacity = 0.18;
    gridHelper.material.transparent = true;
    arenaGroup.add(gridHelper);

    /* ── CONCENTRIC RINGS — pulsing energy rings ── */
    var ringRadii = [2.5, 4.2, 6.0, 8.0, 10.0];
    var ringColors = [0x00f0ff, 0x00c8dd, 0x00a0bb, 0x008099, 0x006077];
    for (var ri = 0; ri < ringRadii.length; ri++) {
      var rGeo = new THREE.RingGeometry(ringRadii[ri] - 0.02, ringRadii[ri] + 0.02, 80);
      var rMat = new THREE.MeshBasicMaterial({
        color: ringColors[ri],
        transparent: true,
        opacity: 0.12 - ri * 0.015,
        side: THREE.DoubleSide,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      });
      var rMesh = new THREE.Mesh(rGeo, rMat);
      rMesh.rotation.x = -Math.PI / 2;
      rMesh.position.y = 0.01;
      rMesh.userData._arenaRing = true;
      rMesh.userData._ringIdx = ri;
      arenaGroup.add(rMesh);
    }

    /* ── CENTER CORE — glowing energy nexus ── */
    var coreGeo = new THREE.CircleGeometry(1.8, 48);
    var coreMat = new THREE.MeshBasicMaterial({
      color: 0x00d4ff,
      transparent: true,
      opacity: 0.1,
      side: THREE.DoubleSide,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    var coreGlow = new THREE.Mesh(coreGeo, coreMat);
    coreGlow.rotation.x = -Math.PI / 2;
    coreGlow.position.y = 0.015;
    coreGlow.userData._arenaCore = true;
    arenaGroup.add(coreGlow);

    /* ── OUTER GLOW — wide subtle aura ── */
    var outerGeo = new THREE.RingGeometry(5, 11.5, 64);
    var outerMat = new THREE.MeshBasicMaterial({
      color: 0x004466,
      transparent: true,
      opacity: 0.06,
      side: THREE.DoubleSide,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    var outerGlow = new THREE.Mesh(outerGeo, outerMat);
    outerGlow.rotation.x = -Math.PI / 2;
    outerGlow.position.y = 0.012;
    arenaGroup.add(outerGlow);

    /* ── DIVIDER LINE — mid-field energy barrier ── */
    var divGeo = new THREE.PlaneGeometry(14, 0.04);
    var divMat = new THREE.MeshBasicMaterial({
      color: 0x00ccff,
      transparent: true,
      opacity: 0.15,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    var divLine = new THREE.Mesh(divGeo, divMat);
    divLine.rotation.x = -Math.PI / 2;
    divLine.position.y = 0.018;
    arenaGroup.add(divLine);

    /* ── LANE MARKERS — subtle position guides ── */
    var laneXs = [-2.2, 0, 2.2];
    for (var li = 0; li < laneXs.length; li++) {
      var lGeo = new THREE.PlaneGeometry(0.015, 10);
      var lMat = new THREE.MeshBasicMaterial({
        color: 0x003355,
        transparent: true,
        opacity: 0.2,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      });
      var lane = new THREE.Mesh(lGeo, lMat);
      lane.rotation.x = -Math.PI / 2;
      lane.position.set(laneXs[li], 0.013, 0);
      arenaGroup.add(lane);
    }

    /* ── VERTICAL BEAMS — energy columns at corners ── */
    var beamPositions = [
      [-5, 0, -5], [5, 0, -5], [-5, 0, 5], [5, 0, 5],
      [0, 0, -6], [0, 0, 6]
    ];
    var beamColors = [0x0066aa, 0x0066aa, 0x0066aa, 0x0066aa, 0xcc00ff, 0x00ccff];
    for (var bi = 0; bi < beamPositions.length; bi++) {
      var bGeo = new THREE.CylinderGeometry(0.02, 0.02, 18, 6);
      var bMat = new THREE.MeshBasicMaterial({
        color: beamColors[bi],
        transparent: true,
        opacity: 0.12,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      });
      var beam = new THREE.Mesh(bGeo, bMat);
      beam.position.set(beamPositions[bi][0], 9, beamPositions[bi][2]);
      arenaGroup.add(beam);

      /* Beam base glow */
      var bgGeo = new THREE.CircleGeometry(0.35, 16);
      var bgMat = new THREE.MeshBasicMaterial({
        color: beamColors[bi],
        transparent: true,
        opacity: 0.15,
        side: THREE.DoubleSide,
        blending: THREE.AdditiveBlending,
        depthWrite: false
      });
      var baseGlow = new THREE.Mesh(bgGeo, bgMat);
      baseGlow.rotation.x = -Math.PI / 2;
      baseGlow.position.set(beamPositions[bi][0], 0.02, beamPositions[bi][2]);
      arenaGroup.add(baseGlow);
    }

    /* ── ARENA LIGHTING — dramatic spots ── */
    var arenaSpot = new THREE.SpotLight(0x0088cc, 1.2, 25, 0.6, 0.5, 1);
    arenaSpot.position.set(0, 12, 0);
    arenaSpot.target.position.set(0, 0, 0);
    arenaGroup.add(arenaSpot);
    arenaGroup.add(arenaSpot.target);

    var playerSpot = new THREE.SpotLight(0x00ccff, 0.5, 16, 0.5, 0.6, 1);
    playerSpot.position.set(0, 8, 6);
    playerSpot.target.position.set(0, 0, 3.5);
    arenaGroup.add(playerSpot);
    arenaGroup.add(playerSpot.target);

    var enemySpot = new THREE.SpotLight(0xcc44ff, 0.4, 16, 0.5, 0.6, 1);
    enemySpot.position.set(0, 8, -6);
    enemySpot.target.position.set(0, 0, -3.5);
    arenaGroup.add(enemySpot);
    arenaGroup.add(enemySpot.target);

    var arenaFill = new THREE.PointLight(0x224466, 0.5, 18);
    arenaFill.position.set(0, 3, 0);
    arenaGroup.add(arenaFill);

    scene.add(arenaGroup);
  }

  function buildSkybox() {
    /* Dense star field — two layers for parallax depth */
    var count1 = 1500;
    var pos1 = new Float32Array(count1 * 3);
    for (var i = 0; i < count1 * 3; i++) pos1[i] = (Math.random() - 0.5) * 90;
    var sg1 = new THREE.BufferGeometry();
    sg1.setAttribute('position', new THREE.BufferAttribute(pos1, 3));
    scene.add(new THREE.Points(sg1, new THREE.PointsMaterial({
      color: 0xaabbdd, size: 0.1, transparent: true, opacity: 0.7
    })));

    var count2 = 400;
    var pos2 = new Float32Array(count2 * 3);
    for (var j = 0; j < count2 * 3; j++) pos2[j] = (Math.random() - 0.5) * 70;
    var sg2 = new THREE.BufferGeometry();
    sg2.setAttribute('position', new THREE.BufferAttribute(pos2, 3));
    scene.add(new THREE.Points(sg2, new THREE.PointsMaterial({
      color: 0x6688ff, size: 0.22, transparent: true, opacity: 0.5
    })));

    /* Bright accent stars */
    var count3 = 80;
    var pos3 = new Float32Array(count3 * 3);
    for (var k = 0; k < count3 * 3; k++) pos3[k] = (Math.random() - 0.5) * 85;
    var sg3 = new THREE.BufferGeometry();
    sg3.setAttribute('position', new THREE.BufferAttribute(pos3, 3));
    scene.add(new THREE.Points(sg3, new THREE.PointsMaterial({
      color: 0xffffff, size: 0.3, transparent: true, opacity: 0.9
    })));

    /* Background sphere with deep space gradient */
    var bgGeo = new THREE.SphereGeometry(48, 16, 16);
    var bgMat = new THREE.MeshBasicMaterial({ color: 0x000206, side: THREE.BackSide });
    scene.add(new THREE.Mesh(bgGeo, bgMat));

    /* Nebula accents — subtle colored spheres */
    var nebulaData = [
      { pos: [-20, 15, -30], color: 0x220044, size: 12, opacity: 0.04 },
      { pos: [25, -10, -25], color: 0x001133, size: 15, opacity: 0.035 },
      { pos: [-15, -8, 20], color: 0x002244, size: 10, opacity: 0.03 },
      { pos: [10, 20, -20], color: 0x110022, size: 8, opacity: 0.05 }
    ];
    for (var ni = 0; ni < nebulaData.length; ni++) {
      var nd = nebulaData[ni];
      var nGeo = new THREE.SphereGeometry(nd.size, 8, 8);
      var nMat = new THREE.MeshBasicMaterial({
        color: nd.color,
        transparent: true,
        opacity: nd.opacity,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        side: THREE.BackSide
      });
      var nMesh = new THREE.Mesh(nGeo, nMat);
      nMesh.position.set(nd.pos[0], nd.pos[1], nd.pos[2]);
      scene.add(nMesh);
    }
  }

  var idleT = 0;
  function animate() {
    requestAnimationFrame(animate);
    var dt = clock.getDelta();
    var elapsed = clock.getElapsedTime();
    idleT += dt;

    /* Update hologram shader uniforms + skeletal animation mixers */
    playerUnits.concat(enemyUnits).forEach(function (u) {
      if (!u) return;
      var mx = u.userData.animMixer;
      if (mx && typeof mx.update === 'function') {
        mx.update(dt);
      }
      if (u.userData.holoUniforms && u.userData.holoUniforms.length) {
        u.userData.holoUniforms.forEach(function (uni) {
          uni.uTime.value = elapsed;
        });
      }
    });

    /* Unit idle bobbing */
    playerUnits.forEach(function (u, i) {
      if (u && !u.userData.isDead) {
        u.position.y = Math.sin(idleT * 1.2 + i * 1.1) * 0.04;
        if (u.userData.ring) u.userData.ring.material.opacity = 0.2 + Math.sin(idleT * 2 + i) * 0.15;
      }
    });
    enemyUnits.forEach(function (u, i) {
      if (u && !u.userData.isDead) {
        u.position.y = Math.sin(idleT * 1.1 + i * 1.3 + 2) * 0.035;
        if (u.userData.ring) u.userData.ring.material.opacity = 0.15 + Math.sin(idleT * 1.8 + i) * 0.12;
      }
    });

    /* Animate arena rings — pulsing outward */
    if (arenaGroup) {
      arenaGroup.traverse(function (child) {
        if (child.userData._arenaRing) {
          var idx = child.userData._ringIdx || 0;
          var pulse = Math.sin(elapsed * 1.5 - idx * 0.8) * 0.5 + 0.5;
          child.material.opacity = (0.06 + pulse * 0.08) - idx * 0.008;
        }
        if (child.userData._arenaCore) {
          child.material.opacity = 0.06 + Math.sin(elapsed * 2.0) * 0.04;
        }
      });
    }
    particleSystems.forEach(function (ps) {
      if (!ps.alive) return;
      ps.t += dt;
      if (ps.t > ps.life) {
        ps.alive = false;
        scene.remove(ps.mesh);
        return;
      }
      var posArr = ps.mesh.geometry.attributes.position.array;
      for (var i = 0; i < ps.count; i++) {
        posArr[i * 3] += ps.vel[i * 3] * dt;
        posArr[i * 3 + 1] += ps.vel[i * 3 + 1] * dt;
        posArr[i * 3 + 2] += ps.vel[i * 3 + 2] * dt;
        ps.vel[i * 3 + 1] -= 4 * dt;
      }
      ps.mesh.geometry.attributes.position.needsUpdate = true;
      ps.mesh.material.opacity = Math.max(0, 1 - ps.t / ps.life);
    });
    /* Idle camera sway — subtle orbit when no animation is playing */
    if (typeof animating === 'undefined' || !animating) {
      var swayX = Math.sin(elapsed * 0.15) * 0.12;
      var swayY = Math.cos(elapsed * 0.2) * 0.06;
      camera.position.x = originalCamPos.x + swayX;
      camera.position.y = originalCamPos.y + swayY;
      camera.lookAt(originalCamTarget.x, originalCamTarget.y, originalCamTarget.z);
    }

    renderer.render(scene, camera);

    /* Rebuild floating HP bars every frame — always accurate, always visible */
    renderFloatingHPBars();
  }

  /** Render floating HP bars above every alive unit — called every frame */
  function renderFloatingHPBars() {
    var hpOverlay = document.getElementById('mw-hp-overlay');
    if (!hpOverlay || !camera || !containerEl) return;
    var g = getG();
    if (!g) return;
    hpOverlay.innerHTML = '';
    var W = containerEl.clientWidth;
    var H = containerEl.clientHeight;
    var pa = sortedByPos(g.allies);
    var pe = sortedByPos(g.enemies);

    function renderOne(unit, unit3d, isEnemy) {
      if (!unit || !unit3d || unit.hp <= 0 || (unit3d.userData && unit3d.userData.isDead)) return;
      var v = new THREE.Vector3();
      unit3d.getWorldPosition(v);
      v.y += 1.65;
      v.project(camera);
      if (v.z > 1) return;
      var sx = (v.x * 0.5 + 0.5) * W;
      var sy = (-v.y * 0.5 + 0.5) * H;
      if (sx < -80 || sx > W + 80 || sy < -60 || sy > H + 60) return;

      var maxHp = Math.max(1, Number(unit.maxHp) || 1);
      var hp = Math.max(0, Number(unit.hp) || 0);
      var pct = (hp / maxHp) * 100;
      var pctClass = pct < 20 ? 'crit' : pct < 40 ? 'low' : '';

      var el = document.createElement('div');
      el.className = 'mw-hp-float';
      el.style.left = sx + 'px';
      el.style.top = sy + 'px';
      el.innerHTML = '<div class="mw-hp-float-name' + (isEnemy ? ' enemy' : '') + '">' +
        String(unit.name || 'UNIT').toUpperCase() +
        '</div><div class="mw-hp-float-track"><div class="mw-hp-float-fill ' +
        (isEnemy ? 'enemy' : 'player') + ' ' + pctClass +
        '" style="width:' + pct + '%"></div></div>' +
        '<div class="mw-hp-float-nums">' + hp + ' / ' + maxHp + '</div>';
      hpOverlay.appendChild(el);
    }

    for (var i = 0; i < 3; i++) {
      renderOne(pa[i], playerUnits[i], false);
      renderOne(pe[i], enemyUnits[i], true);
    }
  }

  function spawnParticles(x, y, z, color, count) {
    var geo = new THREE.BufferGeometry();
    var pos = new Float32Array(count * 3);
    var vel = new Float32Array(count * 3);
    for (var i = 0; i < count; i++) {
      pos[i * 3] = x + (Math.random() - 0.5) * 0.3;
      pos[i * 3 + 1] = y + (Math.random() - 0.5) * 0.3;
      pos[i * 3 + 2] = z + (Math.random() - 0.5) * 0.3;
      vel[i * 3] = (Math.random() - 0.5) * 4;
      vel[i * 3 + 1] = Math.random() * 5 + 1;
      vel[i * 3 + 2] = (Math.random() - 0.5) * 4;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    var mat = new THREE.PointsMaterial({ color: color, size: 0.12, transparent: true, opacity: 1 });
    var mesh = new THREE.Points(geo, mat);
    scene.add(mesh);
    particleSystems.push({ mesh: mesh, vel: vel, count: count, t: 0, life: 0.9, alive: true });
  }

  function flashScreen(col, dur) {
    var fl = document.getElementById('mw-flash-overlay');
    if (!fl) return;
    fl.style.background = col || 'white';
    fl.style.transition = 'none';
    fl.style.opacity = '0.85';
    setTimeout(function () {
      fl.style.transition = 'opacity ' + (dur || 0.25) + 's ease';
      fl.style.opacity = '0';
    }, 30);
  }

  function showDmgNumber(txt, color) {
    var el = document.getElementById('mw-dmg-number');
    if (!el) return;
    el.textContent = txt;
    el.style.color = color || '#ff4444';
    el.style.textShadow = '0 0 12px ' + (color || '#ff4444');
    el.style.fontSize = '28px';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0px)';
    setTimeout(function () {
      el.style.fontSize = '18px';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-20px)';
      el.style.transition = 'all 0.5s ease';
    }, 600);
    setTimeout(function () {
      el.style.transition = '';
      el.style.transform = '';
    }, 1200);
  }

  function setCinematicBars(on) {
    var t = document.getElementById('mw-cinematic-bars-top');
    var b = document.getElementById('mw-cinematic-bars-bot');
    if (t) t.style.height = on ? '38px' : '0';
    if (b) b.style.height = on ? '38px' : '0';
  }

  function showAbilityCaption(name, sub) {
    var cap = document.getElementById('mw-ability-caption');
    var nm = document.getElementById('mw-ability-name');
    var sb = document.getElementById('mw-ability-sub');
    if (!cap || !nm || !sb) return;
    nm.textContent = name;
    sb.textContent = sub;
    cap.style.opacity = '1';
    setTimeout(function () {
      cap.style.opacity = '0';
    }, 1800);
  }

  function shakeCanvas(intensity, dur) {
    var c = containerEl;
    if (!c) return;
    var t0 = performance.now();
    function sh() {
      var elapsed = performance.now() - t0;
      if (elapsed > dur) {
        c.style.transform = '';
        return;
      }
      var decay = 1 - elapsed / dur;
      var dx = (Math.random() - 0.5) * intensity * decay;
      var dy = (Math.random() - 0.5) * intensity * decay;
      c.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
      requestAnimationFrame(sh);
    }
    sh();
  }

  function lerpCam(tx, ty, tz, lookX, lookY, lookZ, dur, cb) {
    var sx = camera.position.x,
      sy = camera.position.y,
      sz = camera.position.z;
    var tgt = new THREE.Vector3();
    camera.getWorldDirection(tgt);
    tgt.multiplyScalar(20).add(camera.position);
    var slx = tgt.x,
      sly = tgt.y,
      slz = tgt.z;
    var t0 = performance.now();
    function step() {
      var p = Math.min((performance.now() - t0) / dur, 1);
      var e = p < 0.5 ? 2 * p * p : -2 * p * p + 4 * p - 1;
      camera.position.set(sx + (tx - sx) * e, sy + (ty - sy) * e, sz + (tz - sz) * e);
      camera.lookAt(slx + (lookX - slx) * e, sly + (lookY - sly) * e, slz + (lookZ - slz) * e);
      if (p < 1) requestAnimationFrame(step);
      else if (cb) cb();
    }
    step();
  }

  function resetCamera(dur) {
    lerpCam(originalCamPos.x, originalCamPos.y, originalCamPos.z, originalCamTarget.x, originalCamTarget.y, originalCamTarget.z, dur || 600);
  }

  function addLog(txt, cls) {
    if (typeof lg === 'function') {
      var ty = 'sys';
      if (cls && cls.indexOf('player') >= 0) ty = 'pa';
      if (cls && cls.indexOf('enemy') >= 0) ty = 'ea';
      lg(escTxt(txt), ty);
    }
  }

  function syncHudFromG() {
    var g = getG();
    if (!g) return;
    var pa = sortedByPos(g.allies);
    var pe = sortedByPos(g.enemies);
    var posLbl = ['FRONT', 'MID', 'BACK'];

    /* Legacy corner bars (kept for fallback but hidden via CSS) */
    for (var i = 0; i < 3; i++) {
      var a = pa[i];
      var tag = document.querySelector('#mw-phb' + i + ' .mw-unit-name-tag');
      var hpEl = document.getElementById('mw-php' + i);
      var nums = document.querySelector('#mw-phb' + i + ' .mw-hp-nums');
      if (a && tag) tag.textContent = String(a.name || 'ALLY').toUpperCase() + ' // ' + posLbl[i];
      if (a && hpEl && nums) {
        var mh = Math.max(1, Number(a.maxHp) || 1);
        var h = Math.max(0, Number(a.hp) || 0);
        hpEl.style.width = (h / mh) * 100 + '%';
        nums.textContent = h + ' / ' + mh;
      }
      var e = pe[i];
      tag = document.querySelector('#mw-ehb' + i + ' .mw-unit-name-tag');
      hpEl = document.getElementById('mw-ehp' + i);
      nums = document.querySelector('#mw-ehb' + i + ' .mw-hp-nums');
      if (e && tag) tag.textContent = String(e.name || 'ENEMY').toUpperCase() + ' // ' + posLbl[i];
      if (e && hpEl && nums) {
        var mhE = Math.max(1, Number(e.maxHp) || 1);
        var hE = Math.max(0, Number(e.hp) || 0);
        var pct = (hE / mhE) * 100;
        hpEl.style.width = pct + '%';
        if (pct < 30) hpEl.classList.add('low');
        else hpEl.classList.remove('low');
        nums.textContent = hE + ' / ' + mhE;
      }
    }
    /* Floating HP bars are now rendered every frame in renderFloatingHPBars() */
  }

  function disposeUnitComplete(u) {
    if (!u) return;
    u.traverse(function (o) {
      if (o.isMesh) {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(function (m) { m.dispose(); });
          else o.material.dispose();
        }
      }
    });
    scene.remove(u);
  }

  function clearUnits() {
    playerUnits.forEach(disposeUnitComplete);
    enemyUnits.forEach(disposeUnitComplete);
    playerUnits = [];
    enemyUnits = [];
  }

  function rebuildFromG() {

    var g = getG();
    if (!g || !scene) return;
    /* Skip if G exists but has no populated units yet (init() hasn't run) */
    var hasUnits = (g.allies && g.allies.length > 0) || (g.enemies && g.enemies.length > 0);
    if (!hasUnits) {
      // console.log('[MWArena] rebuildFromG: skipped — no units in G yet');
      return;
    }
    clearUnits();
    var pa = sortedByPos(g.allies);
    var pe = sortedByPos(g.enemies);
    var px = [-2.2, 0, 2.2];
    var pz = [3.2, 3.8, 4.4];
    var ex = [-2.2, 0, 2.2];
    var ez = [-3.2, -3.8, -4.4];
    for (var i = 0; i < 3; i++) {
      playerUnits[i] = createUnitFromState(pa[i] || null, px[i], pz[i], false);
      enemyUnits[i] = createUnitFromState(pe[i] || null, ex[i], ez[i], true);
    }
    syncHudFromG();
  }

  /** Sync HP HUD from G only — mesh death state is driven by executeUnitDeath / rebuildFromG */
  function syncFromG() {
    syncHudFromG();
  }

  function getActionContext() {
    return {
      player: playerUnits,
      enemy: enemyUnits,
      camera: camera,
      scene: scene,
      spawnParticles: spawnParticles,
      flashScreen: flashScreen,
      shakeCanvas: shakeCanvas,
      showDmgNumber: showDmgNumber,
      setCinematicBars: setCinematicBars,
      showAbilityCaption: showAbilityCaption,
      lerpCam: lerpCam,
      resetCamera: resetCamera,
      addLog: addLog,
      updateHP: function (side, slot, damage) {
        syncHudFromG();
      }
    };
  }

  function findUnitRoot(obj) {
    while (obj) {
      if (obj.userData && obj.userData.isUnitRoot) return obj;
      obj = obj.parent;
    }
    return null;
  }

  function onCanvasClick(ev) {
    if (!pickEnemyMode || !raycaster || !camera) return;
    var g = getG();
    if (!g) return;
    var rect = canvas.getBoundingClientRect();
    pointer.x = ((ev.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((ev.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);
    var roots = playerUnits.concat(enemyUnits).filter(function (u) {
      return u && !u.userData.isDead;
    });
    var hits = raycaster.intersectObjects(roots, true);
    for (var i = 0; i < hits.length; i++) {
      var root = findUnitRoot(hits[i].object);
      if (root && root.userData.isEnemy && root.userData.unitId != null) {
        var en = g.enemies.filter(function (e) {
          return e.id === root.userData.unitId;
        })[0];
        if (en && en.hp > 0 && typeof selectTarget === 'function') {
          selectTarget(en);
        }
        break;
      }
    }
  }

  function setEnemyPickMode(on) {
    pickEnemyMode = !!on;
    if (containerEl) containerEl.style.cursor = on ? 'crosshair' : '';
  }

  function flashUnitById(unitId) {
    var all = playerUnits.concat(enemyUnits);
    for (var i = 0; i < all.length; i++) {
      var u = all[i];
      if (!u || u.userData.unitId !== unitId) continue;
      if (u.userData.torso && u.userData.torso.material) {
        var m = u.userData.torso.material;
        var prev = m.emissive ? m.emissive.getHex() : 0;
        m.emissive = new THREE.Color(0xffffff);
        m.emissiveIntensity = 0.6;
        setTimeout(function () {
          m.emissive.setHex(prev);
          m.emissiveIntensity = 0;
        }, 120);
      } else if (u.userData.holoUniforms && u.userData.holoUniforms.length) {
        u.userData.holoUniforms.forEach(function (uni) {
          var o = uni.uOpacity.value;
          uni.uOpacity.value = Math.min(1.35, o * 1.4);
          setTimeout(function () {
            uni.uOpacity.value = o;
          }, 140);
        });
      }
      break;
    }
  }

  function popOnUnit(unitId, text, ty) {
    var all = playerUnits.concat(enemyUnits);
    var root = null;
    for (var i = 0; i < all.length; i++) {
      if (all[i] && all[i].userData.unitId === unitId) {
        root = all[i];
        break;
      }
    }
    var host = document.getElementById('mw-floating-pops');
    if (!host || !root || !camera) return;
    var v = new THREE.Vector3();
    root.getWorldPosition(v);
    v.y += 1.6;
    v.project(camera);
    var x = (v.x * 0.5 + 0.5) * containerEl.clientWidth;
    var y = (-v.y * 0.5 + 0.5) * containerEl.clientHeight;
    var p = document.createElement('div');
    p.className = 'mw-dp ' + (ty || 'damage');
    p.textContent = text;
    p.style.left = x + 'px';
    p.style.top = y + 'px';
    host.appendChild(p);
    setTimeout(function () {
      p.remove();
    }, 1300);
  }

  function skillCodeFromAbility(ab) {
    if (!ab || !ab.name) return 'ability';
    return String(ab.name)
      .toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/[^a-z0-9_]/g, '');
  }

  function unitSlot(u) {
    var g = getG();
    if (!g || !u) return 0;
    var arr = u.isEnemy ? g.enemies : g.allies;
    return sortedByPos(arr).indexOf(u);
  }

  function meshForBattleUnit(u) {
    if (!u) return null;
    var all = playerUnits.concat(enemyUnits);
    for (var i = 0; i < all.length; i++) {
      if (all[i] && all[i].userData.unitId === u.id) return all[i];
    }
    return null;
  }

  /** SquadWars playback: acercar cámara a la línea atacante–objetivo; crit = más cerca / más alto. */
  function focusStrikeCamera(attackerU, targetU, opts) {
    opts = opts || {};
    var ctx = getActionContext();
    var a = meshForBattleUnit(attackerU);
    var t = meshForBattleUnit(targetU);
    if (!a || !t) return Promise.resolve();
    var mx = (a.position.x + t.position.x) * 0.5;
    var mz = (a.position.z + t.position.z) * 0.5;
    var isPl = !a.userData.isEnemy;
    var zPull = opts.crit ? 2.95 : 3.85;
    var zOff = isPl ? mz + zPull : mz - zPull;
    var yLift = opts.crit ? 3.1 : 2.68;
    var dur = opts.crit ? 520 : 430;
    return new Promise(function (resolve) {
      ctx.lerpCam(mx * 0.5, yLift, zOff, mx, 1.08, mz, dur, function () { resolve(); });
    });
  }

  function resetStrikeCamera() {
    return new Promise(function (resolve) {
      getActionContext().resetCamera(440);
      setTimeout(resolve, 200);
    });
  }

  function playAttackVisual(attacker, target, ab, r, side, playbackOpts) {
    playbackOpts = playbackOpts || {};
    var ctx = getActionContext();
    var aslot = unitSlot(attacker);
    var tslot = unitSlot(target);
    var dmg = r && r.damage != null ? r.damage : 0;
    if (r && r.evaded) {
      showDmgNumber('EVADE', '#88aacc');
      return Promise.resolve();
    }
    if (r && r.damage === 0 && !r.evaded) {
      showDmgNumber('BLOCK', '#00f0ff');
      return Promise.resolve();
    }
    var typ = ab && ab.type ? String(ab.type).toLowerCase() : 'attack';
    if (typ === 'special') {
      return executeSpecial(aslot, tslot, skillCodeFromAbility(ab), dmg, false, null, side, ctx, playbackOpts);
    }
    if (typ === 'ability') {
      return executeAbility(aslot, tslot, skillCodeFromAbility(ab), dmg, [], side, ctx, playbackOpts);
    }
    return executeAttack(aslot, tslot, dmg, side, ctx, r, playbackOpts);
  }

  function playHealVisual(healer, targetUnit, amount, side) {
    var ctx = getActionContext();
    var healSide = healer && !healer.isEnemy ? 'player' : 'enemy';
    return executeHealBetween(unitSlot(healer), unitSlot(targetUnit), amount, healSide, ctx);
  }

  function playDefendVisual(unit, side) {
    return executeDefend(unitSlot(unit), side, getActionContext());
  }

  function maybeDeathAnim(target, sideLabel) {
    if (!target || target.hp > 0) return Promise.resolve();
    var slot = unitSlot(target);
    if (slot < 0 || slot > 2) return Promise.resolve();
    return executeUnitDeath(slot, sideLabel, getActionContext());
  }

  /**
   * One cinematic special for multi-target hits (pre-resolved rolls).
   * @param {Array<{t:object,r:object}>} packs — target unit + calc result (damage, evaded, …)
   */
  function playSpecialAoeVisual(attacker, ab, packs, side) {
    var ctx = getActionContext();
    var aslot = unitSlot(attacker);
    var aoeTargets = [];
    var showDmg = 0;
    for (var i = 0; i < packs.length; i++) {
      var pr = packs[i];
      if (!pr || !pr.t || pr.r.evaded || pr.r.damage === 0) continue;
      aoeTargets.push({ slot: unitSlot(pr.t), damage: pr.r.damage });
      showDmg += pr.r.damage;
    }
    if (!aoeTargets.length) return Promise.resolve();
    var tslot = aoeTargets[0].slot;
    return executeSpecial(aslot, tslot, skillCodeFromAbility(ab), showDmg, true, aoeTargets, side, ctx);
  }

  function boot(container) {
    logMwArenaGlbBannerOnce();

    if (!container || typeof THREE === 'undefined') return;
    containerEl = container;
    canvas = document.getElementById('three-canvas');
    if (!canvas) return;
    if (!booted) {
      booted = true;
      renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: false });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.shadowMap.enabled = true;
      renderer.shadowMap.type = THREE.PCFSoftShadowMap;
      renderer.setClearColor(0x000008, 1);
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.0;
      renderer.outputEncoding = THREE.sRGBEncoding;
      renderer.sortObjects = true;
      var W = container.clientWidth,
        H = container.clientHeight;
      renderer.setSize(W, H);
      scene = new THREE.Scene();
      scene.fog = new THREE.FogExp2(0x000510, 0.04);
      camera = new THREE.PerspectiveCamera(55, W / H, 0.1, 100);
      camera.position.set(0, 4.5, 10);
      camera.lookAt(0, 1, 0);
      originalCamPos = { x: 0, y: 4.5, z: 10 };
      originalCamTarget = { x: 0, y: 1, z: 0 };
      clock = new THREE.Clock();
      raycaster = new THREE.Raycaster();
      pointer = new THREE.Vector2();
      buildLighting();
      buildArena();
      buildSkybox();
      animate();
      window.addEventListener('resize', onResize);
      canvas.addEventListener('click', onCanvasClick);
      if (window.MWHologramLayers) {
        holoP = window.MWHologramLayers.mergePreset({
          colorR: 0, colorG: 0.61, colorB: 3,
          opacity: 0.7, fresnelStrength: 0.5, innerGlow: 0.5,
          rimStrength: 1.3, pulseSpeed: 0.8, pulseStrength: 0.12,
          scanSpeed: 1.2, scanDensity: 14, flickerIntensity: 0.0,
          baseOpacity: 1, texOpacity: 0.38, holoOpacityMul: 1,
          ringOpacity: 0.55, ringPulseSpeed: 2.2, beamIntensity: 0.18
        });
      } else {
        holoP = {};
      }
      fetch('/tools/presets/preset-default.json')
        .then(function (r) {
          return r.ok ? r.json() : null;
        })
        .then(function (j) {
          if (j && typeof j === 'object' && window.MWHologramLayers) {
            holoP = window.MWHologramLayers.mergePreset(j);
            /* If units already loaded, rebuild with new preset */
            if (playerUnits.length > 0) rebuildFromG();
          }
        })
        .catch(function () {});
    } else {
      onResize();
    }
    runGlbSmokeTestOnce();
    /* Don't rebuild here — renderUnits() in init() will trigger the single rebuild */
  }

  function onResize() {
    if (!containerEl || !renderer || !camera) return;
    var W = containerEl.clientWidth,
      H = containerEl.clientHeight;
    if (W < 1 || H < 1) return;
    camera.aspect = W / H;
    camera.updateProjectionMatrix();
    renderer.setSize(W, H);
  }

  /**
   * Temporarily suppress hologram flicker/pulse/scan during combat animations.
   */
  var _holoStabilized = false;
  var _savedFlicker = [];
  function stabilizeHolos() {
    if (_holoStabilized) return;
    _holoStabilized = true;
    _savedFlicker = [];
    playerUnits.concat(enemyUnits).forEach(function (u) {
      if (!u || !u.userData.holoUniforms) return;
      u.userData.holoUniforms.forEach(function (uni) {
        _savedFlicker.push({
          uni: uni,
          flicker: uni.uFlickerIntensity.value,
          pulse: uni.uPulseStrength.value,
          scanSpeed: uni.uScanSpeed.value,
          pulseSpeed: uni.uPulseSpeed.value
        });
        /* Freeze all animated shader parameters */
        uni.uFlickerIntensity.value = 0;
        uni.uPulseStrength.value = 0;
        uni.uScanSpeed.value = 0;
        uni.uPulseSpeed.value = 0;
      });
    });
  }

  function restoreHolos() {
    if (!_holoStabilized) return;
    _holoStabilized = false;
    _savedFlicker.forEach(function (s) {
      s.uni.uFlickerIntensity.value = s.flicker;
      s.uni.uPulseStrength.value = s.pulse;
      s.uni.uScanSpeed.value = s.scanSpeed;
      s.uni.uPulseSpeed.value = s.pulseSpeed;
    });
    _savedFlicker = [];
  }

  window.MWArenaThree = {
    boot: boot,
    rebuildFromG: rebuildFromG,
    syncFromG: syncFromG,
    getActionContext: getActionContext,
    setEnemyPickMode: setEnemyPickMode,
    flashUnitById: flashUnitById,
    popOnUnit: popOnUnit,
    unitSlot: unitSlot,
    focusStrikeCamera: focusStrikeCamera,
    resetStrikeCamera: resetStrikeCamera,
    playAttackVisual: playAttackVisual,
    playHealVisual: playHealVisual,
    playDefendVisual: playDefendVisual,
    maybeDeathAnim: maybeDeathAnim,
    playSpecialAoeVisual: playSpecialAoeVisual,
    onResize: onResize,
    stabilizeHolos: stabilizeHolos,
    restoreHolos: restoreHolos
  };
})();

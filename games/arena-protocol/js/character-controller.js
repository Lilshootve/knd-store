/**
 * ============================================================================
 * character-controller.js
 * KND Arena Protocol — Character Animation & Physics Controller
 * ============================================================================
 *
 * USAGE (same pattern for every district):
 *
 *   import { CharacterController, STATES } from './js/character-controller.js';
 *
 *   // 1. Create once per scene
 *   const charCtrl = new CharacterController({
 *     walkSpeed:      16,
 *     runSpeed:       32,
 *     isInputBlocked: () => document.activeElement?.tagName === 'INPUT',
 *   });
 *
 *   // 2. Call after GLB loads (safe to call again on model swap)
 *   charCtrl.setupAnimations(gltf, model);
 *
 *   // 3. Call every frame — returns smoothed XZ velocity
 *   const { vx, vz } = charCtrl.update(dt, mesh);
 *   mesh.position.x = THREE.MathUtils.clamp(mesh.position.x + vx * dt, minX, maxX);
 *   mesh.position.z = THREE.MathUtils.clamp(mesh.position.z + vz * dt, minZ, maxZ);
 *   // NOTE: mesh.position.y and mesh.rotation.y are managed by the controller.
 *
 *   // 4. Cleanup on page unload
 *   charCtrl.dispose();
 *
 * ============================================================================
 *
 * STATE MACHINE:
 *   idle ──► walk ──► run ──► run_stop ──► idle
 *        └──► jump ──► fall ──► land ──────► idle
 *        └──► crouch (CTRL held) ──────────► idle / walk
 *        └──► cover  (C toggle)  ──────────► idle
 *
 * CONTROLS:
 *   WASD / Arrows  move
 *   SHIFT          run (hold while moving)
 *   SPACE          jump
 *   CTRL           crouch (hold)
 *   C              toggle cover
 *
 * ============================================================================
 */

import * as THREE from 'three';

// ─────────────────────────────────────────────────────────────────────────────
// Public constants
// ─────────────────────────────────────────────────────────────────────────────

/** All possible states. Use STATES.* instead of raw strings in host code. */
export const STATES = Object.freeze({
  IDLE:     'idle',
  WALK:     'walk',
  RUN:      'run',
  JUMP:     'jump',
  FALL:     'fall',
  LAND:     'land',
  RUN_STOP: 'run_stop',
  CROUCH:   'crouch',
  COVER:    'cover',
});

/**
 * Normalized clip name map.
 * Keys are internal identifiers; values are the expected clip names in the GLB,
 * normalized to lowercase + trimmed.
 *
 * Export this so host code can verify or override individual entries:
 *   import { CLIP } from './js/character-controller.js';
 *   CLIP.IDLE = 'my_idle_clip';
 */
export const CLIP = {
  // ── Core locomotion ─────────────────────────────────────
  IDLE:          'idle stand',
  WALK:          'walking',
  RUN:           'running',

  // ── Airborne ────────────────────────────────────────────
  JUMP:          'jumping up',
  FALL:          'falling idle',
  LAND:          'hard landing',

  // ── Transitions (LoopOnce) ──────────────────────────────
  RUN_STOP:      'run to stop',

  // ── Crouch ──────────────────────────────────────────────
  CROUCH_IDLE:   'idle cover crouched',
  CROUCH_LEFT:   'crouched sneaking left',
  CROUCH_RIGHT:  'crouched sneaking right',

  // ── Cover — enter / exit (LoopOnce) ─────────────────────
  COVER_ENTER:      'stand to cover',
  COVER_ENTER_FAST: 'stand to cover fast',
  COVER_EXIT:       'cover to stand',
  COVER_EXIT_FAST:  'cover to stand fast',

  // ── Cover — idle & lateral movement ─────────────────────
  COVER_IDLE:    'idle cover standing',
  COVER_LEFT:    'left cover sneak',
  COVER_RIGHT:   'right cover sneak',
};

// ─────────────────────────────────────────────────────────────────────────────
// Internal constants
// ─────────────────────────────────────────────────────────────────────────────

/** Clips that must play once and freeze on the last frame. */
const ONE_SHOT_CLIPS = new Set([
  CLIP.JUMP,
  CLIP.LAND,
  CLIP.RUN_STOP,
  CLIP.COVER_ENTER,
  CLIP.COVER_ENTER_FAST,
  CLIP.COVER_EXIT,
  CLIP.COVER_EXIT_FAST,
]);

/** Internal state used while the cover-exit animation is playing. */
const _STATE_COVER_EXIT = '_cover_exit';

/** Default configuration. Every key can be overridden in the constructor. */
const DEFAULTS = {
  walkSpeed:         16,    // world units / second
  runSpeed:          32,    // world units / second
  crouchSpeed:       6,     // world units / second
  coverLateralSpeed: 5,     // world units / second (A/D while in cover)
  jumpForce:         8,     // initial Y velocity on jump
  gravity:           20,    // Y deceleration (units/s²)
  groundY:           0,     // Y position of the ground plane
  fadeTime:          0.20,  // default cross-fade duration (seconds)
  fastFadeTime:      0.08,  // fast fade for reactive transitions
  rotSmoothing:      0.12,  // character rotation lerp factor (0–1, per frame)
  velSmoothing:      9,     // exponential velocity smoothing factor
  /**
   * Return true to block all movement input.
   * Override in each district to match its UI (chat box, modals, etc.).
   */
  isInputBlocked: () => {
    const tag = document.activeElement?.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// CharacterController
// ─────────────────────────────────────────────────────────────────────────────

export class CharacterController {

  /**
   * @param {Partial<typeof DEFAULTS>} options
   */
  constructor(options = {}) {
    this._opts = { ...DEFAULTS, ...options };

    // ── Animation state ──────────────────────────────────
    this._state     = STATES.IDLE;
    this._prevState = null;
    this._inCover   = false;   // true while _state is COVER or _STATE_COVER_EXIT

    // ── Animation objects ────────────────────────────────
    this._mixer    = null;
    /** @type {Record<string, THREE.AnimationAction>} normalized-name → action */
    this._actions  = {};
    this._current  = null;     // normalized name of the currently playing clip
    this._rootMesh = null;     // THREE.Object3D whose position.y / rotation.y we own

    // ── Physics ──────────────────────────────────────────
    this._grounded = true;
    this._velY     = 0;
    /** Smoothed XZ velocity returned to host each frame. */
    this._vel      = new THREE.Vector3();

    // ── Input ────────────────────────────────────────────
    this._keys     = {};
    /** Edge-triggered jump request; set in keydown, consumed in state machine. */
    this._jumpReq  = false;

    // ── Bound event handlers (kept for removeEventListener) ──
    this._onKeyDown = this._handleKeyDown.bind(this);
    this._onKeyUp   = this._handleKeyUp.bind(this);
    this._onFinish  = this._handleFinished.bind(this);

    // capture:true fires before browser default actions (e.g. Ctrl+W closes tab)
    document.addEventListener('keydown', this._onKeyDown, { capture: true });
    document.addEventListener('keyup',   this._onKeyUp,   { capture: true });
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Public API
  // ───────────────────────────────────────────────────────────────────────────

  /**
   * Initialize or re-initialize animations from a loaded GLTF.
   * Safe to call multiple times — cleans up the previous mixer first.
   *
   * @param {object} gltf          Result from GLTFLoader (must have .animations)
   * @param {THREE.Object3D} rootMesh  The mesh whose .position.y / .rotation.y
   *                                   will be controlled by this instance.
   * @returns {this}  Chainable.
   */
  setupAnimations(gltf, rootMesh) {
    // ── Teardown previous mixer ──────────────────────────
    if (this._mixer) {
      this._mixer.stopAllAction();
      this._mixer.removeEventListener('finished', this._onFinish);
      this._mixer = null;
    }
    this._actions = {};
    this._current = null;
    this._rootMesh = rootMesh;

    if (!gltf?.animations?.length) {
      console.warn('[CharCtrl] No animations found in GLTF.');
      return this;
    }

    // ── Build action dictionary ──────────────────────────
    // AnimationMixer must target the skinned mesh, not the root group,
    // so we find the first SkinnedMesh child if rootMesh is a Group.
    const mixerTarget = this._findSkinnedRoot(rootMesh);
    this._mixer = new THREE.AnimationMixer(mixerTarget);
    this._mixer.addEventListener('finished', this._onFinish);

    console.groupCollapsed('[CharCtrl] Clips loaded:');
    gltf.animations.forEach(clip => {
      const key = this._normalize(clip.name);
      this._actions[key] = this._mixer.clipAction(clip);
      console.log(`  · "${clip.name}"  →  key: "${key}"`);
    });
    console.groupEnd();

    // ── Configure loop modes ─────────────────────────────
    Object.entries(this._actions).forEach(([name, action]) => {
      if (ONE_SHOT_CLIPS.has(name)) {
        action.loop              = THREE.LoopOnce;
        action.clampWhenFinished = true;
      } else {
        action.loop = THREE.LoopRepeat;
      }
    });

    // ── Reset state and play idle ─────────────────────────
    this._state    = STATES.IDLE;
    this._inCover  = false;
    this._grounded = true;
    this._velY     = 0;
    this._vel.set(0, 0, 0);
    this._current  = null;
    this._fadeTo(CLIP.IDLE, 0); // instant start, no fade-in on load

    return this;
  }

  /**
   * Call every frame from the render loop — ideally right after calculating dt.
   *
   *   • Reads input state
   *   • Runs state machine
   *   • Updates THREE.AnimationMixer
   *   • Applies Y physics (jump / fall / land)
   *   • Rotates the mesh to face the movement direction
   *
   * @param {number} dt                     Frame delta time in seconds
   * @param {THREE.Object3D} [rootMesh]     Optionally update the mesh reference
   * @returns {{ vx: number, vz: number }}  Smoothed XZ velocity.
   *          Apply to mesh position in the host with bounds clamping:
   *            mesh.position.x = clamp(mesh.position.x + vx * dt, minX, maxX);
   *            mesh.position.z = clamp(mesh.position.z + vz * dt, minZ, maxZ);
   */
  update(dt, rootMesh) {
    if (rootMesh && rootMesh !== this._rootMesh) this._rootMesh = rootMesh;
    if (this._mixer) this._mixer.update(dt);

    const input = this._readInput();
    this._runStateMachine(input, dt);
    this._applyYPhysics(dt);
    this._updateFacing(dt);

    return { vx: this._vel.x, vz: this._vel.z };
  }

  /**
   * Force an immediate state change from outside the controller.
   * Use sparingly — prefer letting the state machine handle transitions.
   *
   * @param {string} newState  One of STATES.*
   */
  changeState(newState) {
    this._enterState(newState);
  }

  /** @type {string} Current state name (one of STATES.*) */
  get state()      { return this._state; }

  /** @type {{ x: number, y: number, z: number }} Current velocity */
  get velocity()   { return { x: this._vel.x, y: this._velY, z: this._vel.z }; }

  /** @type {boolean} Whether the character is on the ground */
  get isGrounded() { return this._grounded; }

  /**
   * Remove all event listeners and stop the AnimationMixer.
   * Call on scene dispose / page unload to avoid memory leaks.
   */
  dispose() {
    document.removeEventListener('keydown', this._onKeyDown, { capture: true });
    document.removeEventListener('keyup',   this._onKeyUp,   { capture: true });
    if (this._mixer) {
      this._mixer.stopAllAction();
      this._mixer.removeEventListener('finished', this._onFinish);
      this._mixer = null;
    }
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Private — Input
  // ───────────────────────────────────────────────────────────────────────────

  _handleKeyDown(e) {
    if (e.repeat) return;
    this._keys[e.code] = true;

    // Remaining logic requires non-blocked input
    if (this._opts.isInputBlocked()) return;

    // Block browser shortcuts that conflict with game controls.
    // Ctrl+W closes the tab, Ctrl+R refreshes, Ctrl+S saves, etc.
    // We prevent these only when the game has focus (input not blocked).
    const GAME_KEYS = new Set([
      'KeyW','KeyA','KeyS','KeyD',
      'ArrowUp','ArrowDown','ArrowLeft','ArrowRight',
      'Space','ShiftLeft','ShiftRight',
      'ControlLeft','ControlRight',
      'KeyC',
    ]);
    if (GAME_KEYS.has(e.code)) {
      e.preventDefault();
    }

    // Space — edge-triggered jump (consumed once by state machine)
    if (e.code === 'Space') {
      if (
        this._grounded &&
        (this._state === STATES.IDLE ||
         this._state === STATES.WALK ||
         this._state === STATES.RUN)
      ) {
        this._jumpReq = true;
      }
    }

    // C — toggle cover (only from ground locomotion states)
    if (e.code === 'KeyC') {
      this._toggleCover();
    }
  }

  _handleKeyUp(e) {
    this._keys[e.code] = false;
  }

  /**
   * Build a snapshot of the current input state.
   * Returns zero-movement when input is blocked.
   *
   * @returns {{ left, right, fwd, back, run, crouch, moving, dx, dz }}
   */
  _readInput() {
    if (this._opts.isInputBlocked()) {
      return { left: false, right: false, fwd: false, back: false,
               run: false, crouch: false, moving: false, dx: 0, dz: 0 };
    }
    const k      = this._keys;
    const left   = !!(k.KeyA   || k.ArrowLeft);
    const right  = !!(k.KeyD   || k.ArrowRight);
    const fwd    = !!(k.KeyW   || k.ArrowUp);
    const back   = !!(k.KeyS   || k.ArrowDown);
    return {
      left, right, fwd, back,
      run:    !!(k.ShiftLeft   || k.ShiftRight),
      crouch: !!(k.ControlLeft || k.ControlRight),
      moving: left || right || fwd || back,
      dx:     (right ? 1 : 0) - (left ? 1 : 0),
      dz:     (back  ? 1 : 0) - (fwd  ? 1 : 0),
    };
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Private — State Machine
  // ───────────────────────────────────────────────────────────────────────────

  /**
   * Central update: reads input, decides state transitions, sets target velocity.
   */
  _runStateMachine(input, dt) {
    const s = this._state;
    const o = this._opts;
    let targetVX = 0, targetVZ = 0;

    // ── COVER (active or exiting) ────────────────────────
    if (s === STATES.COVER || s === _STATE_COVER_EXIT) {
      if (s === STATES.COVER) {
        // Only A/D lateral movement while in cover; W/S are ignored
        if (input.left)  targetVX = -o.coverLateralSpeed;
        if (input.right) targetVX =  o.coverLateralSpeed;
        this._tickCoverAnims(input);
      }
      // _STATE_COVER_EXIT: hold still; _handleFinished resolves to IDLE

    // ── CROUCH (held) ────────────────────────────────────
    } else if (s === STATES.CROUCH) {
      const len = Math.hypot(input.dx, input.dz);
      if (len > 0) {
        targetVX = (input.dx / len) * o.crouchSpeed;
        targetVZ = (input.dz / len) * o.crouchSpeed;
      }
      this._tickCrouchAnims(input);

      // Release CTRL → return to locomotion
      if (!input.crouch) {
        this._enterState(input.moving ? STATES.WALK : STATES.IDLE);
        return;
      }

    // ── AIRBORNE ─────────────────────────────────────────
    } else if (s === STATES.JUMP || s === STATES.FALL) {
      // Air control at walk speed
      const len = Math.hypot(input.dx, input.dz);
      if (len > 0) {
        targetVX = (input.dx / len) * o.walkSpeed;
        targetVZ = (input.dz / len) * o.walkSpeed;
      }
      // Apex passed → switch to fall
      if (s === STATES.JUMP && this._velY < 0) {
        this._enterState(STATES.FALL);
      }

    // ── LAND / RUN_STOP (one-shot; frozen until finished) ─
    } else if (s === STATES.LAND || s === STATES.RUN_STOP) {
      // Hold still; _handleFinished moves to IDLE
      targetVX = 0;
      targetVZ = 0;

    // ── NORMAL LOCOMOTION: IDLE / WALK / RUN ─────────────
    } else {
      const speed = input.run ? o.runSpeed : o.walkSpeed;
      const len   = Math.hypot(input.dx, input.dz);
      if (len > 0) {
        targetVX = (input.dx / len) * speed;
        targetVZ = (input.dz / len) * speed;
      }

      // Consume jump request (edge-triggered in keydown)
      if (this._jumpReq) {
        this._jumpReq = false;
        this._enterState(STATES.JUMP);
        return;
      }

      // Enter crouch
      if (input.crouch) {
        this._enterState(STATES.CROUCH);
        return;
      }

      // Locomotion transitions
      if (input.moving) {
        const desired = input.run ? STATES.RUN : STATES.WALK;
        if (s !== desired) this._enterState(desired);
      } else {
        if      (s === STATES.RUN)  this._enterState(STATES.RUN_STOP);
        else if (s === STATES.WALK) this._enterState(STATES.IDLE);
        // IDLE → already correct; no-op
      }
    }

    // ── Smooth XZ velocity ───────────────────────────────
    const sm = 1 - Math.exp(-o.velSmoothing * dt);
    this._vel.x = THREE.MathUtils.lerp(this._vel.x, targetVX, sm);
    this._vel.z = THREE.MathUtils.lerp(this._vel.z, targetVZ, sm);
  }

  /**
   * Transition to a new state, triggering the appropriate animation.
   * Guards against re-entering the same state (no-op).
   *
   * @param {string} newState
   */
  _enterState(newState) {
    if (newState === this._state) return;
    this._prevState = this._state;
    this._state     = newState;

    const o = this._opts;

    switch (newState) {
      case STATES.IDLE:
        this._inCover = false;
        this._fadeTo(CLIP.IDLE);
        break;

      case STATES.WALK:
        this._fadeTo(CLIP.WALK);
        break;

      case STATES.RUN:
        this._fadeTo(CLIP.RUN);
        break;

      case STATES.JUMP:
        this._velY     = o.jumpForce;
        this._grounded = false;
        this._fadeTo(CLIP.JUMP, o.fastFadeTime);
        break;

      case STATES.FALL:
        this._fadeTo(CLIP.FALL);
        break;

      case STATES.LAND: {
        const landAction = this._fadeTo(CLIP.LAND, o.fastFadeTime);
        // Play landing at 1.5× speed so recovery feels snappy
        if (landAction) landAction.timeScale = 1.5;
        // Safety fallback: if 'finished' never fires, force-exit after 1.2 s
        clearTimeout(this._landTimer);
        this._landTimer = setTimeout(() => {
          if (this._state === STATES.LAND) this._enterState(STATES.IDLE);
        }, 1200);
        break;
      }

      case STATES.RUN_STOP:
        this._fadeTo(CLIP.RUN_STOP);
        break;

      case STATES.CROUCH:
        this._fadeTo(CLIP.CROUCH_IDLE);
        break;

      case STATES.COVER:
        this._inCover = true;
        this._fadeTo(CLIP.COVER_ENTER, o.fastFadeTime);
        break;
    }
  }

  /** Toggle cover mode (C key). Called once on keydown. */
  _toggleCover() {
    if (!this._inCover) {
      // Enter cover — only allowed from ground locomotion
      if (
        this._state === STATES.IDLE ||
        this._state === STATES.WALK ||
        this._state === STATES.RUN
      ) {
        this._enterState(STATES.COVER);
      }
    } else {
      // Exit cover — play exit animation; _handleFinished resolves to IDLE
      this._inCover = false;
      this._state   = _STATE_COVER_EXIT;
      this._fadeTo(CLIP.COVER_EXIT, this._opts.fastFadeTime);
    }
  }

  /**
   * Sub-state animation updates while in cover.
   * Only runs after the entry animation has finished.
   */
  _tickCoverAnims(input) {
    // Don't interrupt the entry transition
    if (
      this._current === CLIP.COVER_ENTER ||
      this._current === CLIP.COVER_ENTER_FAST
    ) return;

    if (input.left  && this._current !== CLIP.COVER_LEFT)  { this._fadeTo(CLIP.COVER_LEFT);  return; }
    if (input.right && this._current !== CLIP.COVER_RIGHT) { this._fadeTo(CLIP.COVER_RIGHT); return; }
    if (!input.left && !input.right && this._current !== CLIP.COVER_IDLE) {
      this._fadeTo(CLIP.COVER_IDLE);
    }
  }

  /**
   * Sub-state animation updates while crouching.
   */
  _tickCrouchAnims(input) {
    if (!input.moving) {
      if (this._current !== CLIP.CROUCH_IDLE) this._fadeTo(CLIP.CROUCH_IDLE);
      return;
    }
    // Pick directional sneak based on primary horizontal axis.
    // If only W/S pressed, default to CROUCH_LEFT (forward sneak).
    const goLeft = input.dx < 0 || (input.dx === 0 && input.fwd);
    const target = goLeft ? CLIP.CROUCH_LEFT : CLIP.CROUCH_RIGHT;
    if (this._current !== target) this._fadeTo(target);
  }

  /**
   * AnimationMixer 'finished' event — resolves LoopOnce transitions.
   *
   * @param {{ action: THREE.AnimationAction }} e
   */
  _handleFinished(e) {
    const name = this._normalize(e.action.getClip().name);

    // Landing → idle
    if (name === CLIP.LAND) {
      clearTimeout(this._landTimer);
      this._enterState(STATES.IDLE);
      return;
    }

    // Run-stop → idle
    if (name === CLIP.RUN_STOP) {
      this._enterState(STATES.IDLE);
      return;
    }

    // Cover entry done → loop cover idle
    if (name === CLIP.COVER_ENTER || name === CLIP.COVER_ENTER_FAST) {
      if (this._state === STATES.COVER) {
        this._fadeTo(CLIP.COVER_IDLE);
      }
      return;
    }

    // Cover exit done → return to idle
    if (name === CLIP.COVER_EXIT || name === CLIP.COVER_EXIT_FAST) {
      this._state   = STATES.IDLE;
      this._inCover = false;
      this._fadeTo(CLIP.IDLE);
      return;
    }
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Private — Physics
  // ───────────────────────────────────────────────────────────────────────────

  _applyYPhysics(dt) {
    if (!this._rootMesh || this._grounded) return;

    this._velY -= this._opts.gravity * dt;
    this._rootMesh.position.y += this._velY * dt;

    // Ground collision
    if (this._rootMesh.position.y <= this._opts.groundY) {
      this._rootMesh.position.y = this._opts.groundY;
      this._velY     = 0;
      this._grounded = true;

      if (this._state === STATES.FALL || this._state === STATES.JUMP) {
        this._enterState(STATES.LAND);
      }
    }
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Private — Rotation
  // ───────────────────────────────────────────────────────────────────────────

  _updateFacing(_dt) {
    if (!this._rootMesh) return;
    // Freeze rotation during landing and cover transitions
    if (
      this._state === STATES.LAND ||
      this._state === STATES.COVER ||
      this._state === _STATE_COVER_EXIT
    ) return;

    if (this._vel.x * this._vel.x + this._vel.z * this._vel.z > 0.08) {
      const target = Math.atan2(this._vel.x, this._vel.z);
      this._rootMesh.rotation.y = THREE.MathUtils.lerp(
        this._rootMesh.rotation.y,
        target,
        this._opts.rotSmoothing,
      );
    }
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Private — Animation helpers
  // ───────────────────────────────────────────────────────────────────────────

  /**
   * Crossfade from the current clip to a new one.
   * Fades out every running action; resets and fades in the new action.
   * No-ops if the target is already playing.
   *
   * @param {string} clipName  Value from the CLIP map (will be resolved)
   * @param {number} [duration]
   */
  _fadeTo(clipName, duration = this._opts.fadeTime) {
    if (!this._mixer) return null;

    const action = this._resolve(clipName);
    if (!action) {
      console.warn(`[CharCtrl] Clip not found: "${clipName}". Available:`, Object.keys(this._actions));
      return null;
    }

    const resolvedName = this._normalize(action.getClip().name);
    if (resolvedName === this._current) return action;

    // Fade out all currently running actions
    Object.values(this._actions).forEach(a => {
      if (a !== action && a.isRunning()) a.fadeOut(duration);
    });

    // Start the new action
    action.reset().fadeIn(duration).play();
    this._current = resolvedName;
    return action;
  }

  /**
   * Resolve a clip name to an AnimationAction using three-level matching:
   *   1. Exact normalized key match
   *   2. All words of target are present in a key (order-independent)
   *   3. First significant word is present in a key
   *
   * This tolerates minor GLB export name variations (e.g., extra spaces,
   * slightly different capitalization).
   *
   * @param {string} name  Target clip name (from CLIP map)
   * @returns {THREE.AnimationAction|null}
   */
  _resolve(name) {
    // Level 1 — exact match
    if (this._actions[name]) return this._actions[name];

    const words = name.split(' ').filter(Boolean);
    const keys  = Object.keys(this._actions);

    // Level 2 — all words present in key
    for (const key of keys) {
      if (words.every(w => key.includes(w))) return this._actions[key];
    }

    // Level 3 — first word present
    if (words[0]) {
      for (const key of keys) {
        if (key.includes(words[0])) return this._actions[key];
      }
    }

    return null;
  }

  /**
   * Normalize a clip name to lowercase + trimmed (same as dict key format).
   * @param {string} name
   * @returns {string}
   */
  _normalize(name) {
    return name.toLowerCase().trim();
  }

  /**
   * Find the deepest SkinnedMesh-containing root for the AnimationMixer.
   * If rootMesh itself is a SkinnedMesh, use it.
   * If it's a Group wrapping one, use the Group (mixer can still drive it).
   * Three.js AnimationMixer works correctly on a Group if clips reference bones.
   *
   * @param {THREE.Object3D} root
   * @returns {THREE.Object3D}
   */
  _findSkinnedRoot(root) {
    // Prefer the root itself if it contains skinned children — the mixer
    // must target the same object that owns the AnimationClip tracks.
    return root;
  }
}

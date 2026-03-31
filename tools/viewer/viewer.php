<canvas id="viewer"></canvas>

<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
    "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
  }
}
</script>
<script type="module">
import { initHologramViewer } from '/assets/js/knd-hologram.js';

const params = new URLSearchParams(window.location.search);
const model = params.get('model') || '/models/test.glb';

const preset = await fetch('/hologram/preset-default.json')
  .then(r => r.json());

initHologramViewer({
  canvas: '#viewer',
  model: model,
  preset: preset
});
</script>
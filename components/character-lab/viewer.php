<?php
/**
 * Character Lab - GLB viewer (model-viewer: auto-center, orbit, responsive)
 */
?>
<div id="cl-viewer-wrap" class="character-lab-viewer rounded overflow-hidden bg-dark" style="min-height:320px; aspect-ratio:16/10;">
    <div id="cl-viewer-empty" class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
        <div class="text-white-50 mb-3"><i class="fas fa-cube fa-4x"></i></div>
        <h5 class="text-white mb-1">No 3D preview yet</h5>
        <p class="text-white-50 small mb-0">Generate a character to view the GLB here.</p>
    </div>
    <model-viewer id="cl-model-viewer" camera-controls auto-rotate interaction-prompt="none" style="display:none; width:100%; height:100%;"></model-viewer>
</div>
<div id="cl-download-wrap" class="mt-3" style="display:none;">
    <a id="cl-download-glb" href="#" class="btn btn-success" download><i class="fas fa-download me-1"></i>Download GLB</a>
</div>

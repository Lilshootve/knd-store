<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/includes/env.php';

return [

    // URL de tu sitio
    'API_BASE' => knd_env_required('KND_API_BASE'),

    // token secreto del worker (debe coincidir con el servidor)
    'WORKER_TOKEN' => knd_env_required('KND_WORKER_TOKEN'),

    // ID del worker (para logs)
    'WORKER_ID' => 'GPU-PC-01',
    
    
    'COMFY_OUTPUT_DIR'     => 'C:\\AI\\Comfyui3d\\Comfyui3d\\ComfyUI_windows_portable\\ComfyUI\\output',
    // Where to copy final images (e.g. F:\KND\output). Also set in config/labs.local.php.
    

    // endpoint de ComfyUI (o usa COMFYUI_LOCAL si el worker corre en el mismo PC)
    'COMFY_URL' => 'http://127.0.0.1:8190',
     'COMFYUI_LOCAL' => 'http://127.0.0.1:8190',  // descomenta para evitar el túnel

    // tiempo de lease
    'LEASE_SECONDS' => 600,

    // pausa entre polling si no hay jobs
    'IDLE_SLEEP' => 2,

];
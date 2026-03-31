# KND Labs Worker - Ejecución en Windows (HTTP Mode)

El worker procesa los jobs en cola **sin conexión MySQL directa**. Usa la API HTTP (lease/complete/fail) en Hostinger.

## Requisitos

- PHP CLI (extensiones: **curl**, json) — **no necesita pdo_mysql**
- ComfyUI accesible (https://comfy.kndstore.com o 127.0.0.1:8188)

## Configuración

### 1. Token en el servidor (Hostinger)

Crea `config/worker_secrets.local.php` (copia de `config/worker_secrets.local.example.php`):

```php
<?php
return [
    'WORKER_TOKEN' => 'tu_token_seguro_minimo_32_caracteres',
];
```

Este archivo **no se sube a Git**. Genera un token aleatorio, p.ej.:
`php -r "echo bin2hex(random_bytes(24));"`

### 2. Config en tu PC (Windows)

Crea `workers/worker_config.local.php` (copia de `workers/worker_config.local.example.php`):

```php
<?php
return [
    'API_BASE'       => 'https://kndstore.com',
    'WORKER_TOKEN'   => 'el_mismo_token_que_worker_secrets',
    'COMFYUI_BASE'   => 'https://comfy.kndstore.com',   // o http://127.0.0.1:8188
    'COMFYUI_TOKEN'  => '',   // X-KND-TOKEN si ComfyUI lo usa
];
```

**IMPORTANTE**: `WORKER_TOKEN` debe coincidir exactamente con el de `worker_secrets.local.php` en el servidor.

## Uso

```bash
# Un solo job
php workers/labs_worker.php

# Loop continuo (Task Scheduler)
php workers/labs_worker.php --loop --sleep=2 --worker-id=PC1
```

- `--loop`: ejecutar en bucle
- `--sleep=N`: segundos entre iteraciones (default: 2)
- `--worker-id=ID`: identificador del worker

## Task Scheduler (Windows)

1. **Programador de tareas** → Crear tarea
2. **Trigger**: Al iniciar sesión (o Repetir cada 1 min)
3. **Acción**:
   - Programa: `C:\php\php.exe`
   - Argumentos: `C:\ruta\kndstore\workers\labs_worker.php --loop --sleep=2 --worker-id=PC1`
   - Iniciar en: `C:\ruta\kndstore`

## Flujo HTTP

1. **lease.php** — toma 1 job queued, lo pasa a processing
2. Worker envía prompt a ComfyUI local
3. Poll `/history/{prompt_id}` hasta imagen lista
4. **complete.php** o **fail.php** según resultado

## Notas

- Límite 2 jobs activos por usuario (generate.php en Hostinger)
- 3 intentos por job; tras 3 fallos → `failed`
- El worker solo usa HTTP; no necesita MySQL en tu PC

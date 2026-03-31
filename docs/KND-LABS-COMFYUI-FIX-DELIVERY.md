# KND Labs + ComfyUI – Corrección integración (entrega)

## 1. Diagnóstico exacto

### Por qué upscale daba HTTP 404

- **Dónde ocurría**: En el **worker** al subir la imagen de entrada a ComfyUI (`workerUploadToComfyui`). El worker usaba **solo** la ruta `/upload/image`.
- **Causa**: Algunas instalaciones de ComfyUI exponen la API bajo un prefijo (p. ej. `/api/upload/image`). Con base `http://127.0.0.1:8190`, la única URL probada era `http://127.0.0.1:8190/upload/image`, que en tu caso devolvía 404.
- **Qué se hizo**: Se proban **dos rutas** en este orden: `/upload/image` y `/api/upload/image`. Se registra en log cada intento (URL y código HTTP). Si una devuelve 200 y JSON con `name` o `filename`, se usa esa. Además, en `includes/comfyui.php` la función `comfyui_upload_image` (usada por `generate.php` al subir la imagen en upscale desde la web) ya probaba ambas rutas; se añadieron logs en fallo (base URL y respuesta) para depurar.

### Por qué consistency “fallaba” y no mostraba imagen

- **Flujo**: Consistency usa `consistency_create.php`, que deja en el payload `ref_image_url` (tmp_image.php). El worker descarga esa referencia, la sube a ComfyUI y ejecuta el workflow. El fallo de **upload 404** afectaba también a consistency (subida de la imagen de referencia). Además, si el worker no tenía `COMFY_OUTPUT_DIR` configurado o el archivo no estaba en esa carpeta, **no se copiaba** el resultado a storage, y la UI depende de `output_path` o del proxy a ComfyUI `/view`.
- **Causas**: (1) Mismo 404 en la subida de la imagen de referencia. (2) Sin archivo en disco, el worker no tenía lógica para obtener la imagen vía ComfyUI (p. ej. `/view`) y guardarla en storage, así que a veces no se rellenaba `output_path` y la UI no mostraba imagen.
- **Qué se hizo**: (1) Mismo arreglo de upload (dos rutas + logs). (2) Si el archivo no está en `COMFY_OUTPUT_DIR`, el worker obtiene la imagen con `comfyui_fetch_output_image_bytes` (history + `/view`), la valida y la guarda en storage y, si está definido, en `KND_FINAL_IMAGE_DIR`. Así consistency (y el resto de tools) siempre pueden tener `output_path` y la UI puede mostrar el resultado.

### Por qué otros shells generaban archivo pero no mostraban resultado

- **Causa**: El worker solo copiaba a storage cuando `COMFY_OUTPUT_DIR` estaba configurado **y** el archivo existía en la ruta construida con `subfolder` + `filename`. Si esa carpeta no era la real de ComfyUI (o no estaba configurada), no se escribía `output_path` y la web intentaba servir por proxy a ComfyUI `/view`. Si el servidor web no puede alcanzar ComfyUI (otra máquina, firewall, etc.), la imagen no se ve.
- **Qué se hizo**: El worker **siempre** intenta tener los bytes de la imagen: primero desde disco (`COMFY_OUTPUT_DIR` + subfolder + filename) y, si no, vía `GET .../view?...`. Esos bytes se validan (tamaño > 0, extensión png/jpg/webp) y se guardan en `storage/{LABS_UPLOAD_DIR}/job_<id>_<tool>.<ext>`, y se envía `output_path` en complete. La web sirve entonces desde storage (`api/labs/image.php`), sin depender de la carpeta por defecto de ComfyUI ni del proxy a ComfyUI.

---

## 2. Archivos modificados

| Archivo | Cambios |
|--------|---------|
| `config/labs.php` | `COMFY_OUTPUT_DIR` por defecto vacío. Añadido `KND_FINAL_IMAGE_DIR` (por defecto `F:\KND\images`). Comentarios de uso. |
| `config/labs.local.example.php` | Ejemplo de `KND_FINAL_IMAGE_DIR` (p. ej. `F:\KND\images`), aclaraciones para Windows y `COMFY_OUTPUT_DIR`. |
| `includes/comfyui.php` | `comfyui_upload_image`: logs en fallo (base, lastErr, respuesta). Nueva `comfyui_fetch_output_image_bytes(promptId, baseUrl, token)` que obtiene la primera imagen del history y la descarga por `/view` o `/api/view`. `comfyui_fetch_job_image_bytes` ahora delega en esa función. |
| `workers/labs_worker.php` | `workerUploadToComfyui`: prueba `/upload/image` y `/api/upload/image`, log de cada intento (URL + HTTP code). Carga de `KND_FINAL_IMAGE_DIR` desde config. Tras polling: obtener imagen desde disco o `comfyui_fetch_output_image_bytes`, validar bytes y extensión, guardar en storage y, si existe, en `KND_FINAL_IMAGE_DIR`. Logs de origen, tamaño, rutas y fallos. |
| `api/labs/image.php` | Validación de tamaño > 0 al servir desde `output_path`. Content-Type según extensión (png/jpg/webp). Logs cuando el archivo no es válido o no está en storage. |
| `docs/KND-LABS-COMFYUI.md` | Sección de rutas (COMFY_OUTPUT_DIR, KND_FINAL_IMAGE_DIR, LABS_UPLOAD_DIR). Flujo del worker actualizado (obtener imagen, guardar en storage y en    ). |
| `docs/KND-LABS-COMFYUI-FIX-DELIVERY.md` | Este documento (diagnóstico, archivos, resumen, pruebas). |

---

## 3. Resumen de la solución

- **Upload (404)**  
  - Worker y `comfyui_upload_image` intentan `/upload/image` y `/api/upload/image`.  
  - Logs: URL probada y código HTTP en cada intento; en error, base y respuesta en PHP error_log.

- **Salida y resultado en UI**  
  - Tras detectar outputs en history (filename + subfolder):  
    1. Intentar leer archivo desde `COMFY_OUTPUT_DIR` (si está definido).  
    2. Si no hay archivo en disco, obtener imagen con `comfyui_fetch_output_image_bytes` (history + `/view` o `/api/view`).  
    3. Validar tamaño > 0 y extensión png/jpg/webp.  
    4. Escribir en `storage/{LABS_UPLOAD_DIR}/job_<job_id>_<tool>.<ext>` y enviar `output_path` en complete.  
    5. Si está definido `KND_FINAL_IMAGE_DIR`, copiar también ahí (p. ej. `F:\KND\images`).  
  - Con esto, upscale, consistency y el resto de shells que generan imagen pueden mostrar resultado en KND Labs aunque ComfyUI escriba en su carpeta por defecto.

- **Rutas centralizadas**  
  - `config/labs.php`: `COMFY_OUTPUT_DIR`, `KND_FINAL_IMAGE_DIR`, `LABS_UPLOAD_DIR`.  
  - Override en `config/labs.local.php` o env (p. ej. `KND_FINAL_IMAGE_DIR`, `COMFY_OUTPUT_DIR`).  
  - Worker lee `KND_FINAL_IMAGE_DIR` de config/env.

- **Symlink**  
  - No se reutiliza lógica de symlink en el flujo PHP de Labs. El worker **copia** el archivo a storage y a `KND_FINAL_IMAGE_DIR`.  
  - El comentario en `workers/comfyui_3d_config.py` (“may be symlinked from ComfyUI output”) sigue siendo solo para 3D; no se ha tocado ese flujo.

---

## 4. Pruebas manuales recomendadas

### Configuración previa

- En `config/labs.local.php` (o env) definir, por ejemplo:
  - `KND_FINAL_IMAGE_DIR` = `F:\KND\images` (o `F:\KND\output`).
  - `COMFY_OUTPUT_DIR` = ruta real de salida de ComfyUI (opcional; si no, el worker usará `/view`).
- Asegurar que `F:\KND\images` existe y el usuario del worker tiene permiso de escritura.
- Worker: en `worker_config.local.php` (o env) tener `COMFYUI_BASE` = `http://127.0.0.1:8190` (o la URL correcta de tu ComfyUI).

### Upscale

1. Ir a Labs → Upscale, subir una imagen y generar.
2. En el worker (consola): comprobar líneas como `ComfyUI upload: http://.../upload/image HTTP 200` (o `.../api/upload/image HTTP 200`). Si ves 404 en una y 200 en la otra, queda claro qué ruta usa tu ComfyUI.
3. Comprobar que el job termina en “done” y que la imagen se muestra en la UI.
4. Comprobar que existe `storage/uploads/labs/job_<id>_upscale.png` y, si configuraste `KND_FINAL_IMAGE_DIR`, `F:\KND\images\job_<id>_upscale.png`.

### Consistency

1. Tener un job “done” de Text2Img (o similar) para usar como referencia.
2. Ir a Consistency, elegir “from recent” (o subir referencia), rellenar base/scene prompt y generar.
3. En el worker: ver que no hay 404 en upload y que aparece algo como `outputs detected filename=... subfolder=...` y `saved output to ...` o `copied to KND_FINAL_IMAGE_DIR`.
4. Comprobar que el job termina y la imagen de consistency se muestra en la UI.
5. Comprobar archivo en storage y en `F:\KND\images` si está configurado.

### Un shell que ya generaba imagen (p. ej. Text2Img)

1. Generar una imagen con Text2Img.
2. Comprobar que el resultado se muestra en la UI (sin depender de la carpeta por defecto de ComfyUI).
3. En worker: ver logs de “saved output to …” y, si aplica, “copied to KND_FINAL_IMAGE_DIR”.
4. Comprobar que existe el archivo en `storage/uploads/labs/` y en `F:\KND\images`.

### Logs útiles

- Worker (stdout): cada intento de upload (URL + HTTP code), “outputs detected”, “saved output to”, “copied to KND_FINAL_IMAGE_DIR” o “failed to copy”, “could not obtain image bytes”.
- PHP error_log: “ComfyUI upload failed”, “ComfyUI fetch output image”, “api/labs/image: output_path not readable”.

---

## 5. Criterios de aceptación (checklist)

- [ ] Upscale ya no devuelve HTTP 404 (upload con una de las dos rutas).
- [ ] Consistency termina y la imagen se muestra en resultado.
- [ ] Los demás shells que generan imagen muestran el resultado en KND Labs.
- [ ] El archivo final queda en `F:\KND\images` (o `F:\KND\output`) cuando `KND_FINAL_IMAGE_DIR` está configurado.
- [ ] KND Labs no depende de `C:\...\ComfyUI_windows_portable\ComfyUI\output` para mostrar imágenes (se sirven desde storage / opcionalmente desde KND_FINAL_IMAGE_DIR si expones esa ruta).
- [ ] Los logs permiten ver qué URL de upload se usó, qué outputs se detectaron y en qué rutas se guardó o falló.

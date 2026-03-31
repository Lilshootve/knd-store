# Diagnóstico producción KND Store (Hostinger)

Problemas reportados: créditos no aparecen, "My Profile" no aparece, nivel no aparece en header, error de base de datos al generar en KND Labs.

---

## A. Archivos backend que deben existir en producción

### Header (créditos + nivel + My Profile)
- `includes/header.php` – ya cargado por todas las páginas.
- `includes/support_credits.php` – **obligatorio** para el badge de créditos. El header hace `require_once __DIR__ . '/support_credits.php'` (desde `includes/`).
- `includes/knd_profile.php` – **obligatorio** para el badge de nivel. El header hace `require_once __DIR__ . '/knd_profile.php'`.
- `includes/knd_xp.php` – requerido por `knd_profile.php` (nivel, curva XP).
- `includes/knd_badges.php` – requerido por `knd_xp.php` (sistema de badges).
- `includes/config.php` – conexión DB y constantes (ya cargado por header).

### Créditos (Support Credits)
- `includes/support_credits.php` – lógica de puntos, `get_available_points`, `release_available_points_if_due`, `expire_points_if_due`.
- `includes/config.php` – `getDBConnection()`.

### Perfil (My Profile)
- `my-profile.php` – página de perfil.
- `includes/knd_profile.php`, `includes/knd_xp.php`, `includes/knd_badges.php`, `includes/support_credits.php`, `includes/config.php`, `includes/header.php`, `includes/footer.php`, `includes/auth.php`, etc.

### KND Labs / generación
- `api/labs/generate.php` – endpoint que crea el job y descuenta KP.
- `includes/session.php`, `config.php`, `auth.php`, `support_credits.php`, `ai.php`, `json.php`, `comfyui.php`, `comfyui_provider.php`, `settings.php`, `storage.php`, `labs_image_helper.php`.
- `includes/ai.php` – `ai_spend_points()` (INSERT en `points_ledger` con `source_type = 'ai_job_spend'`).
- `includes/comfyui_provider.php` – usa `settings_get($pdo, ...)` → tabla `knd_settings`.
- `includes/settings.php` – acceso a `knd_settings`.

---

## B. Tablas necesarias

| Tabla | Uso |
|-------|-----|
| `users` | Login, sesión, créditos por user_id, nivel (knd_user_xp.user_id), labs jobs. |
| `support_payments` | Pagos de créditos; `release_available_points_if_due` hace JOIN con `points_ledger`. |
| `points_ledger` | Saldo de KP: earn/spend, status (pending/available/spent/expired), `get_available_points`, `ai_spend_points`. |
| `knd_user_xp` | Nivel y XP en header/perfil (`get_xp_badge_data`). Si no existe, se usa fallback `user_xp`. |
| `user_xp` | Fallback antiguo para XP (solo columna `xp`; el nivel se calcula con `xp_calc_level`). |
| `knd_labs_jobs` | Jobs de Labs (text2img, upscale, etc.); INSERT desde `api/labs/generate.php`. |
| `knd_settings` | Config ComfyUI (URLs, token, provider_mode); `settings_get` / `comfyui_get_base_url`. |

Otras tablas usadas en el flujo pero no críticas para “créditos + nivel + generar”:  
`knd_seasons`, `knd_season_stats`, `knd_xp_ledger`, `knd_badges`, `knd_user_badges`, `admin_users`, etc.

---

## C. Columnas necesarias por tabla

### `users`
- Mínimo: `id`, `username`, `password_hash`, `created_at`, `updated_at`.
- Para créditos/labs: sin columnas extra obligatorias en support_credits para el saldo.
- Para Labs recientes: `labs_recent_private` (opcional; ver `sql/users_alter_labs_recent_private.sql`).
- Para riesgo: `risk_flag` (opcional; ver `sql/knd_users_alter_risk_flag.sql`).
- Para email/verificación: `email`, `email_verified`, etc. (según `sql/knd_users_alter_email.sql`).

### `support_payments`
- `id`, `user_id`, `method`, `amount_usd`, `currency`, `status`, `provider_txn_id`, `notes`, `created_at`, `confirmed_at`, `updated_at`.  
- Definición: `sql/knd_support_payments.sql`.

### `points_ledger`
- `id`, `user_id`, `source_type`, `source_id`, `entry_type`, `status`, `points`, `available_at`, `expires_at`, `created_at`.
- **`source_type`** debe incluir al menos: `'support_payment','redemption','adjustment'` (base).  
- Para Labs/generación: debe incluir **`'ai_job_spend','ai_job_refund','ai_job_complete'`** (y si usas avatar/3D: `'avatar_shop','3d_generation','3d_generation_refund'`).  
- Definición base: `sql/knd_points_ledger.sql`.  
- Añadir valores: `sql/points_ledger_add_3d_generation.sql`, `sql/points_ledger_add_ai_jobs.sql` (este es el crítico para generar en Labs).

### `knd_user_xp`
- `user_id`, `xp`, `level`, `updated_at`.  
- Definición: `sql/knd_user_xp.sql`.

### `user_xp` (fallback)
- `user_id`, `xp`, `updated_at`.  
- Definición: `sql/user_xp.sql`.

### `knd_labs_jobs`
- Base: `id`, `user_id`, `tool`, `prompt`, `negative_prompt`, `comfy_prompt_id`, `status`, `image_url`, `error_message`, `created_at`, `updated_at`.  
- **Obligatorias para generar (api/labs/generate.php):**  
  `cost_kp`, `quality`, `provider`, `priority`, `payload_json`  
  → `sql/knd_labs_jobs_alter_cost.sql`, `sql/knd_labs_jobs_alter_queue.sql`, `sql/knd_labs_jobs_alter_provider.sql`.  
- Para resultado: `output_path` (y opcionalmente `input_path`) → `sql/knd_labs_jobs_alter_output_path.sql`.

### `knd_settings`
- `key` (PK), `value`, `updated_at`.  
- Definición: `sql/knd_settings.sql`.

---

## D. Endpoints / API necesarios

- `POST /api/labs/generate.php` – crear job de Labs (text2img, upscale, character), descuenta KP, INSERT en `knd_labs_jobs` y `points_ledger`.
- `GET /api/labs/status.php` – estado del job (polling desde el front).
- `GET /api/labs/jobs.php` – listado de jobs del usuario.
- Otros relacionados: `api/labs/queue/lease.php`, `api/labs/queue/complete.php` (worker), `api/labs/image.php`, `api/labs/tmp_image.php`, etc.

---

## E. Posibles causas exactas de que no aparezcan créditos, perfil y nivel

1. **Archivos no subidos**
   - Falta `includes/support_credits.php` → el header no puede llamar a `get_available_points`; el badge de créditos queda vacío y cualquier excepción en el try/catch deja `$creditsBadgeHtml = ''`.
   - Falta `includes/knd_profile.php` o `includes/knd_xp.php` o `includes/knd_badges.php` → el try/catch del nivel falla y `$levelBadgeHtml = ''`.

2. **Ruta de includes incorrecta**
   - Si en producción el `header.php` se carga desde otra ruta (o existe otro `header.php`), `__DIR__ . '/support_credits.php'` podría no apuntar al archivo correcto y dar error o no definir las funciones.

3. **Base de datos**
   - `getDBConnection()` falla (config incorrecta, BD no accesible) → tanto créditos como nivel devuelven 0 o vacío y pueden no mostrarse.
   - Tabla `points_ledger` no existe → `get_available_points` y `release_available_points_if_due` fallan → excepción en header → créditos no se muestran.
   - Tabla `support_payments` no existe → `release_available_points_if_due` falla al hacer JOIN → mismo efecto.
   - Tabla `knd_user_xp` (y opcionalmente `user_xp`) no existe → `get_xp_badge_data` falla o devuelve nivel 0 → nivel no se muestra o se oculta.

4. **Sesión**
   - Si `$_SESSION['dr_user_id']` no está definido o el usuario no está logueado, el header no entra en el bloque `if ($drLoggedIn)` y no pinta créditos ni nivel ni enlace a My Profile (el dropdown sí puede mostrar “My Profile” pero los badges no).

5. **Excepciones silenciosas**
   - Cualquier `Throwable` en el bloque de créditos o de nivel deja el badge correspondiente vacío; en producción con `display_errors = 0` no se ve el error, solo la ausencia del dato.

---

## F. Posible causa exacta del error de base de datos al generar (KND Labs)

1. **Columnas faltantes en `knd_labs_jobs`**  
   El INSERT en `api/labs/generate.php` usa:
   - `user_id`, `tool`, `prompt`, `negative_prompt`, `status`, `cost_kp`, `quality`, `provider`, `priority`, `payload_json`.  
   Si en producción solo existe la tabla base (`sql/knd_labs_jobs.sql`) y no se han ejecutado los ALTER que añaden `cost_kp`, `quality`, `provider`, `priority`, `payload_json`, el INSERT falla con error de columna desconocida.

2. **Tabla `knd_settings` no existe**  
   `comfyui_get_base_url()` y `comfyui_get_token()` llaman a `settings_get($pdo, ...)`. Si `knd_settings` no existe, la consulta falla y puede propagarse como error de BD al generar.

3. **`points_ledger.source_type` sin 'ai_job_spend'**  
   Tras crear el job, se llama a `ai_spend_points($pdo, $userId, $jobId, $costKp)`, que hace:
   - `INSERT INTO points_ledger (..., source_type, ...) VALUES (..., 'ai_job_spend', ...)`.  
   Si la columna `source_type` no incluye el valor `'ai_job_spend'` en su ENUM, el INSERT falla y se ve como error de base de datos al generar.

4. **Tabla `points_ledger` o `users` no existe**  
   Menos probable si el resto de la web carga, pero si en el path de generación falta alguna de las dos, también daría error de BD.

---

## G. Lista concreta de lo que debes subir al hosting

Asegúrate de tener en el mismo árbol que tu DocumentRoot (p. ej. `public_html`):

- `includes/config.php` (o config local según tu criterio; si está en .gitignore, configurar a mano en producción).
- `includes/support_credits.php`
- `includes/knd_profile.php`
- `includes/knd_xp.php`
- `includes/knd_badges.php`
- `includes/settings.php`
- `includes/comfyui_provider.php`
- `includes/comfyui.php`
- `includes/ai.php`
- `includes/json.php`
- `includes/storage.php`
- `includes/labs_image_helper.php`
- `includes/auth.php`
- `includes/session.php`
- `includes/header.php`
- `includes/footer.php`
- `includes/bootstrap.php`
- `api/labs/generate.php`
- `api/labs/status.php`
- `api/labs/jobs.php`
- Y el resto de archivos PHP/JS/CSS que ya uses en producción para el header, perfil, créditos y Labs.

(No hace falta subir `sql/` al servidor; solo ejecutar los SQL necesarios en el panel de MySQL de Hostinger.)

---

## H. Lista concreta de SQL que debes ejecutar en Hostinger si faltan tablas o columnas

Orden sugerido (respeta dependencias de claves foráneas):

1. **Usuarios (si no existe)**  
   - `sql/users.sql`  
   - `sql/knd_users_alter_email.sql`  
   - `sql/users_alter_labs_recent_private.sql`  
   - `sql/knd_users_alter_risk_flag.sql`  
   (y otros ALTER de `users` que uses.)

2. **Support Credits (créditos en header)**  
   - `sql/knd_support_payments.sql`  
   - `sql/knd_points_ledger.sql`  
   - `sql/points_ledger_add_avatar_shop.sql` (si usas avatar shop)  
   - `sql/points_ledger_add_3d_generation.sql`  
   - **`sql/points_ledger_add_ai_jobs.sql`** (necesario para que `ai_spend_points` funcione al generar en Labs).

3. **Nivel / XP (header y perfil)**  
   - `sql/user_xp.sql` (fallback antiguo)  
   - `sql/knd_user_xp.sql`  
   (y si usas temporadas/badges: `sql/knd_seasons.sql`, `sql/knd_badges.sql`, `sql/knd_user_badges.sql`, `sql/knd_xp_ledger.sql`, `sql/knd_season_stats.sql`, etc.)

4. **KND Labs (generación)**  
   - `sql/knd_labs_jobs.sql`  
   - `sql/knd_labs_jobs_alter_cost.sql`  
   - `sql/knd_labs_jobs_alter_queue.sql`  
   - `sql/knd_labs_jobs_alter_provider.sql`  
   - `sql/knd_labs_jobs_alter_output_path.sql`

5. **Settings (ComfyUI / Labs)**  
   - `sql/knd_settings.sql`

Si una tabla ya existe, omite su CREATE. Si un ALTER falla por “column already exists”, puedes ignorarlo.  
El orden crítico para que deje de fallar la generación es:  
- `knd_labs_jobs` con todas las columnas anteriores.  
- `points_ledger` con `source_type` que incluya `ai_job_spend` (y `ai_job_refund`, `ai_job_complete`).  
- `knd_settings` existente para que no falle `settings_get` en ComfyUI.

---

## Referencia rápida de SQL mencionados en el código

- **Créditos (get_available_points):**  
  `SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE user_id = ? AND status = 'available' AND entry_type = 'earn'`  
  y resta de gastos con `entry_type = 'spend'`.  
  Requiere tabla `points_ledger` con columnas `user_id`, `status`, `entry_type`, `points`.

- **Nivel (get_xp_badge_data):**  
  `SELECT xp, level FROM knd_user_xp WHERE user_id = ?`  
  o fallback `SELECT xp FROM user_xp WHERE user_id = ?`.

- **Generar (api/labs/generate.php):**  
  - INSERT en `knd_labs_jobs (user_id, tool, prompt, negative_prompt, status, cost_kp, quality, provider, priority, payload_json)`.  
  - Luego `ai_spend_points` → INSERT en `points_ledger` con `source_type = 'ai_job_spend'`.

Si quieres, en un siguiente paso se puede revisar el mensaje de error exacto que devuelve la API al generar (JSON o log de PHP) para acotar si el fallo es por columna faltante, tabla faltante o ENUM de `source_type`.

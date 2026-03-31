# Diagnóstico: cambios del Home no aparecen en la página

## 1. ¿Qué archivo genera el HTML del home?

**Conclusión: `index.php` en la raíz del proyecto.**

- En `.htaccess` **no** hay `DirectoryIndex`; Apache usa el valor por defecto (`index.html`, `index.php`).
- **No** hay reglas que reescriban `/` ni `index.php` a otro archivo. Las reglas existentes son solo para rutas como `/arena`, `/labs`, `/drop`, etc.
- No existe `index.html` en la raíz, así que al pedir `/` o `/index.php` se sirve **`index.php`**.
- Flujo: `index.php` → `require_once` session, config, header, footer, products-data → genera HTML con las secciones Hero, KND Labs, Gallery Preview, Arena.

---

## 2. ¿Las secciones nuevas están en el archivo correcto?

**Sí.** En `index.php` están:

- Hero fullscreen (`#home-fullpage`, `.home-section-full`)
- Sección **KND Labs** (`#labs`, `.home-labs-section`) con headline, CTAs y visual
- Sección **Gallery Preview** (`#gallery-preview`, `.home-gallery-preview-section`) con encabezado y grid de imágenes
- Sección **KND Arena / Games** (`#lastroll-promo`)
- Inclusión de `home-sections.css` y `home-sections.js` vía `$extraHead`

`generateHeader()` en `includes/header.php` recibe `$extraHead` y lo incluye en el `<head>` (líneas 21–23), así que el markup y los assets del home están en el flujo correcto.

---

## 3. Includes y layout

- **header.php**: define `generateHeader($title, $description, $extraHead)` y sí concatena `$extraHead` al HTML del `<head>`.
- **footer.php**: define `generateFooter()` y `generateScripts()`; se llaman al final de `index.php`. No hay ningún include que “sustituya” el contenido del body; todo el body (Hero, Labs, Gallery, Arena, etc.) está escrito directamente en `index.php` después de `generateNavigation()`.
- No se usan partials/sections externos para esas bloques; no hay riesgo de que el home cargue otro layout sin las secciones nuevas.

---

## 4. Rutas de assets (CSS/JS)

- **Archivos**: `assets/css/home-sections.css` y `assets/js/home-sections.js` existen en el repo.
- **Inclusión**: se añaden en `index.php` con rutas **relativas**:  
  `href="assets/css/home-sections.css?v=..."` y `src="assets/js/home-sections.js?v=..."`.  
  El resto del header usa rutas **absolutas** (`/assets/css/style.css`, etc.).
- **Recomendación**: usar rutas absolutas también para el home (`/assets/...`) para evitar 404 si la página se sirve con una base URL distinta (subcarpeta, proxy, etc.). Cambio aplicado en `index.php`.

Si en producción los CSS/JS del home dan 404, el scroll-snap y los estilos de las secciones no se aplican y la página se verá “vieja”.

---

## 5. ¿Apache está sirviendo otra carpeta?

**Muy probablemente sí.** En `DEPLOYMENT.md` se indica que el sitio en vivo se despliega en **`public_html`** (por ejemplo `/home/u354862096/domains/kndstore.com/public_html`).

- El **código que editas** (commit/push) está en tu **repositorio** (por ejemplo `e:\repo\kndstore` o un clone en el servidor que no es el document root).
- El **código que Apache sirve** es el que está en **`public_html`** (o el DocumentRoot que tenga configurado el hosting).

Si solo haces **commit + push**, actualizas el remoto (GitHub/GitLab, etc.), pero **no** el contenido de `public_html` a menos que:

- entres por SSH al servidor,
- hagas `cd` a esa carpeta (`public_html`),
- y ejecutes ahí `git pull` (o el script de deploy: `./deploy.sh main`, o `git fetch` + `git reset --hard origin/main`).

Mientras no actualices los archivos en **esa** carpeta, Apache seguirá sirviendo la versión antigua de `index.php` y no verás las nuevas secciones.

---

## 6. Condiciones que oculten las secciones

Revisado en `index.php`:

- No hay `if`/`isset` alrededor del bloque Hero / Labs / Gallery / Arena que impida su salida.
- La galería usa `$homeGalleryImages`; si la carpeta no existe o está vacía, solo no se muestra el grid de imágenes; el resto de la sección Gallery Preview (título, CTA) sí se imprime.
- No hay `require`/`include` condicional que “cambie” de página para el home.

No se encontró ninguna condición que esconda las secciones nuevas.

---

## 7. Errores silenciosos

- **config.php**: en producción (`SERVER_NAME` distinto de localhost/127.0.0.1) pone `display_errors = 0` y `log_errors = 1`. Los errores se escriben en `logs/php-error.log`.
- **index.php** fuerza al inicio `display_errors = 1` y `error_reporting(E_ALL)`, así que en cualquier entorno donde se ejecute ese `index.php`, los errores fatales se mostrarían. Si la página carga “normal” pero sin las secciones, no es un fatal en ese script.
- `setCacheHeaders('html')` y `startPerformanceTimer()` están definidos en `includes/config.php`; no hay riesgo de “función no definida” si accedes al home.

Si en el servidor se estuviera ejecutando **otro** `index.php` (por ejemplo una copia antigua en `public_html` sin las nuevas funciones o con un include roto), podría haber un error que se registre en `log_errors` y no se vea en pantalla. Conviene revisar `logs/php-error.log` en el servidor después de abrir `/` o `/index.php`.

---

## 8. Resumen y qué hacer

| Punto | Estado |
|-------|--------|
| Archivo que sirve el home | `index.php` (raíz). Correcto. |
| Secciones nuevas en ese archivo | Sí (Hero, KND Labs, Gallery Preview, Arena). |
| Layout e includes | Correctos; `$extraHead` se usa en el header. |
| CSS/JS del home | Existen; rutas pasadas a absolutas en `index.php`. |
| Apache sirviendo otra carpeta | Muy probable: sitio en vivo en `public_html`. |
| Condiciones que oculten contenido | Ninguna relevante. |
| Errores silenciosos | Revisar `logs/php-error.log` en el servidor. |

**Acción más probable:**  
Actualizar el código **en la carpeta que Apache usa como document root** (p. ej. `public_html`):

```bash
cd /home/u354862096/domains/kndstore.com/public_html   # o la ruta que uses
git fetch origin
git reset --hard origin/main
```

Después, hard refresh en el navegador. Si usas un script de deploy (`deploy.sh`), ejecútalo desde esa misma carpeta.

**Comprobación rápida:**  
En el navegador, “Ver código fuente” de la página del home. Deberías ver:

- `<section class="home-section-full home-labs-section" id="labs">`
- `<section class="home-section-full home-gallery-preview-section" id="gallery-preview">`
- `<link rel="stylesheet" href="/assets/css/home-sections.css?v=...">`

Si no aparecen, el HTML que estás viendo no es el del `index.php` actualizado (caché o no haber desplegado en `public_html`).

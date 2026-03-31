# Informe de auditoría de uso y aislamiento (`/legacy`)

**Fecha del análisis:** 2026-03-22  
**Alcance:** PHP plano, `.htaccess`, referencias en JS/HTML hacia `/api/` y assets; **sin** ejecución en producción ni revisión de logs de Apache.

---

## 1. Resumen

| Métrica | Valor (aprox.) |
|--------|----------------|
| Archivos `.php` en el repositorio | ~398 |
| Inclusión `require_once` / similares | Uso generalizado en todo el árbol (núcleo en `includes/`) |
| Referencias a rutas `/api/...php` en front (muestra representativa) | Decenas de coincidencias en `assets/js/`, `games/`, `labs/`, `admin/` |

Se aplicó una política **conservadora**: solo se movieron artefactos con **cero referencias** de runtime en el repo y **sin** rol en rutas amigables de Apache.

**Archivos movidos a `legacy/`:** únicamente la carpeta `_cursor_quarantine/` → `legacy/_cursor_quarantine/` (detalle en [`legacy/README.md`](legacy/README.md)).

**Nota:** `test.php` y `test_badge_system.php` en la raíz no se movieron: quedaron clasificados como no referenciados por el sitio, pero **sí** dependen de `__DIR__ . '/includes/...'` respecto a la raíz del repo; moverlos sin tocar rutas rompería los scripts, en conflicto con “solo mover / no refactorizar”.

---

## 2. Mapa de dependencias (metodología)

### 2.1 Puntos de entrada y rutas activas

- **Raíz y PHP sueltos:** páginas como `index.php`, `knd-labs.php`, juegos bajo `games/`, etc., enlazan entre sí y cargan `includes/`.
- **Apache (`/.htaccess`):** reglas `RewriteRule` mapean URLs cortas a archivos concretos. Destinos relevantes:
  - `^arena/?$` → `/knd-arena.php`
  - `^drop/?$` → `/games/knd-neural-link/drops.php`
  - `^lastroll/?$` → `/death-roll-lobby.php`; `^lastroll/game/?$` → `/death-roll-game.php`
  - `^credits/?$` → `/support-credits.php`; `^rewards/?$` → `/rewards.php`
  - `^game-fairness/?$` → `/game-fairness.php`
  - `^how-knd-arena-works/?$` → `/how-knd-arena-works.php`
  - `^character-lab/?$` → `/labs-character-lab.php`
  - `^labs-next/?$` → `/labs-next.php`
  - `^labs-legacy/?$` → `/knd-labs-legacy.php`
  - `^labs/?$` y `^knd-labs/?$` → `/knd-labs.php`
  - Varias rutas `^labs/...\.php$` → `labs-*.php` en raíz
  - `^hologram-lab/?$` → `/tools/hologram-lab/index.html`

Cualquier análisis de “¿se usa este `.php`?” debe cruzar **nombre de URL** y **destino interno**, no solo el nombre del fichero.

### 2.2 Grafo PHP (`require` / `include`)

- La mayoría de scripts arrancan con `require_once` hacia `includes/session.php`, `includes/config.php`, `includes/auth.php`, etc.
- **`includes/`** está protegido por reglas de reescritura (no accesible directamente por URL) pero **sí** es crítico vía includes: **no mover**.

### 2.3 Front: endpoints y assets

- Búsqueda de patrones tipo `/api/...` en `.js`, `.php` y `.html` muestra consumo distribuido (Labs, Mind Wars, avatares, soporte, etc.).
- Algunos assets se **enumeran en runtime** (p. ej. frames de avatar en `index.php`); no se puede declarar “no usado” solo por no aparecer como string fija en el código.

---

## 3. Archivos movidos (✅)

Ver tabla y motivos en [`legacy/README.md`](legacy/README.md).

- `legacy/_cursor_quarantine/` (antes `_cursor_quarantine/` en la raíz)

---

## 4. Archivos y áreas dudosas (⚠️ no movidos)

| Candidato | Motivo |
|-----------|--------|
| Carpeta `--/` en la raíz (`squad-arena.php`, `arena-actions.js`, `combo-builder.*`, etc.) | Parece copia o ubicación errónea; `squad-arena/mind-wars-select.php` redirige a `/squad-arena/squad-arena.php`, pero el único `squad-arena.php` encontrado en el árbol está bajo `--/`. Mover podría eliminar la única copia en repo o confundir despliegue manual. |
| `scripts/test_mindwars_battles.php` | Script CLI sin referencias web; puede usarse en CI o localmente. |
| `about us/knd-about-concept.html` | Referenciado en comentarios/CSS como origen de diseño; no es basura obvia. |
| `test.php`, `test_badge_system.php` (raíz) | Sin referencias en el resto del repo; **no movidos** porque `require` usa rutas relativas a la raíz del proyecto (`__DIR__.'/includes/...'`). |
| Duplicados de ruta `api\foo` vs `api/foo` (Windows) | Pueden ser el mismo archivo visto dos veces; requiere normalización antes de clasificar. |
| Endpoints `/api/*.php` sin coincidencia en front | Podrían ser webhooks, apps externas, o llamadas dinámicas; **no** mover sin trazabilidad. |

---

## 5. Crítico — no tocar (❌)

- `includes/` (núcleo compartido).
- `api/` y `admin/` salvo análisis de orfandad con trazas y pruebas.
- Destinos de `RewriteRule` en `.htaccess`.
- Webhooks, workers y jobs bajo rutas conocidas por integración.

---

## 6. Recomendaciones de limpieza futura

1. **PHP:** Introducir PHPStan o Psalm con reglas de “dead code” sobre funciones/métodos; complementar con cobertura de tests donde exista.
2. **CSS:** PurgeCSS o auditoría por lista de clases usadas en plantillas (grep de selectores no basta).
3. **APIs:** Registrar llamadas reales (logs de Apache `access.log` filtrado por `/api/`) durante una ventana representativa.
4. **Carpeta `squad-arena/`:** Resolver si `squad-arena.php` debe vivir en `squad-arena/` para coincidir con las redirecciones actuales, o actualizar enlaces hacia `squad-arena-v2/`.
5. **Documentación:** Actualizar exclusiones en `docs/SMOKE_TESTS.md` para `legacy/_cursor_quarantine` si los smoke tests siguen en uso.

---

## 7. Consola

No se generó salida interactiva adicional; este archivo es el reporte principal del análisis.

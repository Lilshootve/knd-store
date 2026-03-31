# Legacy / aislamiento de código

Archivos movidos aquí el **2026-03-22** como parte de la auditoría estática de uso (plan: aislamiento sin borrar ni refactorizar).

## Archivos y carpetas movidos

| Ruta original | Ruta en `legacy/` | Clasificación |
|---------------|-------------------|---------------|
| `_cursor_quarantine/` | `legacy/_cursor_quarantine/` | Seguro (✅) |

## Motivo de clasificación (✅)

- **`_cursor_quarantine/`**: Carpeta explícita de cuarentena (scripts `debug_db*.php`, `test-*.php`, PNG de salida bajo `_out/`). No hay referencias en código de producción; solo menciones en documentación (`docs/SMOKE_TESTS.md`, `docs/PURGE_REPORT_2025-03-05.md`). Verificación cruzada: búsqueda por nombres de archivo y por `_cursor_quarantine`.

## Archivos evaluados como ✅ pero NO movidos (evitar rotura)

| Archivo | Motivo |
|---------|--------|
| `test.php` (raíz) | Usa `require_once __DIR__ . '/includes/...'`; al colocarlo bajo `legacy/` deja de resolver `includes/` sin editar rutas (el plan pedía no refactorizar). |
| `test_badge_system.php` (raíz) | Igual: depende de `__DIR__ . '/includes/config.php'` desde la raíz del proyecto. |

Si en el futuro se desea aislarlos, bastaría moverlos y ajustar las rutas a `__DIR__ . '/../includes/...'` o un bootstrap común.

## Riesgos potenciales

1. **Documentación:** `docs/SMOKE_TESTS.md` excluye `_cursor_quarantine` por ruta antigua; conviene actualizar el patrón a `legacy/_cursor_quarantine` cuando se revisen los smoke tests.
2. **Scripts en cuarentena:** Varios `test-*.php` bajo `legacy/_cursor_quarantine/2025-03-05/` usan rutas tipo `__DIR__ . '/includes/...'` que no apuntan al `includes/` real del proyecto (carpeta local inexistente). Se asume que eran borradores o no ejecutables tal cual; el aislamiento no empeora ese estado.

Nada de lo movido formaba parte de cadenas `require`/`include` de la aplicación principal.

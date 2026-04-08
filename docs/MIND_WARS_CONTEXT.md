# Mind Wars — contexto técnico (KND Store)

Documento de **entrada rápida** para desarrollo. Mantén **este archivo** cuando cambien rutas, tablas o flujos. La base de datos en repo vive en **`sql/`** (vuelca de nuevo cuando cambies el esquema en producción).

Documentación relacionada (otros enfoques / histórico): `docs/MIND_WARS_UNIFIED_SYSTEM.md`, `docs/MIND_WARS_PRODUCTION_READY.md`, `docs/MIND_WARS_COMBO_API.md`, `docs/KND_COMBAT_DEPENDENCY_MAP.md`.

---

## 1. Estado (verificado en código + SQL del repo)

- **Combate autoritativo en PHP**: el cliente (`mind-wars-arena.js`) envía acciones; el servidor persiste y devuelve estado en JSON (`perform_action.php`, `state_json` en BD).
- **Modos**: PvE y PvP están cableados en el JS (poll PvP ~2s, `battleMode` 1v1 / 3v3, cola ranked en lobby).
- **Avatares**: definición en `mw_avatars` + stats numéricas en `mw_avatar_stats` (mind, focus, speed, luck). Skills jugables según datos JSON en `mw_avatar_skills` (ver §5).
- **Unity**: no es necesario para el producto web actual; mismo backend HTTP serviría a un cliente futuro si expones contratos estables y auth clara.

---

## 2. SQL en `sql/` (abril 2026)

| Archivo | Uso |
|--------|-----|
| `u354862096_kndstore.sql` | Dump completo (Mind Wars, Nexus, inventario KND, etc.). |
| `mw_avatars.sql` | Estructura + datos parciales de `mw_avatars` (útil diff rápido). |
| `mw_avatar_skills.sql` | Estructura + filas de `mw_avatar_skills`. |

---

## 3. Superficies de juego (front)

**Arena principal (turnos, UI arena)**  
- Página: `games/mind-wars/mind-wars-arena.php` (incrusta HTML/JS).  
- Lógica cliente: `games/mind-wars/mind-wars-arena.js` (comentario de flujo en cabecera del archivo).  
- HTML estático alternativo: `games/mind-wars/mind-wars-arena.html`.

**Lobby (misiones, leaderboard, matchmaking)**  
- `games/mind-wars/lobby.js` → varios `fetch` a `/api/mind-wars/*` (ver §6).

**Otro hilo de producto (squads / combos 3 cartas)**  
- Documentado en `MIND_WARS_UNIFIED_SYSTEM.md` bajo `squad-arena/`; no sustituye la arena `mind-wars-arena.js`, conviven en el repo.

---

## 4. Bootstrap y motor (PHP)

- APIs Mind Wars cargan vía `includes/mind_wars_arena_bootstrap.php` → `includes/mind_wars.php` + `includes/mind_wars_combat_actions.php`.
- `api/mind-wars/perform_action.php` (ejemplo de cadena real) también incluye: `games/mindwars3v3/engine/PveKnockoutResolver.php`, `includes/knd_badges.php`, `includes/knowledge_duel.php`, `includes/mind_wars_rewards.php`, `includes/mind_wars_combo.php`.

---

## 5. Datos de avatar y skills

**`mw_avatars` (dump)**  
- `class` enum en BD: `Striker`, `Tank`, `Support`, `Controller`, `Strategist` (cinco valores; alinear lenguaje de diseño “4 roles” con estos nombres en UI/copy).

**`mw_avatar_stats`**  
- Por fila: `mind`, `focus`, `speed`, `luck`.

**`mw_avatar_skills`**  
- Campos **activos para definición de habilidades** (JSON validado): `basic_data`, `passive_data`, `ability_data`, `special_data`.  
- Columnas tipo `passive`, `ability`, `special`, `*_code`, `status_effect`, etc.: tratar como **legacy / display** salvo que el motor las lea explícitamente en `includes/mind_wars*.php` (confirmar en código si dudas).

---

## 6. Endpoints HTTP usados por la arena y lobby (referencia)

**Arena (`mind-wars-arena.js`)**  
- `GET /api/avatars/get.php` — colección del usuario.  
- `POST /api/mind-wars/start_battle.php` — inicio batalla (form POST + CSRF).  
- `POST /api/mind-wars/perform_action.php` — acciones de turno.  
- `POST /api/mind-wars/forfeit.php` — rendición.  
- `GET /api/mind-wars/get_battle_state.php?battle_token=…` — estado (incl. PvP poll).  
- `POST /api/mind-wars/pve_submit.php` — cierre/envío PvE donde aplique.

**Lobby (`lobby.js`, muestra)**  
- `GET /api/mind-wars/get_lobby_data.php`  
- `GET /api/mind-wars/get_leaderboard_preview.php`, `GET /api/mind-wars/leaderboard.php`  
- `POST /api/mind-wars/mission_claim.php`  
- Matchmaking: `POST /api/mind-wars/start_matchmaking.php`, `GET /api/mind-wars/queue_status.php`, `POST /api/mind-wars/queue_dequeue.php`, `POST /api/mind-wars/pvp_join_matched.php`  
- `POST /api/avatar/set_favorite.php`

**Catálogo completo `api/mind-wars/`** (mantén la lista al añadir archivos):  
`achievements.php`, `challenge_accept.php`, `challenge_cancel.php`, `challenge_create.php`, `challenge_decline.php`, `challenge_inbox.php`, `forfeit.php`, `get_battle_state.php`, `get_leaderboard_preview.php`, `get_lobby_data.php`, `get_state.php`, `leaderboard.php`, `lore.php`, `mission_claim.php`, `online_players.php`, `perform_action.php`, `pve_enemy.php`, `pve_submit.php`, `pvp_join_matched.php`, `queue_dequeue.php`, `queue_enqueue.php`, `queue_status.php`, `recent_battles.php`, `season_info.php`, `seasons_history.php`, `start_battle.php`, `start_matchmaking.php`.

---

## 7. Tablas Mind Wars (estructura en `u354862096_kndstore.sql`)

Resumen de tablas centrales:

- **`knd_mind_wars_battles`** — `battle_token`, `state_json` (JSON), `mode`, `result`, `turns_played`, recompensas opcionales (`xp_gained`, `knowledge_energy_gained`, `rank_gained`), `battle_log_json`, timestamps.  
  En el dump actual del repo las columnas incluyen `user_id`, `avatar_item_id`, `enemy_avatar_id` (orientado PvE clásico).

- **`knd_mind_wars_battle_participants`** — `battle_id`, `user_id`, `avatar_item_id`, `side` (`player` | `enemy`).

- **`knd_mind_wars_challenges`** — retos 1v1 por temporada, tokens, `battle_id` / `battle_token`, estados pending/accepted/…

- **`knd_mind_wars_matchmaking_queue`** — cola ranked, `queue_token`, snapshot de `rank_score`, emparejamiento.

- **`knd_mind_wars_rankings`** — por `season_id` + `user_id`: `rank_score`, `wins`, `losses`.

- **`knd_mind_wars_seasons`** — `name`, ventanas `starts_at` / `ends_at`, `status` (`upcoming` | `active` | `finished`).

- **`knd_mw1v1_battles`** — tabla adicional en el dump (legado o modo paralelo); no confundir con `knd_mind_wars_battles` sin revisar código que la escribe.

---

## 8. Alerta: coherencia BD ↔ `api/nexus/world.php`

El archivo `api/nexus/world.php` consulta `knd_mind_wars_battles` con columnas del estilo `attacker_id`, `defender_id`, `attacker_avatar_item_id`, `defender_avatar_item_id`, `status = 'finished'`.

El dump **`u354862096_kndstore.sql` en repo no define esas columnas** en `knd_mind_wars_battles` (solo el esquema `user_id` / `enemy_avatar_id` / …).

**Acción:** o bien actualizas el dump y migraciones para reflejar el esquema real de producción, o ajustas la query del Nexus al esquema que realmente tienes. Hasta que coincidan, el feed “última batalla” del Nexus puede fallar en entornos importados solo desde este dump.

---

## 9. Nexus (mundo Habbo-like) y WebSocket — recomendación breve

Mind Wars **no depende** del WebSocket del Nexus. El Nexus (`games/arena-protocol/nexus-city.html`) usa **Node** `games/nexus-ws.js` (puerto por defecto 8765 en el HTML).

Para producción: sirve `wss://` detrás del mismo dominio (reverse proxy), proceso supervisado (systemd/Docker), TLS en el proxy, y **límites de tasa** en mensajes de movimiento. No hace falta Unity para eso.

---

## 10. Checklist al cambiar el sistema

1. Vuelca BD a `sql/u354862096_kndstore.sql` (o migraciones incrementales si las usas).  
2. Actualiza este archivo si cambian endpoints o tablas.  
3. Comprueba `api/nexus/world.php` contra el esquema real de batallas.  
4. Si añades skill nueva, documenta el shape JSON en `mw_avatar_skills` y valida en staging.

---

*Generado a partir del estado del repositorio (arena, APIs, dump SQL).*

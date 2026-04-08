# KND Combat — matriz de dependencias (baseline)

Documento de referencia para el refactor por modos (`mindwars1v1`, `mindwars3v3`, `squadwars`).  
Generado como **Paso 1** del plan arquitectónico; actualizar al mover código fuera de `includes/`.

## 1. Puntos de entrada por modo

| Modo | UI / API principales | Motor / includes |
|------|----------------------|------------------|
| **1v1 + 3v3 cola** (mismo endpoint) | `api/mind-wars/start_battle.php`, `start_matchmaking.php`, `perform_action.php`, `get_battle_state.php`, `get_state.php`, `pvp_join_matched.php`, colas/challenges | `includes/mind_wars.php` → `includes/mind_wars_combat_actions.php`; `perform_action` también `knd_badges`, `knowledge_duel`, `mind_wars_rewards`, `mind_wars_combo` |
| **3v3 cola (lógica KO)** | Misma que arriba; `state.meta.format === '3v3'` | `games/mindwars3v3/engine/PveKnockoutResolver.php` (`knd_mw3v3_resolve_pve_knockouts`) |
| **Squad legacy** | `games/mind-wars-squad/api/start_battle_3v3.php`, `perform_action_3v3.php` | `includes/mind_wars.php` + `includes/mw_squad.php` (wrapper → `games/mind-wars-squad/includes/mw_squad.php`) |
| **Squad nuevo (plan)** | `games/squadwars/api/*` (bootstrap + contratos) | `games/squadwars/engine/*` (sin acoplar a `mw_skill_*` 1v1) |

## 2. Grafo `require_once` (combat core)

```
config/bootstrap.php
  └─ define BASE_PATH

includes/mind_wars.php
  ├─ config.php (vía otros entrypoints)
  ├─ knd_avatar.php
  ├─ mind_wars_skill_handlers.php
  └─ mind_wars_skills.php

includes/mind_wars_combat_actions.php
  └─ mind_wars.php (si no cargado)

games/mindwars1v1/bootstrap.php
  ├─ mind_wars.php
  └─ mind_wars_combat_actions.php

api/mind-wars/perform_action.php
  ├─ games/mindwars1v1/bootstrap.php
  ├─ knd_badges.php, knowledge_duel.php, mind_wars_rewards.php, mind_wars_combo.php
  └─ games/mindwars3v3/engine/PveKnockoutResolver.php

games/mind-wars-squad/api/*.php
  ├─ mind_wars.php
  └─ includes/mw_squad.php → games/mind-wars-squad/includes/mw_squad.php
```

## 3. Tablas DB (combate actual)

| Tabla | Uso |
|-------|-----|
| `knd_mind_wars_battles` | Estado JSON 1v1 / 3v3 cola / metadatos modo |
| `knd_mind_wars_battle_participants` | PvP lado / usuario |
| `knd_mind_wars_matchmaking_queue` | Cola ranked/PvP |
| `knd_mind_wars_challenges`, rankings, seasons | Meta PvP/PvE |
| `mw_avatars`, `mw_avatar_stats`, `mw_avatar_skills` | Datos avatar combate |

**Migración futura (plan):** `knd_mw1v1_battles`, `knd_mw3v3_battles`, `knd_squadwars_battles` — ver `sql/migrations/knd_combat_mode_tables.sql`.

## 4. Claves de estado relevantes

- **1v1 / 3v3 cola:** `player`, `enemy`, `turn`, `next_actor`, `log`, `meta.format`, `meta.player_queue`, `meta.enemy_wave_index`, …
- **Squad legacy:** estructura en `mw_squad.php` (`squads`, `turn_order`, …)
- **Squad plan:** `games/squadwars/contracts/SquadStateV1.php` (documentación + helpers)

## 5. Riesgos de acoplamiento

- `perform_action.php` concentrado: PvE, PvP, combo, 3v3 KO.
- `mind_wars.php` como módulo amplio (temporada, matchmaking, utilidades).
- Dispatch dinámico `mw_skill_*` en 1v1 vs squad legacy.
- Rutas: APIs squad pedían `includes/mw_squad.php` inexistente en repo; corregido con wrapper.

## 6. Convención de carga modular

- **Arena 1v1/3v3 cola:** `require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';`
- **Helpers solo 3v3 cola:** `require_once BASE_PATH . '/games/mindwars3v3/bootstrap.php';`
- **Squad nuevo:** `require_once BASE_PATH . '/games/squadwars/bootstrap.php';`

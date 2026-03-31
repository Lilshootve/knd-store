# Mind Wars Squad (3v3 PvE)

Isolated under `games/mind-wars-squad/`. Lobby link: `games/mind-wars/lobby-partials/panels_right.php`.

## Layout

- `view/squad-battle.php` — UI (requires login)
- `api/start_battle_3v3.php`, `api/perform_action_3v3.php` — JSON API
- `includes/mw_squad.php` — state, turn order, combat, persistence
- `assets/css/squad-battle.css`, `assets/js/squad-battle.js` — front-end

## Database

Battles are stored in **`knd_mind_wars_battles`** with `mode = 'pve_3v3'`. See [`sql/schema_squad_3v3.sql`](sql/schema_squad_3v3.sql) for notes (including cleanup if you created legacy `mw_battles`).

## Rewards

On battle end, `perform_action_3v3.php` calls `mw_apply_rewards_to_user()` (same as 1v1): user XP, knowledge energy on the **lead** avatar slot (first item id stored on the row), season rank, and Mind Wars badge checks.

## API (relative to site root)

- `POST /games/mind-wars-squad/api/start_battle_3v3.php`
- `POST /games/mind-wars-squad/api/perform_action_3v3.php`

Body: JSON with `csrf_token` plus payload fields as implemented in `squad-battle.js`.

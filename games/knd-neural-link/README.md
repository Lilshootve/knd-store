# KND Neural Link (sandbox)

Isolated capsule / “neural link” flow under `/games/knd-neural-link/`. Uses the same session and DB as the main site (`dr_user_id`, `getDBConnection()`), debits **KND Points** via `points_ledger`, grants **`knd_avatar_items`** into `knd_user_avatar_inventory`, and tracks pity in **`knl_neural_link_pity`** (no `ALTER` on `users`).

## Setup

1. Run [`schema_drops.sql`](schema_drops.sql) once (creates `knl_neural_link_pity`, `knd_drop_log` if missing, and view `v_knl_drop_stats`).
2. If `knd_drop_log` already existed without `item_id` / `cost_kp`, apply the `ALTER` comments in that file.
3. Ensure `points_ledger.source_type` includes `knl_neural_link` (or rely on fallbacks `drop_entry` / `adjustment`). See [`sql/points_ledger_add_knl_neural_link.sql`](sql/points_ledger_add_knl_neural_link.sql) — merge the ENUM with your live DB list.

## URLs

- UI: `/games/knd-neural-link/drops.php`
- API: `/games/knd-neural-link/api/get_drop_rates.php`, `/games/knd-neural-link/api/open_drop.php`

## Production toggles

- In `api/open_drop.php`: set **`$SANDBOX_MODE = true`** for real debits and inventory. **`false`** = dry-run JSON only (no ledger/inventory/pity/log writes).
- Pack costs and weights: [`includes/knl_packs.php`](includes/knl_packs.php).

## Debug

Server logs prefix: **`[KNL AGUACATE]`** + step (`auth`, `db`, `kp_debit`, `pick_item`, `inventory`, `commit`, …). JSON errors may include `aguacate` and, on localhost, `debug_detail`.

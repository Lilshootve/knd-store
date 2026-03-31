-- Holo orb micro-rewards: server-side cooldown anchor (run once per environment).
-- If the column already exists, skip or remove this line from your deploy script.

ALTER TABLE users
  ADD COLUMN last_orb_claim_at DATETIME NULL DEFAULT NULL
  COMMENT 'Last successful holo orb claim (UTC)';

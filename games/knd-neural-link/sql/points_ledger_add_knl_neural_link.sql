-- Add knl_neural_link (and drop_entry if missing) to points_ledger.source_type
-- Run after your latest points_ledger ENUM migration.
-- Inspect current ENUM with: SHOW COLUMNS FROM points_ledger LIKE 'source_type';
-- Then merge this value into that list.

ALTER TABLE `points_ledger` MODIFY COLUMN `source_type`
  ENUM(
    'support_payment','redemption','adjustment','avatar_shop',
    '3d_generation','3d_generation_refund',
    'ai_job_spend','ai_job_refund','ai_job_complete',
    'character_lab_spend','character_lab_refund',
    '3d_lab_spend','3d_lab_refund',
    'drop_entry','knl_neural_link'
  ) NOT NULL;

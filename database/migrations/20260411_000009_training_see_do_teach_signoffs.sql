-- See 1 / Do 1 / Teach 1: each signed by a manager+; assignment completes when all three are set
ALTER TABLE training_assignments
    ADD COLUMN signoff_see_at DATETIME NULL AFTER notes,
    ADD COLUMN signoff_see_by_user_id BIGINT UNSIGNED NULL AFTER signoff_see_at,
    ADD COLUMN signoff_do_at DATETIME NULL AFTER signoff_see_by_user_id,
    ADD COLUMN signoff_do_by_user_id BIGINT UNSIGNED NULL AFTER signoff_do_at,
    ADD COLUMN signoff_teach_at DATETIME NULL AFTER signoff_do_by_user_id,
    ADD COLUMN signoff_teach_by_user_id BIGINT UNSIGNED NULL AFTER signoff_teach_at;

-- Legacy single-step completions: treat as all three signed at once
UPDATE training_assignments
SET
    signoff_see_at = completed_at,
    signoff_see_by_user_id = completed_by_user_id,
    signoff_do_at = completed_at,
    signoff_do_by_user_id = completed_by_user_id,
    signoff_teach_at = completed_at,
    signoff_teach_by_user_id = completed_by_user_id
WHERE completed_at IS NOT NULL
  AND signoff_see_at IS NULL;

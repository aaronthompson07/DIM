-- Use ONLY if the app shows "Database upgrade required" but you already fixed tables manually.
-- 1) Confirm appointment_board_entries exists and board_public_key column exists on tenants.
-- 2) Mark appointment-board migrations as applied so the runner stops retrying failed DDL:

INSERT IGNORE INTO schema_migrations (migration) VALUES
('20260622_000032_appointment_board.sql'),
('20260622_000034_appointment_board_repair.sql');

-- Optional: deactivate global maintenance notice (different from migration lock):
-- UPDATE system_messages SET is_active = 0 WHERE scope = 'global' AND is_maintenance = 1;

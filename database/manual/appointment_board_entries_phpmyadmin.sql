-- Run in phpMyAdmin if CREATE TABLE with foreign keys failed (errno 150).
-- No foreign keys; column types should match your tenants/users id columns.
-- Check types: SHOW COLUMNS FROM tenants LIKE 'id'; SHOW COLUMNS FROM users LIKE 'id';

CREATE TABLE IF NOT EXISTS appointment_board_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    patient_name VARCHAR(120) NOT NULL,
    assigned_user_id INT UNSIGNED NOT NULL,
    appointment_at DATETIME NOT NULL,
    notes TEXT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_abe_tenant_appt (tenant_id, appointment_at),
    INDEX idx_abe_assigned_user (assigned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If the above fails on column type, replace INT UNSIGNED with the exact type from SHOW COLUMNS
-- (e.g. bigint(20) unsigned or int(11)).

UPDATE tenants
SET board_public_key = LOWER(REPLACE(UUID(), '-', ''))
WHERE board_public_key IS NULL OR board_public_key = '';

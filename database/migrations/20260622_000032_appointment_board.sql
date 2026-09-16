ALTER TABLE tenants
    ADD COLUMN board_public_key VARCHAR(64) NULL AFTER company_username,
    ADD UNIQUE KEY uq_tenants_board_public_key (board_public_key);

CREATE TABLE IF NOT EXISTS appointment_board_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    patient_name VARCHAR(120) NOT NULL,
    assigned_user_id BIGINT UNSIGNED NOT NULL,
    appointment_at DATETIME NOT NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_abe_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_abe_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_abe_tenant_appt (tenant_id, appointment_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE tenants
SET board_public_key = LOWER(REPLACE(UUID(), '-', ''))
WHERE board_public_key IS NULL OR board_public_key = '';

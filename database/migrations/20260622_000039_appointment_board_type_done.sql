ALTER TABLE appointment_board_entries
    ADD COLUMN appointment_type ENUM('surgery', 'room') NOT NULL DEFAULT 'room' AFTER patient_name,
    ADD COLUMN completed_at DATETIME NULL AFTER notes,
    ADD INDEX idx_abe_tenant_active (tenant_id, completed_at, appointment_at);

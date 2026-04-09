ALTER TABLE system_messages
    MODIFY COLUMN tenant_id BIGINT UNSIGNED NULL;

ALTER TABLE system_messages
    ADD COLUMN scope ENUM('tenant', 'global') NOT NULL DEFAULT 'tenant' AFTER tenant_id;

UPDATE system_messages
SET scope = 'tenant'
WHERE scope IS NULL OR scope = '';

UPDATE system_messages
SET scope = 'global', tenant_id = NULL
WHERE is_maintenance = 1;

CREATE INDEX idx_system_messages_scope_active ON system_messages (scope, is_active, is_maintenance, created_at);

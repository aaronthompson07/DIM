-- Tenant-defined priority levels with colors (replaces free-text priority_key for UI)
CREATE TABLE IF NOT EXISTS tenant_priorities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    color_hex CHAR(7) NOT NULL DEFAULT '#64748b',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tenant_priorities_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_priorities_tenant (tenant_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tenant_priorities (tenant_id, name, color_hex, sort_order, is_active)
SELECT t.id, s.name, s.color_hex, s.ord, 1
FROM tenants t
CROSS JOIN (
    SELECT 0 AS ord, 'Normal' AS name, '#64748b' AS color_hex
    UNION ALL SELECT 1, 'High', '#dc2626'
    UNION ALL SELECT 2, 'Low', '#94a3b8'
) AS s
WHERE NOT EXISTS (SELECT 1 FROM tenant_priorities x WHERE x.tenant_id = t.id);

ALTER TABLE tasks
ADD COLUMN priority_id BIGINT UNSIGNED NULL AFTER assigned_user_id;

UPDATE tasks t
INNER JOIN tenant_priorities p ON p.tenant_id = t.tenant_id AND LOWER(TRIM(p.name)) = LOWER(TRIM(t.priority_key))
SET t.priority_id = p.id
WHERE t.priority_id IS NULL;

UPDATE tasks t
INNER JOIN (
    SELECT tenant_id, MIN(id) AS pick_id
    FROM tenant_priorities
    WHERE is_active = 1
    GROUP BY tenant_id
) m ON m.tenant_id = t.tenant_id
SET t.priority_id = m.pick_id
WHERE t.priority_id IS NULL;

ALTER TABLE tasks
ADD CONSTRAINT fk_tasks_tenant_priority FOREIGN KEY (priority_id) REFERENCES tenant_priorities(id) ON DELETE SET NULL;

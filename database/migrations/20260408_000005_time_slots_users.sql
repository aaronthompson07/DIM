-- Tenant-configurable time-of-day labels (Morning, Afternoon, etc.)
CREATE TABLE IF NOT EXISTS tenant_time_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_time_slots_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_time_slots_tenant (tenant_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tasks
ADD COLUMN time_slot_id BIGINT UNSIGNED NULL AFTER recurrence_day_of_month,
ADD CONSTRAINT fk_tasks_time_slot FOREIGN KEY (time_slot_id) REFERENCES tenant_time_slots(id) ON DELETE SET NULL;

ALTER TABLE tasks MODIFY task_time TIME NULL;

ALTER TABLE task_occurrences
ADD COLUMN time_slot_id BIGINT UNSIGNED NULL AFTER due_date,
ADD CONSTRAINT fk_occ_time_slot FOREIGN KEY (time_slot_id) REFERENCES tenant_time_slots(id) ON DELETE SET NULL;

-- Default slots for every tenant that has none yet
INSERT INTO tenant_time_slots (tenant_id, name, sort_order, is_active)
SELECT t.id, s.name, s.ord, 1
FROM tenants t
CROSS JOIN (
    SELECT 0 AS ord, 'Morning' AS name
    UNION ALL SELECT 1, 'Afternoon'
    UNION ALL SELECT 2, 'Evening'
) AS s
WHERE NOT EXISTS (SELECT 1 FROM tenant_time_slots x WHERE x.tenant_id = t.id);

-- Attach tasks to earliest active slot per tenant
UPDATE tasks t
INNER JOIN tenant_time_slots s ON s.tenant_id = t.tenant_id AND s.is_active = 1
INNER JOIN (
    SELECT tenant_id, MIN(sort_order) AS mo
    FROM tenant_time_slots
    WHERE is_active = 1
    GROUP BY tenant_id
) m ON m.tenant_id = t.tenant_id AND s.sort_order = m.mo
SET t.time_slot_id = s.id
WHERE t.time_slot_id IS NULL;

UPDATE task_occurrences o
INNER JOIN tasks tk ON tk.id = o.task_id
SET o.time_slot_id = tk.time_slot_id
WHERE o.time_slot_id IS NULL;

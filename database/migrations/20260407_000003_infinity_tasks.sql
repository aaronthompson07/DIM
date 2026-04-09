ALTER TABLE tasks
ADD COLUMN is_infinity TINYINT(1) NOT NULL DEFAULT 0 AFTER task_time;

CREATE INDEX idx_tasks_tenant_infinity_active ON tasks (tenant_id, is_infinity, is_active);

-- Soft-delete tasks: hidden from admin list and live board; row kept for reporting/history
ALTER TABLE tasks
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD INDEX idx_tasks_tenant_deleted (tenant_id, deleted_at);

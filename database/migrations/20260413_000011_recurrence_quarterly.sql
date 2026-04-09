ALTER TABLE tasks
    MODIFY COLUMN recurrence_type ENUM('none', 'daily', 'weekly', 'biweekly', 'monthly', 'quarterly') NOT NULL DEFAULT 'none';

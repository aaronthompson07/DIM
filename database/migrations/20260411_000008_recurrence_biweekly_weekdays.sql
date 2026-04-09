-- Bi-weekly schedule + multiple weekdays per week (comma-separated 0=Sun … 6=Sat)
ALTER TABLE tasks
    MODIFY COLUMN recurrence_type ENUM('none', 'daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'none';

ALTER TABLE tasks
    ADD COLUMN recurrence_weekdays VARCHAR(32) NULL AFTER recurrence_weekday,
    ADD COLUMN recurrence_anchor_date DATE NULL AFTER recurrence_weekdays;

INSERT INTO tenants (
    name,
    company_username,
    password_hash,
    owner_email,
    timezone,
    theme_default,
    trial_started_at,
    trial_ends_at,
    plan_key,
    is_demo,
    is_active
) VALUES (
    'Demo Company',
    'demo',
    '$2y$10$Dra68v7Yq7RE9JzhVV5z7eqDzwNz5hL7N6NQW9Rdrgx4u5u9uQf2W',
    'demo@doitmeow.local',
    'UTC',
    'light',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 30 DAY),
    'basic_ads',
    1,
    1
)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (tenant_id, display_name, email, role, pin_hash, pin_lookup_hash, is_active)
SELECT t.id, 'Demo Admin', 'admin@demo.local', 'tenant_admin', '$2y$10$CwGwA/ueT4nA0Qwe0YQWZO6c9sW6P0me9zYx9P5Xe2qiH6m0VYR9W', '8d969eef6ecad3c29a3a629280e686cff8fabd87f5e97d1f1f3f6a89e4f8d5f6', 1
FROM tenants t
WHERE t.company_username = 'demo'
AND NOT EXISTS (
    SELECT 1 FROM users u WHERE u.tenant_id = t.id AND u.email = 'admin@demo.local'
);

INSERT INTO tasks (
    tenant_id,
    title,
    details,
    priority_key,
    recurrence_type,
    task_time,
    is_active
)
SELECT t.id, 'Morning Checklist', 'Demo recurring daily task', 'high', 'daily', '09:00:00', 1
FROM tenants t
WHERE t.company_username = 'demo'
AND NOT EXISTS (
    SELECT 1 FROM tasks x WHERE x.tenant_id = t.id AND x.title = 'Morning Checklist'
);

INSERT INTO task_occurrences (tenant_id, task_id, due_date, due_time, completed_at, completed_by_user_id)
SELECT t.id, tk.id, CURDATE(), '09:00:00', NULL, NULL
FROM tenants t
INNER JOIN tasks tk ON tk.tenant_id = t.id AND tk.title = 'Morning Checklist'
WHERE t.company_username = 'demo'
AND NOT EXISTS (
    SELECT 1 FROM task_occurrences o
    WHERE o.tenant_id = t.id AND o.task_id = tk.id AND o.due_date = CURDATE()
);

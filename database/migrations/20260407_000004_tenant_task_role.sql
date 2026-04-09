-- Role between tenant_manager and tenant_user: can create/edit tasks from dashboard, no other admin.

ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'owner',
    'super_admin',
    'tenant_admin',
    'tenant_manager',
    'tenant_task',
    'tenant_user'
  ) NOT NULL;

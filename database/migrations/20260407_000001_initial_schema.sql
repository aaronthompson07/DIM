CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    company_username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    owner_email VARCHAR(190) NOT NULL,
    timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
    logo_url VARCHAR(255) NULL,
    theme_default ENUM('light', 'dark') NOT NULL DEFAULT 'light',
    trial_started_at DATETIME NOT NULL,
    trial_ends_at DATETIME NOT NULL,
    plan_key VARCHAR(50) NOT NULL DEFAULT 'basic_ads',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    role ENUM('owner', 'super_admin', 'tenant_admin', 'tenant_manager', 'tenant_task', 'tenant_user') NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    pin_lookup_hash CHAR(64) NOT NULL,
    failed_pin_attempts INT NOT NULL DEFAULT 0,
    pin_locked_until DATETIME NULL,
    user_theme_override ENUM('light', 'dark') NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_users_tenant_pin (tenant_id, pin_lookup_hash),
    INDEX idx_users_tenant_role (tenant_id, role),
    INDEX idx_users_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    details TEXT NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    priority_key VARCHAR(40) NOT NULL DEFAULT 'normal',
    recurrence_type ENUM('none', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'none',
    recurrence_weekday TINYINT UNSIGNED NULL,
    recurrence_day_of_month TINYINT UNSIGNED NULL,
    task_time TIME NOT NULL,
    is_infinity TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tasks_tenant_active (tenant_id, is_active),
    INDEX idx_tasks_tenant_infinity_active (tenant_id, is_infinity, is_active),
    INDEX idx_tasks_tenant_priority (tenant_id, priority_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_occurrences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    due_time TIME NOT NULL,
    completed_at DATETIME NULL,
    completed_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_occ_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_occ_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_occ_today_open (tenant_id, due_date, completed_at),
    INDEX idx_occ_task_date (task_id, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feature_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    monthly_price DECIMAL(10,2) NULL,
    show_ads TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_level_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id BIGINT UNSIGNED NOT NULL,
    feature_id BIGINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_slf_level FOREIGN KEY (level_id) REFERENCES subscription_levels(id) ON DELETE CASCADE,
    CONSTRAINT fk_slf_feature FOREIGN KEY (feature_id) REFERENCES feature_flags(id) ON DELETE CASCADE,
    UNIQUE KEY uq_slf_level_feature (level_id, feature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_tenant_created (tenant_id, created_at),
    INDEX idx_logs_action_created (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (feature_key, name, description) VALUES
('tasks.basic', 'Task Management', 'Create, edit, assign, and complete tasks'),
('tasks.reports', 'Reports', 'Access reports and charts'),
('chat.arrowchat', 'ArrowChat', 'Use in-app chat integration'),
('theme.custom', 'Theme Controls', 'Tenant and user theme controls'),
('ads.toggle', 'Ad-Free Experience', 'Disable ads for paid levels')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO subscription_levels (level_key, name, is_paid, monthly_price, show_ads, is_active) VALUES
('basic_ads', 'Basic (Ads)', 0, NULL, 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), show_ads = VALUES(show_ads), is_active = VALUES(is_active);

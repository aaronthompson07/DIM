-- Tenant support tickets (visible to tenant users; owner/super_admin see all tenants)
CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    attachment_storage_key VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    closed_by_user_id BIGINT UNSIGNED NULL,
    INDEX idx_support_tenant_status (tenant_id, status, updated_at DESC),
    INDEX idx_support_updated (updated_at DESC),
    CONSTRAINT fk_support_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_tickets_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_tickets_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    attachment_storage_key VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_support_msg_ticket (ticket_id, id),
    CONSTRAINT fk_support_msg_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.12', CURDATE(), 0, 'Changelog page now lists versions in proper release order (newest first) instead of sorting version numbers as plain text.'),
('1.0.12', CURDATE(), 1, 'Support: submit tickets with title, message, optional image, and priority; your company can view and reply on shared tenant tickets. Owner and super admin can open All tickets to see every company, reply, and close items. Ticket view updates on its own while you stay on the page.');

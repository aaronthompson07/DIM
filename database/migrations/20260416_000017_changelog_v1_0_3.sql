INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.3', CURDATE(), 0, 'Owner and super admin can publish global messages visible to all tenants.'),
('1.0.3', CURDATE(), 1, 'Tenant admins can still publish tenant-only notices for their own company.'),
('1.0.3', CURDATE(), 2, 'Maintenance mode is now global-only and enforces read-only for everyone except owner/super admin.');

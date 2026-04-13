INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.4', CURDATE(), 0, 'Tenant admins no longer see global or maintenance message options in Admin; only owner/super admin do.'),
('1.0.4', CURDATE(), 1, 'Dashboard periodic refresh keeps your page scroll position when system messages or weather alerts are shown.');

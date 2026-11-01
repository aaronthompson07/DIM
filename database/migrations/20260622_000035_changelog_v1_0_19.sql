INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.19', CURDATE(), 0, 'Fix appointment board database migrations (create missing table) and prevent admin/impersonation failures when the board schema is incomplete.');

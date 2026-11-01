INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.20', CURDATE(), 0, 'Appointment board: auto-repair missing database tables on each request; fix MySQL migration runner so DDL migrations apply reliably (board API and admin/impersonation).');

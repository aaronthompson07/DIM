INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.22', CURDATE(), 0, 'Fix false “maintenance mode” lock when database migrations partially applied; idempotent migration errors no longer disable writes after the appointment board schema is ready.');

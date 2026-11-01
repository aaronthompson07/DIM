INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.21', CURDATE(), 0, 'Appointment board table: create without foreign keys and match tenant/user id column types so MySQL errno 150 does not block setup on existing databases.');

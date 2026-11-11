INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.27', CURDATE(), 0, 'Added Holiday themes for platform owners: turn seasonal backgrounds off, leave them automatic by calendar, or force one holiday look for every company.'),
('1.0.27', CURDATE(), 1, 'Stored site-wide holiday theme mode in a new platform settings table so the owner can change themes for all clients from Admin.');

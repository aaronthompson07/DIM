INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.2', CURDATE(), 0, 'Added tenant system messages that display above weather alerts on the dashboard.'),
('1.0.2', CURDATE(), 1, 'Added maintenance-mode messages: most users become read-only while owner and super admin retain write access.'),
('1.0.2', CURDATE(), 2, 'Added Admin - Messages tab so tenant admins, super admins, and owners can publish and delete notices.');
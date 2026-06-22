INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.14', CURDATE(), 0, 'Admin: new Home overview with clear descriptions and shortcuts into each configuration area, plus links to support, task reports (when enabled), and cross-tenant tickets for owner/super admin.'),
('1.0.14', CURDATE(), 1, 'Dashboard header is slimmer: Support moved to the site footer after PIN sign-in; Reports and “all tickets” are reached from Admin → Home when your role and plan allow.'),
('1.0.14', CURDATE(), 2, 'Removed the standalone US federal holiday calendar page. Seasonal page backgrounds still use your company time zone for “today” around US federal holidays.');

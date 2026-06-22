INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.9', CURDATE(), 0, 'Dashboard: when your “Assigned to me” count goes up while the page is open (for example a teammate just assigned you work), the Assigned to me list opens so new items are easy to spot.'),
('1.0.9', CURDATE(), 1, 'Tasks: inactive tasks no longer appear on the open board or in Assigned to me; materializing today’s board can fall back to an inactive time-of-day column if every column is turned off so tasks still show up.');

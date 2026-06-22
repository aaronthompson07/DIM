INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.11', CURDATE(), 0, 'Fixed dashboard failing to load (empty board and no time-of-day columns): the task-board repair query now uses separate SQL parameters compatible with native database prepared statements.');

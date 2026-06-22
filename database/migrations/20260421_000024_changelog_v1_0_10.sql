INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.10', CURDATE(), 0, 'Fixed dashboard not showing some recurring infinity tasks (for example weekly on a set weekday): the app now materializes missing task rows the same way for both infinity and non-infinity tasks, so assignments appear after refresh without needing a special admin save day.');

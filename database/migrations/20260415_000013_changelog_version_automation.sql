-- Release 1.0.1: version file + agent-maintained changelog (see .cursor/rules/changelog-version.mdc)
INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.1', CURDATE(), 0, 'App version is read from the repo root VERSION file (optional APP_VERSION in .env overrides).'),
('1.0.1', CURDATE(), 1, 'Cursor agents bump VERSION and add a migration that INSERTs changelog_entries so you do not edit the database by hand.');

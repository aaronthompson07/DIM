INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.25', CURDATE(), 0, 'Added IIS support so the site runs on Plesk for Windows: clean URLs now work via a web.config (the equivalent of the Apache rewrite rules).'),
('1.0.25', CURDATE(), 1, 'Hardened hosting: a safety-net config blocks direct web access to secrets, source code, database migrations, and uploaded files if the document root is misconfigured.');

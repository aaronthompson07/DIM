-- Application changelog (version history) for the public /changelog page
CREATE TABLE IF NOT EXISTS changelog_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    released_at DATE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    change_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_changelog_released (released_at DESC, version),
    INDEX idx_changelog_version_order (version, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO changelog_entries (version, released_at, sort_order, change_text) VALUES
('1.0.0', CURDATE(), 0, 'Initial changelog: version is shown in the site footer with a link to this page.'),
('1.0.0', CURDATE(), 1, 'Release notes are stored in the database and can be extended with future migrations or admin tooling.');

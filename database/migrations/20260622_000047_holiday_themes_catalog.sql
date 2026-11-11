CREATE TABLE IF NOT EXISTS holiday_themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skin_key VARCHAR(64) NOT NULL,
    display_name VARCHAR(128) NOT NULL,
    description VARCHAR(255) NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holiday_themes_skin_key (skin_key),
    INDEX idx_holiday_themes_enabled_sort (is_enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO holiday_themes (skin_key, display_name, description, is_enabled, sort_order) VALUES
('new_year', 'New Year''s Day', 'Gold and midnight celebration tones', 1, 10),
('mlk', 'Martin Luther King Jr. Day', 'Purple and warm gold accents', 1, 20),
('presidents', 'Presidents'' Day', 'Red and blue civic tones', 1, 30),
('memorial', 'Memorial Day', 'Red and blue remembrance tones', 1, 40),
('juneteenth', 'Juneteenth', 'Red, green, and gold celebration tones', 1, 50),
('independence', 'Independence Day', 'Red, white, and blue fireworks tones', 1, 60),
('labor', 'Labor Day', 'Late-summer amber and blue', 1, 70),
('columbus', 'Columbus Day', 'Ocean blue and gold exploration tones', 1, 80),
('veterans', 'Veterans Day', 'Honoring red and navy tones', 1, 90),
('thanksgiving', 'Thanksgiving Day', 'Harvest amber and brown', 1, 100),
('christmas', 'Christmas Day', 'Evergreen, crimson, and soft gold', 1, 110)
ON DUPLICATE KEY UPDATE
    display_name = VALUES(display_name),
    description = VALUES(description),
    sort_order = VALUES(sort_order);

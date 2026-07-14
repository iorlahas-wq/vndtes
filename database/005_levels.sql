CREATE TABLE levels (
    level_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(20) NOT NULL UNIQUE,
    level_order TINYINT UNSIGNED NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO levels
(level_name, level_order)
VALUES
('ND I',1),
('ND II',2),
('HND I',3),
('HND II',4);
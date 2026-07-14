CREATE TABLE programme_types (
    programme_type_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    programme_name VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO programme_types
(programme_name, description)
VALUES
('ND','National Diploma'),
('HND','Higher National Diploma');
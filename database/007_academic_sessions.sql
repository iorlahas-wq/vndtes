CREATE TABLE academic_sessions (
    session_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    session_name VARCHAR(20) NOT NULL UNIQUE,

    is_current TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO academic_sessions (session_name, is_current)
VALUES
('2025/2026',0),
('2026/2027',1),
('2027/2028',0);
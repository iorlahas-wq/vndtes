CREATE TABLE scenarios (

    scenario_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    scenario_code VARCHAR(20) NOT NULL UNIQUE,

    scenario_title VARCHAR(200) NOT NULL,

    category ENUM(
        'Routing',
        'Switching',
        'Subnetting',
        'Wireless',
        'Security',
        'Network Services',
        'Mixed'
    ) NOT NULL,

    difficulty ENUM(
        'Beginner',
        'Intermediate',
        'Advanced'
    ) NOT NULL DEFAULT 'Beginner',

    estimated_time SMALLINT UNSIGNED DEFAULT 30,

    instructions TEXT NOT NULL,

    expected_outcome TEXT,

    status ENUM(
        'Draft',
        'Published',
        'Archived'
    ) DEFAULT 'Draft',

    created_by INT UNSIGNED NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_scenario_creator
        FOREIGN KEY (created_by)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
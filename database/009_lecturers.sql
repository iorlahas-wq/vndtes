CREATE TABLE lecturers (
    lecturer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    staff_id VARCHAR(30) NOT NULL UNIQUE,

    department_id SMALLINT UNSIGNED NOT NULL,

    designation VARCHAR(100) DEFAULT NULL,

    specialization VARCHAR(150) DEFAULT NULL,

    employment_status ENUM(
        'Active',
        'Sabbatical',
        'Retired',
        'Resigned',
        'Suspended'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lecturer_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_lecturer_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
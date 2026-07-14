CREATE TABLE students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    matric_no VARCHAR(30) NOT NULL UNIQUE,

    department_id SMALLINT UNSIGNED NOT NULL,

    programme_type_id TINYINT UNSIGNED NOT NULL,

    option_id SMALLINT UNSIGNED DEFAULT NULL,

    level_id TINYINT UNSIGNED NOT NULL,

    current_session_id SMALLINT UNSIGNED NOT NULL,

    admission_year YEAR NOT NULL,

    graduation_year YEAR DEFAULT NULL,

    current_semester ENUM('First','Second')
        NOT NULL DEFAULT 'First',

    academic_status ENUM(
        'Active',
        'SIWES',
        'Deferred',
        'Graduated',
        'Withdrawn',
        'Suspended'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_student_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_student_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_student_programme
        FOREIGN KEY (programme_type_id)
        REFERENCES programme_types(programme_type_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_student_option
        FOREIGN KEY (option_id)
        REFERENCES programme_options(option_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_student_level
        FOREIGN KEY (level_id)
        REFERENCES levels(level_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_student_session
        FOREIGN KEY (current_session_id)
        REFERENCES academic_sessions(session_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
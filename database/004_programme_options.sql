CREATE TABLE programme_options (
    option_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id SMALLINT UNSIGNED NOT NULL,
    programme_type_id TINYINT UNSIGNED NOT NULL,
    option_name VARCHAR(100) NOT NULL,
    option_code VARCHAR(20) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_option_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_option_programme
        FOREIGN KEY (programme_type_id)
        REFERENCES programme_types(programme_type_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE KEY uk_option_code (option_code)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO programme_options
(department_id, programme_type_id, option_name, option_code)
VALUES
(1,2,'Networking and Cloud Computing','NCC'),
(1,2,'Software and Web Development','SWD'),
(1,2,'Cybersecurity','CYS'),
(1,2,'Artificial Intelligence','AI');
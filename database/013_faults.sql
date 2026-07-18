CREATE TABLE faults (

    fault_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    fault_code VARCHAR(20) NOT NULL UNIQUE,

    fault_title VARCHAR(150) NOT NULL,

    category VARCHAR(100) NOT NULL,

    difficulty ENUM(
        'Beginner',
        'Intermediate',
        'Advanced'
    ) DEFAULT 'Beginner',

    affected_device_type VARCHAR(100) NOT NULL,

    symptoms TEXT,

    probable_cause TEXT,

    expected_solution TEXT,

    estimated_time INT DEFAULT 10,

    status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
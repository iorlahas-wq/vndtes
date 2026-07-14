CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    role_id TINYINT UNSIGNED NOT NULL,

    full_name VARCHAR(150) NOT NULL,

    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Matric No, Staff ID or Admin Username',

    email VARCHAR(150) DEFAULT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    password_reset_token VARCHAR(255) DEFAULT NULL,

    password_reset_expires DATETIME DEFAULT NULL,

    email_verified_at DATETIME DEFAULT NULL,

    profile_photo VARCHAR(255) NOT NULL DEFAULT 'default.png',

    account_status ENUM(
        'Active',
        'Pending',
        'Suspended'
    ) NOT NULL DEFAULT 'Active',

    last_login DATETIME DEFAULT NULL,

    remember_token VARCHAR(100) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
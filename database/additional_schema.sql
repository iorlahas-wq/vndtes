CREATE TABLE scenario_device_instances (
    instance_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    scenario_device_id INT(10) UNSIGNED NOT NULL,
    instance_name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    instance_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (instance_id),

    UNIQUE KEY uq_scenario_instance_name (
        scenario_device_id,
        instance_name
    ),

    KEY idx_instance_scenario_device (
        scenario_device_id
    ),

    CONSTRAINT fk_instance_scenario_device
        FOREIGN KEY (scenario_device_id)
        REFERENCES scenario_devices (scenario_device_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
  
  CREATE TABLE scenario_device_interfaces (
    interface_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id INT(10) UNSIGNED NOT NULL,
    interface_name VARCHAR(50) NOT NULL,
    interface_type VARCHAR(50) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    subnet_mask VARCHAR(45) DEFAULT NULL,
    mac_address VARCHAR(17) DEFAULT NULL,
    interface_status ENUM('Up','Down','Administratively Down') NOT NULL DEFAULT 'Down',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (interface_id),

    UNIQUE KEY uq_instance_interface (
        instance_id,
        interface_name
    ),

    KEY idx_interface_instance (
        instance_id
    ),

    CONSTRAINT fk_interface_instance
        FOREIGN KEY (instance_id)
        REFERENCES scenario_device_instances (instance_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
  
  CREATE TABLE scenario_connections (
    connection_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    interface_a_id INT(10) UNSIGNED NOT NULL,
    interface_b_id INT(10) UNSIGNED NOT NULL,
    connection_type ENUM('Ethernet','Serial','Wireless','Other') NOT NULL DEFAULT 'Ethernet',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (connection_id),

    UNIQUE KEY uq_connection_pair (
        interface_a_id,
        interface_b_id
    ),

    KEY idx_connection_interface_a (
        interface_a_id
    ),

    KEY idx_connection_interface_b (
        interface_b_id
    ),

    CONSTRAINT fk_connection_interface_a
        FOREIGN KEY (interface_a_id)
        REFERENCES scenario_device_interfaces (interface_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_connection_interface_b
        FOREIGN KEY (interface_b_id)
        REFERENCES scenario_device_interfaces (interface_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

  -- SCENARIO ATTEMPTS - STUDENT ATTEMPTS AT SCENARIOS
  CREATE TABLE scenario_attempts (
    attempt_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    scenario_id INT(10) UNSIGNED NOT NULL,
    attempt_number TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('In Progress','Completed','Submitted','Abandoned') NOT NULL DEFAULT 'In Progress',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    completed_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (attempt_id),

    KEY idx_attempt_student (student_id),
    KEY idx_attempt_scenario (scenario_id),
    KEY idx_attempt_status (status),

    CONSTRAINT fk_attempt_student
        FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_attempt_scenario
        FOREIGN KEY (scenario_id)
        REFERENCES scenarios(scenario_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- SCENARIO ATTEMPT ACTIONS - LOG OF ACTIONS TAKEN BY STUDENTS DURING SCENARIO ATTEMPTS
CREATE TABLE scenario_attempt_actions (
    action_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id INT(10) UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id INT(10) UNSIGNED NULL,
    action_data TEXT NULL,
    action_result TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (action_id),

    KEY idx_action_attempt (attempt_id),
    KEY idx_action_type (action_type),
    KEY idx_action_target (target_type, target_id),

    CONSTRAINT fk_action_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES scenario_attempts(attempt_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


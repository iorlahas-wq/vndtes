CREATE TABLE scenario_devices (

    scenario_device_id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    scenario_id INT(10) UNSIGNED NOT NULL,

    device_id INT(10) UNSIGNED NOT NULL,

    quantity TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,

    notes VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_scenario (scenario_id),

    INDEX idx_device (device_id),

    CONSTRAINT fk_scenario_devices_scenario
        FOREIGN KEY (scenario_id)
        REFERENCES scenarios (scenario_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_scenario_devices_device
        FOREIGN KEY (device_id)
        REFERENCES devices (device_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE scenario_devices
ADD CONSTRAINT uq_scenario_device
UNIQUE (scenario_id, device_id);
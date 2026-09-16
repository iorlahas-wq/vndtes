CREATE TABLE scenario_faults (

    scenario_fault_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    scenario_id INT UNSIGNED NOT NULL,

    fault_id INT UNSIGNED NOT NULL,

    display_order INT UNSIGNED NOT NULL DEFAULT 1,

    notes VARCHAR(255) DEFAULT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_scenario_faults_scenario (scenario_id),

    INDEX idx_scenario_faults_fault (fault_id),

    CONSTRAINT fk_scenario_faults_scenario
        FOREIGN KEY (scenario_id)
        REFERENCES scenarios (scenario_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_scenario_faults_fault
        FOREIGN KEY (fault_id)
        REFERENCES faults (fault_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_scenario_fault
        UNIQUE (scenario_id, fault_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- VNDTES: Scenario student-availability window
-- Run once before using the updated lecturer/scenario_add.php.
-- Existing scenarios remain valid: NULL means no lower/upper boundary.

ALTER TABLE scenarios
    ADD COLUMN available_from DATETIME NULL AFTER expected_outcome,
    ADD COLUMN available_until DATETIME NULL AFTER available_from;

CREATE INDEX idx_scenarios_student_availability
    ON scenarios (status, available_from, available_until);

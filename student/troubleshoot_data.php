<?php
declare(strict_types=1);

/**
 * VNDTES troubleshooting workspace data loader.
 *
 * Loads scenario devices, interfaces, master connections, student-created
 * connections, assigned faults and recent attempt activity.
 *
 * This function is intended to be included by student/troubleshoot.php.
 * It does not perform authorization; the calling page must verify that the
 * attempt belongs to the authenticated student before calling it.
 */
function loadTroubleshootData(int $scenarioId, int $attemptId): array
{
    if ($scenarioId <= 0 || $attemptId <= 0) {
        throw new InvalidArgumentException(
            'A valid scenario ID and troubleshooting attempt ID are required.'
        );
    }

    $pdo = db();

    /*
     * Scenario devices
     */
    $stmt = $pdo->prepare("
        SELECT
            sd.scenario_device_id,
            sd.quantity,
            sd.notes,
            d.device_id,
            d.device_code,
            d.device_name,
            d.device_type,
            d.vendor,
            d.model
        FROM scenario_devices AS sd
        INNER JOIN devices AS d
            ON d.device_id = sd.device_id
        WHERE sd.scenario_id = ?
        ORDER BY d.device_type ASC, d.device_name ASC
    ");
    $stmt->execute([$scenarioId]);
    $scenarioDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Scenario interfaces
     */
    $stmt = $pdo->prepare("
        SELECT
            sdi.interface_id,
            sdi.instance_id,
            sdi.interface_name,
            sdi.interface_type,
            sdi.ip_address,
            sdi.subnet_mask,
            sdi.mac_address,
            sdi.interface_status,
            sdi.notes,
            sdi_instance.scenario_device_id,
            d.device_id,
            d.device_code,
            d.device_name,
            d.device_type
        FROM scenario_device_interfaces AS sdi
        INNER JOIN scenario_device_instances AS sdi_instance
            ON sdi_instance.instance_id = sdi.instance_id
        INNER JOIN scenario_devices AS sd
            ON sd.scenario_device_id = sdi_instance.scenario_device_id
        INNER JOIN devices AS d
            ON d.device_id = sd.device_id
        WHERE sd.scenario_id = ?
        ORDER BY d.device_name ASC, sdi.interface_id ASC
    ");
    $stmt->execute([$scenarioId]);
    $scenarioInterfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Master scenario connections.
     * Both endpoints are joined back to the scenario to prevent unrelated
     * connection records from being included in this workspace.
     */
    $stmt = $pdo->prepare("
        SELECT
            sc.connection_id,
            sc.connection_type,
            sc.status,
            sc.notes,
            ia.interface_id AS interface_a_id,
            ia.interface_name AS interface_a_name,
            ib.interface_id AS interface_b_id,
            ib.interface_name AS interface_b_name,
            da.device_name AS device_a_name,
            da.device_code AS device_a_code,
            db.device_name AS device_b_name,
            db.device_code AS device_b_code
        FROM scenario_connections AS sc
        INNER JOIN scenario_device_interfaces AS ia
            ON ia.interface_id = sc.interface_a_id
        INNER JOIN scenario_device_instances AS inst_a
            ON inst_a.instance_id = ia.instance_id
        INNER JOIN scenario_devices AS sd_a
            ON sd_a.scenario_device_id = inst_a.scenario_device_id
        INNER JOIN devices AS da
            ON da.device_id = sd_a.device_id
        INNER JOIN scenario_device_interfaces AS ib
            ON ib.interface_id = sc.interface_b_id
        INNER JOIN scenario_device_instances AS inst_b
            ON inst_b.instance_id = ib.instance_id
        INNER JOIN scenario_devices AS sd_b
            ON sd_b.scenario_device_id = inst_b.scenario_device_id
        INNER JOIN devices AS db
            ON db.device_id = sd_b.device_id
        WHERE sd_a.scenario_id = ?
          AND sd_b.scenario_id = ?
        ORDER BY sc.connection_id ASC
    ");
    $stmt->execute([$scenarioId, $scenarioId]);
    $scenarioConnections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Active student-created connections for this attempt
     */
    $stmt = $pdo->prepare("
        SELECT
            sac.student_connection_id,
            sac.interface_a_id,
            sac.interface_b_id,
            sac.connection_type,
            sac.status
        FROM scenario_attempt_connections AS sac
        WHERE sac.attempt_id = ?
          AND sac.status = 'Active'
        ORDER BY sac.student_connection_id ASC
    ");
    $stmt->execute([$attemptId]);
    $studentTopologyConnections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Active faults assigned to this scenario and available for diagnosis
     */
    $stmt = $pdo->prepare("
        SELECT
            f.fault_id,
            f.fault_code,
            f.fault_title,
            f.category,
            f.difficulty,
            f.affected_device_type,
            f.symptoms,
            f.estimated_time
        FROM scenario_faults AS sf
        INNER JOIN faults AS f
            ON f.fault_id = sf.fault_id
        WHERE sf.scenario_id = ?
          AND sf.is_active = 1
          AND f.status = 'Active'
        ORDER BY f.category ASC, f.fault_title ASC
    ");
    $stmt->execute([$scenarioId]);
    $assignedFaults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Recent student activity. The system-only target fault marker is
     * intentionally excluded from the visible activity history.
     */
    $stmt = $pdo->prepare("
        SELECT
            action_id,
            action_type,
            target_type,
            target_id,
            action_data,
            action_result,
            created_at
        FROM scenario_attempt_actions
        WHERE attempt_id = ?
          AND action_type <> 'SYSTEM_FAULT_TARGET'
        ORDER BY action_id DESC
        LIMIT 50
    ");
    $stmt->execute([$attemptId]);
    $activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Preserve the existing last-activity update. The caller is responsible
     * for confirming attempt ownership before invoking this loader.
     */
    $stmt = $pdo->prepare("
        UPDATE scenario_attempts
        SET last_activity_at = NOW()
        WHERE attempt_id = ?
          AND status = 'In Progress'
    ");
    $stmt->execute([$attemptId]);

    return [
        'scenarioDevices' => $scenarioDevices,
        'scenarioInterfaces' => $scenarioInterfaces,
        'scenarioConnections' => $scenarioConnections,
        'studentTopologyConnections' => $studentTopologyConnections,
        'assignedFaults' => $assignedFaults,
        'activityLog' => $activityLog,
    ];
}

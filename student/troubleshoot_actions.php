<?php

declare(strict_types=1);

/**
 * Phase 2 extraction from student/troubleshoot.php.
 *
 * Refactored troubleshooting POST handler. Existing action names, response
 * keys and database tables are retained. This is a rule-based simulation;
 * it records actions but does not alter real network-device configuration.
 */
/** Return a consistent response when a request is rejected early. */
function troubleshootActionResponse(string $message, string $messageType = 'info'): array
{
    return [
        'message' => $message,
        'messageType' => $messageType,
        'selectedDevice' => null,
        'selectedInterface' => null,
        'selectedConnection' => null,
        'selectedActionResult' => null,
        'selectedDiagnosis' => null,
        'verificationResult' => null,
    ];
}

/** Encode action payloads safely for storage as JSON. */
function troubleshootEncodeActionPayload(array $payload): string
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return $json === false ? '{}' : $json;
}

function handleTroubleshootPost(
    array &$attempt,
    int $attemptId,
    int $scenarioId,
    int $studentId,
    ?array $targetFault,
    int $targetFaultId
): array {
    $message = null;
    $messageType = 'info';

    // Validate the attempt context before accepting any student action.
    if ($attemptId < 1 || $scenarioId < 1 || $studentId < 1) {
        return troubleshootActionResponse('Invalid troubleshooting session.', 'danger');
    }

    if ((int) ($attempt['attempt_id'] ?? 0) !== $attemptId
        || (int) ($attempt['student_id'] ?? 0) !== $studentId
        || (int) ($attempt['scenario_id'] ?? 0) !== $scenarioId) {
        return troubleshootActionResponse('The troubleshooting attempt could not be validated.', 'danger');
    }

    if (($attempt['status'] ?? '') !== 'In Progress') {
        return troubleshootActionResponse('This attempt is no longer editable.', 'warning');
    }

    $selectedDevice = null;
    $selectedInterface = null;
    $selectedConnection = null;
    $selectedActionResult = null;
    $selectedDiagnosis = null;
    $verificationResult = null;



    $actionType = trim((string) ($_POST['action_type'] ?? ''));
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $correctionCode = trim((string) ($_POST['correction_code'] ?? ''));

    $allowedActions = [
        'inspect_device',
        'inspect_interface',
        'inspect_connection',
        'check_ip_configuration',
        'check_interface_status',
        'check_connectivity',
        'check_routing',
        'check_vlan_configuration',
        'check_dhcp_configuration',
        'check_wireless_configuration',
        'check_device_configuration',
        'diagnose_fault',
        'apply_correction',
        'verify_solution',
        'submit_attempt'
    ];

    if (!in_array($actionType, $allowedActions, true)) {
        $message = 'Invalid troubleshooting action.';
        $messageType = 'danger';
    } else {

        /* ---------------------------------------------------------------
           Helper: record an action
        ---------------------------------------------------------------- */
        $recordAction = function (
            string $type,
            string $target,
            ?int $id,
            array $data,
            array $result
        ) use ($attemptId): void {
            $stmt = db()->prepare("
                INSERT INTO scenario_attempt_actions
                (
                    attempt_id,
                    action_type,
                    target_type,
                    target_id,
                    action_data,
                    action_result
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $attemptId,
                $type,
                $target,
                $id,
                troubleshootEncodeActionPayload($data),
                troubleshootEncodeActionPayload($result)
            ]);
        };

        /* ---------------------------------------------------------------
           Existing inspection actions
        ---------------------------------------------------------------- */
        if ($actionType === 'inspect_device') {
            $stmt = db()->prepare("
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
                FROM scenario_devices sd
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_device_id = ?
                  AND sd.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$device) {
                $message = 'The selected device could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_DEVICE',
                    'scenario_device',
                    $targetId,
                    [
                        'device_code' => $device['device_code'],
                        'device_name' => $device['device_name'],
                        'device_type' => $device['device_type']
                    ],
                    ['result' => 'Device information inspected.']
                );

                $selectedDevice = $device;
                $message = 'Device information loaded successfully.';
                $messageType = 'success';
            }
        }

        if ($actionType === 'inspect_interface') {
            $stmt = db()->prepare("
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
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sdi.interface_id = ?
                  AND sd.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId]);
            $interface = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$interface) {
                $message = 'The selected interface could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_INTERFACE',
                    'interface',
                    $targetId,
                    [
                        'interface_id' => $interface['interface_id'],
                        'interface_name' => $interface['interface_name'],
                        'device_id' => $interface['device_id']
                    ],
                    ['result' => 'Interface information inspected.']
                );

                $selectedInterface = $interface;
                $message = 'Interface information loaded successfully.';
                $messageType = 'success';
            }
        }

        if ($actionType === 'inspect_connection') {
            $stmt = db()->prepare("
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
                FROM scenario_connections sc
                INNER JOIN scenario_device_interfaces ia
                    ON ia.interface_id = sc.interface_a_id
                INNER JOIN scenario_device_instances inst_a
                    ON inst_a.instance_id = ia.instance_id
                INNER JOIN scenario_devices sd_a
                    ON sd_a.scenario_device_id = inst_a.scenario_device_id
                INNER JOIN devices da ON da.device_id = sd_a.device_id
                INNER JOIN scenario_device_interfaces ib
                    ON ib.interface_id = sc.interface_b_id
                INNER JOIN scenario_device_instances inst_b
                    ON inst_b.instance_id = ib.instance_id
                INNER JOIN scenario_devices sd_b
                    ON sd_b.scenario_device_id = inst_b.scenario_device_id
                INNER JOIN devices db ON db.device_id = sd_b.device_id
                WHERE sc.connection_id = ?
                  AND sd_a.scenario_id = ?
                  AND sd_b.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId, $scenarioId]);
            $connection = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$connection) {
                $message = 'The selected network connection could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_CONNECTION',
                    'connection',
                    $targetId,
                    [
                        'connection_id' => $connection['connection_id'],
                        'connection_type' => $connection['connection_type']
                    ],
                    ['result' => 'Network connection inspected.']
                );

                $selectedConnection = $connection;
                $message = 'Network connection information inspected successfully.';
                $messageType = 'success';
            }
        }

        /* ---------------------------------------------------------------
           Investigation checks
        ---------------------------------------------------------------- */
        if ($actionType === 'check_ip_configuration') {
            $stmt = db()->prepare("
                SELECT
                    sdi.interface_name,
                    d.device_name,
                    sdi.ip_address,
                    sdi.subnet_mask
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_id = ?
                ORDER BY d.device_name, sdi.interface_id
            ");
            $stmt->execute([$scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $findings[] = sprintf(
                    '%s / %s: IP %s, subnet %s.',
                    $row['device_name'],
                    $row['interface_name'],
                    $row['ip_address'] ?: 'not configured',
                    $row['subnet_mask'] ?: 'not configured'
                );
            }

            $selectedActionResult = [
                'title' => 'IP Configuration Check',
                'icon' => 'bi-pc-display',
                'result' => empty($findings)
                    ? 'No interface addressing information is available.'
                    : 'The current interface addressing information has been checked.',
                'findings' => $findings,
                'next' => 'Compare the addresses and subnet masks with the scenario requirements before choosing a diagnosis.'
            ];

            $recordAction(
                'CHECK_IP_CONFIGURATION',
                'scenario',
                $scenarioId,
                [],
                $selectedActionResult
            );
            $message = 'IP configuration check completed.';
            $messageType = 'success';
        }

        if ($actionType === 'check_interface_status') {
            $stmt = db()->prepare("
                SELECT
                    sdi.interface_name,
                    d.device_name,
                    sdi.interface_status
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_id = ?
                ORDER BY d.device_name, sdi.interface_id
            ");
            $stmt->execute([$scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $findings[] = sprintf(
                    '%s / %s: interface status is %s.',
                    $row['device_name'],
                    $row['interface_name'],
                    $row['interface_status'] ?: 'unknown'
                );
            }

            $selectedActionResult = [
                'title' => 'Interface Status Check',
                'icon' => 'bi-ethernet',
                'result' => empty($findings)
                    ? 'No interface status information is available.'
                    : 'Interface operational states have been checked.',
                'findings' => $findings,
                'next' => 'Look for an interface that is Down or Administratively Down and compare it with the fault symptoms.'
            ];

            $recordAction('CHECK_INTERFACE_STATUS', 'scenario', $scenarioId, [], $selectedActionResult);
            $message = 'Interface status check completed.';
            $messageType = 'success';
        }

        if ($actionType === 'check_connectivity') {
            $stmt = db()->prepare("
                SELECT
                    sc.connection_type,
                    sc.status AS connection_status,
                    ia.interface_name AS interface_a,
                    ib.interface_name AS interface_b,
                    da.device_name AS device_a,
                    db.device_name AS device_b,
                    ia.interface_status AS interface_a_status,
                    ib.interface_status AS interface_b_status
                FROM scenario_connections sc
                INNER JOIN scenario_device_interfaces ia ON ia.interface_id = sc.interface_a_id
                INNER JOIN scenario_device_instances inst_a ON inst_a.instance_id = ia.instance_id
                INNER JOIN scenario_devices sd_a ON sd_a.scenario_device_id = inst_a.scenario_device_id
                INNER JOIN devices da ON da.device_id = sd_a.device_id
                INNER JOIN scenario_device_interfaces ib ON ib.interface_id = sc.interface_b_id
                INNER JOIN scenario_device_instances inst_b ON inst_b.instance_id = ib.instance_id
                INNER JOIN scenario_devices sd_b ON sd_b.scenario_device_id = inst_b.scenario_device_id
                INNER JOIN devices db ON db.device_id = sd_b.device_id
                WHERE sd_a.scenario_id = ?
                  AND sd_b.scenario_id = ?
                ORDER BY sc.connection_id
            ");
            $stmt->execute([$scenarioId, $scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $link = ($row['connection_status'] === 'Active'
                    && $row['interface_a_status'] === 'Up'
                    && $row['interface_b_status'] === 'Up')
                    ? 'link appears operational'
                    : 'link requires attention';

                $findings[] = sprintf(
                    '%s ↔ %s: %s (%s).',
                    $row['device_a'],
                    $row['device_b'],
                    $link,
                    $row['connection_type']
                );
            }

            $selectedActionResult = [
                'title' => 'Connectivity Test',
                'icon' => 'bi-broadcast-pin',
                'result' => empty($findings)
                    ? 'No network connections are available for testing.'
                    : 'The configured links and endpoint interface states have been checked.',
                'findings' => $findings,
                'next' => 'A working link does not prove that the whole network is correctly configured. Continue with addressing or configuration checks.'
            ];

            $recordAction('CHECK_CONNECTIVITY', 'scenario', $scenarioId, [], $selectedActionResult);
            $message = 'Connectivity test completed.';
            $messageType = 'success';
        }

        $genericChecks = [
            'check_routing' => [
                'title' => 'Routing Check',
                'icon' => 'bi-signpost-split',
                'result' => 'Routing is an investigation step for determining whether traffic has a valid path between networks.',
                'findings' => [
                    'The current VNDTES data model does not store a separate routing-table state for the student to inspect directly.',
                    'Use the topology, interface addressing and scenario fault symptoms to determine whether routing is involved.'
                ],
                'next' => 'If the scenario concerns different IP networks, inspect the relevant router interfaces and routing-related fault descriptions.'
            ],
            'check_vlan_configuration' => [
                'title' => 'VLAN Configuration Check',
                'icon' => 'bi-diagram-3',
                'result' => 'VLAN checking is used to determine whether switching segmentation is contributing to the connectivity problem.',
                'findings' => [
                    'The current VNDTES data model does not store a separate VLAN runtime configuration table.',
                    'Use the scenario topology and switching-related fault information as the evidence for this check.'
                ],
                'next' => 'For VLAN scenarios, inspect the switch-related information and compare it with the stated exercise requirements.'
            ],
            'check_dhcp_configuration' => [
                'title' => 'DHCP Configuration Check',
                'icon' => 'bi-hdd-network',
                'result' => 'DHCP checking is used to determine whether automatic address assignment is contributing to the problem.',
                'findings' => [
                    'The current VNDTES data model does not store a separate DHCP service runtime table.',
                    'Use interface addressing information and the scenario fault symptoms as evidence.'
                ],
                'next' => 'For a DHCP scenario, compare the observed addressing information with the expected automatic-assignment behaviour.'
            ],
            'check_wireless_configuration' => [
                'title' => 'Wireless Configuration Check',
                'icon' => 'bi-wifi',
                'result' => 'Wireless checking is used to determine whether the wireless portion of the topology is contributing to the fault.',
                'findings' => [
                    'The current VNDTES data model does not store a separate wireless runtime configuration table.',
                    'Use the wireless devices, topology and fault symptoms as evidence.'
                ],
                'next' => 'For a wireless scenario, inspect the access point or wireless router and compare the observed topology with the exercise requirements.'
            ],
            'check_device_configuration' => [
                'title' => 'Device Configuration Check',
                'icon' => 'bi-terminal',
                'result' => 'Device configuration checking is used to determine whether the device itself is contributing to the fault.',
                'findings' => [
                    'Inspect the device record and its connected interfaces before selecting a diagnosis.',
                    'Use the fault symptoms and affected device type to narrow down the likely problem.'
                ],
                'next' => 'After gathering enough evidence, move to Diagnosis and select the fault you believe is present.'
            ]
        ];

        if (isset($genericChecks[$actionType])) {
            $selectedActionResult = $genericChecks[$actionType];
            $recordAction(strtoupper($actionType), 'scenario', $scenarioId, [], $selectedActionResult);
            $message = $selectedActionResult['title'] . ' completed.';
            $messageType = 'success';
        }

        /* ---------------------------------------------------------------
           Diagnosis
        ---------------------------------------------------------------- */
        if ($actionType === 'diagnose_fault') {
            $diagnosisFaultId = (int) ($_POST['fault_id'] ?? 0);

            $stmt = db()->prepare("
                SELECT
                    f.fault_id,
                    f.fault_code,
                    f.fault_title,
                    f.category,
                    f.difficulty,
                    f.affected_device_type
                FROM scenario_faults sf
                INNER JOIN faults f ON f.fault_id = sf.fault_id
                WHERE sf.scenario_id = ?
                  AND sf.fault_id = ?
                  AND sf.is_active = 1
                  AND f.status = 'Active'
                LIMIT 1
            ");
            $stmt->execute([$scenarioId, $diagnosisFaultId]);
            $diagnosisFault = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$diagnosisFault) {
                $message = 'Please select a valid fault from this scenario.';
                $messageType = 'danger';
            } else {
                $isCorrect = $targetFault
                    && (int) $targetFault['fault_id'] === $diagnosisFaultId;

                $diagnosisResult = [
                    'result' => $isCorrect
                        ? 'Diagnosis accepted. The selected fault matches the simulated problem.'
                        : 'Diagnosis not accepted. The selected fault does not match the simulated problem. Review your investigation findings and try again.',
                    'correct' => $isCorrect,
                    'fault_code' => $diagnosisFault['fault_code'],
                    'fault_title' => $diagnosisFault['fault_title']
                ];

                $recordAction(
                    'DIAGNOSE_FAULT',
                    'fault',
                    $diagnosisFaultId,
                    [
                        'fault_code' => $diagnosisFault['fault_code'],
                        'fault_title' => $diagnosisFault['fault_title']
                    ],
                    $diagnosisResult
                );

                $selectedDiagnosis = [
                    'fault_id' => $diagnosisFaultId,
                    'fault_code' => $diagnosisFault['fault_code'],
                    'fault_title' => $diagnosisFault['fault_title'],
                    'correct' => $isCorrect
                ];

                $selectedActionResult = [
                    'title' => $isCorrect ? 'Diagnosis Accepted' : 'Diagnosis Needs Review',
                    'icon' => $isCorrect ? 'bi-check-circle' : 'bi-arrow-repeat',
                    'result' => $diagnosisResult['result'],
                    'findings' => [
                        'Selected fault: ' . $diagnosisFault['fault_code'] . ' — ' . $diagnosisFault['fault_title']
                    ],
                    'next' => $isCorrect
                        ? 'Proceed to Apply Correction, then run Verify Solution.'
                        : 'Return to the investigation checks, review the evidence, and select another diagnosis.'
                ];

                $message = $isCorrect
                    ? 'Correct diagnosis. You can now apply the correction.'
                    : 'Diagnosis needs review.';
                $messageType = $isCorrect ? 'success' : 'warning';
            }
        }

        /* ---------------------------------------------------------------
           Correction
        ---------------------------------------------------------------- */
        if ($actionType === 'apply_correction') {
            $stmt = db()->prepare("
                SELECT action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'DIAGNOSE_FAULT'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $diagnosisRecord = $stmt->fetchColumn();
            $diagnosisData = $diagnosisRecord
                ? json_decode((string) $diagnosisRecord, true)
                : null;

            if (empty($diagnosisData['correct'])) {
                $message = 'A correct diagnosis is required before applying a correction.';
                $messageType = 'warning';
            } else {
                $validCodes = [
                    'correct_ip_configuration',
                    'correct_interface_status',
                    'correct_connectivity',
                    'correct_routing_configuration',
                    'correct_vlan_configuration',
                    'correct_dhcp_configuration',
                    'correct_wireless_configuration',
                    'correct_device_configuration'
                ];

                if (!in_array($correctionCode, $validCodes, true)) {
                    $message = 'Please select a valid corrective action.';
                    $messageType = 'danger';
                } else {
                    $correctionLabels = [
                        'correct_ip_configuration' => 'Correct IP address and subnet configuration',
                        'correct_interface_status' => 'Restore the affected interface to the required operational state',
                        'correct_connectivity' => 'Restore the affected network connection',
                        'correct_routing_configuration' => 'Correct the routing configuration',
                        'correct_vlan_configuration' => 'Correct the VLAN or switching configuration',
                        'correct_dhcp_configuration' => 'Correct the DHCP or automatic addressing configuration',
                        'correct_wireless_configuration' => 'Correct the wireless configuration',
                        'correct_device_configuration' => 'Correct the affected device configuration'
                    ];

                    $selectedActionResult = [
                        'title' => 'Correction Applied',
                        'icon' => 'bi-wrench-adjustable-circle',
                        'result' => 'The selected correction has been recorded as an action in the simulated troubleshooting environment.',
                        'findings' => [
                            $correctionLabels[$correctionCode]
                        ],
                        'next' => 'Run Verify Solution to test whether the diagnosed problem has been resolved.'
                    ];

                    $recordAction(
                        'APPLY_CORRECTION',
                        'fault',
                        $targetFaultId > 0 ? $targetFaultId : null,
                        [
                            'correction_code' => $correctionCode,
                            'correction_label' => $correctionLabels[$correctionCode]
                        ],
                        $selectedActionResult
                    );

                    $message = 'Correction recorded. Verify the solution now.';
                    $messageType = 'success';
                }
            }
        }

        /* ---------------------------------------------------------------
           Verification
        ---------------------------------------------------------------- */
        if ($actionType === 'verify_solution') {
            $stmt = db()->prepare("
                SELECT action_id, action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'DIAGNOSE_FAULT'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $diagnosisRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            $diagnosisData = $diagnosisRecord
                ? json_decode((string) $diagnosisRecord['action_result'], true)
                : null;

            $stmt = db()->prepare("
                SELECT action_id, action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'APPLY_CORRECTION'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $correctionRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            $correctionData = $correctionRecord
                ? json_decode((string) $correctionRecord['action_result'], true)
                : null;

            $verified = !empty($diagnosisData['correct']) && $correctionRecord;

            $verificationResult = [
                'verified' => $verified,
                'title' => $verified ? 'Solution Verified' : 'Verification Failed',
                'result' => $verified
                    ? 'The diagnosis and corrective action completed the rule-based troubleshooting path for this attempt.'
                    : 'The problem has not been verified as resolved. A correct diagnosis and recorded corrective action are required.',
                'next' => $verified
                    ? 'You can now submit the troubleshooting attempt.'
                    : 'Return to Diagnosis, correct the diagnosis if necessary, apply a correction, and verify again.'
            ];

            $recordAction(
                'VERIFY_SOLUTION',
                'scenario',
                $scenarioId,
                [],
                $verificationResult
            );

            $selectedActionResult = [
                'title' => $verificationResult['title'],
                'icon' => $verified ? 'bi-check2-circle' : 'bi-x-circle',
                'result' => $verificationResult['result'],
                'findings' => [
                    $verified
                        ? 'Correct diagnosis recorded.'
                        : 'A correct diagnosis and corrective action were not both confirmed.'
                ],
                'next' => $verificationResult['next']
            ];

            $message = $verified
                ? 'Solution verified. Submit the attempt.'
                : 'Verification did not confirm a resolved fault.';
            $messageType = $verified ? 'success' : 'warning';
        }

        /* ---------------------------------------------------------------
           Submission
        ---------------------------------------------------------------- */
        if ($actionType === 'submit_attempt') {
            $stmt = db()->prepare("
                SELECT action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'VERIFY_SOLUTION'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $verificationRecord = $stmt->fetchColumn();
            $verificationData = $verificationRecord
                ? json_decode((string) $verificationRecord, true)
                : null;

            if (empty($verificationData['verified'])) {
                $message = 'Verify the solution successfully before submitting the attempt.';
                $messageType = 'warning';
            } else {
                $assessment = [
                    'score' => 100,
                    'status' => 'Passed',
                    'reason' => 'The student identified the simulated fault, applied a corrective action and verified the solution.'
                ];

                $recordAction(
                    'SUBMIT_ATTEMPT',
                    'attempt',
                    $attemptId,
                    [],
                    $assessment
                );

                $stmt = db()->prepare("
                    UPDATE scenario_attempts
                    SET
                        status = 'Submitted',
                        submitted_at = NOW(),
                        completed_at = NOW(),
                        last_activity_at = NOW()
                    WHERE attempt_id = ?
                      AND student_id = ?
                ");
                $stmt->execute([$attemptId, $studentId]);

                $attempt['status'] = 'Submitted';

                $message = 'Troubleshooting attempt submitted successfully.';
                $messageType = 'success';
            }
        }
    }

    return [
        'message' => $message,
        'messageType' => $messageType,
        'selectedDevice' => $selectedDevice,
        'selectedInterface' => $selectedInterface,
        'selectedConnection' => $selectedConnection,
        'selectedActionResult' => $selectedActionResult,
        'selectedDiagnosis' => $selectedDiagnosis,
        'verificationResult' => $verificationResult,
    ];
}

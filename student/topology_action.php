<?php
declare(strict_types=1);

/**
 * VNDTES student topology actions.
 *
 * Handles creation and removal of student-created links for an active
 * troubleshooting attempt. Master scenario connections are read-only.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function topologyJson(bool $success, string $message, array $extra = [], int $statusCode = 200): never
{
    http_response_code($statusCode);

    $payload = array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    echo $json !== false
        ? $json
        : '{"success":false,"message":"Unable to encode response."}';
    exit;
}

function topologyReadRequest(): array
{
    $input = $_POST;

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($input === [] && str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    return $input;
}

/**
 * Load an interface only when it belongs to the specified scenario.
 */
function loadTopologyInterface(int $interfaceId, int $scenarioId): ?array
{
    if ($interfaceId <= 0 || $scenarioId <= 0) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            sdi.interface_id,
            sdi.instance_id,
            sdi.interface_name,
            sdi.interface_type,
            sdi.interface_status,
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
        WHERE sdi.interface_id = ?
          AND sd.scenario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$interfaceId, $scenarioId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Append an auditable action to the student's attempt.
 */
function recordTopologyAction(
    PDO $pdo,
    int $attemptId,
    string $actionType,
    int $targetId,
    array $actionData,
    array $actionResult
): void {
    $stmt = $pdo->prepare("
        INSERT INTO scenario_attempt_actions
            (attempt_id, action_type, target_type, target_id, action_data, action_result)
        VALUES
            (?, ?, 'student_connection', ?, ?, ?)
    ");

    $stmt->execute([
        $attemptId,
        $actionType,
        $targetId,
        json_encode($actionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        json_encode($actionResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    topologyJson(false, 'Only POST requests are allowed.', [], 405);
}

if (currentUserRole() !== 'Student') {
    topologyJson(false, 'Student access is required.', [], 403);
}

$input = topologyReadRequest();

$action = trim((string) ($input['action'] ?? ''));
$attemptId = filter_var($input['attempt_id'] ?? null, FILTER_VALIDATE_INT);
$interfaceA = filter_var($input['interface_a_id'] ?? null, FILTER_VALIDATE_INT);
$interfaceB = filter_var($input['interface_b_id'] ?? null, FILTER_VALIDATE_INT);
$connectionId = filter_var($input['student_connection_id'] ?? null, FILTER_VALIDATE_INT);
$connectionType = trim((string) ($input['connection_type'] ?? 'Ethernet'));

$attemptId = is_int($attemptId) ? $attemptId : 0;
$interfaceA = is_int($interfaceA) ? $interfaceA : 0;
$interfaceB = is_int($interfaceB) ? $interfaceB : 0;
$connectionId = is_int($connectionId) ? $connectionId : 0;

if ($attemptId <= 0 || $action === '') {
    topologyJson(false, 'Invalid topology request.', [], 422);
}

$allowedConnectionTypes = ['Ethernet', 'Serial', 'Wireless', 'Other'];
if (!in_array($connectionType, $allowedConnectionTypes, true)) {
    topologyJson(false, 'Invalid connection type.', [], 422);
}

$pdo = db();

try {
    // Resolve the application student record from the authenticated user.
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM students
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) currentUserId()]);
    $studentId = (int) $stmt->fetchColumn();

    if ($studentId <= 0) {
        topologyJson(false, 'Student record could not be resolved.', [], 403);
    }

    // Ownership and editability are checked on every request.
    $stmt = $pdo->prepare("
        SELECT attempt_id, student_id, scenario_id, status
        FROM scenario_attempts
        WHERE attempt_id = ?
          AND student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$attemptId, $studentId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        topologyJson(false, 'The troubleshooting attempt could not be found.', [], 404);
    }

    if (($attempt['status'] ?? '') !== 'In Progress') {
        topologyJson(false, 'This troubleshooting attempt is no longer editable.', [], 409);
    }

    $scenarioId = (int) $attempt['scenario_id'];

    if ($action === 'create_connection') {
        if ($interfaceA <= 0 || $interfaceB <= 0) {
            topologyJson(false, 'Both interface ports are required.', [], 422);
        }

        if ($interfaceA === $interfaceB) {
            topologyJson(false, 'An interface cannot be connected to itself.', [], 422);
        }

        $a = loadTopologyInterface($interfaceA, $scenarioId);
        $b = loadTopologyInterface($interfaceB, $scenarioId);

        if (!$a || !$b) {
            topologyJson(false, 'One or both selected interfaces are not valid for this scenario.', [], 422);
        }

        // Prevent an interface from being connected to itself and avoid
        // duplicate active student links, regardless of endpoint order.
        $stmt = $pdo->prepare("
            SELECT student_connection_id
            FROM scenario_attempt_connections
            WHERE attempt_id = ?
              AND status = 'Active'
              AND (
                    (interface_a_id = ? AND interface_b_id = ?)
                 OR (interface_a_id = ? AND interface_b_id = ?)
              )
            LIMIT 1
        ");
        $stmt->execute([$attemptId, $interfaceA, $interfaceB, $interfaceB, $interfaceA]);
        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            topologyJson(true, 'This student connection already exists.', [
                'student_connection_id' => $existingId,
                'interface_a_id' => $interfaceA,
                'interface_b_id' => $interfaceB,
                'connection_type' => $connectionType,
                'already_exists' => true,
            ]);
        }

        // Master scenario links are immutable from the student workspace.
        // Scope the check to the current scenario through both endpoint joins.
        $stmt = $pdo->prepare("
            SELECT sc.connection_id
            FROM scenario_connections AS sc
            INNER JOIN scenario_device_interfaces AS ia
                ON ia.interface_id = sc.interface_a_id
            INNER JOIN scenario_device_instances AS inst_a
                ON inst_a.instance_id = ia.instance_id
            INNER JOIN scenario_devices AS sd_a
                ON sd_a.scenario_device_id = inst_a.scenario_device_id
            INNER JOIN scenario_device_interfaces AS ib
                ON ib.interface_id = sc.interface_b_id
            INNER JOIN scenario_device_instances AS inst_b
                ON inst_b.instance_id = ib.instance_id
            INNER JOIN scenario_devices AS sd_b
                ON sd_b.scenario_device_id = inst_b.scenario_device_id
            WHERE sc.status = 'Active'
              AND sd_a.scenario_id = ?
              AND sd_b.scenario_id = ?
              AND (
                    (sc.interface_a_id = ? AND sc.interface_b_id = ?)
                 OR (sc.interface_a_id = ? AND sc.interface_b_id = ?)
              )
            LIMIT 1
        ");
        $stmt->execute([$scenarioId, $scenarioId, $interfaceA, $interfaceB, $interfaceB, $interfaceA]);
        $masterConnectionId = (int) $stmt->fetchColumn();

        if ($masterConnectionId > 0) {
            topologyJson(true, 'These interfaces are already connected in the scenario topology.', [
                'master_connection_id' => $masterConnectionId,
                'interface_a_id' => $interfaceA,
                'interface_b_id' => $interfaceB,
                'already_exists' => true,
                'master_connection' => true,
            ]);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO scenario_attempt_connections
                (attempt_id, interface_a_id, interface_b_id, connection_type, status)
            VALUES
                (?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([$attemptId, $interfaceA, $interfaceB, $connectionType]);
        $newId = (int) $pdo->lastInsertId();

        recordTopologyAction(
            $pdo,
            $attemptId,
            'CREATE_STUDENT_CONNECTION',
            $newId,
            [
                'student_connection_id' => $newId,
                'interface_a_id' => $interfaceA,
                'interface_b_id' => $interfaceB,
                'connection_type' => $connectionType,
            ],
            [
                'result' => 'Student network connection created.',
                'student_connection_id' => $newId,
            ]
        );

        $stmt = $pdo->prepare("
            UPDATE scenario_attempts
            SET last_activity_at = NOW()
            WHERE attempt_id = ?
              AND student_id = ?
              AND status = 'In Progress'
        ");
        $stmt->execute([$attemptId, $studentId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('The attempt is no longer editable.');
        }

        $pdo->commit();

        topologyJson(true, 'Network connection created successfully.', [
            'student_connection_id' => $newId,
            'interface_a_id' => $interfaceA,
            'interface_b_id' => $interfaceB,
            'connection_type' => $connectionType,
        ]);
    }

    if ($action === 'remove_connection' || $action === 'disconnect_connection') {
        if ($connectionId <= 0) {
            topologyJson(false, 'A student connection ID is required.', [], 422);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT student_connection_id, interface_a_id, interface_b_id, connection_type, status
            FROM scenario_attempt_connections
            WHERE student_connection_id = ?
              AND attempt_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$connectionId, $attemptId]);
        $connection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$connection) {
            $pdo->rollBack();
            topologyJson(false, 'The selected student connection was not found.', [], 404);
        }

        if (($connection['status'] ?? '') !== 'Active') {
            $pdo->commit();
            topologyJson(true, 'The student connection is already inactive.', [
                'student_connection_id' => $connectionId,
                'already_inactive' => true,
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE scenario_attempt_connections
            SET status = 'Inactive'
            WHERE student_connection_id = ?
              AND attempt_id = ?
              AND status = 'Active'
        ");
        $stmt->execute([$connectionId, $attemptId]);

        recordTopologyAction(
            $pdo,
            $attemptId,
            'REMOVE_STUDENT_CONNECTION',
            $connectionId,
            [
                'student_connection_id' => $connectionId,
                'interface_a_id' => (int) $connection['interface_a_id'],
                'interface_b_id' => (int) $connection['interface_b_id'],
                'connection_type' => (string) $connection['connection_type'],
            ],
            [
                'result' => 'Student network connection removed.',
                'student_connection_id' => $connectionId,
            ]
        );

        $stmt = $pdo->prepare("
            UPDATE scenario_attempts
            SET last_activity_at = NOW()
            WHERE attempt_id = ?
              AND student_id = ?
              AND status = 'In Progress'
        ");
        $stmt->execute([$attemptId, $studentId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('The attempt is no longer editable.');
        }

        $pdo->commit();

        topologyJson(true, 'Student network connection removed.', [
            'student_connection_id' => $connectionId,
        ]);
    }

    topologyJson(false, 'Unsupported topology action.', [], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('VNDTES topology action error: ' . $e->getMessage());

    topologyJson(false, 'The topology action could not be completed. Please try again.', [], 500);
}

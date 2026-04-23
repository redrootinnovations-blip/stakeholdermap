<?php
// ============================================================
// Stakeholder Map – API Diagnostic Test
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. PHP Version
$results['php_version'] = PHP_VERSION;

// 2. Test config include
try {
    require_once __DIR__ . '/config.php';
    $results['config_loaded'] = true;
} catch (Throwable $e) {
    $results['config_loaded'] = false;
    $results['config_error'] = $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Test DB connection
try {
    $db = getDB();
    $results['db_connected'] = true;
} catch (Throwable $e) {
    $results['db_connected'] = false;
    $results['db_error'] = $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Test tables exist
try {
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    $results['tables'] = $tables;
    $results['tables_ok'] = in_array('users', $tables) && in_array('sessions', $tables) && in_array('stakeholders', $tables);
} catch (Throwable $e) {
    $results['tables_error'] = $e->getMessage();
}

// 5. Test mail function
$results['mail_function_exists'] = function_exists('mail');

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

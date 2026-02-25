<?php
/**
 * EPCO - Health Check Endpoint
 * GET /health       → solo app (para Docker healthcheck)
 * GET /health?db=1  → app + conectividad MySQL (para diagnóstico)
 */
header('Content-Type: application/json; charset=UTF-8');

$result = [
    'status' => 'ok',
    'service' => 'epco-app',
    'time' => date('c')
];

// Si se pide chequeo de BD
if (isset($_GET['db'])) {
    $dbHost = getenv('DB_HOST') ?: 'db';
    $dbName = getenv('DB_NAME') ?: 'epco';
    $dbUser = getenv('DB_USER') ?: 'epco_user';
    $dbPass = getenv('DB_PASS');
    $dbPass = ($dbPass !== false) ? $dbPass : '';

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_TIMEOUT => 3]
        );
        $result['database'] = 'connected';
        $result['db_host'] = $dbHost;
        $result['db_name'] = $dbName;
        $result['db_user'] = $dbUser;
    } catch (PDOException $e) {
        http_response_code(503);
        $result['status'] = 'degraded';
        $result['database'] = 'error';
        $result['db_host'] = $dbHost;
        $result['db_name'] = $dbName;
        $result['db_user'] = $dbUser;
        $result['db_error'] = $e->getMessage();
    }
}

http_response_code($result['status'] === 'ok' ? 200 : 503);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

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

// Si se pide chequeo de BD (restringir a localhost/Docker internos)
if (isset($_GET['db'])) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isInternal = in_array($remoteIp, ['127.0.0.1', '::1', '172.16.0.0/12'], true) || strpos($remoteIp, '172.') === 0 || strpos($remoteIp, '10.') === 0;
    
    if (!$isInternal && (ENVIRONMENT ?? 'production') === 'production') {
        $result['database'] = 'access_restricted';
    } else {
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
    } catch (PDOException $e) {
        http_response_code(503);
        $result['status'] = 'degraded';
        $result['database'] = 'error';
        // No exponer detalles de error en producción
        if ((ENVIRONMENT ?? 'production') === 'development') {
            $result['db_error'] = $e->getMessage();
        }
    }
    }
}

http_response_code($result['status'] === 'ok' ? 200 : 503);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

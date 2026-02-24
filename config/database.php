<?php
/**
 * EPCO - Configuración de Base de Datos (Docker)
 * Lee credenciales desde variables de entorno del contenedor
 */

// Prevenir acceso directo
if (!defined('EPCO_APP')) {
    die('Acceso directo no permitido');
}

// Credenciales desde variables de entorno (definidas en docker-compose.yml)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'epco');
define('DB_USER', getenv('DB_USER') ?: 'epco_user');
define('DB_PASS', getenv('DB_PASS') ?: 'EpcoSecure2026');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Conexión PDO
$dsn = sprintf(
    "mysql:host=%s;dbname=%s;charset=%s",
    DB_HOST,
    DB_NAME,
    DB_CHARSET
);

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    http_response_code(500);
    die("Error de conexión a la base de datos.");
}

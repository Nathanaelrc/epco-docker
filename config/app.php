<?php
/**
 * EPCO - Configuración Principal de la Aplicación
 * Este archivo contiene todas las configuraciones globales
 */

// Prevenir acceso directo
if (!defined('EPCO_APP')) {
    define('EPCO_APP', true);
}

// =============================================
// CONFIGURACIÓN DE SESIÓN SEGURA
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    // Configurar cookies de sesión seguras
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Regenerar ID de sesión periódicamente para prevenir session fixation
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// =============================================
// ZONA HORARIA
// =============================================
date_default_timezone_set('America/Santiago');

// =============================================
// MODO DE DESARROLLO / PRODUCCIÓN
// =============================================
$environment = strtolower(trim((string)(getenv('APP_ENV') ?: 'production')));
if (!in_array($environment, ['development', 'production'], true)) {
    $environment = 'production';
}
define('ENVIRONMENT', $environment); // 'development' o 'production'

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// =============================================
// RUTAS DE LA APLICACIÓN
// =============================================
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// URLs (Docker: la app se sirve desde la raíz)
define('BASE_URL', '/');
define('ASSETS_URL', '/assets/');
define('UPLOADS_URL', '/uploads/');

// =============================================
// INFORMACIÓN DE LA APLICACIÓN
// =============================================
define('APP_NAME', 'EPCO');
define('APP_FULL_NAME', 'EPCO - Portal Corporativo');
define('APP_VERSION', '2.0.0');
define('APP_COMPANY', 'EPCO');

// =============================================
// CONFIGURACIÓN DE TEMA
// =============================================
define('PRIMARY_COLOR', '#0a2540');
define('SECONDARY_COLOR', '#ffffff');
define('ACCENT_COLOR', '#1e3a5f');
define('DANGER_COLOR', '#dc2626');
define('SUCCESS_COLOR', '#16a34a');
define('WARNING_COLOR', '#f59e0b');

// =============================================
// ROLES DE USUARIO
// =============================================
define('ROLE_ADMIN', 'admin');
define('ROLE_SOPORTE', 'soporte');
define('ROLE_SOCIAL', 'social');
define('ROLE_USER', 'user');

// =============================================
// CONFIGURACIÓN DE SLA (en horas)
// =============================================
define('SLA_CONFIG', [
    'urgente' => [
        'first_response' => 1,      // 1 hora para primera respuesta
        'assignment' => 0.5,        // 30 min para asignación
        'resolution' => 4,          // 4 horas para resolución
        'color' => '#dc2626',
        'label' => 'Urgente'
    ],
    'alta' => [
        'first_response' => 4,      // 4 horas para primera respuesta
        'assignment' => 2,          // 2 horas para asignación
        'resolution' => 24,         // 24 horas para resolución
        'color' => '#f59e0b',
        'label' => 'Alta'
    ],
    'media' => [
        'first_response' => 8,      // 8 horas para primera respuesta
        'assignment' => 4,          // 4 horas para asignación
        'resolution' => 48,         // 48 horas para resolución
        'color' => '#3b82f6',
        'label' => 'Media'
    ],
    'baja' => [
        'first_response' => 24,     // 24 horas para primera respuesta
        'assignment' => 8,          // 8 horas para asignación
        'resolution' => 72,         // 72 horas para resolución
        'color' => '#64748b',
        'label' => 'Baja'
    ]
]);

// =============================================
// CONFIGURACIÓN DE TICKETS
// =============================================
define('TICKET_STATUSES', [
    'abierto' => ['label' => 'Abierto', 'color' => 'primary', 'icon' => 'bi-circle'],
    'asignado' => ['label' => 'Asignado', 'color' => 'info', 'icon' => 'bi-person-check'],
    'en_proceso' => ['label' => 'En Proceso', 'color' => 'warning', 'icon' => 'bi-gear'],
    'pendiente' => ['label' => 'Pendiente', 'color' => 'secondary', 'icon' => 'bi-clock'],
    'resuelto' => ['label' => 'Resuelto', 'color' => 'success', 'icon' => 'bi-check-circle'],
    'cerrado' => ['label' => 'Cerrado', 'color' => 'dark', 'icon' => 'bi-x-circle']
]);

define('TICKET_CATEGORIES', [
    'hardware' => ['label' => 'Hardware', 'icon' => 'bi-pc-display'],
    'software' => ['label' => 'Software', 'icon' => 'bi-app'],
    'red' => ['label' => 'Red', 'icon' => 'bi-wifi'],
    'acceso' => ['label' => 'Acceso', 'icon' => 'bi-key'],
    'otro' => ['label' => 'Otro', 'icon' => 'bi-question-circle']
]);

// =============================================
// CONFIGURACIÓN DE ARCHIVOS
// =============================================
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_FILE_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv'
]);

// =============================================
// CONFIGURACIÓN DE SEGURIDAD
// =============================================
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// =============================================
// FUNCIONES DE SEGURIDAD GLOBALES
// =============================================

/**
 * Generar token CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verificar token CSRF
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Input HTML para CSRF
 */
function csrfInput() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

/**
 * Sanitizar entrada
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Redirigir con mensaje
 */
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

/**
 * Obtener y limpiar mensaje flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Log de actividad - Registra en BD y archivo
 * @param int|null $userId ID del usuario
 * @param string $action Acción realizada (login, logout, ticket_created, etc.)
 * @param string|null $entityType Tipo de entidad (users, tickets, documents, etc.)
 * @param int|null $entityId ID de la entidad
 * @param string $details Detalles adicionales
 */
function logActivity($userId, $action, $entityType = null, $entityId = null, $details = '') {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Registrar en base de datos
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $userId ?: null,
                $action,
                $entityType,
                $entityId,
                $details,
                $ip,
                substr($userAgent, 0, 255)
            ]);
        }
    } catch (Exception $e) {
        error_log("Error registrando log de auditoría: " . $e->getMessage());
    }
    
    // También registrar en archivo como respaldo
    $logFile = LOGS_PATH . '/activity_' . date('Y-m-d') . '.log';
    $logEntry = sprintf(
        "[%s] User: %s | IP: %s | Action: %s | Entity: %s#%s | Details: %s\n",
        date('Y-m-d H:i:s'),
        $userId ?? 'guest',
        $ip,
        $action,
        $entityType ?? '-',
        $entityId ?? '-',
        $details
    );
    
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

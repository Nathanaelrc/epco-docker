<?php
/**
 * Funciones de autenticación EPCO
 */

// Cargar dependencias solo si no fueron cargadas por bootstrap
if (!isset($pdo)) {
    if (!defined('EPCO_APP')) {
        define('EPCO_APP', true);
    }
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/database.php';
}
if (!function_exists('sanitize')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * Login con email o nombre de usuario
 */
function login($identifier, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Verificar bloqueo por intentos fallidos
        if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS && !empty($user['locked_until']) && $user['locked_until'] > date('Y-m-d H:i:s')) {
            return false;
        }
        
        // Regenerar session ID para prevenir session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Resetear intentos fallidos
        $resetStmt = $pdo->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?');
        $resetStmt->execute([$user['id']]);
        
        return true;
    }
    
    // Incrementar intentos fallidos si el usuario existe
    if ($user) {
        $attempts = ($user['login_attempts'] ?? 0) + 1;
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
        }
        $failStmt = $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?');
        $failStmt->execute([$attempts, $lockUntil, $user['id']]);
    }
    
    return false;
}

/**
 * Cerrar sesión
 */
function logout() {
    $_SESSION = [];
    
    // Eliminar cookie de sesión
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Obtener rol del usuario actual
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Verificar si el usuario tiene un rol específico
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Requerir autenticación
 */
function requireAuth($redirect = 'iniciar_sesion.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

/**
 * Requerir rol específico
 */
function requireRole($roles, $redirect = 'index.php') {
    requireAuth();
    if (!hasRole($roles)) {
        header("Location: $redirect");
        exit;
    }
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare('SELECT id, name, username, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Registrar nuevo usuario
 */
function registerUser($name, $username, $email, $password, $role = 'user') {
    global $pdo;
    
    // Verificar si el email ya existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'El correo ya está registrado'];
    }
    
    // Verificar si el username ya existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'El nombre de usuario ya está en uso'];
    }
    
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $username, $email, $hash, $role]);
    
    return ['success' => true, 'message' => 'Usuario registrado correctamente', 'user_id' => $pdo->lastInsertId()];
}

/**
 * Generar username desde nombre completo
 */
function generateUsername($fullName) {
    // Normalizar: quitar acentos, convertir a minúsculas
    $name = strtolower(trim($fullName));
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    
    // Dividir en palabras
    $parts = preg_split('/\s+/', $name);
    
    if (count($parts) >= 2) {
        // nombre.apellido
        return $parts[0] . '.' . $parts[count($parts) - 1];
    }
    return $parts[0];
}
?>

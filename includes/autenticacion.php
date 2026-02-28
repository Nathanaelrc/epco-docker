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
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        return true;
    }
    return false;
}

/**
 * Cerrar sesión
 */
function logout() {
    session_destroy();
    session_start();
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

<?php
/**
 * EPCO - Bootstrap Principal
 * Archivo de inicialización centralizado
 * Todos los archivos deben incluir este archivo
 */

// Establecer encoding UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// =============================================
// HEADERS DE SEGURIDAD HTTP
// =============================================
if (!headers_sent()) {
    // Content-Type con charset
    header('Content-Type: text/html; charset=UTF-8');
    
    // Prevenir clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevenir MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Activar filtro XSS del navegador
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer Policy - evitar filtrar info sensible
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions Policy - restringir APIs del navegador
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Cache control para páginas autenticadas
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['logged_in'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

// Definir constante para prevenir acceso directo
if (!defined('EPCO_APP')) {
    define('EPCO_APP', true);
}

// Obtener la ruta raíz del proyecto
define('EPCO_ROOT', dirname(__DIR__));

// Cargar configuración de la aplicación
require_once EPCO_ROOT . '/config/app.php';

// Cargar configuración de base de datos
require_once EPCO_ROOT . '/config/database.php';

// Cargar helpers
require_once EPCO_ROOT . '/includes/helpers.php';

// Cargar autenticación
require_once EPCO_ROOT . '/includes/auth.php';

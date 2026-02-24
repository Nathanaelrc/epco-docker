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

// Header Content-Type con charset
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
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

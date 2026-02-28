<?php
/**
 * EPCO - Logout
 */
require_once '../includes/bootstrap.php';

// Registrar logout antes de destruir sesión
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'Cierre de sesión');
}

logout();
header('Location: index.php');
exit;
?>

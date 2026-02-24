<?php
/**
 * EPCO - Intranet (Redirección a login o dashboard)
 */
require_once '../includes/bootstrap.php';

if (isLoggedIn()) {
    header('Location: intranet_dashboard.php');
} else {
    header('Location: login.php?redirect=intranet_dashboard');
}
exit;
?>

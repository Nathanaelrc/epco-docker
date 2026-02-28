<?php
/**
 * EPCO - Intranet (Redirección a login o dashboard)
 */
require_once '../includes/bootstrap.php';

if (isLoggedIn()) {
    header('Location: panel_intranet.php');
} else {
    header('Location: iniciar_sesion.php?redirect=intranet_dashboard');
}
exit;
?>

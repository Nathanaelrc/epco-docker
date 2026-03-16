<?php
/**
 * Header común para todas las páginas
 */
if (!defined('EPCO_APP')) {
    define('EPCO_APP', true);
    require_once __DIR__ . '/../config/app.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Empresa Portuaria Coquimbo' ?> - Portal Corporativo</title>
    
    <!-- Preconnect para CDNs -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Favicon WebP con fallback PNG -->
    <link rel="icon" type="image/webp" href="img/Logo01.webp">
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <!-- Google Fonts - Montserrat + Lato (con display=swap para evitar bloqueo) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- GSAP - carga diferida, solo cuando se necesita -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js" defer></script>
    
    <!-- Chart.js - carga diferida, solo páginas que lo necesitan -->
    <?php if (!empty($needsChartJs)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <?php endif; ?>
    
    <link href="css/base.css" rel="stylesheet">
</head>
<body>

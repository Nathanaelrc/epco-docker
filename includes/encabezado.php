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
    
    <!-- Google Fonts - Barlow (con display=swap para evitar bloqueo) -->
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- GSAP - carga diferida, solo cuando se necesita -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js" defer></script>
    
    <!-- Chart.js - carga diferida, solo páginas que lo necesitan -->
    <?php if (!empty($needsChartJs)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: #0a2540;
            --secondary-color: #ffffff;
            --accent-color: #1e3a5f;
            --accent-light: #2d4a6f;
        }
        
        * {
            font-family: 'Barlow', sans-serif;
        }
        
        body {
            background: var(--primary-color);
            color: var(--secondary-color);
            min-height: 100vh;
        }
        
        .bg-primary-dark {
            background-color: var(--primary-color) !important;
        }
        
        .bg-accent {
            background-color: var(--accent-color) !important;
        }
        
        .text-primary-dark {
            color: var(--primary-color) !important;
        }
        
        .btn-epco {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-epco:hover {
            background-color: var(--accent-light);
            border-color: var(--accent-light);
            color: white;
            transform: translateY(-2px);
        }
        
        .card-epco {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .card-epco:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .navbar-epco {
            background: rgba(10, 37, 64, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(10, 37, 64, 0.25);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }
        
        /* Animaciones */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
        }
        
        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
        }
        
        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
        }
    </style>
</head>
<body>

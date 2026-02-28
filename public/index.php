<?php
/**
 * EPCO - Página de Inicio
 * 3 opciones: Intranet, Soporte TI, Canal de Denuncias
 */
require_once '../includes/bootstrap.php';

// Verificar si el usuario está logueado y su rol
$isLoggedIn = isLoggedIn();
$userRole = $isLoggedIn ? getUserRole() : null;
// Usuario soporte logueado ve opciones especiales
$isSoporteRole = $userRole === 'soporte';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Portal Corporativo</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/Logo01.png">
    <link rel="shortcut icon" type="image/png" href="img/Logo01.png">
    
    <!-- Google Fonts - Barlow -->
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    
    <style>
        * {
            font-family: 'Barlow', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%),
                        url('img/Puerto03.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
        }
        
        .hero-section {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            position: relative;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            pointer-events: none;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1200px;
        }
        
        .logo-text {
            font-size: 2.8rem;
            font-weight: 700;
            letter-spacing: -1px;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            margin-bottom: 5px;
            margin-top: 20px;
        }
        
        .company-logo {
            width: 120px;
            height: auto;
            filter: drop-shadow(0 4px 15px rgba(0,0,0,0.3));
            transition: transform 0.3s ease;
        }
        
        .company-logo:hover {
            transform: scale(1.05);
        }
        
        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 50px;
        }
        
        .card-wrapper {
            width: 100%;
            max-width: 350px;
        }
        
        .card-option {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 40px 30px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: block;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .card-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--card-color), var(--card-color-light));
        }
        
        .card-option:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
            text-decoration: none;
        }
        
        .icon-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            transition: all 0.4s ease;
        }
        
        .card-option:hover .icon-wrapper {
            transform: scale(1.1) rotate(5deg);
        }
        
        .card-intranet {
            --card-color: #0ea5e9;
            --card-color-light: #0284c7;
        }
        .card-intranet .icon-wrapper {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }
        
        .card-soporte {
            --card-color: #0891b2;
            --card-color-light: #22d3ee;
        }
        .card-soporte .icon-wrapper {
            background: linear-gradient(135deg, #0891b2, #22d3ee);
        }
        
        .card-denuncias {
            --card-color: #dc2626;
            --card-color-light: #f87171;
        }
        .card-denuncias .icon-wrapper {
            background: linear-gradient(135deg, #dc2626, #f87171);
        }
        
        .card-title {
            text-align: center;
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 15px;
        }
        
        .card-text {
            text-align: center;
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        
        .card-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 50px;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .card-intranet .card-badge {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }
        
        .card-soporte .card-badge {
            background: linear-gradient(135deg, #0891b2, #22d3ee);
        }
        
        .card-denuncias .card-badge {
            background: linear-gradient(135deg, #dc2626, #f87171);
        }
        
        .footer-info {
            margin-top: 50px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.875rem;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @media (max-width: 768px) {
            .logo-text {
                font-size: 3.5rem;
            }
            .cards-container {
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="content-wrapper">
            <!-- Logo y título -->
            <div class="text-center mb-4">
                <img src="img/Logo01.png" alt="Empresa Portuaria Coquimbo" class="company-logo">
                <h1 class="logo-text">Empresa Portuaria Coquimbo</h1>
                <p class="subtitle">Portal Corporativo</p>
            </div>
            
            <!-- Cards de opciones -->
            <div class="cards-container">
                <?php if ($isSoporteRole): ?>
                <!-- Usuario soporte logueado - Mostrar Dashboard Soporte + Intranet -->
                <div class="card-wrapper">
                    <a href="soporte_admin.php" class="card-option card-soporte">
                        <div class="icon-wrapper floating">
                            <i class="bi bi-headset text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="card-title">Dashboard Soporte TI</h3>
                        <p class="card-text">Gestiona tickets y solicitudes de soporte técnico.</p>
                        <div class="text-center">
                            <span class="card-badge">
                                <i class="bi bi-speedometer2 me-2"></i>Ir al Dashboard
                            </span>
                        </div>
                    </a>
                </div>
                
                <!-- Intranet oculta temporalmente
                <div class="card-wrapper">
                    <a href="panel_intranet.php" class="card-option card-intranet">
                        <div class="icon-wrapper floating" style="animation-delay: 0.5s;">
                            <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="card-title">Intranet</h3>
                        <p class="card-text">Accede a recursos internos, noticias y documentos.</p>
                        <div class="text-center">
                            <span class="card-badge">
                                <i class="bi bi-arrow-right me-2"></i>Ingresar
                            </span>
                        </div>
                    </a>
                </div>
                -->
                
                <div class="text-center w-100 mt-4">
                    <span class="text-white-50 me-3">Bienvenido, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></strong> (Soporte)</span>
                    <a href="cerrar_sesion.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-left me-1"></i>Cerrar Sesión
                    </a>
                </div>
                
                <?php else: ?>
                <!-- Usuarios normales o no logueados - 3 opciones -->
                <!-- Intranet oculta temporalmente
                <div class="card-wrapper">
                    <a href="intranet.php" class="card-option card-intranet">
                        <div class="icon-wrapper floating">
                            <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="card-title">Intranet</h3>
                        <p class="card-text">Accede a recursos internos, noticias y documentos de la empresa.</p>
                        <div class="text-center">
                            <span class="card-badge">
                                <i class="bi bi-arrow-right me-2"></i>Ingresar
                            </span>
                        </div>
                    </a>
                </div>
                -->
                
                <!-- Soporte TI -->
                <div class="card-wrapper">
                    <a href="soporte.php" class="card-option card-soporte">
                        <div class="icon-wrapper floating" style="animation-delay: 0.5s;">
                            <i class="bi bi-headset text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="card-title">Soporte TI</h3>
                        <p class="card-text">Reporta problemas técnicos y haz seguimiento de tus tickets.</p>
                        <div class="text-center">
                            <span class="card-badge">
                                <i class="bi bi-arrow-right me-2"></i>Ingresar
                            </span>
                        </div>
                    </a>
                </div>
                
                <!-- Canal de Denuncias (oculto temporalmente) -->
                <!--
                <div class="card-wrapper">
                    <a href="denuncias.php" class="card-option card-denuncias">
                        <div class="icon-wrapper floating" style="animation-delay: 1s;">
                            <i class="bi bi-shield-check text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="card-title">Canal de Denuncias</h3>
                        <p class="card-text">Reporta situaciones según la Ley Karin de forma segura y confidencial.</p>
                        <div class="text-center">
                            <span class="card-badge">
                                <i class="bi bi-arrow-right me-2"></i>Ingresar
                            </span>
                        </div>
                    </a>
                </div>
                -->
                
                <?php if ($isLoggedIn): ?>
                <!-- Usuario logueado - Mostrar opción de cerrar sesión -->
                <div class="text-center w-100 mt-4">
                    <span class="text-white-50 me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        Bienvenido, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></strong>
                        <span class="badge bg-light text-dark ms-2"><?= ucfirst($userRole) ?></span>
                    </span>
                    <a href="cerrar_sesion.php" class="btn btn-outline-light btn-sm ms-3">
                        <i class="bi bi-box-arrow-left me-1"></i>Cerrar Sesión
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Footer info -->
            <div class="text-center footer-info">
                <p>
                    <i class="bi bi-lock-fill me-2"></i>Conexión segura · HTTPS · Datos protegidos
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PWA Service Worker -->
    <script>
        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(err => console.log('SW:', err));
        }
    </script>
</body>
</html>

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
    
    <!-- Preconnect CDNs -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <title>Empresa Portuaria Coquimbo - Portal Corporativo</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link rel="shortcut icon" type="image/webp" href="img/Logo01.webp">
    
    <!-- Google Fonts - Montserrat + Lato -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    
    <link href="css/home.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto03.webp" : "img/Puerto03.jpg" ?>') center/cover no-repeat fixed; }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="content-wrapper">
            <!-- Logo y título -->
            <div class="text-center mb-4">
                <picture>
                    <source srcset="img/Logo01.webp" type="image/webp">
                    <img src="img/Logo01.png" alt="Empresa Portuaria Coquimbo" class="company-logo" loading="eager" width="120" height="120">
                </picture>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <!-- PWA Service Worker -->
    <script>
        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(err => console.log('SW:', err));
        }
    </script>
</body>
</html>

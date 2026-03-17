<?php
/**
 * EPCO - Login
 */
require_once '../includes/bootstrap.php';
require_once '../includes/autenticacion.php';

/**
 * Validar que el redirect sea seguro (solo rutas internas)
 */
function isSafeRedirect($redirect) {
    if (!$redirect) return false;
    
    // Lista blanca de páginas permitidas
    $allowedPages = [
        'soporte_admin', 'panel_intranet', 'intranet_soporte',
        'denuncias', 'denuncia_seguimiento', 'perfil', 'index',
        'documentos', 'eventos', 'base_conocimiento', 'buscar', 'soporte',
        'intranet', 'crear_ticket', 'reportes', 'encuesta'
    ];
    
    // Limpiar el redirect
    $redirect = basename(str_replace('.php', '', $redirect));
    
    // Mapeo de nombres antiguos a nuevos
    $aliases = [
        'intranet_dashboard' => 'panel_intranet',
        'profile' => 'perfil',
        'documents' => 'documentos',
        'events' => 'eventos',
        'knowledge_base' => 'base_conocimiento',
        'search' => 'buscar',
    ];
    if (isset($aliases[$redirect])) {
        $redirect = $aliases[$redirect];
    }
    
    return in_array($redirect, $allowedPages);
}

// Si ya está logueado, redirigir automáticamente
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? null;
    
    if ($redirect && isSafeRedirect($redirect)) {
        $redirect = basename(str_replace('.php', '', $redirect));
        $aliases = ['intranet_dashboard' => 'panel_intranet', 'profile' => 'perfil', 'documents' => 'documentos', 'events' => 'eventos', 'knowledge_base' => 'base_conocimiento', 'search' => 'buscar'];
        if (isset($aliases[$redirect])) $redirect = $aliases[$redirect];
        header("Location: {$redirect}.php");
    } elseif ($_SESSION['user_role'] === 'soporte') {
        header("Location: soporte_admin.php");
    } else {
        header("Location: panel_intranet.php");
    }
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página e intente de nuevo.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            $error = 'Por favor complete todos los campos.';
        } else {
            if (login($identifier, $password)) {
                // Registrar login exitoso
                logActivity($_SESSION['user_id'], 'login', 'users', $_SESSION['user_id'], 'Inicio de sesión exitoso');
                
                // Redirección: primero verificar si hay un redirect específico y es seguro
                $redirect = $_GET['redirect'] ?? null;
                
                if ($redirect && isSafeRedirect($redirect)) {
                    $redirect = basename(str_replace('.php', '', $redirect));
                    $aliases = ['intranet_dashboard' => 'panel_intranet', 'profile' => 'perfil', 'documents' => 'documentos', 'events' => 'eventos', 'knowledge_base' => 'base_conocimiento', 'search' => 'buscar'];
                    if (isset($aliases[$redirect])) $redirect = $aliases[$redirect];
                    header("Location: {$redirect}.php");
                } elseif ($_SESSION['user_role'] === 'soporte') {
                    header("Location: soporte_admin.php");
                } else {
                    header("Location: panel_intranet.php");
                }
                exit;
            } else {
                // Registrar intento fallido
                logActivity(null, 'login_failed', 'users', null, "Intento de login fallido para: $identifier");
                $error = 'Credenciales incorrectas. Intente nuevamente.';
            }
        }
    }
}

$pageTitle = 'Iniciar Sesión';
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
    <title>Empresa Portuaria Coquimbo - Iniciar Sesión</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    
    <link href="css/auth.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto03.webp" : "img/Puerto03.jpg" ?>') center/cover no-repeat fixed; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="login-header">
                        <h1 class="text-white fw-bold mb-2" style="font-size: 2.5rem;">Empresa Portuaria Coquimbo</h1>
                        <p class="text-white-50 mb-0">Portal Corporativo</p>
                    </div>
                    
                    <div class="p-5">
                        <h4 class="text-dark fw-bold mb-4 text-center">Iniciar Sesión</h4>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger rounded-3 mb-4">
                            <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <?= csrfInput() ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">Usuario o Correo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                                    <input type="text" name="identifier" class="form-control" placeholder="Ingrese su usuario o correo" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Ingrese su contraseña" required>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label text-muted" for="remember">Recordarme</label>
                                </div>
                                <a href="recuperar_contrasena.php" class="text-decoration-none" style="color: #0ea5e9;">¿Olvidó su contraseña?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-lg w-100 text-white mb-4">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left me-2"></i>Volver al inicio
                            </a>
                        </div>
                    </div>
                </div>
                
                <p class="text-center text-white-50 mt-4 small">
                    <i class="bi bi-shield-check me-2"></i>Conexión segura con encriptación
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>

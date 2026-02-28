<?php
/**
 * EPCO - Login
 */
require_once '../includes/bootstrap.php';
require_once '../includes/auth.php';

/**
 * Validar que el redirect sea seguro (solo rutas internas)
 */
function isSafeRedirect($redirect) {
    if (!$redirect) return false;
    
    // Lista blanca de páginas permitidas
    $allowedPages = [
        'soporte_admin', 'intranet_dashboard', 'intranet_soporte',
        'denuncias', 'denuncia_seguimiento', 'profile', 'index',
        'documents', 'events', 'knowledge_base', 'search', 'soporte'
    ];
    
    // Limpiar el redirect
    $redirect = basename(str_replace('.php', '', $redirect));
    
    return in_array($redirect, $allowedPages);
}

// Si ya está logueado, redirigir automáticamente
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? null;
    
    if ($redirect && isSafeRedirect($redirect)) {
        $redirect = basename(str_replace('.php', '', $redirect));
        header("Location: $redirect");
    } elseif ($_SESSION['user_role'] === 'soporte') {
        header("Location: soporte_admin");
    } else {
        header("Location: intranet_dashboard");
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
                    header("Location: $redirect");
                } elseif ($_SESSION['user_role'] === 'soporte') {
                    header("Location: soporte_admin");
                } else {
                    header("Location: intranet_dashboard");
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
    <title>EPCO - Iniciar Sesión</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    
    <style>
        * { font-family: 'Barlow', sans-serif; }
        body {
            background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%),
                        url('img/Puerto03.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }
        .container {
            position: relative;
            z-index: 1;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5), 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 40px;
            text-align: center;
        }
        .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(10, 37, 64, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 37, 64, 0.4);
        }
        .input-group-text {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-right: none;
            border-radius: 12px 0 0 12px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="login-header">
                        <h1 class="text-white fw-bold mb-2" style="font-size: 2.5rem;">EPCO</h1>
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
                                <a href="forgot_password.php" class="text-decoration-none" style="color: #0ea5e9;">¿Olvidó su contraseña?</a>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

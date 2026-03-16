<?php
/**
 * EPCO - Recuperación de Contraseña
 */
require_once '../includes/bootstrap.php';

$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$success = false;

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header('Location: panel_intranet.php');
    exit;
}

// Paso 1: Solicitar recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'request') {
        $email = sanitize($_POST['email']);
        
        // Buscar usuario
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generar token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Invalidar tokens anteriores
            $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $stmt->execute([$user['id']]);
            
            // Insertar nuevo token
            $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], $token, $expiresAt]);
            
            // Encolar email (en producción se enviaría realmente)
            $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
                        dirname($_SERVER['REQUEST_URI']) . '/recuperar_contrasena.php?step=reset&token=' . $token;
            
            $emailBody = "
                <h2>Recuperación de Contraseña</h2>
                <p>Hola {$user['name']},</p>
                <p>Has solicitado restablecer tu contraseña en el Portal Empresa Portuaria Coquimbo.</p>
                <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
                <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                <p>Este enlace expirará en 1 hora.</p>
                <p>Si no solicitaste este cambio, ignora este mensaje.</p>
                <br>
                <p>Saludos,<br>Portal Empresa Portuaria Coquimbo</p>
            ";
            
            $stmt = $pdo->prepare('INSERT INTO email_queue (to_email, to_name, subject, body, template) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['email'], $user['name'], 'Recuperación de Contraseña - Empresa Portuaria Coquimbo', $emailBody, 'password_reset']);
            
            logActivity(null, 'password_reset_requested', 'users', $user['id'], "Solicitud de recuperación de contraseña para {$user['email']}");
        }
        
        // Siempre mostrar mensaje de éxito para no revelar si el email existe
        $message = 'Si el email está registrado, recibirás instrucciones para restablecer tu contraseña.';
        $messageType = 'success';
        $success = true;
    }
    
    if ($_POST['action'] === 'reset') {
        $token = sanitize($_POST['token']);
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verificar token
        $stmt = $pdo->prepare('
            SELECT pr.*, u.id as user_id, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
        ');
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            $message = 'El enlace ha expirado o no es válido. Solicita uno nuevo.';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'La contraseña debe tener al menos 6 caracteres';
            $messageType = 'danger';
            $step = 'reset';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Las contraseñas no coinciden';
            $messageType = 'danger';
            $step = 'reset';
        } else {
            // Actualizar contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE token = ?');
            $stmt->execute([$token]);
            
            logActivity(null, 'password_reset_completed', 'users', $reset['user_id'], "Contraseña restablecida para {$reset['email']}");
            
            $message = '¡Contraseña actualizada exitosamente! Ahora puedes iniciar sesión.';
            $messageType = 'success';
            $step = 'success';
        }
    }
}

// Verificar token para paso de reset
if ($step === 'reset' && $token) {
    $stmt = $pdo->prepare('
        SELECT * FROM password_resets 
        WHERE token = ? AND used_at IS NULL AND expires_at > NOW()
    ');
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        $message = 'El enlace ha expirado o no es válido. Solicita uno nuevo.';
        $messageType = 'danger';
        $step = 'request';
    }
}
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
    <title>Empresa Portuaria Coquimbo - Recuperar Contraseña</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        body {
            background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%),
                        url('<?= WEBP_SUPPORT ? "img/Puerto03.webp" : "img/Puerto03.jpg" ?>') center/cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }
        .container { position: relative; z-index: 1; }
        .reset-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .reset-header {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 40px;
            text-align: center;
        }
        .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
        }
        .form-control:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(10, 37, 64, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
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
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="reset-card">
                    <div class="reset-header">
                        <h1 class="text-white fw-bold mb-2" style="font-size: 2.5rem;">Empresa Portuaria Coquimbo</h1>
                        <p class="text-white-50 mb-0">Portal Corporativo</p>
                    </div>
                    
                    <div class="p-5">
                        <?php if ($step === 'success'): ?>
                            <!-- Éxito -->
                            <div class="text-center">
                                <div class="success-icon">
                                    <i class="bi bi-check-lg text-white fs-1"></i>
                                </div>
                                <h4 class="fw-bold mb-3">¡Contraseña Actualizada!</h4>
                                <p class="text-muted mb-4">Tu contraseña ha sido cambiada exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.</p>
                                <a href="iniciar_sesion.php" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Ir a Iniciar Sesión
                                </a>
                            </div>
                            
                        <?php elseif ($step === 'reset'): ?>
                            <!-- Paso 2: Nueva contraseña -->
                            <h4 class="text-dark fw-bold mb-2 text-center">Nueva Contraseña</h4>
                            <p class="text-muted text-center mb-4">Ingresa tu nueva contraseña</p>
                            
                            <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType ?> rounded-3 mb-4">
                                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                                <?= $message ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="reset">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">Confirmar Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Repite la contraseña" minlength="6" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                                    <i class="bi bi-check-lg me-2"></i>Cambiar Contraseña
                                </button>
                            </form>
                            
                        <?php else: ?>
                            <!-- Paso 1: Solicitar email -->
                            <h4 class="text-dark fw-bold mb-2 text-center">Recuperar Contraseña</h4>
                            <p class="text-muted text-center mb-4">Ingresa tu email y te enviaremos instrucciones</p>
                            
                            <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType ?> rounded-3 mb-4">
                                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                                <?= $message ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$success): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="request">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                                        <input type="email" name="email" class="form-control" placeholder="Ingresa tu email" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                                    <i class="bi bi-send me-2"></i>Enviar Instrucciones
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="iniciar_sesion.php" class="text-muted text-decoration-none">
                                <i class="bi bi-arrow-left me-2"></i>Volver a Iniciar Sesión
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
</body>
</html>

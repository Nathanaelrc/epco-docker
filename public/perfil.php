<?php
/**
 * EPCO - Perfil de Usuario
 */
require_once '../includes/bootstrap.php';

requireAuth('iniciar_sesion.php?redirect=perfil.php');
$user = getCurrentUser();

$message = '';
$messageType = '';

// Obtener datos completos del usuario
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $birthday = $_POST['birthday'] ?? null;
        $bio = sanitize($_POST['bio'] ?? '');
        
        $stmt = $pdo->prepare('UPDATE users SET name=?, phone=?, department=?, position=?, birthday=?, bio=? WHERE id=?');
        $stmt->execute([$name, $phone, $department, $position, $birthday ?: null, $bio, $user['id']]);
        
        // Actualizar sesión
        $_SESSION['user_name'] = $name;
        
        logActivity($user['id'], 'profile_updated', 'users', $user['id'], 'Perfil actualizado');
        
        $message = 'Perfil actualizado correctamente';
        $messageType = 'success';
        
        // Recargar datos
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (!password_verify($currentPassword, $userData['password'])) {
            $message = 'La contraseña actual es incorrecta';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'La nueva contraseña debe tener al menos 6 caracteres';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Las contraseñas no coinciden';
            $messageType = 'danger';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashedPassword, $user['id']]);
            
            logActivity($user['id'], 'password_changed', 'users', $user['id'], 'Contraseña cambiada');
            
            $message = 'Contraseña actualizada correctamente';
            $messageType = 'success';
        }
    }
    
    if ($action === 'update_preferences') {
        $theme = sanitize($_POST['theme'] ?? 'light');
        $itemsPerPage = (int)($_POST['items_per_page'] ?? 20);
        
        // Verificar si existe registro de preferencias
        $stmt = $pdo->prepare('SELECT id FROM user_preferences WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('UPDATE user_preferences SET theme=?, items_per_page=? WHERE user_id=?');
            $stmt->execute([$theme, $itemsPerPage, $user['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, theme, items_per_page) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], $theme, $itemsPerPage]);
        }
        
        $message = 'Preferencias guardadas';
        $messageType = 'success';
    }
    
    if ($action === 'update_notifications') {
        $emailTicketCreated = isset($_POST['email_ticket_created']) ? 1 : 0;
        $emailTicketUpdated = isset($_POST['email_ticket_updated']) ? 1 : 0;
        $emailNews = isset($_POST['email_news']) ? 1 : 0;
        $emailEvents = isset($_POST['email_events']) ? 1 : 0;
        
        $stmt = $pdo->prepare('SELECT id FROM notification_settings WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('UPDATE notification_settings SET email_ticket_created=?, email_ticket_updated=?, email_news=?, email_events=? WHERE user_id=?');
            $stmt->execute([$emailTicketCreated, $emailTicketUpdated, $emailNews, $emailEvents, $user['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO notification_settings (user_id, email_ticket_created, email_ticket_updated, email_news, email_events) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $emailTicketCreated, $emailTicketUpdated, $emailNews, $emailEvents]);
        }
        
        $message = 'Configuración de notificaciones guardada';
        $messageType = 'success';
    }
}

// Obtener preferencias
$stmt = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
$stmt->execute([$user['id']]);
$preferences = $stmt->fetch() ?: ['theme' => 'light', 'items_per_page' => 20];

// Obtener configuración de notificaciones
$stmt = $pdo->prepare('SELECT * FROM notification_settings WHERE user_id = ?');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetch() ?: [
    'email_ticket_created' => 1, 'email_ticket_updated' => 1, 
    'email_news' => 1, 'email_events' => 1
];

// Estadísticas del usuario
$myTickets = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$myTickets->execute([$user['id']]);
$ticketCount = $myTickets->fetchColumn();

// Actividad reciente
$activity = $pdo->prepare("
    SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
");
$activity->execute([$user['id']]);
$recentActivity = $activity->fetchAll();

$roleLabels = ['admin' => 'Administrador', 'soporte' => 'Soporte TI', 'social' => 'Comunicaciones', 'user' => 'Usuario'];
$roleColors = ['admin' => 'danger', 'soporte' => 'warning', 'social' => 'info', 'user' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Mi Perfil</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { background: #f1f5f9; min-height: 100vh; }
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 60px 0 80px;
            position: relative;
        }
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }
        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 10;
        }
        .profile-content {
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nav-pills .nav-link {
            color: #64748b;
            border-radius: 10px;
            padding: 12px 20px;
        }
        .nav-pills .nav-link.active {
            background: var(--primary);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10,37,64,0.1);
        }
        .stat-mini {
            text-align: center;
            padding: 20px;
        }
        .stat-mini .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
    </style>
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="d-flex align-items-center gap-4">
                <div class="avatar-large">
                    <?= strtoupper(substr($userData['name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="mb-1"><?= htmlspecialchars($userData['name']) ?></h1>
                    <p class="mb-2 opacity-75">
                        <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($userData['email']) ?>
                    </p>
                    <span class="badge bg-<?= $roleColors[$userData['role']] ?>">
                        <?= $roleLabels[$userData['role']] ?>
                    </span>
                    <?php if ($userData['department']): ?>
                    <span class="badge bg-light text-dark ms-2">
                        <?= htmlspecialchars($userData['department']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="container profile-content pb-5">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div class="nav flex-column nav-pills p-3" id="profileTab" role="tablist">
                            <button class="nav-link active text-start" data-bs-toggle="pill" data-bs-target="#tab-profile">
                                <i class="bi bi-person me-2"></i>Mi Perfil
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-security">
                                <i class="bi bi-shield-lock me-2"></i>Seguridad
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-preferences">
                                <i class="bi bi-gear me-2"></i>Preferencias
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-notifications">
                                <i class="bi bi-bell me-2"></i>Notificaciones
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-activity">
                                <i class="bi bi-clock-history me-2"></i>Actividad
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="row g-0 text-center">
                            <div class="col-12 border-bottom">
                                <div class="stat-mini">
                                    <div class="number"><?= $ticketCount ?></div>
                                    <small class="text-muted">Mis Tickets</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="stat-mini">
                                    <small class="text-muted">Miembro desde</small>
                                    <div class="fw-semibold"><?= date('d M Y', strtotime($userData['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="tab-content">
                    <!-- Tab: Profile -->
                    <div class="tab-pane fade show active" id="tab-profile">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Información Personal</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nombre completo</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled>
                                            <small class="text-muted">Contacta al administrador para cambiar el email</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Teléfono</label>
                                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha de nacimiento</label>
                                            <input type="date" name="birthday" class="form-control" value="<?= $userData['birthday'] ?? '' ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Departamento</label>
                                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($userData['department'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Cargo</label>
                                            <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($userData['position'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Biografía</label>
                                            <textarea name="bio" class="form-control" rows="3" placeholder="Cuéntanos sobre ti..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-lg me-2"></i>Guardar Cambios
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Security -->
                    <div class="tab-pane fade" id="tab-security">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Contraseña actual</label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nueva contraseña</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirmar nueva contraseña</label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-key me-2"></i>Cambiar Contraseña
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Sesiones Activas</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-laptop fs-4 text-primary me-3"></i>
                                        <div>
                                            <div class="fw-semibold">Sesión actual</div>
                                            <small class="text-muted"><?= $_SERVER['HTTP_USER_AGENT'] ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-success">Activa</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Preferences -->
                    <div class="tab-pane fade" id="tab-preferences">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Preferencias de la Aplicación</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_preferences">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Tema</label>
                                            <select name="theme" class="form-select">
                                                <option value="light" <?= ($preferences['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Claro</option>
                                                <option value="dark" <?= ($preferences['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Oscuro</option>
                                                <option value="auto" <?= ($preferences['theme'] ?? '') === 'auto' ? 'selected' : '' ?>>Automático (según sistema)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Elementos por página</label>
                                            <select name="items_per_page" class="form-select">
                                                <option value="10" <?= ($preferences['items_per_page'] ?? 20) == 10 ? 'selected' : '' ?>>10</option>
                                                <option value="20" <?= ($preferences['items_per_page'] ?? 20) == 20 ? 'selected' : '' ?>>20</option>
                                                <option value="50" <?= ($preferences['items_per_page'] ?? 20) == 50 ? 'selected' : '' ?>>50</option>
                                                <option value="100" <?= ($preferences['items_per_page'] ?? 20) == 100 ? 'selected' : '' ?>>100</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-lg me-2"></i>Guardar Preferencias
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Notifications -->
                    <div class="tab-pane fade" id="tab-notifications">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Configuración de Notificaciones</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_notifications">
                                    <h6 class="text-muted mb-3">Notificaciones por Email</h6>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_ticket_created" class="form-check-input" id="notifTicketCreated" <?= ($notifications['email_ticket_created'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifTicketCreated">Cuando se crea un ticket</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_ticket_updated" class="form-check-input" id="notifTicketUpdated" <?= ($notifications['email_ticket_updated'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifTicketUpdated">Cuando se actualiza mi ticket</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_news" class="form-check-input" id="notifNews" <?= ($notifications['email_news'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifNews">Nuevas noticias</label>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="email_events" class="form-check-input" id="notifEvents" <?= ($notifications['email_events'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifEvents">Recordatorios de eventos</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Guardar Configuración
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Activity -->
                    <div class="tab-pane fade" id="tab-activity">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Actividad Reciente</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                <p class="text-muted text-center py-4">No hay actividad registrada</p>
                                <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($recentActivity as $act): ?>
                                    <div class="d-flex mb-3 pb-3 border-bottom">
                                        <div class="me-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                                                <i class="bi bi-activity"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($act['action']) ?></div>
                                            <small class="text-muted"><?= $act['details'] ?></small>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($act['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

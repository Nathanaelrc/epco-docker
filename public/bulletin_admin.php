<?php
/**
 * EPCO - Administración de Boletines Internos
 * Acceso: admin, social
 */
require_once '../includes/bootstrap.php';

requireAuth('login.php');
$user = getCurrentUser();

// Solo admin y social pueden acceder
if (!in_array($user['role'], ['admin', 'social'])) {
    header('Location: intranet_dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Categorías y sus configuraciones
$categories = [
    'urgent' => ['label' => 'Urgente', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)', 'icon' => 'bi-exclamation-triangle-fill'],
    'event' => ['label' => 'Evento', 'color' => '#0891b2', 'bg' => 'rgba(8,145,178,0.1)', 'icon' => 'bi-calendar-event-fill'],
    'info' => ['label' => 'Información', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)', 'icon' => 'bi-info-circle-fill'],
    'maintenance' => ['label' => 'Mantenimiento', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)', 'icon' => 'bi-tools'],
    'celebration' => ['label' => 'Celebración', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'icon' => 'bi-balloon-fill']
];

$priorities = [
    'low' => 'Baja',
    'normal' => 'Normal',
    'high' => 'Alta'
];

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Crear boletín
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $category = sanitize($_POST['category'] ?? 'info');
        $priority = sanitize($_POST['priority'] ?? 'normal');
        $icon = sanitize($_POST['icon'] ?? 'bi-megaphone');
        $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
        $deadlineDate = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $expandedContent = sanitize($_POST['expanded_content'] ?? '');
        $actionUrl = sanitize($_POST['action_url'] ?? '');
        $actionLabel = sanitize($_POST['action_label'] ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            $message = 'El título y contenido son obligatorios.';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('INSERT INTO bulletins (title, content, category, priority, icon, event_date, deadline_date, expires_at, expanded_content, action_url, action_label, is_pinned, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $content, $category, $priority, $icon, $eventDate, $deadlineDate, $expiresAt, $expandedContent, $actionUrl, $actionLabel, $isPinned, $user['id']]);
            
            $message = 'Boletín creado exitosamente.';
            $messageType = 'success';
        }
    }
    
    // Actualizar boletín
    if ($action === 'update') {
        $id = (int)$_POST['bulletin_id'];
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $category = sanitize($_POST['category'] ?? 'info');
        $priority = sanitize($_POST['priority'] ?? 'normal');
        $icon = sanitize($_POST['icon'] ?? 'bi-megaphone');
        $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
        $deadlineDate = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $expandedContent = sanitize($_POST['expanded_content'] ?? '');
        $actionUrl = sanitize($_POST['action_url'] ?? '');
        $actionLabel = sanitize($_POST['action_label'] ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare('UPDATE bulletins SET title=?, content=?, category=?, priority=?, icon=?, event_date=?, deadline_date=?, expires_at=?, expanded_content=?, action_url=?, action_label=?, is_pinned=?, is_active=? WHERE id=?');
        $stmt->execute([$title, $content, $category, $priority, $icon, $eventDate, $deadlineDate, $expiresAt, $expandedContent, $actionUrl, $actionLabel, $isPinned, $isActive, $id]);
        
        $message = 'Boletín actualizado.';
        $messageType = 'success';
    }
    
    // Eliminar boletín
    if ($action === 'delete') {
        $id = (int)$_POST['bulletin_id'];
        $stmt = $pdo->prepare('DELETE FROM bulletins WHERE id = ?');
        $stmt->execute([$id]);
        
        $message = 'Boletín eliminado.';
        $messageType = 'success';
    }
    
    // Toggle estado
    if ($action === 'toggle_active') {
        $id = (int)$_POST['bulletin_id'];
        $stmt = $pdo->prepare('UPDATE bulletins SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        
        $message = 'Estado actualizado.';
        $messageType = 'success';
    }
    
    // Toggle pinned
    if ($action === 'toggle_pinned') {
        $id = (int)$_POST['bulletin_id'];
        $stmt = $pdo->prepare('UPDATE bulletins SET is_pinned = NOT is_pinned WHERE id = ?');
        $stmt->execute([$id]);
        
        $message = 'Boletín actualizado.';
        $messageType = 'success';
    }
}

// Obtener boletines
$bulletins = $pdo->query('
    SELECT b.*, u.name as author_name 
    FROM bulletins b 
    LEFT JOIN users u ON b.author_id = u.id 
    ORDER BY b.is_pinned DESC, b.created_at DESC
')->fetchAll();

// Estadísticas
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END) as destacados,
        SUM(CASE WHEN category = 'urgent' THEN 1 ELSE 0 END) as urgentes
    FROM bulletins
")->fetch();

// Editar boletín
$editBulletin = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM bulletins WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editBulletin = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Gestión de Boletines</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    
    <style>
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        .page-header { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 40px 0; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 0.85rem; color: #64748b; }
        
        .bulletin-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #e2e8f0; transition: all 0.3s; }
        .bulletin-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .bulletin-card.pinned { border-left-color: #f59e0b; background: linear-gradient(90deg, rgba(245,158,11,0.05), white); }
        .bulletin-card.inactive { opacity: 0.6; }
        
        .category-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .priority-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
        .priority-badge.high { background: #fef2f2; color: #dc2626; }
        .priority-badge.normal { background: #f0fdf4; color: #16a34a; }
        .priority-badge.low { background: #f8fafc; color: #64748b; }
        
        .form-section { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-section h5 { color: var(--primary); font-weight: 700; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
        
        .icon-selector { display: flex; flex-wrap: wrap; gap: 8px; }
        .icon-option { width: 40px; height: 40px; border: 2px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .icon-option:hover { border-color: var(--primary); background: #f8fafc; }
        .icon-option.selected { border-color: var(--primary); background: var(--primary); color: white; }
        .icon-option input { display: none; }
        
        .action-btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; }
        .action-btn:hover { transform: scale(1.1); }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-2"><i class="bi bi-pin-angle me-2"></i>Gestión de Boletines</h1>
                    <p class="mb-0 opacity-75">Administra los comunicados del boletín interno</p>
                </div>
                <a href="intranet_dashboard.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>
    
    <div class="container pb-5">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Boletines</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-value text-success"><?= $stats['activos'] ?></div>
                    <div class="stat-label">Activos</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-value text-warning"><?= $stats['destacados'] ?></div>
                    <div class="stat-label">Destacados</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-value text-danger"><?= $stats['urgentes'] ?></div>
                    <div class="stat-label">Urgentes</div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Formulario -->
            <div class="col-lg-5">
                <div class="form-section">
                    <h5><i class="bi bi-<?= $editBulletin ? 'pencil' : 'plus-circle' ?> me-2"></i><?= $editBulletin ? 'Editar Boletín' : 'Nuevo Boletín' ?></h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editBulletin ? 'update' : 'create' ?>">
                        <?php if ($editBulletin): ?>
                        <input type="hidden" name="bulletin_id" value="<?= $editBulletin['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Título *</label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editBulletin['title'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Contenido breve *</label>
                            <textarea name="content" class="form-control" rows="3" required><?= htmlspecialchars($editBulletin['content'] ?? '') ?></textarea>
                            <small class="text-muted">Se muestra en la vista resumida</small>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Categoría</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($categories as $key => $cat): ?>
                                    <option value="<?= $key ?>" <?= ($editBulletin['category'] ?? '') === $key ? 'selected' : '' ?>><?= $cat['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prioridad</label>
                                <select name="priority" class="form-select">
                                    <?php foreach ($priorities as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($editBulletin['priority'] ?? 'normal') === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ícono</label>
                            <div class="icon-selector">
                                <?php 
                                $icons = ['bi-megaphone-fill', 'bi-exclamation-triangle-fill', 'bi-calendar-event-fill', 'bi-info-circle-fill', 'bi-tools', 'bi-gift-fill', 'bi-people-fill', 'bi-star-fill', 'bi-bell-fill', 'bi-lightning-fill', 'bi-shield-fill', 'bi-heart-fill'];
                                foreach ($icons as $icon): 
                                $selected = ($editBulletin['icon'] ?? 'bi-megaphone-fill') === $icon;
                                ?>
                                <label class="icon-option <?= $selected ? 'selected' : '' ?>">
                                    <input type="radio" name="icon" value="<?= $icon ?>" <?= $selected ? 'checked' : '' ?>>
                                    <i class="bi <?= $icon ?>"></i>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha evento</label>
                                <input type="date" name="event_date" class="form-control" value="<?= $editBulletin['event_date'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha límite</label>
                                <input type="date" name="deadline_date" class="form-control" value="<?= $editBulletin['deadline_date'] ?? '' ?>">
                                <small class="text-muted">Para countdown</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Contenido expandido</label>
                            <textarea name="expanded_content" class="form-control" rows="3"><?= htmlspecialchars($editBulletin['expanded_content'] ?? '') ?></textarea>
                            <small class="text-muted">Se muestra al hacer click en "Ver más"</small>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">URL de acción</label>
                                <input type="url" name="action_url" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($editBulletin['action_url'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Texto del botón</label>
                                <input type="text" name="action_label" class="form-control" placeholder="Ej: Ver más" value="<?= htmlspecialchars($editBulletin['action_label'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fecha de expiración</label>
                            <input type="date" name="expires_at" class="form-control" value="<?= $editBulletin['expires_at'] ?? '' ?>">
                            <small class="text-muted">El boletín se ocultará después de esta fecha</small>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned" <?= ($editBulletin['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isPinned">Destacar (aparece primero)</label>
                            </div>
                            <?php if ($editBulletin): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($editBulletin['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Activo (visible en intranet)</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-<?= $editBulletin ? 'check-lg' : 'plus-lg' ?> me-2"></i><?= $editBulletin ? 'Guardar Cambios' : 'Crear Boletín' ?>
                            </button>
                            <?php if ($editBulletin): ?>
                            <a href="bulletin_admin.php" class="btn btn-outline-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de boletines -->
            <div class="col-lg-7">
                <div class="form-section">
                    <h5><i class="bi bi-list-ul me-2"></i>Boletines (<?= count($bulletins) ?>)</h5>
                    
                    <?php if (empty($bulletins)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">No hay boletines creados</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($bulletins as $b): ?>
                    <div class="bulletin-card <?= $b['is_pinned'] ? 'pinned' : '' ?> <?= !$b['is_active'] ? 'inactive' : '' ?>" style="border-left-color: <?= $categories[$b['category']]['color'] ?>;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:36px;height:36px;border-radius:8px;background:<?= $categories[$b['category']]['bg'] ?>;color:<?= $categories[$b['category']]['color'] ?>;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi <?= $b['icon'] ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($b['title']) ?></h6>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <?php if ($b['is_pinned']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-pin-fill"></i></span>
                                <?php endif; ?>
                                <?php if (!$b['is_active']): ?>
                                <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="text-muted small mb-2"><?= htmlspecialchars($b['content']) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="category-badge" style="background:<?= $categories[$b['category']]['bg'] ?>;color:<?= $categories[$b['category']]['color'] ?>;">
                                    <?= $categories[$b['category']]['label'] ?>
                                </span>
                                <span class="priority-badge <?= $b['priority'] ?>"><?= $priorities[$b['priority']] ?></span>
                                <?php if ($b['deadline_date']): ?>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d/m/Y', strtotime($b['deadline_date'])) ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-1">
                                <!-- Toggle Pinned -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_pinned">
                                    <input type="hidden" name="bulletin_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="action-btn btn btn-<?= $b['is_pinned'] ? 'warning' : 'outline-warning' ?>" title="Destacar">
                                        <i class="bi bi-pin<?= $b['is_pinned'] ? '-fill' : '' ?>"></i>
                                    </button>
                                </form>
                                
                                <!-- Toggle Active -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="bulletin_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="action-btn btn btn-<?= $b['is_active'] ? 'success' : 'outline-secondary' ?>" title="<?= $b['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi bi-<?= $b['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
                                    </button>
                                </form>
                                
                                <!-- Editar -->
                                <a href="?edit=<?= $b['id'] ?>" class="action-btn btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                
                                <!-- Eliminar -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este boletín?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="bulletin_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="action-btn btn btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Selector de íconos
        document.querySelectorAll('.icon-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>

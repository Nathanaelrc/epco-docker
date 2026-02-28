<?php
/**
 * EPCO - Detalle de Ticket (estilo ServiceNow)
 * Página dedicada para ver y gestionar un ticket individual
 */
require_once '../includes/bootstrap.php';
requireAuth('iniciar_sesion.php?redirect=detalle_ticket.php');

$user = getCurrentUser();
if (!in_array($user['role'], ['admin', 'soporte'])) {
    header('Location: index.php');
    exit;
}

$ticketId = (int)($_GET['id'] ?? 0);
if (!$ticketId) {
    header('Location: soporte_admin.php?page=tickets');
    exit;
}

// Labels
$statusColors = ['abierto' => 'primary', 'asignado' => 'info', 'en_proceso' => 'warning', 'pendiente' => 'secondary', 'en_pausa' => 'info', 'resuelto' => 'success', 'cerrado' => 'dark'];
$statusLabels = ['abierto' => 'Abierto', 'asignado' => 'Asignado', 'en_proceso' => 'En Proceso', 'pendiente' => 'Pendiente', 'en_pausa' => 'En Pausa', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
$priorityColors = ['urgente' => 'danger', 'alta' => 'warning', 'media' => 'info', 'baja' => 'secondary'];
$categoryLabels = ['hardware' => 'Hardware', 'software' => 'Software', 'red' => 'Red', 'acceso' => 'Acceso', 'otro' => 'Otro'];

// Técnicos
$technicians = $pdo->query("SELECT id, name, email, role FROM users WHERE role IN ('admin', 'soporte')")->fetchAll();

// Mensajes flash
$message = '';
$messageType = '';
if (isset($_GET['msg'])) {
    $msgs = [
        'updated' => ['Ticket actualizado correctamente', 'success'],
        'comment_added' => ['Comentario agregado', 'success'],
        'data_updated' => ['Datos del ticket actualizados', 'success'],
        'resolved' => ['Ticket resuelto y cerrado', 'success'],
        'deleted' => ['Ticket eliminado', 'success'],
        'error' => ['Error al procesar la solicitud', 'danger'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        [$message, $messageType] = $msgs[$_GET['msg']];
    }
}

// ========== PROCESAR ACCIONES POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectBase = "detalle_ticket.php?id={$ticketId}";
    
    // Actualizar ticket (estado, asignación, prioridad, resolución)
    if ($action === 'update_ticket_work') {
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $resolution = sanitize($_POST['resolution'] ?? '');
        $assignTo = !empty($_POST['assign_to']) ? (int)$_POST['assign_to'] : null;
        $priority = sanitize($_POST['priority'] ?? '');
        
        $currentStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $currentStmt->execute([$ticketId]);
        $currentTicket = $currentStmt->fetch();
        
        if ($currentTicket) {
            $stmt = $pdo->prepare('UPDATE tickets SET status = ?, resolution = ?, assigned_to = ?, priority = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newStatus, $resolution, $assignTo, $priority, $ticketId]);
            
            $changes = [];
            if ($currentTicket['status'] !== $newStatus) $changes[] = "Estado: {$newStatus}";
            if ($currentTicket['priority'] !== $priority) $changes[] = "Prioridad: {$priority}";
            if ($currentTicket['assigned_to'] != $assignTo) $changes[] = "Asignación actualizada";
            logActivity($user['id'], 'ticket_updated', 'tickets', $ticketId, implode(', ', $changes) ?: 'Ticket actualizado');
            
            // Correo al técnico asignado
            if ($assignTo && $currentTicket['assigned_to'] != $assignTo) {
                try {
                    $techStmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
                    $techStmt->execute([$assignTo]);
                    $techData = $techStmt->fetch();
                    if ($techData && !empty($techData['email'])) {
                        require_once __DIR__ . '/../includes/ServicioCorreo.php';
                        $mailService = new MailService();
                        $ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
                        $ticketStmt->execute([$ticketId]);
                        $ticketData = $ticketStmt->fetch();
                        $mailService->sendTicketAssignedNotification($ticketData, $techData['name'], $techData['email']);
                    }
                } catch (Exception $e) {}
            }
            
            // Correo si se resuelve/cierra
            if (in_array($newStatus, ['resuelto', 'cerrado']) && !in_array($currentTicket['status'], ['resuelto', 'cerrado'])) {
                try {
                    $ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
                    $ticketStmt->execute([$ticketId]);
                    $ticketData = $ticketStmt->fetch();
                    if ($ticketData && !empty($ticketData['user_email'])) {
                        require_once __DIR__ . '/../includes/ServicioCorreo.php';
                        $mailService = new MailService();
                        $ticketData['subject'] = $ticketData['title'];
                        $ticketData['resolution'] = $resolution;
                        $ticketData['status'] = $newStatus;
                        $mailService->sendTicketClosedNotification($ticketData, $user['name']);
                    }
                } catch (Exception $e) {}
            }
            
            header("Location: {$redirectBase}&msg=updated");
            exit;
        }
    }
    
    // Agregar comentario
    if ($action === 'add_comment') {
        $comment = sanitize($_POST['comment'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        
        if (!empty($comment)) {
            $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$ticketId, $user['id'], $user['name'], $comment, $isInternal]);
            $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$ticketId]);
            logActivity($user['id'], 'comment_added', 'tickets', $ticketId, 'Comentario agregado');
            
            header("Location: {$redirectBase}&msg=comment_added#actividad");
            exit;
        }
    }
    
    // Editar datos del ticket
    if ($action === 'update_ticket') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $userName = sanitize($_POST['user_name'] ?? '');
        $userEmail = sanitize($_POST['user_email'] ?? '');
        
        if (!empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('UPDATE tickets SET title = ?, description = ?, category = ?, user_name = ?, user_email = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $description, $category, $userName, $userEmail, $ticketId]);
            logActivity($user['id'], 'ticket_updated', 'tickets', $ticketId, 'Datos del ticket editados');
            
            header("Location: {$redirectBase}&msg=data_updated");
            exit;
        }
    }
    
    // Eliminar ticket
    if ($action === 'delete_ticket') {
        // Eliminar archivos
        $ticketStmt = $pdo->prepare('SELECT ticket_number FROM tickets WHERE id = ?');
        $ticketStmt->execute([$ticketId]);
        $td = $ticketStmt->fetch();
        if ($td) {
            $dir = __DIR__ . '/uploads/tickets/' . $td['ticket_number'];
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $f) unlink($dir . '/' . $f);
                rmdir($dir);
            }
        }
        $pdo->prepare('DELETE FROM ticket_comments WHERE ticket_id = ?')->execute([$ticketId]);
        $pdo->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?')->execute([$ticketId]);
        $pdo->prepare('DELETE FROM tickets WHERE id = ?')->execute([$ticketId]);
        logActivity($user['id'], 'ticket_deleted', 'tickets', $ticketId, 'Ticket eliminado');
        
        header("Location: soporte_admin.php?page=tickets&msg=ticket_deleted");
        exit;
    }
}

// ========== CARGAR DATOS DEL TICKET ==========
$stmt = $pdo->prepare("
    SELECT t.*, 
           COALESCE(u.name, t.user_name) as user_name, 
           a.name as assigned_name
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.id = ?
");
$stmt->execute([$ticketId]);
$t = $stmt->fetch();

if (!$t) {
    header('Location: soporte_admin.php?page=tickets');
    exit;
}

// Comentarios
$ticketComments = $pdo->prepare('SELECT * FROM ticket_comments WHERE ticket_id = ? ORDER BY created_at ASC');
$ticketComments->execute([$ticketId]);
$comments = $ticketComments->fetchAll();

// Evidencia
$ticketNum = $t['ticket_number'];
$evidenceDir = __DIR__ . '/uploads/tickets/' . $ticketNum;
$evidenceFiles = [];
$evidenceImages = [];
$evidenceDocs = [];
if (is_dir($evidenceDir)) {
    $scan = array_diff(scandir($evidenceDir), ['.', '..', '.gitkeep']);
    foreach ($scan as $f) { $evidenceFiles[$f] = 'uploads/tickets/' . $ticketNum . '/' . $f; }
}
foreach ($comments as $c) {
    if (preg_match('/Archivos adjuntos:\s*(.+)$/m', $c['comment'], $m)) {
        $names = array_map('trim', explode(',', $m[1]));
        foreach ($names as $fname) {
            $fname = trim($fname);
            if ($fname && !isset($evidenceFiles[$fname])) { $evidenceFiles[$fname] = 'uploads/tickets/' . $ticketNum . '/' . $fname; }
        }
    }
}
foreach ($evidenceFiles as $fn => $fu) {
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $evidenceImages[$fn] = $fu; } else { $evidenceDocs[$fn] = $fu; }
}
$totalEvidence = count($evidenceFiles);

$createdDate = new DateTime($t['created_at']);
$now = new DateTime();
$diffDays = $createdDate->diff($now)->days;
$updatedAt = $t['updated_at'] ?? $t['created_at'];

// Página de retorno
$returnPage = $_GET['from'] ?? 'dashboard';
$returnFilter = $_GET['filter'] ?? '';
$returnUrl = "soporte_admin.php?page={$returnPage}" . ($returnFilter ? "&filter={$returnFilter}" : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - <?= htmlspecialchars($t['ticket_number']) ?></title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0c5a8a;
            --primary-soft: #e0f2fe;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        * { font-family: 'Barlow', sans-serif; }
        body { background: var(--gray-50); }
        
        /* Header del ticket */
        .ticket-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c5a8a 100%);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ticket-header .ticket-id {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        /* Secciones */
        .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-500);
            margin-bottom: 12px;
        }
        .info-label {
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-bottom: 2px;
        }
        
        /* Cards */
        .detail-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
        }
        .detail-card .card-section {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        .detail-card .card-section:last-child {
            border-bottom: none;
        }
        
        /* Comentarios */
        .comment-item {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }
        .comment-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .comment-bubble {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Toast */
        .msg-banner {
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    
    <!-- Header del Ticket -->
    <div class="ticket-header">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-sm btn-outline-light" title="Volver">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
            <span class="ticket-id"><?= htmlspecialchars($t['ticket_number']) ?></span>
            <span class="badge bg-<?= $statusColors[$t['status']] ?> py-1 px-2"><?= $statusLabels[$t['status']] ?></span>
            <span class="badge bg-<?= $priorityColors[$t['priority']] ?> py-1 px-2"><?= ucfirst($t['priority']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <small class="text-white-50"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></small>
            <?php if ($t['assigned_name']): ?>
            <small class="text-white-50">| Asignado a: <strong class="text-white"><?= htmlspecialchars($t['assigned_name']) ?></strong></small>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mensaje flash -->
    <?php if ($message): ?>
    <div class="msg-banner bg-<?= $messageType === 'success' ? 'success' : 'danger' ?> text-white">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <div class="container-fluid p-4">
        <div class="row g-4">
            
            <!-- ========== COLUMNA IZQUIERDA: Información ========== -->
            <div class="col-lg-4">
                <div class="detail-card">
                    
                    <!-- Información General -->
                    <div class="card-section">
                        <div class="section-title">Información del Ticket</div>
                        <div class="mb-3">
                            <div class="info-label">Título</div>
                            <p class="mb-0 fw-semibold" style="font-size: 0.95rem;"><?= htmlspecialchars($t['title']) ?></p>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="info-label">Categoría</div>
                                <span class="badge bg-light text-dark border"><?= $categoryLabels[$t['category']] ?? $t['category'] ?></span>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Prioridad</div>
                                <span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Solicitante -->
                    <div class="card-section">
                        <div class="section-title">Solicitante</div>
                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: var(--gray-100);">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: var(--primary-soft); color: var(--primary-dark); font-weight: 600;">
                                <?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size: 0.88rem;"><?= htmlspecialchars($t['user_name'] ?? 'Sin nombre') ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($t['user_email'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Descripción -->
                    <div class="card-section">
                        <div class="section-title">Descripción</div>
                        <div class="p-3 rounded" style="background: var(--gray-50); border: 1px solid var(--gray-200); max-height: 200px; overflow-y: auto;">
                            <p class="mb-0" style="font-size: 0.88rem; line-height: 1.6; white-space: pre-line;"><?= htmlspecialchars($t['description']) ?></p>
                        </div>
                    </div>
                    
                    <!-- Fechas -->
                    <div class="card-section">
                        <div class="section-title">Fechas</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Creado:</small>
                                <small class="fw-semibold"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Actualizado:</small>
                                <small class="fw-semibold"><?= date('d/m/Y H:i', strtotime($updatedAt)) ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Antigüedad:</small>
                                <small class="badge bg-<?= $diffDays > 3 ? 'warning' : 'light' ?> text-<?= $diffDays > 3 ? 'dark' : 'muted' ?>"><?= $diffDays === 0 ? 'Hoy' : $diffDays . ' día' . ($diffDays > 1 ? 's' : '') ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Adjuntos -->
                    <div class="card-section">
                        <div class="section-title">Adjuntos (<?= $totalEvidence ?>)</div>
                        <?php if ($totalEvidence > 0): ?>
                            <?php if (count($evidenceImages) > 0): ?>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($evidenceImages as $fname => $furl):
                                    $displayName = preg_replace('/^[a-f0-9]+_/', '', $fname);
                                    $fullDiskPath = __DIR__ . '/' . $furl;
                                    if (file_exists($fullDiskPath)): ?>
                                <a href="<?= htmlspecialchars($furl) ?>" target="_blank" class="d-block rounded overflow-hidden" style="width: 64px; height: 64px; border: 2px solid var(--gray-200);">
                                    <img src="<?= htmlspecialchars($furl) ?>" alt="<?= htmlspecialchars($displayName) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </a>
                                <?php endif; endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (count($evidenceDocs) > 0): ?>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach ($evidenceDocs as $fname => $furl):
                                    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                                    $displayName = preg_replace('/^[a-f0-9]+_/', '', $fname);
                                    $fullDiskPath = __DIR__ . '/' . $furl;
                                    if (file_exists($fullDiskPath)): ?>
                                <a href="<?= htmlspecialchars($furl) ?>" download class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="background: var(--gray-100); font-size: 0.82rem;">
                                    <i class="bi bi-file-earmark text-muted"></i>
                                    <span class="text-truncate text-dark"><?= htmlspecialchars($displayName) ?></span>
                                    <i class="bi bi-download text-muted ms-auto"></i>
                                </a>
                                <?php endif; endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                        <div class="text-center py-3 rounded" style="background: var(--gray-50); border: 1px dashed #cbd5e1;">
                            <p class="mb-0 small text-muted">Sin archivos</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Editar Datos (colapsable) -->
                    <div class="card-section p-0">
                        <button class="btn btn-link w-100 text-start d-flex justify-content-between align-items-center py-2 px-3 text-decoration-none collapsed" 
                                type="button" data-bs-toggle="collapse" data-bs-target="#editSection"
                                style="background: var(--gray-50); color: var(--gray-500); font-size: 0.72rem; font-weight: 600; letter-spacing: 0.5px;">
                            <span>EDITAR DATOS DEL TICKET</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="collapse" id="editSection">
                            <div class="p-3">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_ticket">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Solicitante</label>
                                            <input type="text" name="user_name" class="form-control form-control-sm" value="<?= htmlspecialchars($t['user_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Email</label>
                                            <input type="email" name="user_email" class="form-control form-control-sm" value="<?= htmlspecialchars($t['user_email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Categoría</label>
                                            <select name="category" class="form-select form-select-sm">
                                                <option value="hardware" <?= $t['category'] === 'hardware' ? 'selected' : '' ?>>Hardware</option>
                                                <option value="software" <?= $t['category'] === 'software' ? 'selected' : '' ?>>Software</option>
                                                <option value="red" <?= $t['category'] === 'red' ? 'selected' : '' ?>>Red</option>
                                                <option value="acceso" <?= $t['category'] === 'acceso' ? 'selected' : '' ?>>Acceso</option>
                                                <option value="otro" <?= $t['category'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Título</label>
                                            <input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars($t['title']) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold">Descripción</label>
                                            <textarea name="description" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($t['description']) ?></textarea>
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="btn btn-dark btn-sm">Guardar Datos</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ========== COLUMNA DERECHA: Área de Trabajo ========== -->
            <div class="col-lg-8">
                
                <!-- Panel de Trabajo -->
                <div class="detail-card mb-4">
                    <div class="card-section">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_ticket_work">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size: 0.78rem;">Estado</label>
                                    <select name="new_status" class="form-select form-select-sm">
                                        <option value="abierto" <?= $t['status'] === 'abierto' ? 'selected' : '' ?>>Abierto</option>
                                        <option value="en_proceso" <?= $t['status'] === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                                        <option value="pendiente" <?= $t['status'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="en_pausa" <?= $t['status'] === 'en_pausa' ? 'selected' : '' ?>>En Pausa</option>
                                        <option value="resuelto" <?= $t['status'] === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                                        <option value="cerrado" <?= $t['status'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size: 0.78rem;">Asignado a</label>
                                    <select name="assign_to" class="form-select form-select-sm">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>" <?= $t['assigned_to'] == $tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size: 0.78rem;">Prioridad</label>
                                    <select name="priority" class="form-select form-select-sm">
                                        <option value="baja" <?= $t['priority'] === 'baja' ? 'selected' : '' ?>>Baja</option>
                                        <option value="media" <?= $t['priority'] === 'media' ? 'selected' : '' ?>>Media</option>
                                        <option value="alta" <?= $t['priority'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                        <option value="urgente" <?= $t['priority'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold" style="font-size: 0.78rem;">Notas de Resolución / Trabajo</label>
                                <textarea name="resolution" class="form-control" rows="3" placeholder="Escribe aquí las notas de trabajo, solución aplicada o información relevante..."><?= htmlspecialchars($t['resolution'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Actualizar Ticket</button>
                                    <button type="submit" class="btn btn-success btn-sm" onclick="document.querySelector('[name=new_status]').value='resuelto'; if(!document.querySelector('[name=resolution]').value.trim()) document.querySelector('[name=resolution]').value='Ticket resuelto por el equipo de soporte.';">
                                        Resolver y Cerrar
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Eliminar (separado) -->
                        <div class="mt-3 pt-3 border-top">
                            <form method="POST" onsubmit="return confirm('¿Eliminar este ticket? Esta acción no se puede deshacer.')">
                                <input type="hidden" name="action" value="delete_ticket">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar Ticket</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Actividad / Historial -->
                <div class="detail-card" id="actividad">
                    <div class="card-section py-2">
                        <div class="section-title mb-0">
                            Actividad / Notas de Trabajo
                            <span class="badge bg-secondary ms-1"><?= count($comments) ?></span>
                        </div>
                    </div>
                    
                    <div class="card-section" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($comments)): ?>
                        <div class="text-center py-5">
                            <p class="text-muted mb-0">Sin actividad registrada</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <div class="comment-avatar" style="background: <?= $c['is_internal'] ? '#fef3c7' : 'var(--primary-soft)' ?>; color: <?= $c['is_internal'] ? '#92400e' : 'var(--primary-dark)' ?>;">
                                <?= strtoupper(substr($c['user_name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <span class="fw-semibold" style="font-size: 0.85rem;"><?= htmlspecialchars($c['user_name'] ?? 'Sistema') ?></span>
                                        <?php if ($c['is_internal']): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Interno</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small>
                                </div>
                                <div class="comment-bubble" style="background: <?= $c['is_internal'] ? '#fef9c3' : 'var(--gray-100)' ?>;">
                                    <?php
                                    $commentText = htmlspecialchars($c['comment']);
                                    if (preg_match('/Archivos adjuntos:\s*(.+)$/m', $c['comment'], $cm)) {
                                        $fileNames = array_map('trim', explode(',', $cm[1]));
                                        $linkedNames = [];
                                        foreach ($fileNames as $fn) {
                                            $fn = trim($fn);
                                            if (!$fn) continue;
                                            $fileUrl = 'uploads/tickets/' . $ticketNum . '/' . $fn;
                                            $cleanName = preg_replace('/^[a-f0-9]+_/', '', $fn);
                                            $fExt = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
                                            $fIsImg = in_array($fExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            if ($fIsImg) {
                                                $linkedNames[] = '<a href="' . htmlspecialchars($fileUrl) . '" target="_blank" class="text-primary">' . htmlspecialchars($cleanName) . '</a>';
                                            } else {
                                                $linkedNames[] = '<a href="' . htmlspecialchars($fileUrl) . '" download class="text-primary">' . htmlspecialchars($cleanName) . '</a>';
                                            }
                                        }
                                        $commentText = str_replace(htmlspecialchars($cm[0]), 'Archivos adjuntos: ' . implode(', ', $linkedNames), $commentText);
                                    }
                                    echo nl2br($commentText);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Agregar Comentario -->
                    <div class="card-section border-top">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-2">
                                <textarea name="comment" class="form-control" rows="3" placeholder="Agregar nota de trabajo..." required></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" name="is_internal" class="form-check-input" id="internalCheck">
                                    <label class="form-check-label small text-muted" for="internalCheck">Nota interna (no visible al usuario)</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Enviar Comentario</button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

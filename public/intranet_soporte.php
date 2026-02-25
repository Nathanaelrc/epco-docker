<?php
/**
 * EPCO - Soporte TI Integrado en Intranet
 * Formulario de tickets y seguimiento sin salir de la intranet
 */
require_once '../includes/bootstrap.php';

requireAuth('login.php');
$user = getCurrentUser();

$success = '';
$error = '';
$ticketNumber = '';
$activeTab = $_GET['tab'] ?? 'crear';

// Configuración de uploads
$uploadDir = __DIR__ . '/uploads/tickets/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$maxFileSize = 5 * 1024 * 1024; // 5MB
$maxFiles = 5;

// Procesar creación de ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_ticket') {
        $category = sanitize($_POST['category'] ?? 'otro');
        $priority = sanitize($_POST['priority'] ?? 'media');
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($title) || empty($description)) {
            $error = 'Por favor complete todos los campos obligatorios.';
        } else {
            // Generar número de ticket
            $ticketNumber = 'TK-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Obtener SLA según prioridad
            $stmt = $pdo->prepare('SELECT first_response_minutes, resolution_minutes FROM sla_settings WHERE priority = ?');
            $stmt->execute([$priority]);
            $sla = $stmt->fetch();
            
            // Crear ticket
            $stmt = $pdo->prepare('INSERT INTO tickets (ticket_number, user_id, user_name, user_email, category, priority, title, description, sla_response_target, sla_resolution_target) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $ticketNumber, 
                $user['id'], 
                $user['name'], 
                $user['email'], 
                $category, 
                $priority, 
                $title, 
                $description,
                $sla['first_response_minutes'] ?? 120,
                $sla['resolution_minutes'] ?? 1440
            ]);
            $ticketId = $pdo->lastInsertId();
            
            // Procesar archivos adjuntos
            $uploadedFiles = [];
            if (!empty($_FILES['attachments']['name'][0])) {
                $ticketDir = $uploadDir . $ticketNumber . '/';
                if (!is_dir($ticketDir)) {
                    mkdir($ticketDir, 0755, true);
                }
                
                $fileCount = min(count($_FILES['attachments']['name']), $maxFiles);
                
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['attachments']['tmp_name'][$i];
                        $fileName = $_FILES['attachments']['name'][$i];
                        $fileSize = $_FILES['attachments']['size'][$i];
                        $fileType = $_FILES['attachments']['type'][$i];
                        
                        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxFileSize) {
                            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                            $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
                            $destination = $ticketDir . $newFileName;
                            
                            if (move_uploaded_file($tmpName, $destination)) {
                                $uploadedFiles[] = $newFileName;
                            }
                        }
                    }
                }
                
                if (!empty($uploadedFiles)) {
                    $filesText = "📎 Archivos adjuntos: " . implode(", ", $uploadedFiles);
                    $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_name, comment, is_internal) VALUES (?, ?, ?, 0)');
                    $stmt->execute([$ticketId, $user['name'], $filesText]);
                }
            }
            
            $success = "¡Ticket creado exitosamente! Tu número de seguimiento es: <strong>{$ticketNumber}</strong>";
            if (!empty($uploadedFiles)) {
                $success .= "<br><small class='text-muted'>Se adjuntaron " . count($uploadedFiles) . " archivo(s)</small>";
            }
            
            // Enviar notificación por correo al equipo de soporte y confirmación al usuario
            try {
                require_once __DIR__ . '/../includes/MailService.php';
                $mailService = new MailService();
                $ticketData = [
                    'id' => $ticketId,
                    'ticket_number' => $ticketNumber,
                    'subject' => $title,
                    'title' => $title,
                    'category' => $category,
                    'priority' => $priority,
                    'status' => 'abierto',
                    'user_name' => $user['name'],
                    'user_email' => $user['email'],
                    'department' => 'No especificado',
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $mailService->sendTicketCreatedNotification($ticketData);
                $mailService->sendTicketConfirmationToUser($ticketData);
            } catch (Exception $e) {
                error_log("Error enviando correo desde intranet_soporte: " . $e->getMessage());
            }
        }
    }
    
    // Agregar comentario a ticket
    if ($_POST['action'] === 'add_comment') {
        $ticketId = (int)$_POST['ticket_id'];
        $comment = sanitize($_POST['comment'] ?? '');
        
        if (!empty($comment) && $ticketId > 0) {
            $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal) VALUES (?, ?, ?, ?, 0)');
            $stmt->execute([$ticketId, $user['id'], $user['name'], $comment]);
            
            $stmt = $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?');
            $stmt->execute([$ticketId]);
            
            $success = 'Comentario agregado exitosamente.';
        }
        $activeTab = 'mis-tickets';
    }
}

// Obtener tickets del usuario
$stmt = $pdo->prepare('
    SELECT t.*, 
           (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.id) as comment_count
    FROM tickets t 
    WHERE t.user_id = ? 
    ORDER BY t.created_at DESC
');
$stmt->execute([$user['id']]);
$myTickets = $stmt->fetchAll();

// Estadísticas de tickets del usuario
$ticketStats = [
    'total' => count($myTickets),
    'abiertos' => 0,
    'en_progreso' => 0,
    'resueltos' => 0
];
foreach ($myTickets as $t) {
    if ($t['status'] === 'abierto') $ticketStats['abiertos']++;
    elseif ($t['status'] === 'en_progreso') $ticketStats['en_progreso']++;
    elseif (in_array($t['status'], ['resuelto', 'cerrado'])) $ticketStats['resueltos']++;
}

// FAQ items
$faqs = [
    ['question' => '¿Cómo puedo hacer seguimiento a mi ticket?', 'answer' => 'En la pestaña "Mis Tickets" puedes ver todos tus tickets activos y su estado actual.'],
    ['question' => '¿Cuánto tiempo demora la atención?', 'answer' => '<strong>Urgente:</strong> 4 horas | <strong>Alta:</strong> 8 horas | <strong>Media:</strong> 24 horas | <strong>Baja:</strong> 48 horas'],
    ['question' => '¿Qué información debo incluir?', 'answer' => 'Describe detalladamente el problema, incluye mensajes de error si los hay, pasos para reproducirlo y cambios recientes.'],
    ['question' => '¿Puedo adjuntar archivos?', 'answer' => 'Sí, puedes adjuntar hasta 5 archivos (imágenes, PDF, Word) de máximo 5MB cada uno.'],
    ['question' => '¿Qué hago en caso de emergencia?', 'answer' => 'Contacta directamente al <strong>interno 6479</strong> o envía correo a <strong>gismodes@puertocoquimbo.cl</strong> / <strong>asesorti@puertocoquimbo.cl</strong>']
];

$pageTitle = 'Soporte TI';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Soporte TI</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    <style>
        .support-hero {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 40px 0;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .support-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0ea5e9;
        }
        
        .ticket-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #0ea5e9;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .ticket-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .ticket-card.priority-urgente { border-left-color: #dc2626; }
        .ticket-card.priority-alta { border-left-color: #f59e0b; }
        .ticket-card.priority-media { border-left-color: #3b82f6; }
        .ticket-card.priority-baja { border-left-color: #6b7280; }
        
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .priority-baja { background: #f1f5f9; color: #475569; }
        .priority-media { background: #dbeafe; color: #1e40af; }
        .priority-alta { background: #fed7aa; color: #c2410c; }
        .priority-urgente { background: #fecaca; color: #b91c1c; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-abierto { background: #dbeafe; color: #1e40af; }
        .status-en_progreso { background: #fef3c7; color: #92400e; }
        .status-resuelto { background: #d1fae5; color: #065f46; }
        .status-cerrado { background: #f1f5f9; color: #475569; }
        
        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 25px;
            color: white;
        }
        
        .nav-pills .nav-link {
            color: #64748b;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .nav-pills .nav-link:hover {
            background: #f1f5f9;
        }
        
        .nav-pills .nav-link.active {
            background: #0ea5e9;
            color: white;
        }
        
        .faq-card {
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .accordion-button {
            background: transparent;
            font-weight: 500;
            color: #0ea5e9;
        }
        
        .accordion-button:not(.collapsed) {
            background: #f1f5f9;
            color: #0ea5e9;
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: #e2e8f0;
        }
        
        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: #0ea5e9;
            background: #f1f5f9;
        }
        
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .file-item {
            background: #e2e8f0;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .file-item .remove-file {
            cursor: pointer;
            color: #ef4444;
        }
        
        .ticket-detail-modal .modal-content {
            border-radius: 20px;
            overflow: hidden;
        }
        
        .comment-item {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        
        .comment-item.user-comment {
            background: #f1f5f9;
            margin-right: 20%;
        }
        
        .comment-item.agent-comment {
            background: #dbeafe;
            margin-left: 20%;
        }
        
        .comment-item.system-comment {
            background: #fef3c7;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Hero Section -->
    <div class="support-hero">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="text-white fw-bold mb-2">
                        <i class="bi bi-headset me-3"></i>Centro de Soporte TI
                    </h1>
                    <p class="text-white-50 mb-0">
                        ¿Necesitas ayuda? Crea un ticket y nuestro equipo te asistirá lo antes posible
                    </p>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3 mt-3 mt-lg-0">
                        <div class="col-6 col-md-3">
                            <div class="text-center text-white">
                                <div class="fs-2 fw-bold"><?= $ticketStats['total'] ?></div>
                                <small class="opacity-75">Total</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center text-white">
                                <div class="fs-2 fw-bold"><?= $ticketStats['abiertos'] ?></div>
                                <small class="opacity-75">Abiertos</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center text-white">
                                <div class="fs-2 fw-bold"><?= $ticketStats['en_progreso'] ?></div>
                                <small class="opacity-75">En Proceso</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center text-white">
                                <div class="fs-2 fw-bold"><?= $ticketStats['resueltos'] ?></div>
                                <small class="opacity-75">Resueltos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container pb-5">
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                <div><?= $success ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                <div><?= $error ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4 justify-content-center" id="supportTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'crear' ? 'active' : '' ?>" data-bs-toggle="pill" href="#tab-crear">
                    <i class="bi bi-plus-circle me-2"></i>Crear Ticket
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'mis-tickets' ? 'active' : '' ?>" data-bs-toggle="pill" href="#tab-tickets">
                    <i class="bi bi-ticket-detailed me-2"></i>Mis Tickets
                    <?php if ($ticketStats['abiertos'] + $ticketStats['en_progreso'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $ticketStats['abiertos'] + $ticketStats['en_progreso'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#tab-faq">
                    <i class="bi bi-question-circle me-2"></i>Ayuda Rápida
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Tab: Crear Ticket -->
            <div class="tab-pane fade <?= $activeTab === 'crear' ? 'show active' : '' ?>" id="tab-crear">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <div class="form-header">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="bi bi-headset" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-1">Nuevo Ticket de Soporte</h4>
                                        <p class="mb-0 opacity-75 small">Describe tu problema y te ayudaremos lo antes posible</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="p-4">
                                <input type="hidden" name="action" value="create_ticket">
                                
                                <!-- Info usuario (solo lectura) -->
                                <div class="alert alert-light rounded-3 mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-folder me-1"></i>Categoría
                                        </label>
                                        <select name="category" class="form-select" required>
                                            <option value="hardware">💻 Hardware / Equipos</option>
                                            <option value="software">📦 Software / Aplicaciones</option>
                                            <option value="red">🌐 Red / Internet</option>
                                            <option value="correo">📧 Correo Electrónico</option>
                                            <option value="acceso">🔐 Accesos / Contraseñas</option>
                                            <option value="impresora">🖨️ Impresoras</option>
                                            <option value="otro" selected>📋 Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-flag me-1"></i>Prioridad
                                        </label>
                                        <select name="priority" class="form-select" required>
                                            <option value="baja">🟢 Baja - Consulta general</option>
                                            <option value="media" selected>🔵 Media - Afecta mi trabajo</option>
                                            <option value="alta">🟠 Alta - Urgente, bloquea mi trabajo</option>
                                            <option value="urgente">🔴 Urgente - Afecta a múltiples usuarios</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-chat-left-text me-1"></i>Asunto <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="title" class="form-control" required
                                               placeholder="Ej: No puedo acceder al correo desde esta mañana">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-text-paragraph me-1"></i>Descripción detallada <span class="text-danger">*</span>
                                        </label>
                                        <textarea name="description" class="form-control" rows="5" required
                                                  placeholder="Describe el problema con el mayor detalle posible:&#10;- ¿Qué estabas haciendo?&#10;- ¿Qué mensaje de error aparece?&#10;- ¿Desde cuándo ocurre?"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-paperclip me-1"></i>Archivos adjuntos <small class="text-muted">(opcional)</small>
                                        </label>
                                        <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                                            <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                                            <p class="mb-1">Arrastra archivos aquí o haz clic para seleccionar</p>
                                            <small class="text-muted">Máximo 5 archivos, 5MB cada uno (imágenes, PDF, Word)</small>
                                        </div>
                                        <input type="file" name="attachments[]" id="fileInput" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx" style="display: none;">
                                        <div class="file-preview" id="filePreview"></div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="bi bi-send me-2"></i>Enviar Ticket
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Mis Tickets -->
            <div class="tab-pane fade <?= $activeTab === 'mis-tickets' ? 'show active' : '' ?>" id="tab-tickets">
                <?php if (empty($myTickets)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-ticket-detailed fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="text-muted">No tienes tickets registrados</h5>
                    <p class="text-muted">Crea tu primer ticket de soporte si necesitas ayuda</p>
                    <button class="btn btn-primary" onclick="document.querySelector('[href=\'#tab-crear\']').click()">
                        <i class="bi bi-plus-circle me-2"></i>Crear Ticket
                    </button>
                </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($myTickets as $ticket): ?>
                    <div class="col-lg-6">
                        <div class="ticket-card priority-<?= $ticket['priority'] ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-dark mb-2"><?= $ticket['ticket_number'] ?></span>
                                    <h5 class="mb-1"><?= htmlspecialchars($ticket['title']) ?></h5>
                                </div>
                                <span class="status-badge status-<?= $ticket['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                                </span>
                            </div>
                            
                            <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($ticket['description']) ?>
                            </p>
                            
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="priority-badge priority-<?= $ticket['priority'] ?>">
                                    <?= ucfirst($ticket['priority']) ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-folder me-1"></i><?= ucfirst($ticket['category']) ?>
                                </span>
                                <?php if ($ticket['comment_count'] > 0): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-chat me-1"></i><?= $ticket['comment_count'] ?> comentarios
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                </small>
                                <button class="btn btn-sm btn-outline-primary" onclick="showTicketDetail(<?= $ticket['id'] ?>)">
                                    <i class="bi bi-eye me-1"></i>Ver detalle
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: FAQ -->
            <div class="tab-pane fade" id="tab-faq">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="faq-card p-4">
                            <h5 class="mb-4"><i class="bi bi-question-circle me-2"></i>Preguntas Frecuentes</h5>
                            <div class="accordion" id="faqAccordion">
                                <?php foreach ($faqs as $i => $faq): ?>
                                <div class="accordion-item border-0 border-bottom">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                                            <?= $faq['question'] ?>
                                        </button>
                                    </h2>
                                    <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <?= $faq['answer'] ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4 p-4 bg-light rounded-3">
                                <h6><i class="bi bi-telephone me-2"></i>¿Necesitas ayuda inmediata?</h6>
                                <p class="mb-0 text-muted">
                                    Llama al <strong>interno 6479</strong> o escríbenos a 
                                    <a href="mailto:gismodes@puertocoquimbo.cl">gismodes@puertocoquimbo.cl</a> / <a href="mailto:asesorti@puertocoquimbo.cl">asesorti@puertocoquimbo.cl</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Detalle de Ticket -->
    <div class="modal fade ticket-detail-modal" id="ticketDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-ticket-detailed me-2"></i><span id="modalTicketNumber"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalTicketBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload preview
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const uploadArea = document.querySelector('.file-upload-area');
        
        fileInput.addEventListener('change', updateFilePreview);
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            updateFilePreview();
        });
        
        function updateFilePreview() {
            filePreview.innerHTML = '';
            const files = fileInput.files;
            
            for (let i = 0; i < Math.min(files.length, 5); i++) {
                const file = files[i];
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <i class="bi bi-file-earmark"></i>
                    <span>${file.name}</span>
                    <small class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</small>
                `;
                filePreview.appendChild(div);
            }
        }
        
        // Ticket detail modal
        async function showTicketDetail(ticketId) {
            const modal = new bootstrap.Modal(document.getElementById('ticketDetailModal'));
            modal.show();
            
            document.getElementById('modalTicketBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            `;
            
            try {
                const res = await fetch(`api/ticket_detail.php?id=${ticketId}`);
                const data = await res.json();
                
                if (data.error) {
                    document.getElementById('modalTicketBody').innerHTML = `
                        <div class="alert alert-danger">${data.error}</div>
                    `;
                    return;
                }
                
                document.getElementById('modalTicketNumber').textContent = data.ticket.ticket_number;
                
                let commentsHtml = '';
                if (data.comments && data.comments.length > 0) {
                    data.comments.forEach(c => {
                        const isUser = c.user_id == <?= $user['id'] ?>;
                        const isSystem = c.is_internal == 2;
                        let commentClass = isSystem ? 'system-comment' : (isUser ? 'user-comment' : 'agent-comment');
                        commentsHtml += `
                            <div class="comment-item ${commentClass}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>${c.user_name || 'Sistema'}</strong>
                                    <small class="text-muted">${new Date(c.created_at).toLocaleString('es-CL')}</small>
                                </div>
                                <p class="mb-0">${c.comment}</p>
                            </div>
                        `;
                    });
                }
                
                document.getElementById('modalTicketBody').innerHTML = `
                    <div class="mb-4">
                        <h5>${data.ticket.title}</h5>
                        <div class="d-flex gap-2 mb-3">
                            <span class="priority-badge priority-${data.ticket.priority}">${data.ticket.priority}</span>
                            <span class="status-badge status-${data.ticket.status}">${data.ticket.status.replace('_', ' ')}</span>
                            <span class="badge bg-light text-dark">${data.ticket.category}</span>
                        </div>
                        <p class="text-muted">${data.ticket.description}</p>
                        <small class="text-muted">
                            Creado: ${new Date(data.ticket.created_at).toLocaleString('es-CL')}
                            ${data.ticket.assigned_name ? ' | Asignado a: ' + data.ticket.assigned_name : ''}
                        </small>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="bi bi-chat-dots me-2"></i>Conversación</h6>
                    <div class="comments-list mb-4">
                        ${commentsHtml || '<p class="text-muted text-center">Sin comentarios aún</p>'}
                    </div>
                    
                    ${data.ticket.status !== 'cerrado' ? `
                    <form method="POST" class="border-top pt-3">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="ticket_id" value="${data.ticket.id}">
                        <div class="mb-3">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Escribe un mensaje..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Enviar Mensaje
                        </button>
                    </form>
                    ` : '<div class="alert alert-secondary text-center">Este ticket está cerrado</div>'}
                `;
            } catch (e) {
                document.getElementById('modalTicketBody').innerHTML = `
                    <div class="alert alert-danger">Error al cargar el ticket</div>
                `;
            }
        }
    </script>
</body>
</html>

<?php
/**
 * EPCO - Dashboard Admin Soporte TI
 * Sistema completo con pestañas
 */
require_once '../includes/bootstrap.php';

// Verificar autenticación
requireAuth('iniciar_sesion.php?redirect=soporte_admin.php');

$user = getCurrentUser();

// Solo admin y soporte pueden acceder
if (!in_array($user['role'], ['admin', 'soporte'])) {
    // Mostrar página de error para usuarios no autorizados
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Empresa Portuaria Coquimbo - Acceso Denegado</title>
        <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
        <link href="css/soporte-admin.css?v=2" rel="stylesheet">
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">
                <i class="bi bi-x-circle"></i>
            </div>
            <h2 class="fw-bold mb-3">Acceso Denegado</h2>
            <p class="text-muted mb-4">No tienes permisos para acceder al Dashboard de Soporte TI. Esta área es exclusiva para personal de soporte técnico.</p>
            <a href="index.php" class="btn btn-dark btn-lg px-5">
                <i class="bi bi-house me-2"></i>Volver al Inicio
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$isAdmin = $user['role'] === 'admin';
$page = $_GET['page'] ?? 'dashboard';
$filter = $_GET['filter'] ?? '';

// Procesar mensajes de redirección
$message = '';
$messageType = '';

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'assigned':
            $message = 'Ticket asignado correctamente';
            $messageType = 'success';
            break;
        case 'unassigned':
            $message = 'Ticket desasignado correctamente';
            $messageType = 'success';
            break;
        case 'status_updated':
            $message = 'Estado actualizado correctamente';
            $messageType = 'success';
            break;
        case 'comment_added':
            $message = 'Comentario agregado';
            $messageType = 'success';
            break;
        case 'ticket_created':
            $ticketNum = htmlspecialchars($_GET['ticket'] ?? '');
            $message = "Ticket <strong>$ticketNum</strong> creado exitosamente";
            $messageType = 'success';
            break;
        case 'ticket_updated':
            $message = 'Ticket actualizado correctamente';
            $messageType = 'success';
            break;
        case 'ticket_deleted':
            $message = 'Ticket eliminado correctamente';
            $messageType = 'success';
            break;
        case 'bulk_deleted':
            $count = (int)($_GET['count'] ?? 0);
            $message = "{$count} ticket" . ($count > 1 ? 's' : '') . " eliminado" . ($count > 1 ? 's' : '') . " correctamente";
            $messageType = 'success';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforcePostCsrf();
    $action = $_POST['action'] ?? '';
    
    // ========== PROCESAMIENTO POST ==========
    
    // Asignar ticket (admin y soporte pueden asignar)
    if ($action === 'assign_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        $assignTo = (int)$_POST['assign_to'];
        $stmt = $pdo->prepare('UPDATE tickets SET assigned_to = ?, status = CASE WHEN ? > 0 THEN "en_proceso" ELSE status END WHERE id = ?');
        $stmt->execute([$assignTo ?: null, $assignTo, $ticketId]);
        logActivity($user['id'], 'ticket_assigned', 'tickets', $ticketId, "Ticket asignado a usuario #$assignTo");
        
        // Enviar correo al técnico asignado
        if ($assignTo > 0) {
            try {
                $assigneeStmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
                $assigneeStmt->execute([$assignTo]);
                $assignee = $assigneeStmt->fetch();
                if ($assignee && !empty($assignee['email'])) {
                    $ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
                    $ticketStmt->execute([$ticketId]);
                    $ticketData = $ticketStmt->fetch();
                    if ($ticketData) {
                        require_once __DIR__ . '/../includes/ServicioCorreo.php';
                        $mailService = new MailService();
                        $mailService->sendTicketAssignedNotification($ticketData, $assignee['name'], $assignee['email']);
                    }
                }
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de asignación: ' . $e->getMessage());
            }
        }
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=assigned';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Desasignar ticket
    if ($action === 'unassign_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        $stmt = $pdo->prepare('UPDATE tickets SET assigned_to = NULL WHERE id = ?');
        $stmt->execute([$ticketId]);
        logActivity($user['id'], 'ticket_unassigned', 'tickets', $ticketId, 'Ticket desasignado');
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=unassigned';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Cambiar estado del ticket
    if ($action === 'change_status') {
        $ticketId = (int)$_POST['ticket_id'];
        $newStatus = sanitize($_POST['new_status']);
        $resolution = sanitize($_POST['resolution'] ?? '');
        
        // Obtener estado anterior antes del UPDATE
        $prevStmt = $pdo->prepare('SELECT t.id, t.status, t.title, t.user_email, t.user_name, t.ticket_number, a.name as assigned_name, a.email as assigned_email FROM tickets t LEFT JOIN users a ON t.assigned_to = a.id WHERE t.id = ?');
        $prevStmt->execute([$ticketId]);
        $prevTicket = $prevStmt->fetch();
        $oldStatus = $prevTicket['status'] ?? 'abierto';
        
        $stmt = $pdo->prepare('UPDATE tickets SET status = ?, resolution = ? WHERE id = ?');
        $stmt->execute([$newStatus, $resolution, $ticketId]);
        logActivity($user['id'], 'ticket_updated', 'tickets', $ticketId, "Estado cambiado a: $newStatus");
        
        // Enviar correo al usuario cuando el ticket se cierra o resuelve
        if (in_array($newStatus, ['resuelto', 'cerrado'])) {
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
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de cierre: ' . $e->getMessage());
            }
        }
        
        // Notificar cambio de estado al creador y al técnico asignado (excepto resuelto/cerrado que ya envían su propia notificación)
        if (!in_array($newStatus, ['resuelto', 'cerrado']) && $prevTicket) {
            try {
                require_once __DIR__ . '/../includes/ServicioCorreo.php';
                $mailService = new MailService();
                $mailService->sendTicketStatusUpdateNotification(
                    $prevTicket, $oldStatus, $newStatus, $user['name'],
                    $prevTicket['assigned_email'] ?? null, $prevTicket['assigned_name'] ?? null
                );
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de estado: ' . $e->getMessage());
            }
        }
        header("Location: $redirectUrl");
        exit;
    }
    
    // Actualizar ticket desde panel de trabajo unificado (estilo ServiceNow)
    if ($action === 'update_ticket_work') {
        $ticketId = (int)$_POST['ticket_id'];
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $resolution = sanitize($_POST['resolution'] ?? '');
        $assignTo = !empty($_POST['assign_to']) ? (int)$_POST['assign_to'] : null;
        $priority = sanitize($_POST['priority'] ?? '');
        
        // Obtener datos actuales del ticket para comparar
        $currentStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $currentStmt->execute([$ticketId]);
        $currentTicket = $currentStmt->fetch();
        
        $changes = [];
        
        // Actualizar todos los campos
        $stmt = $pdo->prepare('UPDATE tickets SET status = ?, resolution = ?, assigned_to = ?, priority = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newStatus, $resolution, $assignTo, $priority, $ticketId]);
        
        // Registrar cambios en log
        if ($currentTicket['status'] !== $newStatus) $changes[] = "Estado: {$newStatus}";
        if ($currentTicket['priority'] !== $priority) $changes[] = "Prioridad: {$priority}";
        if ($currentTicket['assigned_to'] != $assignTo) $changes[] = "Asignación actualizada";
        
        logActivity($user['id'], 'ticket_updated', 'tickets', $ticketId, implode(', ', $changes) ?: 'Ticket actualizado');
        
        // Enviar correo al técnico si se cambió la asignación
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
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de asignación: ' . $e->getMessage());
            }
        }
        
        // Enviar correo al usuario cuando el ticket se cierra o resuelve
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
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de cierre: ' . $e->getMessage());
            }
        }
        
        // Notificar cambio de estado al creador y técnico asignado
        if ($currentTicket['status'] !== $newStatus && !in_array($newStatus, ['resuelto', 'cerrado'])) {
            try {
                $ticketStmt = $pdo->prepare('SELECT t.*, a.name as assigned_name, a.email as assigned_email FROM tickets t LEFT JOIN users a ON t.assigned_to = a.id WHERE t.id = ?');
                $ticketStmt->execute([$ticketId]);
                $ticketData = $ticketStmt->fetch();
                if ($ticketData) {
                    require_once __DIR__ . '/../includes/ServicioCorreo.php';
                    $mailService = new MailService();
                    $mailService->sendTicketStatusUpdateNotification(
                        $ticketData, $currentTicket['status'], $newStatus, $user['name'],
                        $ticketData['assigned_email'] ?? null, $ticketData['assigned_name'] ?? null
                    );
                }
            } catch (Exception $e) {
                error_log('[EPCO] Error enviando email de estado: ' . $e->getMessage());
            }
        }
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=ticket_updated';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Auto-asignarse ticket (soporte)
    if ($action === 'self_assign') {
        $ticketId = (int)$_POST['ticket_id'];
        $stmt = $pdo->prepare('UPDATE tickets SET assigned_to = ?, status = "en_proceso" WHERE id = ?');
        $stmt->execute([$user['id'], $ticketId]);
        logActivity($user['id'], 'ticket_assigned', 'tickets', $ticketId, 'Auto-asignación de ticket');
        
        // Enviar correo al técnico (a sí mismo)
        try {
            $ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
            $ticketStmt->execute([$ticketId]);
            $ticketData = $ticketStmt->fetch();
            if ($ticketData && !empty($user['email'])) {
                require_once __DIR__ . '/../includes/ServicioCorreo.php';
                $mailService = new MailService();
                $mailService->sendTicketAssignedNotification($ticketData, $user['name'], $user['email']);
            }
        } catch (Exception $e) {
            error_log('[EPCO] Error enviando email de auto-asignación: ' . $e->getMessage());
        }
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=assigned';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Actualizar ticket (editar campos)
    if ($action === 'update_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $priority = sanitize($_POST['priority'] ?? '');
        $userName = sanitize($_POST['user_name'] ?? '');
        $userEmail = sanitize($_POST['user_email'] ?? '');
        
        if (!empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('UPDATE tickets SET title = ?, description = ?, category = ?, priority = ?, user_name = ?, user_email = ? WHERE id = ?');
            $stmt->execute([$title, $description, $category, $priority, $userName, $userEmail, $ticketId]);
            
            // Actualizar SLA si cambió la prioridad
            $slaStmt = $pdo->prepare('SELECT first_response_minutes, resolution_minutes FROM sla_settings WHERE priority = ?');
            $slaStmt->execute([$priority]);
            $sla = $slaStmt->fetch();
            if ($sla) {
                $pdo->prepare('UPDATE tickets SET sla_response_target = ?, sla_resolution_target = ? WHERE id = ?')
                    ->execute([$sla['first_response_minutes'], $sla['resolution_minutes'], $ticketId]);
            }
            
            logActivity($user['id'], 'ticket_updated', 'tickets', $ticketId, "Ticket editado: $title");
            
            $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=ticket_updated';
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    // Eliminar ticket
    if ($action === 'delete_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        
        // Obtener número de ticket para el log
        $stmt = $pdo->prepare('SELECT ticket_number, title FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $delTicket = $stmt->fetch();
        
        if ($delTicket) {
            // Eliminar archivos adjuntos del filesystem
            $ticketDir = __DIR__ . '/uploads/tickets/' . $delTicket['ticket_number'] . '/';
            if (is_dir($ticketDir)) {
                $files = glob($ticketDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) unlink($file);
                }
                rmdir($ticketDir);
            }
            
            // Eliminar registros relacionados (cascada manual por seguridad)
            $pdo->prepare('DELETE FROM ticket_comments WHERE ticket_id = ?')->execute([$ticketId]);
            $pdo->prepare('DELETE FROM ticket_history WHERE ticket_id = ?')->execute([$ticketId]);
            $pdo->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?')->execute([$ticketId]);
            $pdo->prepare('DELETE FROM ticket_surveys WHERE ticket_id = ?')->execute([$ticketId]);
            $pdo->prepare('DELETE FROM tickets WHERE id = ?')->execute([$ticketId]);
            
            logActivity($user['id'], 'ticket_deleted', 'tickets', $ticketId, "Ticket {$delTicket['ticket_number']} eliminado: {$delTicket['title']}");
            
            $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=ticket_deleted';
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    // Eliminar tickets en masa
    if ($action === 'bulk_delete_tickets') {
        $ids = $_POST['ticket_ids'] ?? [];
        if (is_array($ids) && count($ids) > 0) {
            $deleted = 0;
            foreach ($ids as $tid) {
                $tid = (int)$tid;
                $stmt = $pdo->prepare('SELECT ticket_number, title FROM tickets WHERE id = ?');
                $stmt->execute([$tid]);
                $delTicket = $stmt->fetch();
                if ($delTicket) {
                    $ticketDir = __DIR__ . '/uploads/tickets/' . $delTicket['ticket_number'] . '/';
                    if (is_dir($ticketDir)) {
                        $files = glob($ticketDir . '*');
                        foreach ($files as $file) { if (is_file($file)) unlink($file); }
                        @rmdir($ticketDir);
                    }
                    $pdo->prepare('DELETE FROM ticket_comments WHERE ticket_id = ?')->execute([$tid]);
                    $pdo->prepare('DELETE FROM ticket_history WHERE ticket_id = ?')->execute([$tid]);
                    $pdo->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?')->execute([$tid]);
                    $pdo->prepare('DELETE FROM ticket_surveys WHERE ticket_id = ?')->execute([$tid]);
                    $pdo->prepare('DELETE FROM tickets WHERE id = ?')->execute([$tid]);
                    logActivity($user['id'], 'ticket_deleted', 'tickets', $tid, "Ticket {$delTicket['ticket_number']} eliminado (masivo)");
                    $deleted++;
                }
            }
            $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=bulk_deleted&count=' . $deleted;
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    // Agregar comentario
    if ($action === 'add_comment') {
        $ticketId = (int)$_POST['ticket_id'];
        $comment = sanitize($_POST['comment']);
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        
        $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$ticketId, $user['id'], $user['name'], $comment, $isInternal]);
        logActivity($user['id'], 'comment_added', 'tickets', $ticketId, 'Comentario agregado al ticket');
        
        // Notificar comentario al creador y técnico asignado
        try {
            $ticketStmt = $pdo->prepare('SELECT t.*, a.name as assigned_name, a.email as assigned_email FROM tickets t LEFT JOIN users a ON t.assigned_to = a.id WHERE t.id = ?');
            $ticketStmt->execute([$ticketId]);
            $ticketData = $ticketStmt->fetch();
            if ($ticketData) {
                require_once __DIR__ . '/../includes/ServicioCorreo.php';
                $mailService = new MailService();
                $mailService->sendTicketCommentNotification(
                    $ticketData, $comment, $user['name'], (bool)$isInternal,
                    $ticketData['assigned_email'] ?? null, $ticketData['assigned_name'] ?? null
                );
            }
        } catch (Exception $e) {
            error_log('[EPCO] Error enviando email de comentario: ' . $e->getMessage());
        }
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=comment_added';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Crear ticket desde el dashboard
    if ($action === 'create_ticket') {
        $name = sanitize($_POST['name'] ?? $user['name']);
        $email = sanitize($_POST['email'] ?? $user['email']);
        $category = sanitize($_POST['category'] ?? 'otro');
        $priority = sanitize($_POST['priority'] ?? 'media');
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($title) || empty($description)) {
            $message = 'Por favor complete todos los campos obligatorios.';
            $messageType = 'danger';
        } else {
            // Verificar si el usuario existe
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            
            if (!$existingUser) {
                $tempPass = password_hash(uniqid(), PASSWORD_BCRYPT);
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $name));
                $baseUsername = $username;
                $counter = 1;
                while (true) {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                    $stmt->execute([$username]);
                    if (!$stmt->fetch()) break;
                    $username = $baseUsername . $counter;
                    $counter++;
                }
                $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, "user")');
                $stmt->execute([$name, $username, $email, $tempPass]);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $existingUser['id'];
            }
            
            // Generar número de ticket
            $ticketNumber = 'TK-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Obtener SLA según prioridad
            $stmt = $pdo->prepare('SELECT first_response_minutes, resolution_minutes FROM sla_settings WHERE priority = ?');
            $stmt->execute([$priority]);
            $sla = $stmt->fetch();
            
            // Crear ticket
            $stmt = $pdo->prepare('INSERT INTO tickets (ticket_number, user_id, user_name, user_email, category, priority, title, description, sla_response_target, sla_resolution_target) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $ticketNumber, $userId, $name, $email, $category, $priority, $title, $description,
                $sla['first_response_minutes'] ?? 120,
                $sla['resolution_minutes'] ?? 1440
            ]);
            $ticketId = $pdo->lastInsertId();
            
            // Procesar archivos adjuntos si hay
            $uploadDir = __DIR__ . '/uploads/tickets/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $maxFileSize = 5 * 1024 * 1024;
            $uploadedFiles = [];
            
            if (!empty($_FILES['attachments']['name'][0])) {
                $ticketDir = $uploadDir . $ticketNumber . '/';
                if (!is_dir($ticketDir)) {
                    mkdir($ticketDir, 0755, true);
                }
                
                $fileCount = min(count($_FILES['attachments']['name']), 5);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['attachments']['tmp_name'][$i];
                        $fileName = $_FILES['attachments']['name'][$i];
                        $fileSize = $_FILES['attachments']['size'][$i];
                        $fileType = $_FILES['attachments']['type'][$i];
                        
                        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxFileSize) {
                            $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
                            $destination = $ticketDir . $newFileName;
                            
                            if (move_uploaded_file($tmpName, $destination)) {
                                // Comprimir imagen automáticamente
                                comprimirImagenSubida($destination);
                                $uploadedFiles[] = $newFileName;
                            }
                        }
                    }
                }
                
                if (!empty($uploadedFiles)) {
                    $filesText = "📎 Archivos adjuntos: " . implode(", ", $uploadedFiles);
                    $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_name, comment, is_internal) VALUES (?, ?, ?, 0)');
                    $stmt->execute([$ticketId, $name, $filesText]);
                }
            }
            
            $message = "Ticket <strong>$ticketNumber</strong> creado exitosamente";
            if (!empty($uploadedFiles)) {
                $message .= " con " . count($uploadedFiles) . " archivo(s) adjunto(s)";
            }
            $messageType = 'success';
            logActivity($user['id'], 'ticket_created', 'tickets', $ticketId, "Ticket $ticketNumber creado desde panel admin: $title");
            
            // Enviar notificación por correo
            try {
                require_once __DIR__ . '/../includes/ServicioCorreo.php';
                $mailService = new MailService();
                $ticketData = [
                    'id' => $ticketId,
                    'ticket_number' => $ticketNumber,
                    'subject' => $title,
                    'category' => $category,
                    'priority' => $priority,
                    'status' => 'abierto',
                    'user_name' => $name,
                    'user_email' => $email,
                    'department' => 'No especificado',
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $mailService->sendTicketCreatedNotification($ticketData);
                $mailService->sendTicketConfirmationToUser($ticketData);
            } catch (Exception $e) {
                error_log("Error enviando correo desde admin: " . $e->getMessage());
            }
            
            // Redirigir al dashboard
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=dashboard&msg=ticket_created&ticket=$ticketNumber");
            exit;
        }
    }
    
    // CRUD Usuarios (solo admin)
    if ($isAdmin) {
        if ($action === 'create_user') {
            $name = sanitize($_POST['name']);
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $role = sanitize($_POST['role']);
            
            // Generar username si no se proporciona
            if (empty($username)) {
                $username = generateUsername($name);
            }
            
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'El email ya está registrado';
                $messageType = 'danger';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $message = 'El nombre de usuario ya existe';
                    $messageType = 'danger';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $username, $email, $password, $role]);
                    logActivity($user['id'], 'user_created', 'users', $pdo->lastInsertId(), "Usuario '$name' creado con rol $role");
                    $message = 'Usuario creado correctamente';
                    $messageType = 'success';
                }
            }
        }
        
        if ($action === 'update_user') {
            $userId = (int)$_POST['user_id'];
            $name = sanitize($_POST['name']);
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = ? WHERE id = ?');
                $stmt->execute([$name, $username, $email, $password, $role, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?');
                $stmt->execute([$name, $username, $email, $role, $userId]);
            }
            logActivity($user['id'], 'user_updated', 'users', $userId, "Usuario '$name' actualizado");
            $message = 'Usuario actualizado correctamente';
            $messageType = 'success';
        }
        
        if ($action === 'delete_user') {
            $userId = (int)$_POST['user_id'];
            if ($userId != $user['id']) {
                // Obtener nombre antes de eliminar
                $delStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $delStmt->execute([$userId]);
                $delUser = $delStmt->fetch();
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                logActivity($user['id'], 'user_deleted', 'users', $userId, "Usuario '" . ($delUser['name'] ?? 'desconocido') . "' eliminado");
                $message = 'Usuario eliminado';
                $messageType = 'success';
            } else {
                $message = 'No puedes eliminarte a ti mismo';
                $messageType = 'danger';
            }
        }
    }
    
    // ===== GESTIÓN DE DESTINATARIOS DE NOTIFICACIONES =====
    if ($action === 'add_notification_email') {
        $notifEmail = sanitize($_POST['notif_email'] ?? '');
        $notifName = sanitize($_POST['notif_name'] ?? '');
        $notifEvent = sanitize($_POST['notif_event'] ?? 'all');
        
        if (!filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'El correo electrónico ingresado no es válido';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO notification_recipients (email, name, event_type, created_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$notifEmail, $notifName, $notifEvent, $user['id']]);
                logActivity($user['id'], 'notification_email_added', 'notification_recipients', $pdo->lastInsertId(), "Correo agregado: $notifEmail ($notifEvent)");
                $message = "Correo <strong>$notifEmail</strong> agregado correctamente";
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'Este correo ya está registrado para este tipo de evento';
                    $messageType = 'warning';
                } else {
                    $message = 'Error al agregar el correo';
                    $messageType = 'danger';
                    error_log("[EPCO] Error insertando destinatario: " . $e->getMessage());
                }
            }
        }
    }
    
    if ($action === 'toggle_notification_email') {
        $notifId = (int)$_POST['notif_id'];
        $stmt = $pdo->prepare('UPDATE notification_recipients SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$notifId]);
        logActivity($user['id'], 'notification_email_toggled', 'notification_recipients', $notifId, "Estado de destinatario cambiado");
        $message = 'Estado del destinatario actualizado';
        $messageType = 'success';
    }
    
    if ($action === 'delete_notification_email') {
        $notifId = (int)$_POST['notif_id'];
        $stmt = $pdo->prepare('SELECT email FROM notification_recipients WHERE id = ?');
        $stmt->execute([$notifId]);
        $delNotif = $stmt->fetch();
        
        $pdo->prepare('DELETE FROM notification_recipients WHERE id = ?')->execute([$notifId]);
        logActivity($user['id'], 'notification_email_deleted', 'notification_recipients', $notifId, "Correo eliminado: " . ($delNotif['email'] ?? 'desconocido'));
        $message = 'Destinatario eliminado correctamente';
        $messageType = 'success';
    }
    
    if ($action === 'send_test_email') {
        $testEmail = sanitize($_POST['test_email'] ?? '');
        if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                require_once __DIR__ . '/../includes/ServicioCorreo.php';
                $mailService = new MailService();
                $testTicket = [
                    'id' => 0,
                    'ticket_number' => 'TEST-0000',
                    'subject' => 'Correo de prueba - Verificación de notificaciones',
                    'category' => 'soporte_tecnico',
                    'priority' => 'media',
                    'user_name' => $user['name'],
                    'user_email' => $user['email'],
                    'department' => 'Soporte TI',
                    'description' => 'Este es un correo de prueba para verificar que las notificaciones funcionan correctamente.',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                // Enviar directamente a la dirección de prueba
                $mailService->sendDirectEmail($testEmail, $testTicket);
                $message = "Correo de prueba enviado a <strong>$testEmail</strong>";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error al enviar correo de prueba: ' . htmlspecialchars($e->getMessage());
                $messageType = 'danger';
            }
        } else {
            $message = 'Correo de prueba no válido';
            $messageType = 'danger';
        }
    }
}

// Obtener datos según la página
if (empty($filter)) $filter = 'all';

// Estadísticas generales
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN status = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN status = 'en_pausa' THEN 1 ELSE 0 END) as en_pausa,
        SUM(CASE WHEN status = 'resuelto' THEN 1 ELSE 0 END) as resueltos,
        SUM(CASE WHEN status = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN priority = 'urgente' AND status NOT IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as urgentes
    FROM tickets
")->fetch();

// Tickets según filtro y página
$where = '';
if ($page === 'mis_tickets' || $page === 'mi_cumplimiento') {
    $where = "WHERE t.assigned_to = " . (int)$user['id'];
} elseif ($page === 'tickets' || $page === 'dashboard' || $page === 'sla' || $page === 'cumplimiento') {
    if ($filter === 'open') $where = "WHERE t.status IN ('abierto', 'en_proceso', 'pendiente')";
    elseif ($filter === 'closed') $where = "WHERE t.status IN ('resuelto', 'cerrado')";
    elseif ($filter === 'urgent') $where = "WHERE t.priority = 'urgente' AND t.status NOT IN ('resuelto', 'cerrado')";
    elseif ($filter === 'mine') $where = "WHERE t.assigned_to = " . (int)$user['id'];
}

$tickets = $pdo->query("
    SELECT t.*, 
           COALESCE(u.name, t.user_name) as user_name, 
           a.name as assigned_name,
           (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count,
           (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id AND tc.comment LIKE '%Archivos adjuntos%') as comment_attachments,
           CASE WHEN t.status IN ('resuelto', 'cerrado')
               THEN COALESCE(sla.resolution_minutes, CASE t.priority WHEN 'urgente' THEN 240 WHEN 'alta' THEN 480 WHEN 'media' THEN 1440 ELSE 2880 END)
                    - TIMESTAMPDIFF(MINUTE, t.created_at, COALESCE(t.resolved_at, t.updated_at))
               ELSE COALESCE(sla.resolution_minutes, CASE t.priority WHEN 'urgente' THEN 240 WHEN 'alta' THEN 480 WHEN 'media' THEN 1440 ELSE 2880 END)
                    - TIMESTAMPDIFF(MINUTE, t.created_at, NOW())
           END as sla_remaining_min
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id
    LEFT JOIN sla_settings sla ON sla.priority = t.priority AND sla.is_active = 1
    $where
    ORDER BY sla_remaining_min ASC
    LIMIT 100
")->fetchAll();

// Técnicos
$technicians = $pdo->query("SELECT id, name, email, role FROM users WHERE role IN ('admin', 'soporte')")->fetchAll();

// Usuarios (para admin)
$allUsers = $isAdmin ? $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll() : [];

// Estadísticas por categoría
$categoryStats = $pdo->query("SELECT category, COUNT(*) as count FROM tickets GROUP BY category")->fetchAll();

// Tickets de hoy
$todayTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Tickets resueltos esta semana
$weekResolved = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resuelto' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

// ===== NUEVAS MÉTRICAS DASHBOARD =====

// 1. Tendencia semanal (tickets por día últimos 7 días)
$weeklyTrend = $pdo->query("
    SELECT DATE(created_at) as fecha, COUNT(*) as total,
           SUM(CASE WHEN status IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as resueltos
    FROM tickets 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY fecha ASC
")->fetchAll();

// Preparar datos para el gráfico
$trendLabels = [];
$trendCreados = [];
$trendResueltos = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][date('w', strtotime($date))];
    $trendLabels[] = $dayName . ' ' . date('d', strtotime($date));
    $found = false;
    foreach ($weeklyTrend as $row) {
        if ($row['fecha'] === $date) {
            $trendCreados[] = (int)$row['total'];
            $trendResueltos[] = (int)$row['resueltos'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $trendCreados[] = 0;
        $trendResueltos[] = 0;
    }
}

// 2. Tiempo promedio de respuesta (últimos 30 días)
$avgResponseTime = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_response
    FROM tickets 
    WHERE first_response_at IS NOT NULL 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();
$avgResponseHours = $avgResponseTime ? round($avgResponseTime / 60, 1) : 0;

// Tiempo promedio de resolución
$avgResolutionTime = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution
    FROM tickets 
    WHERE resolved_at IS NOT NULL 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();
$avgResolutionHours = $avgResolutionTime ? round($avgResolutionTime, 1) : 0;

// 3. Ranking de técnicos (últimos 30 días)
$ticketsPerTechnician = $pdo->query("
    SELECT u.id, u.name, 
           COUNT(t.id) as tickets_asignados,
           SUM(CASE WHEN t.status = 'abierto' THEN 1 ELSE 0 END) as abiertos,
           SUM(CASE WHEN t.status = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
           SUM(CASE WHEN t.status IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as resueltos
    FROM users u
    LEFT JOIN tickets t ON u.id = t.assigned_to
    WHERE u.role IN ('admin', 'soporte')
    GROUP BY u.id, u.name
    ORDER BY tickets_asignados DESC
")->fetchAll();

// 4. Alertas SLA - Tickets próximos a vencer o ya vencidos
$slaAlerts = $pdo->query("
    SELECT t.*, 
           COALESCE(u.name, t.user_name) as user_name,
           TIMESTAMPDIFF(MINUTE, t.created_at, NOW()) as elapsed_minutes,
           CASE t.priority 
               WHEN 'urgente' THEN 60 
               WHEN 'alta' THEN 120 
               WHEN 'media' THEN 240 
               ELSE 480 
           END as sla_limit_minutes
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.status IN ('abierto', 'en_proceso', 'pendiente')
    AND t.first_response_at IS NULL
    HAVING elapsed_minutes >= (sla_limit_minutes * 0.7)
    ORDER BY (elapsed_minutes / sla_limit_minutes) DESC
    LIMIT 5
")->fetchAll();

// Tickets sin asignar por más de 2 horas
$unassignedAlerts = $pdo->query("
    SELECT t.*, COALESCE(u.name, t.user_name) as user_name,
           TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_waiting
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.assigned_to IS NULL 
    AND t.status = 'abierto'
    AND t.created_at <= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY t.created_at ASC
    LIMIT 5
")->fetchAll();

// 7. Tips del día
$tips = [
    ['icon' => 'bi-lightning-charge', 'color' => '#f59e0b', 'tip' => 'Prioriza los tickets urgentes y de alta prioridad al inicio del día.'],
    ['icon' => 'bi-clock-history', 'color' => '#3b82f6', 'tip' => 'Responder rápido mejora la satisfacción del usuario, aunque no tengas la solución aún.'],
    ['icon' => 'bi-chat-quote', 'color' => '#10b981', 'tip' => 'Documenta bien las soluciones para crear una base de conocimiento útil.'],
    ['icon' => 'bi-people', 'color' => '#8b5cf6', 'tip' => 'Si un ticket te toma más de 1 hora, considera pedir ayuda a un compañero.'],
    ['icon' => 'bi-shield-check', 'color' => '#ef4444', 'tip' => 'Verifica siempre que el problema esté completamente resuelto antes de cerrar.'],
    ['icon' => 'bi-graph-up', 'color' => '#06b6d4', 'tip' => 'Revisa las métricas SLA regularmente para mantener un buen rendimiento.'],
    ['icon' => 'bi-emoji-smile', 'color' => '#ec4899', 'tip' => 'Un mensaje amable hace la diferencia. El usuario también está estresado.'],
];
$tipOfDay = $tips[date('z') % count($tips)]; // Cambia cada día del año

// ===== DESTINATARIOS DE NOTIFICACIONES =====
$notificationRecipients = [];
try {
    $notificationRecipients = $pdo->query("
        SELECT nr.*, u.name as created_by_name 
        FROM notification_recipients nr 
        LEFT JOIN users u ON nr.created_by = u.id 
        ORDER BY nr.is_active DESC, nr.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    // La tabla puede no existir aún
    error_log("[EPCO] Tabla notification_recipients no disponible: " . $e->getMessage());
}

$notifStats = [
    'total' => count($notificationRecipients),
    'active' => count(array_filter($notificationRecipients, fn($r) => $r['is_active'])),
    'ticket_created' => count(array_filter($notificationRecipients, fn($r) => $r['is_active'] && in_array($r['event_type'], ['ticket_created', 'all']))),
    'ticket_updated' => count(array_filter($notificationRecipients, fn($r) => $r['is_active'] && in_array($r['event_type'], ['ticket_updated', 'all']))),
];

// ===== ESTADÍSTICAS SLA MEJORADAS =====
// Obtener configuración SLA desde la base de datos
$slaSettings = $pdo->query("SELECT * FROM sla_settings WHERE is_active = 1")->fetchAll();
$slaTargets = [];
foreach ($slaSettings as $s) {
    $slaTargets[$s['priority']] = [
        'response' => round($s['first_response_minutes'] / 60, 1),
        'assignment' => round($s['assignment_minutes'] / 60, 1),
        'resolution' => round($s['resolution_minutes'] / 60, 1),
        'response_min' => $s['first_response_minutes'],
        'assignment_min' => $s['assignment_minutes'],
        'resolution_min' => $s['resolution_minutes']
    ];
}

// Fallback si no hay configuración
if (empty($slaTargets)) {
    $slaTargets = [
        'urgente' => ['response' => 0.5, 'assignment' => 0.25, 'resolution' => 4, 'response_min' => 30, 'assignment_min' => 15, 'resolution_min' => 240],
        'alta' => ['response' => 1, 'assignment' => 0.5, 'resolution' => 8, 'response_min' => 60, 'assignment_min' => 30, 'resolution_min' => 480],
        'media' => ['response' => 2, 'assignment' => 1, 'resolution' => 24, 'response_min' => 120, 'assignment_min' => 60, 'resolution_min' => 1440],
        'baja' => ['response' => 4, 'assignment' => 2, 'resolution' => 48, 'response_min' => 240, 'assignment_min' => 120, 'resolution_min' => 2880]
    ];
}

// Tickets para análisis SLA (últimos 30 días) con nuevos campos
$slaTickets = $pdo->query("
    SELECT t.*, 
           COALESCE(u.name, t.user_name) as user_name,
           a.name as assigned_name,
           -- Tiempo hasta primera respuesta (en minutos)
           CASE 
               WHEN t.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at)
               ELSE TIMESTAMPDIFF(MINUTE, t.created_at, NOW())
           END as response_minutes,
           -- Tiempo hasta asignación (en minutos)
           CASE 
               WHEN t.assigned_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.assigned_at)
               ELSE TIMESTAMPDIFF(MINUTE, t.created_at, NOW())
           END as assignment_minutes,
           -- Tiempo hasta resolución (en minutos, descontando pausas)
           CASE 
               WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) - COALESCE(t.sla_paused_minutes, 0)
               WHEN t.closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.closed_at) - COALESCE(t.sla_paused_minutes, 0)
               ELSE TIMESTAMPDIFF(MINUTE, t.created_at, NOW()) - COALESCE(t.sla_paused_minutes, 0)
           END as resolution_minutes,
           -- Tiempo de trabajo activo (desde asignación hasta resolución)
           CASE 
               WHEN t.work_started_at IS NOT NULL AND t.resolved_at IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, t.work_started_at, t.resolved_at) - COALESCE(t.sla_paused_minutes, 0)
               WHEN t.work_started_at IS NOT NULL 
               THEN TIMESTAMPDIFF(MINUTE, t.work_started_at, NOW()) - COALESCE(t.sla_paused_minutes, 0)
               ELSE NULL
           END as work_minutes
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY t.created_at DESC
")->fetchAll();

// Calcular métricas SLA mejoradas
$slaStats = [
    'total' => count($slaTickets),
    'within_response' => 0,
    'within_assignment' => 0,
    'within_resolution' => 0,
    'breached_response' => 0,
    'breached_assignment' => 0,
    'breached_resolution' => 0,
    'avg_response' => 0,
    'avg_assignment' => 0,
    'avg_resolution' => 0,
    'avg_work' => 0,
    'by_priority' => []
];

$totalResponseMin = 0;
$totalAssignmentMin = 0;
$totalResolutionMin = 0;
$totalWorkMin = 0;
$resolvedCount = 0;
$assignedCount = 0;
$workCount = 0;

foreach ($slaTickets as $ticket) {
    $priority = $ticket['priority'];
    $target = $slaTargets[$priority] ?? $slaTargets['media'];
    
    if (!isset($slaStats['by_priority'][$priority])) {
        $slaStats['by_priority'][$priority] = [
            'total' => 0, 
            'response_ok' => 0, 
            'assignment_ok' => 0, 
            'resolution_ok' => 0,
            'response_breached' => 0,
            'assignment_breached' => 0,
            'resolution_breached' => 0
        ];
    }
    $slaStats['by_priority'][$priority]['total']++;
    
    // Verificar SLA de primera respuesta
    $responseMin = $ticket['response_minutes'];
    $totalResponseMin += $responseMin;
    
    if ($ticket['first_response_at']) {
        if ($responseMin <= $target['response_min']) {
            $slaStats['within_response']++;
            $slaStats['by_priority'][$priority]['response_ok']++;
        } else {
            $slaStats['breached_response']++;
            $slaStats['by_priority'][$priority]['response_breached']++;
        }
    }
    
    // Verificar SLA de asignación
    if ($ticket['assigned_at']) {
        $assignedCount++;
        $assignmentMin = $ticket['assignment_minutes'];
        $totalAssignmentMin += $assignmentMin;
        
        if ($assignmentMin <= $target['assignment_min']) {
            $slaStats['within_assignment']++;
            $slaStats['by_priority'][$priority]['assignment_ok']++;
        } else {
            $slaStats['breached_assignment']++;
            $slaStats['by_priority'][$priority]['assignment_breached']++;
        }
    }
    
    // Verificar SLA de resolución (solo tickets resueltos/cerrados)
    if (in_array($ticket['status'], ['resuelto', 'cerrado'])) {
        $resolvedCount++;
        $resolutionMin = $ticket['resolution_minutes'];
        $totalResolutionMin += $resolutionMin;
        
        if ($resolutionMin <= $target['resolution_min']) {
            $slaStats['within_resolution']++;
            $slaStats['by_priority'][$priority]['resolution_ok']++;
        } else {
            $slaStats['breached_resolution']++;
            $slaStats['by_priority'][$priority]['resolution_breached']++;
        }
        
        // Tiempo de trabajo
        if ($ticket['work_minutes'] !== null) {
            $workCount++;
            $totalWorkMin += $ticket['work_minutes'];
        }
    }
}

// Calcular promedios en horas
if ($slaStats['total'] > 0) {
    $slaStats['avg_response'] = round($totalResponseMin / $slaStats['total'] / 60, 1);
}
if ($assignedCount > 0) {
    $slaStats['avg_assignment'] = round($totalAssignmentMin / $assignedCount / 60, 1);
}
if ($resolvedCount > 0) {
    $slaStats['avg_resolution'] = round($totalResolutionMin / $resolvedCount / 60, 1);
}
if ($workCount > 0) {
    $slaStats['avg_work'] = round($totalWorkMin / $workCount / 60, 1);
}

// Calcular porcentajes de cumplimiento
$respondedCount = $slaStats['within_response'] + $slaStats['breached_response'];
$slaStats['response_compliance'] = $respondedCount > 0 ? round(($slaStats['within_response'] / $respondedCount) * 100, 1) : 100;
$slaStats['assignment_compliance'] = $assignedCount > 0 ? round(($slaStats['within_assignment'] / $assignedCount) * 100, 1) : 100;
$slaStats['resolution_compliance'] = $resolvedCount > 0 ? round(($slaStats['within_resolution'] / $resolvedCount) * 100, 1) : 100;

// Labels
$statusColors = ['abierto' => 'primary', 'asignado' => 'info', 'en_proceso' => 'warning', 'pendiente' => 'secondary', 'en_pausa' => 'info', 'resuelto' => 'success', 'cerrado' => 'dark'];
$statusLabels = ['abierto' => 'Abierto', 'asignado' => 'Asignado', 'en_proceso' => 'En Proceso', 'pendiente' => 'Pendiente', 'en_pausa' => 'En Pausa', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
$priorityColors = ['urgente' => 'danger', 'alta' => 'warning', 'media' => 'info', 'baja' => 'secondary'];
$categoryLabels = ['hardware' => 'Hardware', 'software' => 'Software', 'red' => 'Red', 'acceso' => 'Acceso', 'otro' => 'Otro'];

// ===== DATOS DE AUDITORÍA =====
$auditFilterUser = $_GET['audit_user'] ?? '';
$auditFilterAction = $_GET['audit_action'] ?? '';
$auditFilterEntity = $_GET['audit_entity'] ?? '';
$auditFilterDateFrom = $_GET['audit_date_from'] ?? '';
$auditFilterDateTo = $_GET['audit_date_to'] ?? '';
$auditPage = max(1, (int)($_GET['audit_page'] ?? 1));
$auditPerPage = 30;
$auditOffset = ($auditPage - 1) * $auditPerPage;

// Construir consulta de auditoría
$auditWhere = '1=1';
$auditParams = [];

if (!empty($auditFilterUser)) {
    $auditWhere .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $auditParams[] = "%$auditFilterUser%";
    $auditParams[] = "%$auditFilterUser%";
}
if (!empty($auditFilterAction)) {
    $auditWhere .= ' AND al.action = ?';
    $auditParams[] = $auditFilterAction;
}
if (!empty($auditFilterEntity)) {
    $auditWhere .= ' AND al.entity_type = ?';
    $auditParams[] = $auditFilterEntity;
}
if (!empty($auditFilterDateFrom)) {
    $auditWhere .= ' AND DATE(al.created_at) >= ?';
    $auditParams[] = $auditFilterDateFrom;
}
if (!empty($auditFilterDateTo)) {
    $auditWhere .= ' AND DATE(al.created_at) <= ?';
    $auditParams[] = $auditFilterDateTo;
}

// Contar total logs
$auditCountStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $auditWhere");
$auditCountStmt->execute($auditParams);
$totalAuditLogs = $auditCountStmt->fetchColumn();
$totalAuditPages = ceil($totalAuditLogs / $auditPerPage);

// Obtener logs de auditoría
$auditStmt = $pdo->prepare("
    SELECT al.*, u.name as user_name, u.email as user_email 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE $auditWhere 
    ORDER BY al.created_at DESC 
    LIMIT $auditPerPage OFFSET $auditOffset
");
$auditStmt->execute($auditParams);
$auditLogs = $auditStmt->fetchAll();

// Obtener acciones únicas para filtro
$auditActions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Obtener entidades únicas para filtro
$auditEntities = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas de auditoría
$auditStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
        COUNT(CASE WHEN created_at >= NOW() - INTERVAL 7 DAY THEN 1 END) as week
    FROM activity_logs
")->fetch();

// Actividad por día (últimos 7 días)
$auditChartData = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= NOW() - INTERVAL 7 DAY 
    GROUP BY DATE(created_at) 
    ORDER BY date
")->fetchAll();

// Top acciones
$topActions = $pdo->query("
    SELECT action, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= NOW() - INTERVAL 30 DAY
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll();

// ===== MÉTRICAS DE TICKETS PARA AUDITORÍA =====

// Tickets creados y resueltos por mes (últimos 6 meses)
$monthlyTickets = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        DATE_FORMAT(created_at, '%b %Y') as mes_label,
        COUNT(*) as creados,
        COUNT(CASE WHEN status IN ('resuelto', 'cerrado') THEN 1 END) as resueltos,
        COUNT(CASE WHEN status IN ('resuelto', 'cerrado') AND resolved_at IS NOT NULL 
              AND TIMESTAMPDIFF(HOUR, created_at, resolved_at) <= 24 THEN 1 END) as resueltos_24h
    FROM tickets
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
    ORDER BY mes ASC
")->fetchAll();

// Estadísticas generales de tickets
$ticketMetrics = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('resuelto', 'cerrado') THEN 1 END) as resueltos,
        COUNT(CASE WHEN status NOT IN ('resuelto', 'cerrado') THEN 1 END) as pendientes,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as creados_mes,
        COUNT(CASE WHEN status IN ('resuelto', 'cerrado') AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as resueltos_mes,
        COUNT(CASE WHEN priority = 'urgente' AND status NOT IN ('resuelto', 'cerrado') THEN 1 END) as urgentes_abiertos,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as creados_semana,
        COUNT(CASE WHEN status IN ('resuelto', 'cerrado') AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as resueltos_semana
    FROM tickets
")->fetch();

$ticketMetrics['tasa_resolucion'] = $ticketMetrics['total'] > 0 
    ? round(($ticketMetrics['resueltos'] / $ticketMetrics['total']) * 100, 1) : 0;
$ticketMetrics['tasa_resolucion_mes'] = $ticketMetrics['creados_mes'] > 0 
    ? round(($ticketMetrics['resueltos_mes'] / $ticketMetrics['creados_mes']) * 100, 1) : 0;

// Distribución por estado actual
$ticketsByStatus = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM tickets 
    GROUP BY status 
    ORDER BY FIELD(status, 'abierto', 'en_proceso', 'pendiente', 'resuelto', 'cerrado')
")->fetchAll();

// Distribución por categoría
$ticketsByCategory = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM tickets 
    GROUP BY category 
    ORDER BY count DESC
")->fetchAll();

// Rendimiento por técnico (últimos 30 días)
$techPerformance = $pdo->query("
    SELECT 
        u.name as tech_name,
        COUNT(*) as asignados,
        COUNT(CASE WHEN t.status IN ('resuelto', 'cerrado') THEN 1 END) as resueltos,
        ROUND(AVG(CASE WHEN t.status IN ('resuelto', 'cerrado') AND t.resolved_at IS NOT NULL 
              THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 1) as avg_horas_resolucion
    FROM tickets t
    JOIN users u ON t.assigned_to = u.id
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.id, u.name
    ORDER BY resueltos DESC
    LIMIT 10
")->fetchAll();

// Actividad por hora del día (últimos 30 días)
$ticketsByHour = $pdo->query("
    SELECT HOUR(created_at) as hora, COUNT(*) as count
    FROM tickets
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(created_at)
    ORDER BY hora
")->fetchAll();
$hoursData = array_fill(0, 24, 0);
foreach ($ticketsByHour as $h) { $hoursData[(int)$h['hora']] = (int)$h['count']; }

// Tiempo promedio de resolución por prioridad
$avgResByPriority = $pdo->query("
    SELECT 
        priority,
        COUNT(*) as total,
        ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolved_at, NOW()))), 1) as avg_horas
    FROM tickets
    WHERE status IN ('resuelto', 'cerrado') AND resolved_at IS NOT NULL
    GROUP BY priority
    ORDER BY FIELD(priority, 'urgente', 'alta', 'media', 'baja')
")->fetchAll();

// Tickets reabiertos (cambiados de cerrado/resuelto a otro estado)
$reOpenedCount = $pdo->query("
    SELECT COUNT(DISTINCT entity_id) as count
    FROM activity_logs 
    WHERE action = 'update' 
      AND entity_type = 'ticket' 
      AND details LIKE '%→ abierto%'
      AND (details LIKE '%resuelto →%' OR details LIKE '%cerrado →%')
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch()['count'] ?? 0;

// ===== SLA PERSONAL POR ANALISTA =====
$personalSla = [
    'total' => 0, 'resueltos' => 0,
    'within_response' => 0, 'within_assignment' => 0, 'within_resolution' => 0,
    'breached_response' => 0, 'breached_assignment' => 0, 'breached_resolution' => 0,
    'avg_response' => 0, 'avg_resolution' => 0,
    'response_compliance' => 100, 'assignment_compliance' => 100, 'resolution_compliance' => 100,
    'tasa_resolucion' => 0,
];
$pResponseMin = 0; $pResolutionMin = 0; $pResolved = 0; $pResponded = 0;

foreach ($slaTickets as $ticket) {
    if ((int)($ticket['assigned_to'] ?? 0) !== (int)$user['id']) continue;
    $personalSla['total']++;
    $target = $slaTargets[$ticket['priority']] ?? $slaTargets['media'];
    
    if ($ticket['first_response_at']) {
        $pResponded++;
        $pResponseMin += $ticket['response_minutes'];
        if ($ticket['response_minutes'] <= $target['response_min']) $personalSla['within_response']++;
        else $personalSla['breached_response']++;
    }
    if (in_array($ticket['status'], ['resuelto', 'cerrado'])) {
        $personalSla['resueltos']++;
        $pResolved++;
        $pResolutionMin += $ticket['resolution_minutes'];
        if ($ticket['resolution_minutes'] <= $target['resolution_min']) $personalSla['within_resolution']++;
        else $personalSla['breached_resolution']++;
    }
    if ($ticket['assigned_at']) {
        if ($ticket['assignment_minutes'] <= $target['assignment_min']) $personalSla['within_assignment']++;
        else $personalSla['breached_assignment']++;
    }
}
if ($pResponded > 0) {
    $personalSla['avg_response'] = round($pResponseMin / $pResponded / 60, 1);
    $personalSla['response_compliance'] = round(($personalSla['within_response'] / $pResponded) * 100, 1);
}
if ($pResolved > 0) {
    $personalSla['avg_resolution'] = round($pResolutionMin / $pResolved / 60, 1);
    $personalSla['resolution_compliance'] = round(($personalSla['within_resolution'] / $pResolved) * 100, 1);
}
$pAssignedCount = $personalSla['within_assignment'] + $personalSla['breached_assignment'];
if ($pAssignedCount > 0) {
    $personalSla['assignment_compliance'] = round(($personalSla['within_assignment'] / $pAssignedCount) * 100, 1);
}
$personalSla['tasa_resolucion'] = $personalSla['total'] > 0 
    ? round(($personalSla['resueltos'] / $personalSla['total']) * 100, 1) : 0;

// ===== RENDIMIENTO POR ANALISTA CON SLA (para auditoría e informes) =====
$techSlaPerformance = [];
foreach ($techPerformance as $tech) {
    $techSlaPerformance[$tech['tech_name']] = [
        'asignados' => $tech['asignados'], 'resueltos' => $tech['resueltos'],
        'avg_horas' => $tech['avg_horas_resolucion'],
        'tasa' => $tech['asignados'] > 0 ? round(($tech['resueltos'] / $tech['asignados']) * 100, 1) : 0,
        'sla_response_ok' => 0, 'sla_response_fail' => 0,
        'sla_resolution_ok' => 0, 'sla_resolution_fail' => 0,
    ];
}
foreach ($slaTickets as $ticket) {
    $tn = $ticket['assigned_name'] ?? null;
    if (!$tn || !isset($techSlaPerformance[$tn])) continue;
    $target = $slaTargets[$ticket['priority']] ?? $slaTargets['media'];
    if ($ticket['first_response_at']) {
        if ($ticket['response_minutes'] <= $target['response_min']) $techSlaPerformance[$tn]['sla_response_ok']++;
        else $techSlaPerformance[$tn]['sla_response_fail']++;
    }
    if (in_array($ticket['status'], ['resuelto', 'cerrado'])) {
        if ($ticket['resolution_minutes'] <= $target['resolution_min']) $techSlaPerformance[$tn]['sla_resolution_ok']++;
        else $techSlaPerformance[$tn]['sla_resolution_fail']++;
    }
}
foreach ($techSlaPerformance as &$tp) {
    $rTotal = $tp['sla_response_ok'] + $tp['sla_response_fail'];
    $tp['sla_response_pct'] = $rTotal > 0 ? round(($tp['sla_response_ok'] / $rTotal) * 100, 1) : 100;
    $resTotal = $tp['sla_resolution_ok'] + $tp['sla_resolution_fail'];
    $tp['sla_resolution_pct'] = $resTotal > 0 ? round(($tp['sla_resolution_ok'] / $resTotal) * 100, 1) : 100;
}
unset($tp);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Admin Soporte TI</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/soporte-admin.css?v=2" rel="stylesheet">
    
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral_soporte.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-title">
                <?php
                $pageTitles = [
                    'dashboard' => 'Dashboard',
                    'tickets' => 'Gestión de Tickets',
                    'mis_tickets' => 'Mis Tickets Asignados',
                    'mi_cumplimiento' => 'Mi Cumplimiento SLA',
                    'usuarios' => 'Gestión de Usuarios',
                    'nuevo_ticket' => 'Nuevo Ticket',
                    'sla' => 'Cumplimiento',
                    'cumplimiento' => 'Cumplimiento',
                    'auditoria' => 'Auditoría del Sistema',
                    'notificaciones' => 'Destinatarios de Notificaciones',
                ];
                ?>
                <h1><?= $pageTitles[$page] ?? 'Soporte TI' ?></h1>
                <?php
                $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                $fechaEs = $dias[date('w')] . ', ' . date('d') . ' de ' . $meses[date('n')-1] . ' de ' . date('Y');
                ?>
                <p><?= $fechaEs ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> py-2 px-3 mb-0 small"><?= $message ?></div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content-area">
            <?php if ($page === 'dashboard'): ?>
            <!-- ========== DASHBOARD - ServiceNow Style - Kanban por Estado ========== -->
            <?php
            // Agrupar tickets por estado
            $ticketsByStatusGroup = [
                'abierto' => [], 'en_proceso' => [], 'pendiente' => [],
                'en_pausa' => [], 'resuelto' => [], 'cerrado' => []
            ];
            foreach ($tickets as $t) {
                $st = $t['status'];
                if (isset($ticketsByStatusGroup[$st])) $ticketsByStatusGroup[$st][] = $t;
            }
            ?>
            
            <!-- Stats Strip compacto -->
            <div class="sn-stats-strip">
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['total'] ?></div>
                    <div class="sn-stat-label">Total</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['abiertos'] ?></div>
                    <div class="sn-stat-label">Nuevos</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['en_proceso'] ?></div>
                    <div class="sn-stat-label">En Proceso</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['pendientes'] ?? 0 ?></div>
                    <div class="sn-stat-label">Pendientes</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:#8b5cf6"><?= $stats['en_pausa'] ?? 0 ?></div>
                    <div class="sn-stat-label">En Pausa</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['resueltos'] ?? 0 ?></div>
                    <div class="sn-stat-label">Resueltos</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['cerrados'] ?></div>
                    <div class="sn-stat-label">Cerrados</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= $stats['urgentes'] ?></div>
                    <div class="sn-stat-label">Urgentes</div>
                    <?php if ($stats['urgentes'] > 0): ?>
                    <div class="sn-stat-trend text-danger"><i class="bi bi-exclamation-circle"></i></div>
                    <?php endif; ?>
                </div>
                <div class="sn-stat-item" style="border-right:none;">
                    <a href="?page=nuevo_ticket" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo Ticket</a>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if (!empty($slaAlerts) || !empty($unassignedAlerts)): ?>
            <div class="sn-alert-bar <?= count($slaAlerts) > 3 ? 'danger' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill" style="color:#d97706; font-size:1rem;"></i>
                <small class="fw-semibold">Alertas activas:</small>
                <?php foreach (array_slice($slaAlerts, 0, 4) as $alert): 
                    $pct = round(($alert['elapsed_minutes'] / max($alert['sla_limit_minutes'], 1)) * 100);
                ?>
                <span class="badge bg-<?= $pct >= 100 ? 'danger' : 'warning text-dark' ?>" style="font-size:0.68rem">
                    <?= $alert['ticket_number'] ?> — <?= $pct >= 100 ? 'SLA vencido' : (100-$pct).'% restante' ?>
                </span>
                <?php endforeach; ?>
                <?php foreach (array_slice($unassignedAlerts, 0, 2) as $alert): ?>
                <span class="badge bg-info text-dark" style="font-size:0.68rem">
                    <?= $alert['ticket_number'] ?> — Sin asignar <?= $alert['hours_waiting'] ?>h
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Toolbar: Breadcrumb + Search -->
            <div class="sn-toolbar">
                <div class="sn-breadcrumb">
                    <a href="?page=dashboard">Incidentes</a>
                    <span class="separator">&gt;</span>
                    <span style="color: var(--gray-900); font-weight: 600;">Vista por Estado</span>
                </div>
                <div class="sn-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="dashSearchInput" placeholder="Buscar tickets por número, título, usuario..." onkeyup="filterDashCards()">
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="auto-refresh-toggle d-flex align-items-center gap-1" style="font-size:0.75rem;">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="autoRefreshToggle" style="cursor:pointer;">
                            <label class="form-check-label text-muted" for="autoRefreshToggle" style="cursor:pointer; font-size:0.72rem;">Auto</label>
                        </div>
                        <span id="refreshCountdown" class="text-muted" style="font-size:0.68rem; min-width:28px;"></span>
                    </div>
                    <a href="?page=cumplimiento" class="btn btn-outline-dark btn-sm"><i class="bi bi-speedometer2 me-1"></i>Cumplimiento</a>
                    <a href="?page=auditoria" class="btn btn-outline-dark btn-sm"><i class="bi bi-journal-text me-1"></i>Auditoría</a>
                </div>
            </div>
            
            <!-- Kanban: Cards por estado - Grid 2x3 -->
            <div class="row g-3" id="dashStatusLanes">
                <?php
                $laneConfig = [
                    'abierto'    => ['label' => 'Nuevos',      'icon' => 'bi-plus-circle',      'color' => '#3b82f6'],
                    'en_proceso' => ['label' => 'En Proceso',  'icon' => 'bi-gear',             'color' => '#f59e0b'],
                    'pendiente'  => ['label' => 'Pendientes',  'icon' => 'bi-hourglass-split', 'color' => '#64748b'],
                    'en_pausa'   => ['label' => 'En Pausa',    'icon' => 'bi-pause-circle',    'color' => '#8b5cf6'],
                    'resuelto'   => ['label' => 'Resueltos',   'icon' => 'bi-check-circle',    'color' => '#22c55e'],
                    'cerrado'    => ['label' => 'Cerrados',    'icon' => 'bi-lock',            'color' => '#1e293b'],
                ];
                $statusLabelsShort = ['abierto' => 'Abierto', 'en_proceso' => 'En Proceso', 'pendiente' => 'Pendiente', 'en_pausa' => 'En Pausa', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
                foreach ($laneConfig as $statusKey => $lane):
                    $laneTickets = $ticketsByStatusGroup[$statusKey] ?? [];
                ?>
                <div class="col-lg-6">
                    <div class="lane-card lane-<?= $statusKey ?>">
                        <div class="lane-header">
                            <span class="lane-title"><i class="bi <?= $lane['icon'] ?>"></i><?= $lane['label'] ?></span>
                            <span class="lane-count"><?= count($laneTickets) ?></span>
                        </div>
                        <?php if (!empty($laneTickets)): ?>
                        <div class="lane-body">
                            <table class="lane-table">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Título</th>
                                        <th>Usuario</th>
                                        <th>Categoría</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Evidencia</th>
                                        <th>Asignado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($laneTickets as $t): 
                                    $hasAttachments = ($t['attachment_count'] ?? 0) > 0 || ($t['comment_attachments'] ?? 0) > 0;
                                ?>
                                <tr onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=dashboard'" class="ticket-row" data-ticket-id="<?= $t['id'] ?>" data-search="<?= strtolower($t['ticket_number'] . ' ' . htmlspecialchars($t['title']) . ' ' . ($t['user_name'] ?? '') . ' ' . ($t['assigned_name'] ?? '')) ?>">
                                    <td><span class="ticket-link"><?= $t['ticket_number'] ?></span></td>
                                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($t['title']) ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar"><?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?></div>
                                            <span><?= htmlspecialchars($t['user_name'] ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td><?= $categoryLabels[$t['category']] ?? ucfirst($t['category']) ?></td>
                                    <td><span class="priority-badge <?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                                    <td><span class="status-badge <?= $t['status'] ?>"><?= $statusLabelsShort[$t['status']] ?? ucfirst($t['status']) ?></span></td>
                                    <td class="text-center"><?= $hasAttachments ? '<i class="bi bi-paperclip text-primary"></i>' : '<span class="text-muted">No</span>' ?></td>
                                    <td><?= htmlspecialchars($t['assigned_name'] ?? 'Sin asignar') ?></td>
                                    <td class="text-nowrap text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="lane-empty">
                            <i class="bi bi-inbox"></i> Sin tickets
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- FAQ / Guía de Trabajo -->
            <div class="mt-4" id="dashFaqSection">
                <div class="card" style="border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff; border-bottom:1px solid #e2e8f0; padding:12px 18px; cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#faqContent" aria-expanded="false">
                        <span style="font-weight:700; font-size:0.88rem; color:#1e293b;"><i class="bi bi-question-circle me-2" style="color:#64748b;"></i>Guía de trabajo — ¿Cómo se ordenan y trabajan los tickets?</span>
                        <i class="bi bi-chevron-down text-muted" style="transition:transform 0.2s;" id="faqChevron"></i>
                    </div>
                    <div class="collapse" id="faqContent">
                        <div class="card-body" style="padding:20px 24px; font-size:0.82rem; color:#334155; line-height:1.7;">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 style="font-weight:700; color:#0c5a8a; font-size:0.85rem;"><i class="bi bi-sort-numeric-up me-1"></i>Orden de los tickets</h6>
                                    <p class="mb-2">Los tickets se ordenan automáticamente por <strong>tiempo restante de SLA</strong> (de menor a mayor). Los que tienen menos tiempo disponible aparecen primero para que sean atendidos con mayor urgencia.</p>
                                    <ul class="mb-0" style="padding-left:1.2rem;">
                                        <li><span class="badge bg-danger" style="font-size:0.65rem;">Negativo</span> — SLA vencido, requiere atención inmediata</li>
                                        <li><span class="badge bg-warning text-dark" style="font-size:0.65rem;">&lt; 2 horas</span> — SLA próximo a vencer</li>
                                        <li><span class="badge bg-success" style="font-size:0.65rem;">&gt; 2 horas</span> — Dentro del plazo acordado</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="font-weight:700; color:#0c5a8a; font-size:0.85rem;"><i class="bi bi-clock-history me-1"></i>Objetivos SLA por prioridad</h6>
                                    <table class="table table-sm table-bordered mb-0" style="font-size:0.76rem;">
                                        <thead style="background:#f8fafc;"><tr><th>Prioridad</th><th>Respuesta</th><th>Resolución</th></tr></thead>
                                        <tbody>
                                            <tr><td><span class="priority-pill urgente" style="font-size:0.68rem;">Urgente</span></td><td>30 min</td><td>4 horas</td></tr>
                                            <tr><td><span class="priority-pill alta" style="font-size:0.68rem;">Alta</span></td><td>1 hora</td><td>8 horas</td></tr>
                                            <tr><td><span class="priority-pill media" style="font-size:0.68rem;">Media</span></td><td>2 horas</td><td>24 horas</td></tr>
                                            <tr><td><span class="priority-pill baja" style="font-size:0.68rem;">Baja</span></td><td>4 horas</td><td>48 horas</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="font-weight:700; color:#0c5a8a; font-size:0.85rem;"><i class="bi bi-kanban me-1"></i>¿Qué significa cada carril?</h6>
                                    <ul class="mb-0" style="padding-left:1.2rem;">
                                        <li><strong>Nuevos</strong> — Tickets recién creados sin atender</li>
                                        <li><strong>En Proceso</strong> — Un técnico está trabajando en el ticket</li>
                                        <li><strong>Pendientes</strong> — Esperando respuesta del usuario o un tercero</li>
                                        <li><strong>En Pausa</strong> — Trabajo pausado temporalmente (escalado, externo, etc.)</li>
                                        <li><strong>Resueltos</strong> — Solución aplicada, esperando confirmación</li>
                                        <li><strong>Cerrados</strong> — Ticket finalizado</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="font-weight:700; color:#0c5a8a; font-size:0.85rem;"><i class="bi bi-tools me-1"></i>¿Cómo trabajar un ticket?</h6>
                                    <ol class="mb-0" style="padding-left:1.2rem;">
                                        <li>Revisa el carril <strong>Nuevos</strong> y toma los tickets con menor SLA</li>
                                        <li>Haz clic para ver el detalle y <strong>asígnate</strong> el ticket</li>
                                        <li>Cambia el estado a <strong>En Proceso</strong> al comenzar</li>
                                        <li>Si necesitas información del usuario, pasa a <strong>Pendiente</strong></li>
                                        <li>Si debes pausar el trabajo (escalado, etc.), usa <strong>En Pausa</strong></li>
                                        <li>Aplica la solución y marca como <strong>Resuelto</strong></li>
                                        <li>El ticket se cerrará automáticamente o puede cerrarse manualmente</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            function filterDashCards() {
                const q = document.getElementById('dashSearchInput').value.toLowerCase();
                document.querySelectorAll('.ticket-row').forEach(row => {
                    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
                });
                document.querySelectorAll('#dashStatusLanes .lane-card').forEach(card => {
                    const visible = card.querySelectorAll('.ticket-row:not([style*="display: none"])').length;
                    const badge = card.querySelector('.lane-count');
                    if (badge) badge.textContent = visible;
                });
            }

            // === Auto-refresh ===
            (function() {
                const REFRESH_INTERVAL = 30; // seconds
                const toggle = document.getElementById('autoRefreshToggle');
                const countdown = document.getElementById('refreshCountdown');
                const chevron = document.getElementById('faqChevron');
                let timer = null;
                let remaining = REFRESH_INTERVAL;

                // Persist preference
                const saved = localStorage.getItem('epco_auto_refresh');
                if (saved === 'true') toggle.checked = true;

                function tick() {
                    remaining--;
                    countdown.textContent = remaining + 's';
                    if (remaining <= 0) {
                        location.reload();
                    }
                }

                function start() {
                    remaining = REFRESH_INTERVAL;
                    countdown.textContent = remaining + 's';
                    timer = setInterval(tick, 1000);
                    localStorage.setItem('epco_auto_refresh', 'true');
                }

                function stop() {
                    clearInterval(timer);
                    timer = null;
                    countdown.textContent = '';
                    localStorage.setItem('epco_auto_refresh', 'false');
                }

                toggle.addEventListener('change', () => {
                    toggle.checked ? start() : stop();
                });

                if (toggle.checked) start();

                // Chevron rotation for FAQ
                if (chevron) {
                    document.getElementById('faqContent').addEventListener('show.bs.collapse', () => chevron.style.transform = 'rotate(180deg)');
                    document.getElementById('faqContent').addEventListener('hide.bs.collapse', () => chevron.style.transform = 'rotate(0deg)');
                }
            })();
            </script>
            
            <?php elseif ($page === 'tickets'): ?>
            <!-- ========== TICKETS ========== -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div></div>
                <a href="?page=nuevo_ticket" class="btn btn-dark"><i class="bi bi-plus-lg me-2"></i>Nuevo Ticket</a>
            </div>
            
            <!-- Barra de acciones masivas -->
            <div id="bulkBar" class="bulk-action-bar" style="display:none;">
                <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('¿Eliminar los tickets seleccionados? Esta acción no se puede deshacer.');">
            <?= csrfInput() ?>
                    <input type="hidden" name="action" value="bulk_delete_tickets">
                    <div id="bulkIdsContainer"></div>
                    <div class="d-flex align-items-center gap-3">
                        <span id="bulkCount" class="fw-semibold">0 seleccionados</span>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Eliminar Seleccionados</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">Cancelar</button>
                    </div>
                </form>
            </div>
            
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-ticket-detailed me-2"></i>Gestión de Tickets</h5>
                    <div class="filter-tabs">
                        <a href="?page=tickets&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Todos</a>
                        <a href="?page=tickets&filter=open" class="filter-tab <?= $filter === 'open' ? 'active' : '' ?>">Abiertos</a>
                        <a href="?page=tickets&filter=urgent" class="filter-tab <?= $filter === 'urgent' ? 'active' : '' ?>">Urgentes</a>
                        <a href="?page=tickets&filter=closed" class="filter-tab <?= $filter === 'closed' ? 'active' : '' ?>">Cerrados</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="ticketsTable">
                        <thead><tr>
                            <th style="width:40px;"><input type="checkbox" class="form-check-input" id="selectAllTickets" onchange="toggleSelectAll(this, 'ticketsTable')" title="Seleccionar todos"></th>
                            <th>Ticket</th><th>Título</th><th>Usuario</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Evidencia</th><th>Asignado</th><th>Fecha</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2.5rem"></i><p class="mt-2 mb-0">No hay tickets</p></td></tr>
                        <?php else: foreach ($tickets as $t): ?>
                        <tr style="cursor: pointer;" class="ticket-row-hover" data-ticket-id="<?= $t['id'] ?>">
                            <td onclick="event.stopPropagation();"><input type="checkbox" class="form-check-input ticket-check" value="<?= $t['id'] ?>" onchange="updateBulkBar()"></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><span class="ticket-number"><?= $t['ticket_number'] ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><?= htmlspecialchars(substr($t['title'], 0, 35)) ?><?= strlen($t['title']) > 35 ? '...' : '' ?></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><div class="user-info"><div class="user-info-avatar"><?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?></div><span class="small"><?= htmlspecialchars($t['user_name'] ?? '-') ?></span></div></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><span class="badge bg-light text-dark"><?= $categoryLabels[$t['category']] ?? $t['category'] ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'" class="text-center">
                                <?php 
                                    $hasEvidence = (($t['attachment_count'] ?? 0) > 0) || (($t['comment_attachments'] ?? 0) > 0) || is_dir(__DIR__ . '/uploads/tickets/' . $t['ticket_number']);
                                ?>
                                <?php if ($hasEvidence): ?>
                                    <span class="badge bg-success"><i class="bi bi-paperclip me-1"></i>Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">No</span>
                                <?php endif; ?>
                            </td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'"><span class="small text-muted"><?= $t['assigned_name'] ?? '-' ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=tickets'" class="small text-muted"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($page === 'mis_tickets'): ?>
            <!-- ========== MIS TICKETS - Solo listado ========== -->
            <?php
            $misTicketsAbiertos = array_filter($tickets, function($t) { return in_array($t['status'], ['abierto', 'en_proceso', 'pendiente', 'asignado']); });
            $misTicketsResueltos = array_filter($tickets, function($t) { return in_array($t['status'], ['resuelto', 'cerrado']); });
            $misUrgentes = array_filter($tickets, function($t) { return $t['priority'] === 'urgente' && !in_array($t['status'], ['resuelto', 'cerrado']); });
            ?>
            
            <!-- Stats Strip estilo SN -->
            <div class="sn-stats-strip mb-3">
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:var(--primary-dark)"><?= count($tickets) ?></div>
                    <div class="sn-stat-label">Total Asignados</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:#3b82f6"><?= count($misTicketsAbiertos) ?></div>
                    <div class="sn-stat-label">En Curso</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:#059669"><?= count($misTicketsResueltos) ?></div>
                    <div class="sn-stat-label">Resueltos</div>
                </div>
                <div class="sn-stat-item">
                    <div class="sn-stat-number" style="color:#dc2626"><?= count($misUrgentes) ?></div>
                    <div class="sn-stat-label">Urgentes</div>
                    <?php if (count($misUrgentes) > 0): ?>
                    <div class="sn-stat-trend text-danger"><i class="bi bi-exclamation-circle"></i></div>
                    <?php endif; ?>
                </div>
                <div class="sn-stat-item" style="border-right:none;">
                    <a href="?page=mi_cumplimiento" class="btn btn-outline-dark btn-sm"><i class="bi bi-speedometer2 me-1"></i>Mi Cumplimiento SLA</a>
                </div>
                <div class="sn-stat-item" style="border-right:none;">
                    <a href="?page=nuevo_ticket" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo Ticket</a>
                </div>
            </div>
            
            <!-- Toolbar con búsqueda y filtros -->
            <div class="sn-toolbar">
                <div class="sn-breadcrumb">
                    <a href="?page=dashboard">Dashboard</a>
                    <span class="separator">&gt;</span>
                    <span>Mis Tickets</span>
                    <span class="separator">&gt;</span>
                    <span style="color: var(--gray-900); font-weight: 600;"><?= htmlspecialchars($user['name']) ?></span>
                </div>
                <div class="sn-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="myTicketsSearch" placeholder="Buscar en mis tickets..." onkeyup="filterMyTickets()">
                </div>
                <div class="sn-filter-pills">
                    <a href="#" class="sn-pill active" onclick="filterMyByStatus('all');return false;">Todos <small>(<?= count($tickets) ?>)</small></a>
                    <a href="#" class="sn-pill" onclick="filterMyByStatus('open');return false;">En Curso <small>(<?= count($misTicketsAbiertos) ?>)</small></a>
                    <a href="#" class="sn-pill" onclick="filterMyByStatus('closed');return false;">Resueltos <small>(<?= count($misTicketsResueltos) ?>)</small></a>
                    <a href="#" class="sn-pill" onclick="filterMyByStatus('urgent');return false;">Urgentes <small>(<?= count($misUrgentes) ?>)</small></a>
                </div>
            </div>
            
            <!-- Barra de acciones masivas Mis Tickets -->
            <div id="bulkBarMy" class="bulk-action-bar" style="display:none;">
                <form method="POST" id="bulkDeleteFormMy" onsubmit="return confirm('¿Eliminar los tickets seleccionados? Esta acción no se puede deshacer.');">
            <?= csrfInput() ?>
                    <input type="hidden" name="action" value="bulk_delete_tickets">
                    <div id="bulkIdsContainerMy"></div>
                    <div class="d-flex align-items-center gap-3">
                        <span id="bulkCountMy" class="fw-semibold">0 seleccionados</span>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Eliminar Seleccionados</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">Cancelar</button>
                    </div>
                </form>
            </div>
            
            <!-- Tabla unificada de tickets -->
            <?php if (!empty($tickets)): ?>
            <div class="sn-overview-card">
                <div class="table-responsive" style="max-height: calc(100vh - 340px); overflow-y: auto;">
                    <table class="table sn-table mb-0" id="myTicketsTable">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" class="form-check-input" id="selectAllMyTickets" onchange="toggleSelectAll(this, 'myTicketsTable')" title="Seleccionar todos"></th>
                                <th>Número</th>
                                <th>Creado</th>
                                <th>Descripción</th>
                                <th>Solicitante</th>
                                <th>Categoría</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Evidencia</th>
                                <th>Actualizado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr data-ticket-id="<?= $t['id'] ?>" data-status="<?= $t['status'] ?>" data-priority="<?= $t['priority'] ?>">
                            <td onclick="event.stopPropagation();"><input type="checkbox" class="form-check-input ticket-check" value="<?= $t['id'] ?>" onchange="updateBulkBar()"></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;"><span class="sn-link"><?= $t['ticket_number'] ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;" class="text-nowrap"><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($t['title']) ?></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-info-avatar" style="width:26px;height:26px;font-size:0.65rem;"><?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?></div>
                                    <span class="small"><?= htmlspecialchars($t['user_name'] ?? '-') ?></span>
                                </div>
                            </td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;"><span class="small"><?= $categoryLabels[$t['category']] ?? ucfirst($t['category']) ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;">
                                <span class="sn-priority-dot <?= $t['priority'] ?>"></span>
                                <span class="small"><?= ucfirst($t['priority']) ?></span>
                            </td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;"><span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;" class="text-center">
                                <?php 
                                $hasEvidence = (($t['attachment_count'] ?? 0) > 0) || (($t['comment_attachments'] ?? 0) > 0) || is_dir(__DIR__ . '/uploads/tickets/' . $t['ticket_number']);
                                ?>
                                <?php if ($hasEvidence): ?>
                                <span class="badge bg-success"><i class="bi bi-paperclip"></i></span>
                                <?php else: ?>
                                <span class="badge bg-light text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td onclick="window.location='detalle_ticket.php?id=<?= $t['id'] ?>&from=mis_tickets'" style="cursor:pointer;" class="text-nowrap small text-muted"><?= date('Y-m-d H:i', strtotime($t['updated_at'] ?? $t['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-top:1px solid var(--gray-200); background: var(--gray-50);">
                    <small class="text-muted" id="myTicketsCount"><?= count($tickets) ?> registros</small>
                    <a href="?page=mi_cumplimiento" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Ver Mi Cumplimiento SLA</a>
                </div>
            </div>
            
            <script>
            function filterMyTickets() {
                const q = document.getElementById('myTicketsSearch').value.toLowerCase();
                const rows = document.querySelectorAll('#myTicketsTable tbody tr');
                let vis = 0;
                rows.forEach(r => { const s = r.textContent.toLowerCase().includes(q); r.style.display = s ? '' : 'none'; if(s) vis++; });
                document.getElementById('myTicketsCount').textContent = vis + ' registros';
            }
            function filterMyByStatus(status) {
                document.querySelectorAll('.sn-filter-pills .sn-pill').forEach(p => p.classList.remove('active'));
                event.target.closest('.sn-pill').classList.add('active');
                const rows = document.querySelectorAll('#myTicketsTable tbody tr');
                let vis = 0;
                rows.forEach(row => {
                    const st = row.dataset.status, pr = row.dataset.priority;
                    let show = true;
                    if (status === 'open') show = ['abierto','en_proceso','pendiente','asignado'].includes(st);
                    else if (status === 'closed') show = ['resuelto','cerrado'].includes(st);
                    else if (status === 'urgent') show = pr === 'urgente' && !['resuelto','cerrado'].includes(st);
                    row.style.display = show ? '' : 'none';
                    if(show) vis++;
                });
                document.getElementById('myTicketsCount').textContent = vis + ' registros';
            }
            </script>
            <?php else: ?>
            <div class="sn-overview-card p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: var(--gray-300);"></i>
                <p class="text-muted mt-3">No tienes tickets asignados</p>
                <a href="?page=tickets&filter=all" class="btn btn-outline-dark btn-sm">Ver todos los tickets</a>
            </div>
            <?php endif; ?>
            
            <?php elseif ($page === 'mi_cumplimiento'): ?>
            <!-- ========== MI CUMPLIMIENTO SLA - Página separada ========== -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="sn-breadcrumb">
                    <a href="?page=dashboard">Dashboard</a>
                    <span class="separator">&gt;</span>
                    <a href="?page=mis_tickets">Mis Tickets</a>
                    <span class="separator">&gt;</span>
                    <span style="color: var(--gray-900); font-weight: 600;">Mi Cumplimiento SLA</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-dark btn-sm" onclick="downloadPersonalReport()"><i class="bi bi-download me-1"></i>Descargar Reporte</button>
                    <a href="?page=mis_tickets" class="btn btn-outline-dark btn-sm"><i class="bi bi-arrow-left me-1"></i>Mis Tickets</a>
                </div>
            </div>
            
            <?php if ($personalSla['total'] > 0): ?>
            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="personal-sla-card">
                        <div class="personal-sla-value" style="color: <?= $personalSla['tasa_resolucion'] >= 80 ? '#16a34a' : ($personalSla['tasa_resolucion'] >= 50 ? '#d97706' : '#dc2626') ?>"><?= $personalSla['tasa_resolucion'] ?>%</div>
                        <div class="personal-sla-label">Tasa de Resolución</div>
                        <div class="sla-progress-bar">
                            <div class="sla-progress-fill" style="width:<?= $personalSla['tasa_resolucion'] ?>%; background:<?= $personalSla['tasa_resolucion'] >= 80 ? '#16a34a' : ($personalSla['tasa_resolucion'] >= 50 ? '#d97706' : '#dc2626') ?>"></div>
                        </div>
                        <small class="text-muted d-block mt-2"><?= $personalSla['resueltos'] ?> de <?= $personalSla['total'] ?> tickets</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="personal-sla-card">
                        <div class="personal-sla-value" style="color: <?= $personalSla['response_compliance'] >= 80 ? '#16a34a' : ($personalSla['response_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"><?= $personalSla['response_compliance'] ?>%</div>
                        <div class="personal-sla-label">SLA Respuesta</div>
                        <div class="sla-progress-bar">
                            <div class="sla-progress-fill" style="width:<?= $personalSla['response_compliance'] ?>%; background:<?= $personalSla['response_compliance'] >= 80 ? '#16a34a' : ($personalSla['response_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"></div>
                        </div>
                        <small class="text-muted d-block mt-2">Prom: <?= $personalSla['avg_response'] ?>h · Equipo: <?= $slaStats['avg_response'] ?>h</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="personal-sla-card">
                        <div class="personal-sla-value" style="color: <?= $personalSla['assignment_compliance'] >= 80 ? '#16a34a' : ($personalSla['assignment_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"><?= $personalSla['assignment_compliance'] ?>%</div>
                        <div class="personal-sla-label">SLA Asignación</div>
                        <div class="sla-progress-bar">
                            <div class="sla-progress-fill" style="width:<?= $personalSla['assignment_compliance'] ?>%; background:<?= $personalSla['assignment_compliance'] >= 80 ? '#16a34a' : ($personalSla['assignment_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"></div>
                        </div>
                        <small class="text-muted d-block mt-2"><?= $personalSla['within_assignment'] ?> ok · <?= $personalSla['breached_assignment'] ?> incumplidos</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="personal-sla-card">
                        <div class="personal-sla-value" style="color: <?= $personalSla['resolution_compliance'] >= 80 ? '#16a34a' : ($personalSla['resolution_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"><?= $personalSla['resolution_compliance'] ?>%</div>
                        <div class="personal-sla-label">SLA Resolución</div>
                        <div class="sla-progress-bar">
                            <div class="sla-progress-fill" style="width:<?= $personalSla['resolution_compliance'] ?>%; background:<?= $personalSla['resolution_compliance'] >= 80 ? '#16a34a' : ($personalSla['resolution_compliance'] >= 50 ? '#d97706' : '#dc2626') ?>"></div>
                        </div>
                        <small class="text-muted d-block mt-2">Prom: <?= $personalSla['avg_resolution'] ?>h · Equipo: <?= $slaStats['avg_resolution'] ?>h</small>
                    </div>
                </div>
            </div>
            
            <!-- Second row: Comparativa + Tiempos + Resumen personal -->
            <div class="row g-3 mb-4">
                <!-- Comparativa vs Equipo -->
                <div class="col-lg-4">
                    <div class="sn-overview-card" style="height:100%">
                        <div class="sn-overview-header">
                            <span><i class="bi bi-bar-chart me-2"></i>Mi rendimiento vs Equipo</span>
                        </div>
                        <div class="sn-overview-body">
                            <div class="sn-overview-row">
                                <span class="small">Respuesta SLA</span>
                                <div class="d-flex gap-1">
                                    <span class="badge bg-<?= $personalSla['response_compliance'] >= $slaStats['response_compliance'] ? 'success' : 'warning' ?>"><?= $personalSla['response_compliance'] ?>% yo</span>
                                    <span class="badge bg-secondary"><?= $slaStats['response_compliance'] ?>% equipo</span>
                                </div>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">Resolución SLA</span>
                                <div class="d-flex gap-1">
                                    <span class="badge bg-<?= $personalSla['resolution_compliance'] >= $slaStats['resolution_compliance'] ? 'success' : 'warning' ?>"><?= $personalSla['resolution_compliance'] ?>% yo</span>
                                    <span class="badge bg-secondary"><?= $slaStats['resolution_compliance'] ?>% equipo</span>
                                </div>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">Tasa Resolución</span>
                                <div class="d-flex gap-1">
                                    <span class="badge bg-<?= $personalSla['tasa_resolucion'] >= ($ticketMetrics['tasa_resolucion'] ?? 0) ? 'success' : 'warning' ?>"><?= $personalSla['tasa_resolucion'] ?>% yo</span>
                                    <span class="badge bg-secondary"><?= $ticketMetrics['tasa_resolucion'] ?? 0 ?>% equipo</span>
                                </div>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">Asignación SLA</span>
                                <div class="d-flex gap-1">
                                    <span class="badge bg-<?= $personalSla['assignment_compliance'] >= ($slaStats['assignment_compliance'] ?? 0) ? 'success' : 'warning' ?>"><?= $personalSla['assignment_compliance'] ?>% yo</span>
                                    <span class="badge bg-secondary"><?= $slaStats['assignment_compliance'] ?? 0 ?>% equipo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Tiempos Promedio -->
                <div class="col-lg-4">
                    <div class="sn-overview-card" style="height:100%">
                        <div class="sn-overview-header">
                            <span><i class="bi bi-clock-history me-2"></i>Tiempos Promedio</span>
                        </div>
                        <div class="sn-overview-body">
                            <div class="sn-overview-row">
                                <span class="small">Primera respuesta</span>
                                <span class="fw-bold small"><?= $personalSla['avg_response'] ?>h</span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">vs Equipo</span>
                                <span class="small text-muted"><?= $slaStats['avg_response'] ?>h</span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">Resolución total</span>
                                <span class="fw-bold small"><?= $personalSla['avg_resolution'] ?>h</span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">vs Equipo</span>
                                <span class="small text-muted"><?= $slaStats['avg_resolution'] ?>h</span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small">Tickets procesados (30d)</span>
                                <span class="fw-bold small"><?= $personalSla['total'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Info Personal Resumen -->
                <div class="col-lg-4">
                    <div class="sn-overview-card" style="height:100%">
                        <div class="sn-overview-header">
                            <span><i class="bi bi-person-badge me-2"></i>Información del Analista</span>
                        </div>
                        <div class="sn-overview-body">
                            <div class="sn-overview-row">
                                <span class="small text-muted">Nombre</span>
                                <span class="small fw-bold"><?= htmlspecialchars($user['name']) ?></span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small text-muted">Correo</span>
                                <span class="small"><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small text-muted">Rol</span>
                                <span class="badge bg-dark"><?= ucfirst($user['role']) ?></span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small text-muted">Tickets actuales</span>
                                <span class="small fw-bold"><?= count($tickets) ?></span>
                            </div>
                            <div class="sn-overview-row">
                                <span class="small text-muted">Período</span>
                                <span class="small">Últimos 30 días</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de cumplimiento personal -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="sn-overview-card">
                        <div class="sn-overview-header">
                            <span><i class="bi bi-bullseye me-2"></i>Mi Cumplimiento vs Objetivo</span>
                        </div>
                        <div style="padding:16px;">
                            <canvas id="personalSlaRadar" height="220"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="sn-overview-card">
                        <div class="sn-overview-header">
                            <span><i class="bi bi-graph-up me-2"></i>Distribución de Tickets Personales</span>
                        </div>
                        <div style="padding:16px;">
                            <canvas id="personalTicketDistChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <div class="sn-overview-card p-5 text-center">
                <i class="bi bi-speedometer2" style="font-size: 3rem; color: var(--gray-300);"></i>
                <p class="text-muted mt-3">No hay datos SLA personales en los últimos 30 días</p>
                <a href="?page=mis_tickets" class="btn btn-outline-dark btn-sm">Ver Mis Tickets</a>
            </div>
            <?php endif; ?>
            
            <!-- Script para gráficos y descarga -->
            <script>
            <?php if ($personalSla['total'] > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Radar Chart: Mi cumplimiento vs objetivo
                new Chart(document.getElementById('personalSlaRadar'), {
                    type: 'radar',
                    data: {
                        labels: ['Tasa Resolución', 'SLA Respuesta', 'SLA Asignación', 'SLA Resolución'],
                        datasets: [{
                            label: 'Mi Cumplimiento',
                            data: [<?= $personalSla['tasa_resolucion'] ?>, <?= $personalSla['response_compliance'] ?>, <?= $personalSla['assignment_compliance'] ?>, <?= $personalSla['resolution_compliance'] ?>],
                            backgroundColor: 'rgba(12,90,138,0.15)',
                            borderColor: '#0c5a8a',
                            borderWidth: 2,
                            pointBackgroundColor: '#0c5a8a'
                        }, {
                            label: 'Equipo',
                            data: [<?= $ticketMetrics['tasa_resolucion'] ?? 0 ?>, <?= $slaStats['response_compliance'] ?? 0 ?>, <?= $slaStats['assignment_compliance'] ?? 0 ?>, <?= $slaStats['resolution_compliance'] ?? 0 ?>],
                            backgroundColor: 'rgba(107,114,128,0.1)',
                            borderColor: '#6b7280',
                            borderWidth: 1.5,
                            borderDash: [4, 4],
                            pointBackgroundColor: '#6b7280'
                        }, {
                            label: 'Objetivo (80%)',
                            data: [80, 80, 80, 80],
                            backgroundColor: 'transparent',
                            borderColor: '#dc2626',
                            borderWidth: 1,
                            borderDash: [2, 2],
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20, font: { size: 9 } }, pointLabels: { font: { size: 10 } } } },
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
                    }
                });
                // Ticket distribution chart
                <?php
                $myByStatus = ['abierto' => 0, 'en_proceso' => 0, 'pendiente' => 0, 'resuelto' => 0, 'cerrado' => 0];
                foreach ($tickets as $t) { if (isset($myByStatus[$t['status']])) $myByStatus[$t['status']]++; }
                $myByPrio = ['urgente' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0];
                foreach ($tickets as $t) { if (isset($myByPrio[$t['priority']])) $myByPrio[$t['priority']]++; }
                ?>
                new Chart(document.getElementById('personalTicketDistChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Abierto','En Proceso','Pendiente','Resuelto','Cerrado'],
                        datasets: [{
                            label: 'Mis Tickets por Estado',
                            data: [<?= $myByStatus['abierto'] ?>, <?= $myByStatus['en_proceso'] ?>, <?= $myByStatus['pendiente'] ?>, <?= $myByStatus['resuelto'] ?>, <?= $myByStatus['cerrado'] ?>],
                            backgroundColor: ['#3b82f6','#8b5cf6','#f59e0b','#10b981','#6b7280'],
                            borderRadius: 4,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                            x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                        }
                    }
                });
            });
            <?php endif; ?>
            
            function downloadPersonalReport() {
                const reportData = {
                    analista: <?= json_encode($user['name']) ?>,
                    correo: <?= json_encode($user['email']) ?>,
                    rol: <?= json_encode(ucfirst($user['role'])) ?>,
                    periodo: 'Últimos 30 días',
                    fecha_generacion: new Date().toLocaleString('es-CO'),
                    total_tickets: <?= count($tickets) ?>,
                    sla: {
                        tasa_resolucion: '<?= $personalSla['tasa_resolucion'] ?>%',
                        sla_respuesta: '<?= $personalSla['response_compliance'] ?>%',
                        sla_asignacion: '<?= $personalSla['assignment_compliance'] ?>%',
                        sla_resolucion: '<?= $personalSla['resolution_compliance'] ?>%',
                        tiempo_respuesta_promedio: '<?= $personalSla['avg_response'] ?>h',
                        tiempo_resolucion_promedio: '<?= $personalSla['avg_resolution'] ?>h',
                        tickets_resueltos: <?= $personalSla['resueltos'] ?? 0 ?>,
                        tickets_total: <?= $personalSla['total'] ?? 0 ?>
                    },
                    equipo: {
                        sla_respuesta: '<?= $slaStats['response_compliance'] ?? 0 ?>%',
                        sla_resolucion: '<?= $slaStats['resolution_compliance'] ?? 0 ?>%',
                        tiempo_respuesta_promedio: '<?= $slaStats['avg_response'] ?? 0 ?>h',
                        tiempo_resolucion_promedio: '<?= $slaStats['avg_resolution'] ?? 0 ?>h'
                    }
                };
                
                const tickets = <?= json_encode(array_map(function($t) use ($statusLabels, $categoryLabels) {
                    return [
                        'numero' => $t['ticket_number'],
                        'titulo' => $t['title'],
                        'estado' => $statusLabels[$t['status']] ?? $t['status'],
                        'prioridad' => ucfirst($t['priority']),
                        'categoria' => $categoryLabels[$t['category']] ?? $t['category'],
                        'creado' => $t['created_at'],
                        'solicitante' => $t['user_name'] ?? '-'
                    ];
                }, $tickets)) ?>;
                
                let html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Reporte Personal - ${reportData.analista}</title>
                <div class="header">
                    <h1>Reporte de Cumplimiento SLA Personal</h1>
                    <p>${reportData.analista} · ${reportData.correo} · ${reportData.rol} · Generado: ${reportData.fecha_generacion}</p>
                </div>
                <div class="section">
                    <h2>Indicadores Clave de Rendimiento (KPIs)</h2>
                    <div class="grid">
                        <div class="kpi"><div class="value ${parseFloat(reportData.sla.tasa_resolucion) >= 80 ? 'green' : parseFloat(reportData.sla.tasa_resolucion) >= 50 ? 'yellow' : 'red'}">${reportData.sla.tasa_resolucion}</div><div class="label">Tasa Resolución</div></div>
                        <div class="kpi"><div class="value ${parseFloat(reportData.sla.sla_respuesta) >= 80 ? 'green' : parseFloat(reportData.sla.sla_respuesta) >= 50 ? 'yellow' : 'red'}">${reportData.sla.sla_respuesta}</div><div class="label">SLA Respuesta</div></div>
                        <div class="kpi"><div class="value ${parseFloat(reportData.sla.sla_asignacion) >= 80 ? 'green' : parseFloat(reportData.sla.sla_asignacion) >= 50 ? 'yellow' : 'red'}">${reportData.sla.sla_asignacion}</div><div class="label">SLA Asignación</div></div>
                        <div class="kpi"><div class="value ${parseFloat(reportData.sla.sla_resolucion) >= 80 ? 'green' : parseFloat(reportData.sla.sla_resolucion) >= 50 ? 'yellow' : 'red'}">${reportData.sla.sla_resolucion}</div><div class="label">SLA Resolución</div></div>
                    </div>
                </div>
                <div class="section">
                    <h2>Comparativa Personal vs Equipo</h2>
                    <div class="info-grid">
                        <div>
                            <div class="info-item"><span>T. Respuesta (yo)</span><strong>${reportData.sla.tiempo_respuesta_promedio}</strong></div>
                            <div class="info-item"><span>T. Respuesta (equipo)</span><strong>${reportData.equipo.tiempo_respuesta_promedio}</strong></div>
                            <div class="info-item"><span>SLA Respuesta (yo)</span><strong>${reportData.sla.sla_respuesta}</strong></div>
                            <div class="info-item"><span>SLA Respuesta (equipo)</span><strong>${reportData.equipo.sla_respuesta}</strong></div>
                        </div>
                        <div>
                            <div class="info-item"><span>T. Resolución (yo)</span><strong>${reportData.sla.tiempo_resolucion_promedio}</strong></div>
                            <div class="info-item"><span>T. Resolución (equipo)</span><strong>${reportData.equipo.tiempo_resolucion_promedio}</strong></div>
                            <div class="info-item"><span>SLA Resolución (yo)</span><strong>${reportData.sla.sla_resolucion}</strong></div>
                            <div class="info-item"><span>SLA Resolución (equipo)</span><strong>${reportData.equipo.sla_resolucion}</strong></div>
                        </div>
                    </div>
                </div>
                <div class="section">
                    <h2>Listado de Tickets Asignados (${tickets.length})</h2>
                    <table>
                        <thead><tr><th>Número</th><th>Título</th><th>Estado</th><th>Prioridad</th><th>Categoría</th><th>Solicitante</th><th>Creado</th></tr></thead>
                        <tbody>`;
                tickets.forEach(t => {
                    html += `<tr><td>${t.numero}</td><td>${t.titulo}</td><td>${t.estado}</td><td>${t.prioridad}</td><td>${t.categoria}</td><td>${t.solicitante}</td><td>${t.creado}</td></tr>`;
                });
                html += `</tbody></table></div>
                <div class="footer">Empresa Portuaria Coquimbo - Sistema de Soporte TI · Reporte generado automáticamente · ${reportData.fecha_generacion}</div>
                </body></html>`;
                
                const blob = new Blob([html], { type: 'text/html' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'Reporte_SLA_Personal_' + reportData.analista.replace(/\s+/g, '_') + '_' + new Date().toISOString().slice(0,10) + '.html';
                a.click();
                URL.revokeObjectURL(url);
            }
            </script>
            
            <?php elseif ($page === 'nuevo_ticket'): ?>
            <!-- ========== NUEVO TICKET ========== -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom"><h5 class="card-title-custom"><i class="bi bi-plus-circle me-2"></i>Crear Nuevo Ticket</h5></div>
                        <div class="p-4">
                            <form method="POST" enctype="multipart/form-data">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="create_ticket">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nombre *</label>
                                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($user['name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email *</label>
                                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Categoría *</label>
                                        <select name="category" class="form-select" required>
                                            <option value="hardware">Hardware</option>
                                            <option value="software" selected>Software</option>
                                            <option value="red">Red</option>
                                            <option value="acceso">Acceso</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Prioridad *</label>
                                        <select name="priority" class="form-select" required>
                                            <option value="baja">Baja</option>
                                            <option value="media" selected>Media</option>
                                            <option value="alta">Alta</option>
                                            <option value="urgente">Urgente</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Título del problema *</label>
                                        <input type="text" name="title" class="form-control" required placeholder="Describe brevemente el problema">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Descripción detallada *</label>
                                        <textarea name="description" class="form-control" rows="5" required placeholder="Describe el problema con el mayor detalle posible..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Adjuntar evidencia (imágenes, capturas)</label>
                                        <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,.pdf,.doc,.docx">
                                        <small class="text-muted">Máximo 5 archivos. Formatos: JPG, PNG, PDF, DOC (máx 5MB c/u)</small>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-dark btn-lg"><i class="bi bi-send me-2"></i>Crear Ticket</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($page === 'usuarios' && $isAdmin): ?>
            <!-- ========== USUARIOS (SOLO ADMIN) ========== -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Gestión de Usuarios</h5>
                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-plus-lg me-2"></i>Nuevo Usuario</button>
            </div>
            
            <div class="card-custom">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td><div class="user-info"><div class="user-info-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div><?= htmlspecialchars($u['name']) ?></div></td>
                            <td><code><?= htmlspecialchars($u['username'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'soporte' ? 'primary' : ($u['role'] === 'social' ? 'success' : 'secondary')) ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <button class="btn-action btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="Editar"><i class="bi bi-pencil"></i></button>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?')">
            <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-action btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Modal Editar Usuario -->
                        <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <form method="POST">
            <?= csrfInput() ?>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="mb-3"><label class="form-label">Nombre completo</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required></div>
                                            <div class="mb-3"><label class="form-label">Usuario</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username'] ?? '') ?>" required placeholder="nombre.apellido"></div>
                                            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                                            <div class="mb-3"><label class="form-label">Nueva Contraseña (dejar vacío para mantener)</label><input type="password" name="password" class="form-control"></div>
                                            <div class="mb-3">
                                                <label class="form-label">Rol</label>
                                                <select name="role" class="form-select">
                                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Usuario</option>
                                                    <option value="social" <?= $u['role'] === 'social' ? 'selected' : '' ?>>Comunicaciones</option>
                                                    <option value="soporte" <?= $u['role'] === 'soporte' ? 'selected' : '' ?>>Soporte</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-dark">Guardar</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Modal Crear Usuario -->
            <div class="modal fade" id="createUserModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Crear Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
            <?= csrfInput() ?>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="create_user">
                                <div class="mb-3"><label class="form-label">Nombre completo *</label><input type="text" name="name" class="form-control" required placeholder="Ej: Juan Pérez González"></div>
                                <div class="mb-3"><label class="form-label">Usuario</label><input type="text" name="username" class="form-control" placeholder="nombre.apellido (se genera automáticamente si se deja vacío)"></div>
                                <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Contraseña *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                                <div class="mb-3">
                                    <label class="form-label">Rol *</label>
                                    <select name="role" class="form-select" required>
                                        <option value="user">Usuario</option>
                                        <option value="social">Comunicaciones</option>
                                        <option value="soporte">Soporte</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-dark">Crear</button></div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($page === 'sla' || $page === 'cumplimiento'): ?>
            <!-- ========== CUMPLIMIENTO ========== -->
            <div class="row g-4 mb-4">
                <!-- Tarjetas de métricas SLA -->
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value text-<?= $slaStats['response_compliance'] >= 80 ? 'success' : ($slaStats['response_compliance'] >= 60 ? 'warning' : 'danger') ?>"><?= $slaStats['response_compliance'] ?>%</div>
                                <div class="stat-label">SLA Respuesta</div>
                                <small class="text-muted">Promedio: <?= $slaStats['avg_response'] ?>h</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-chat-dots"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value text-<?= $slaStats['assignment_compliance'] >= 80 ? 'success' : ($slaStats['assignment_compliance'] >= 60 ? 'warning' : 'danger') ?>"><?= $slaStats['assignment_compliance'] ?>%</div>
                                <div class="stat-label">SLA Asignación</div>
                                <small class="text-muted">Promedio: <?= $slaStats['avg_assignment'] ?>h</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value text-<?= $slaStats['resolution_compliance'] >= 80 ? 'success' : ($slaStats['resolution_compliance'] >= 60 ? 'warning' : 'danger') ?>"><?= $slaStats['resolution_compliance'] ?>%</div>
                                <div class="stat-label">SLA Resolución</div>
                                <small class="text-muted">Promedio: <?= $slaStats['avg_resolution'] ?>h</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $slaStats['avg_work'] ?>h</div>
                                <div class="stat-label">Tiempo de Trabajo</div>
                                <small class="text-muted">Promedio por ticket</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ciclo de vida del ticket -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="card-title-custom"><i class="bi bi-diagram-3 me-2"></i>Ciclo de Vida del Ticket - Tiempos SLA</h6>
                </div>
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <div class="text-center flex-fill">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-plus-circle text-primary fs-4"></i>
                            </div>
                            <div class="mt-2 fw-bold">Creación</div>
                            <small class="text-muted">Ticket abierto</small>
                        </div>
                        <div class="text-muted"><i class="bi bi-arrow-right fs-4"></i></div>
                        <div class="text-center flex-fill">
                            <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-chat-dots text-info fs-4"></i>
                            </div>
                            <div class="mt-2 fw-bold">Primera Respuesta</div>
                            <small class="text-muted">SLA: Según prioridad</small>
                        </div>
                        <div class="text-muted"><i class="bi bi-arrow-right fs-4"></i></div>
                        <div class="text-center flex-fill">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-person-check text-warning fs-4"></i>
                            </div>
                            <div class="mt-2 fw-bold">Asignación</div>
                            <small class="text-muted">Técnico asignado</small>
                        </div>
                        <div class="text-muted"><i class="bi bi-arrow-right fs-4"></i></div>
                        <div class="text-center flex-fill">
                            <div class="rounded-circle bg-secondary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-gear text-secondary fs-4"></i>
                            </div>
                            <div class="mt-2 fw-bold">En Trabajo</div>
                            <small class="text-muted">Técnico trabajando</small>
                        </div>
                        <div class="text-muted"><i class="bi bi-arrow-right fs-4"></i></div>
                        <div class="text-center flex-fill">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                            <div class="mt-2 fw-bold">Resolución</div>
                            <small class="text-muted">SLA: Según prioridad</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <!-- Objetivos SLA por Prioridad -->
                <div class="col-lg-7">
                    <div class="card-custom p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bullseye me-2"></i>Objetivos SLA por Prioridad</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Prioridad</th>
                                        <th class="text-center">Respuesta</th>
                                        <th class="text-center">Asignación</th>
                                        <th class="text-center">Resolución</th>
                                        <th class="text-center">Tickets</th>
                                        <th class="text-center">Cumplimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slaTargets as $priority => $targets): 
                                        $pStats = $slaStats['by_priority'][$priority] ?? ['total' => 0, 'resolution_ok' => 0, 'resolution_breached' => 0];
                                        $resolvedInPriority = ($pStats['resolution_ok'] ?? 0) + ($pStats['resolution_breached'] ?? 0);
                                        $compliance = $resolvedInPriority > 0 ? round(($pStats['resolution_ok'] / $resolvedInPriority) * 100, 1) : 100;
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-<?= $priorityColors[$priority] ?> px-3"><?= ucfirst($priority) ?></span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark"><?= $targets['response'] ?>h</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark"><?= $targets['assignment'] ?>h</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark"><?= $targets['resolution'] ?>h</span></td>
                                        <td class="text-center"><strong><?= $pStats['total'] ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar bg-<?= $compliance >= 80 ? 'success' : ($compliance >= 60 ? 'warning' : 'danger') ?>" style="width: <?= $compliance ?>%"></div>
                                                </div>
                                                <small class="fw-bold" style="min-width:40px;"><?= $compliance ?>%</small>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico SLA -->
                <div class="col-lg-5">
                    <div class="card-custom p-4" style="height: 100%;">
                        <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2"></i>Cumplimiento General SLA</h6>
                        <div class="chart-container">
                            <canvas id="slaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos de Rendimiento -->
            <div class="row g-3 mb-4">
                <!-- Tendencia Semanal -->
                <div class="col-lg-8">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="chart-title mb-0"><i class="bi bi-graph-up me-2"></i>Tendencia Semanal</h5>
                            <span class="badge bg-light text-dark">Últimos 7 días</span>
                        </div>
                        <div class="chart-container-lg">
                            <canvas id="weeklyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Tickets por Técnico -->
                <div class="col-lg-4">
                    <div class="chart-card" style="height: 100%;">
                        <h5 class="chart-title"><i class="bi bi-person-badge me-2"></i>Tickets por Técnico</h5>
                        <?php if (empty($ticketsPerTechnician) || array_sum(array_column($ticketsPerTechnician, 'tickets_asignados')) == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">Sin tickets asignados</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                            <?php foreach ($ticketsPerTechnician as $tech): ?>
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($tech['name']) ?></span>
                                    <span class="badge bg-primary rounded-pill"><?= $tech['tickets_asignados'] ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($tech['abiertos'] > 0): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Abiertos: <?= $tech['abiertos'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($tech['en_progreso'] > 0): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>En progreso: <?= $tech['en_progreso'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($tech['resueltos'] > 0): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Resueltos: <?= $tech['resueltos'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <!-- Gráfico Estado -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="chart-title">Estado de Tickets</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Gráfico Categoría -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="chart-title">Por Categoría</h5>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detalle de Tickets con Tiempos SLA -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6 class="card-title-custom"><i class="bi bi-table me-2"></i>Detalle de Tickets SLA (Últimos 30 días)</h6>
                    <span class="badge bg-dark"><?= count($slaTickets) ?> tickets</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th class="text-center">T. Respuesta</th>
                                <th class="text-center">T. Asignación</th>
                                <th class="text-center">T. Resolución</th>
                                <th class="text-center">SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($slaTickets, 0, 20) as $t): 
                                $target = $slaTargets[$t['priority']] ?? $slaTargets['media'];
                                
                                // Calcular estados SLA
                                $responseOk = $t['first_response_at'] ? ($t['response_minutes'] <= $target['response_min']) : null;
                                $assignmentOk = $t['assigned_at'] ? ($t['assignment_minutes'] <= $target['assignment_min']) : null;
                                $resolutionOk = in_array($t['status'], ['resuelto', 'cerrado']) ? ($t['resolution_minutes'] <= $target['resolution_min']) : null;
                                
                                // Formatear tiempos
                                $formatTime = function($minutes) {
                                    if ($minutes < 60) return $minutes . 'm';
                                    if ($minutes < 1440) return round($minutes / 60, 1) . 'h';
                                    return round($minutes / 1440, 1) . 'd';
                                };
                                
                                // Determinar estado general del SLA
                                $overallSla = 'ok';
                                if ($responseOk === false || $assignmentOk === false || $resolutionOk === false) {
                                    $overallSla = 'breached';
                                } elseif ($responseOk === null && $assignmentOk === null) {
                                    $overallSla = 'pending';
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="detalle_ticket.php?id=<?= $t['id'] ?>&from=sla" class="text-decoration-none fw-bold"><?= $t['ticket_number'] ?></a>
                                    <div class="small text-muted text-truncate" style="max-width:150px;"><?= htmlspecialchars($t['title']) ?></div>
                                </td>
                                <td><span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                                <td><span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></td>
                                <td class="text-center">
                                    <?php if ($t['first_response_at']): ?>
                                        <span class="badge bg-<?= $responseOk ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $responseOk ? 'success' : 'danger' ?>">
                                            <?= $formatTime($t['response_minutes']) ?>
                                        </span>
                                        <div class="small text-muted">max: <?= $target['response'] ?>h</div>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning">Esperando</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t['assigned_at']): ?>
                                        <span class="badge bg-<?= $assignmentOk ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $assignmentOk ? 'success' : 'danger' ?>">
                                            <?= $formatTime($t['assignment_minutes']) ?>
                                        </span>
                                        <div class="small text-muted">max: <?= $target['assignment'] ?>h</div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (in_array($t['status'], ['resuelto', 'cerrado'])): ?>
                                        <span class="badge bg-<?= $resolutionOk ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $resolutionOk ? 'success' : 'danger' ?>">
                                            <?= $formatTime($t['resolution_minutes']) ?>
                                        </span>
                                        <div class="small text-muted">max: <?= $target['resolution'] ?>h</div>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info">En curso</span>
                                        <div class="small text-muted"><?= $formatTime($t['resolution_minutes']) ?> transcurrido</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($overallSla === 'ok'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>OK</span>
                                    <?php elseif ($overallSla === 'breached'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Excedido</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i>Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($slaTickets) > 20): ?>
                <div class="p-3 bg-light text-center">
                    <small class="text-muted">Mostrando 20 de <?= count($slaTickets) ?> tickets</small>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Gráficos adicionales de rendimiento -->
            <div class="row g-3 mb-4">
                <!-- Prioridad -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title"><i class="bi bi-flag me-2"></i>Por Prioridad</h5>
                        <div class="chart-container">
                            <canvas id="cumplPriorityChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Actividad por Hora -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title"><i class="bi bi-clock me-2"></i>Actividad por Hora</h5>
                        <div class="chart-container">
                            <canvas id="cumplHourlyChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Categoría -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title"><i class="bi bi-pie-chart me-2"></i>Por Categoría</h5>
                        <div class="chart-container">
                            <canvas id="cumplCategoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Descarga por Usuario -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h6 class="card-title-custom"><i class="bi bi-download me-2"></i>Descargar Informe por Técnico</h6>
                </div>
                <div class="p-4">
                    <div class="row align-items-end g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Seleccionar técnico</label>
                            <select id="cumplUserSelect" class="form-select">
                                <option value="">— Todos los técnicos —</option>
                                <?php foreach ($ticketsPerTechnician as $tech): ?>
                                <option value="<?= $tech['id'] ?>" data-name="<?= htmlspecialchars($tech['name']) ?>"><?= htmlspecialchars($tech['name']) ?> (<?= $tech['tickets_asignados'] ?> tickets)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" onclick="downloadUserComplianceReport()">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Descargar Informe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Script Gráficos Cumplimiento -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Priority Chart
                <?php
                $prioData = ['urgente' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0];
                foreach ($tickets as $t) { if (isset($prioData[$t['priority']])) $prioData[$t['priority']]++; }
                ?>
                new Chart(document.getElementById('cumplPriorityChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Urgente','Alta','Media','Baja'],
                        datasets: [{
                            data: [<?= $prioData['urgente'] ?>, <?= $prioData['alta'] ?>, <?= $prioData['media'] ?>, <?= $prioData['baja'] ?>],
                            backgroundColor: ['#dc2626','#f59e0b','#3b82f6','#94a3b8'],
                            borderWidth: 2, borderColor: '#fff'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
                });
                // Hourly Activity
                <?php
                $hourlyData = array_fill(0, 24, 0);
                if (!empty($ticketsByHour)) { foreach ($ticketsByHour as $h) { $hourlyData[(int)$h['hora']] = (int)$h['count']; } }
                $hourLabels = []; for ($i = 7; $i <= 20; $i++) $hourLabels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $hourValues = array_slice($hourlyData, 7, 14);
                ?>
                new Chart(document.getElementById('cumplHourlyChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($hourLabels) ?>,
                        datasets: [{ data: <?= json_encode($hourValues) ?>, backgroundColor: 'rgba(12,90,138,0.6)', borderRadius: 3, barPercentage: 0.7 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 9 } } }, x: { ticks: { font: { size: 8 }, maxRotation: 45 } } } }
                });
                // Category Doughnut
                new Chart(document.getElementById('cumplCategoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_column($ticketsByCategory ?? [], 'category')) ?>.map(c => ({'hardware':'Hardware','software':'Software','red':'Red','accesos':'Accesos','correo':'Correo','impresora':'Impresora','telefonia':'Telefonía','otro':'Otro'})[c] || c),
                        datasets: [{ data: <?= json_encode(array_column($ticketsByCategory ?? [], 'count')) ?>, backgroundColor: ['#3b82f6','#f59e0b','#10b981','#8b5cf6','#ec4899','#6366f1','#14b8a6','#94a3b8'], borderWidth: 2, borderColor: '#fff' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
                });
            });
            
            function downloadUserComplianceReport() {
                const sel = document.getElementById('cumplUserSelect');
                const userId = sel.value;
                const userName = userId ? sel.options[sel.selectedIndex].dataset.name : 'Todos los Técnicos';
                const reportDate = new Date().toLocaleDateString('es-CL', { year: 'numeric', month: 'long', day: 'numeric' });
                
                // Recopilar filas de SLA para este usuario
                const slaData = <?= json_encode(array_map(function($t) use ($slaTargets, $statusLabels) {
                    $target = $slaTargets[$t['priority']] ?? $slaTargets['media'];
                    $formatTime = function($m) { if ($m < 60) return $m.'m'; if ($m < 1440) return round($m/60,1).'h'; return round($m/1440,1).'d'; };
                    return [
                        'id' => $t['id'],
                        'ticket_number' => $t['ticket_number'],
                        'title' => $t['title'],
                        'priority' => ucfirst($t['priority']),
                        'status' => $statusLabels[$t['status']] ?? $t['status'],
                        'assigned_to' => $t['assigned_to'] ?? null,
                        'assigned_name' => $t['assigned_name'] ?? 'Sin asignar',
                        'response_time' => $t['first_response_at'] ? $formatTime($t['response_minutes']) : 'Pendiente',
                        'assignment_time' => $t['assigned_at'] ? $formatTime($t['assignment_minutes']) : 'Sin asignar',
                        'resolution_time' => in_array($t['status'], ['resuelto','cerrado']) ? $formatTime($t['resolution_minutes']) : 'En curso',
                        'sla_ok' => !($t['first_response_at'] && $t['response_minutes'] > $target['response_min']) && !(in_array($t['status'],['resuelto','cerrado']) && $t['resolution_minutes'] > $target['resolution_min']),
                    ];
                }, $slaTickets)) ?>;
                
                const filtered = userId ? slaData.filter(t => String(t.assigned_to) === userId) : slaData;
                const totalFiltered = filtered.length;
                const slaOk = filtered.filter(t => t.sla_ok).length;
                const slaPct = totalFiltered > 0 ? Math.round((slaOk / totalFiltered) * 100) : 100;
                
                let tableRows = '';
                filtered.forEach(t => {
                    const slaClass = t.sla_ok ? 'color:#16a34a' : 'color:#dc2626';
                    const slaIcon = t.sla_ok ? '✓ OK' : '✗ Excedido';
                    tableRows += '<tr><td>' + t.ticket_number + '</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + t.title + '</td><td>' + t.priority + '</td><td>' + t.status + '</td><td>' + t.assigned_name + '</td><td>' + t.response_time + '</td><td>' + t.resolution_time + '</td><td style="font-weight:700;' + slaClass + '">' + slaIcon + '</td></tr>';
                });
                
                const html = `<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<title>Informe Cumplimiento - ${userName}</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Lato:wght@300;400;700&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Lato', sans-serif; color: #1e293b; padding: 30px 40px; background: #fff; line-height: 1.5; }
    h1 { font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 700; color: #0c4a6e; margin-bottom: 8px; border-bottom: 3px solid #0369a1; padding-bottom: 10px; }
    h2 { font-family: 'Montserrat', sans-serif; font-size: 16px; font-weight: 700; color: #0c4a6e; margin: 24px 0 12px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; }
    .header-info { display: flex; justify-content: space-between; color: #64748b; font-size: 13px; margin-bottom: 20px; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
    .stat-box .number { font-size: 22px; font-weight: 700; color: #0369a1; }
    .stat-box .label { font-size: 11px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
    th { background: #0c4a6e; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
    tr:nth-child(even) { background: #f8fafc; }
    tr:hover { background: #f1f5f9; }
    .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 11px; }
    .footer p { margin: 3px 0; }
    .no-print button { background: #0c5a8a; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; }
    @media print { body { padding: 15px; } .no-print { display: none !important; } table { page-break-inside: auto; } tr { page-break-inside: avoid; } }
</style>
</head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
<button onclick="window.print()" style="background:#0c5a8a;color:white;border:none;padding:10px 25px;border-radius:6px;cursor:pointer;">Imprimir / Guardar como PDF</button>
</div>
<h1>Informe de Cumplimiento — ${userName}</h1>
<div class="header-info">
<span>Empresa Portuaria Coquimbo — Soporte TI</span>
<span>Generado el ${reportDate}</span>
</div>
<h2>Resumen</h2>
<div class="stats-grid">
<div class="stat-box"><div class="number">${totalFiltered}</div><div class="label">Total Tickets</div></div>
<div class="stat-box"><div class="number" style="color:#16a34a">${slaOk}</div><div class="label">SLA Cumplido</div></div>
<div class="stat-box"><div class="number" style="color:#dc2626">${totalFiltered - slaOk}</div><div class="label">SLA Excedido</div></div>
<div class="stat-box"><div class="number" style="color:${slaPct >= 80 ? '#16a34a' : slaPct >= 50 ? '#d97706' : '#dc2626'}">${slaPct}%</div><div class="label">Cumplimiento</div></div>
</div>
<h2>Detalle de Tickets</h2>
<table>
<thead><tr><th>Ticket</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Asignado</th><th>T.Respuesta</th><th>T.Resolución</th><th>SLA</th></tr></thead>
<tbody>${tableRows || '<tr><td colspan="8" style="text-align:center;padding:20px;">Sin tickets</td></tr>'}</tbody>
</table>
<div class="footer">
<p>Empresa Portuaria Coquimbo — Sistema de Soporte TI</p>
<p>Informe generado automáticamente · ${reportDate}</p>
</div></body></html>`;

                const w = window.open('', '_blank');
                w.document.write(html);
                w.document.close();
            }
            </script>
            
            <?php elseif ($page === 'auditoria'): ?>
            <!-- ========== AUDITORÍA DEL SISTEMA ========== -->
            
            <!-- Barra de acciones -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <p class="mb-1 text-muted small">Panel de auditoría y métricas del sistema de soporte. Aquí puedes supervisar la actividad, rendimiento de técnicos, cumplimiento SLA y tendencias de tickets.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="downloadAuditCSV()">
                        <i class="bi bi-filetype-csv me-1"></i>Exportar CSV
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="downloadAuditReport()">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Descargar Informe
                    </button>
                </div>
            </div>

            <!-- ===== FILA 1: KPIs principales ===== -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= number_format($ticketMetrics['total']) ?></div>
                                <div class="stat-label">Total Tickets</div>
                                <small class="text-muted"><?= $ticketMetrics['creados_mes'] ?> este mes · <?= $ticketMetrics['creados_semana'] ?> esta semana</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= number_format($ticketMetrics['resueltos']) ?></div>
                                <div class="stat-label">Tickets Resueltos</div>
                                <small class="text-muted"><?= $ticketMetrics['resueltos_mes'] ?> este mes · <?= $ticketMetrics['resueltos_semana'] ?> esta semana</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $ticketMetrics['tasa_resolucion'] ?>%</div>
                                <div class="stat-label">Tasa de Resolución</div>
                                <small class="text-muted"><?= $ticketMetrics['tasa_resolucion_mes'] ?>% este mes · <?= $ticketMetrics['pendientes'] ?> pendientes</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $ticketMetrics['urgentes_abiertos'] ?></div>
                                <div class="stat-label">Urgentes Abiertos</div>
                                <small class="text-muted"><?= $reOpenedCount ?> reabiertos (30 días)</small>
                            </div>
                            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== FILA 2: Gráficos de tendencias ===== -->
            <div class="row g-4 mb-4">
                <!-- Creados vs Resueltos por mes -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-bar-chart-line me-2"></i>Tickets Creados vs Resueltos por Mes</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 280px;">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Distribución por estado -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-pie-chart me-2"></i>Estado Actual de Tickets</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 220px;">
                                <canvas id="auditStatusChart"></canvas>
                            </div>
                            <div class="mt-2">
                                <?php foreach ($ticketsByStatus as $s): ?>
                                <div class="d-flex justify-content-between align-items-center py-1">
                                    <span class="badge bg-<?= $statusColors[$s['status']] ?? 'secondary' ?> py-1"><?= $statusLabels[$s['status']] ?? ucfirst($s['status']) ?></span>
                                    <span class="fw-bold small"><?= $s['count'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== FILA 3: Cumplimiento SLA + Categorías + Horas Pico ===== -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-speedometer2 me-2"></i>Cumplimiento SLA</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 200px; position: relative;">
                                <canvas id="slaComplianceChart"></canvas>
                            </div>
                            <div class="row mt-3 text-center small">
                                <div class="col-4">
                                    <div class="fw-bold <?= $slaStats['response_compliance'] >= 80 ? 'text-success' : ($slaStats['response_compliance'] >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $slaStats['response_compliance'] ?>%</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Respuesta</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold <?= $slaStats['assignment_compliance'] >= 80 ? 'text-success' : ($slaStats['assignment_compliance'] >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $slaStats['assignment_compliance'] ?>%</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Asignación</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold <?= $slaStats['resolution_compliance'] >= 80 ? 'text-success' : ($slaStats['resolution_compliance'] >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $slaStats['resolution_compliance'] ?>%</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Resolución</div>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="small text-muted">
                                <div class="d-flex justify-content-between mb-1"><span>Prom. respuesta:</span><strong><?= $slaStats['avg_response'] ?>h</strong></div>
                                <div class="d-flex justify-content-between mb-1"><span>Prom. asignación:</span><strong><?= $slaStats['avg_assignment'] ?>h</strong></div>
                                <div class="d-flex justify-content-between mb-1"><span>Prom. resolución:</span><strong><?= $slaStats['avg_resolution'] ?>h</strong></div>
                                <div class="d-flex justify-content-between"><span>Prom. trabajo:</span><strong><?= $slaStats['avg_work'] ?>h</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-tags me-2"></i>Tickets por Categoría</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 220px;">
                                <canvas id="auditCategoryChart"></canvas>
                            </div>
                            <div class="mt-2">
                                <?php foreach ($ticketsByCategory as $cat): ?>
                                <div class="d-flex justify-content-between align-items-center py-1">
                                    <span class="small"><?= $categoryLabels[$cat['category']] ?? ucfirst($cat['category']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= $cat['count'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-clock me-2"></i>Horas Pico de Creación</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 260px;">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                            <p class="text-muted text-center mt-2" style="font-size: 0.7rem;">Distribución horaria de tickets creados (últimos 30 días)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== FILA 4: SLA por Prioridad + Tiempos Promedio + Rendimiento técnicos ===== -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-bar-chart me-2"></i>SLA por Prioridad</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 280px;">
                                <canvas id="slaPriorityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-clock-history me-2"></i>Tiempos Promedio</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container" style="height: 280px;">
                                <canvas id="avgTimesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-people me-2"></i>Rendimiento por Analista</h5>
                            <small class="text-muted">Con SLA · 30 días</small>
                        </div>
                        <div class="p-3" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($techSlaPerformance)): ?>
                            <div class="text-center py-4 text-muted"><i class="bi bi-person-x" style="font-size: 2rem;"></i><p class="mt-2 small">Sin datos de técnicos</p></div>
                            <?php else: ?>
                            <?php foreach ($techSlaPerformance as $tName => $tp): 
                                $techPct = $tp['asignados'] > 0 ? round(($tp['resueltos'] / $tp['asignados']) * 100) : 0;
                                $techColor = $techPct >= 80 ? 'success' : ($techPct >= 50 ? 'warning' : 'danger');
                            ?>
                            <div class="mb-3 pb-3" style="border-bottom: 1px solid var(--gray-100);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;background:var(--primary-soft);color:var(--primary-dark);font-weight:600;font-size:0.7rem;">
                                            <?= strtoupper(substr($tName, 0, 1)) ?>
                                        </div>
                                        <span class="small fw-semibold"><?= htmlspecialchars($tName) ?></span>
                                    </div>
                                    <span class="badge bg-<?= $techColor ?>"><?= $techPct ?>%</span>
                                </div>
                                <div class="progress mb-1" style="height: 5px;">
                                    <div class="progress-bar bg-<?= $techColor ?>" style="width: <?= $techPct ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between" style="font-size: 0.65rem; color: #94a3b8;">
                                    <span><?= $tp['resueltos'] ?>/<?= $tp['asignados'] ?> resueltos · <?= $tp['avg_horas'] ?? '-' ?>h prom.</span>
                                </div>
                                <div class="d-flex gap-2 mt-1" style="font-size: 0.62rem;">
                                    <span class="badge bg-<?= $tp['sla_response_pct'] >= 80 ? 'success' : ($tp['sla_response_pct'] >= 50 ? 'warning' : 'danger') ?>" style="font-size:0.6rem;">Resp: <?= $tp['sla_response_pct'] ?>%</span>
                                    <span class="badge bg-<?= $tp['sla_resolution_pct'] >= 80 ? 'success' : ($tp['sla_resolution_pct'] >= 50 ? 'warning' : 'danger') ?>" style="font-size:0.6rem;">Resol: <?= $tp['sla_resolution_pct'] ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== FILA 5: Tabla detallada de analistas ===== -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-person-lines-fill me-2"></i>Rendimiento Detallado por Analista</h5>
                    <small class="text-muted">Datos SLA de los últimos 30 días</small>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="techDetailTable">
                        <thead>
                            <tr>
                                <th>Analista</th>
                                <th class="text-center">Asignados</th>
                                <th class="text-center">Resueltos</th>
                                <th class="text-center">Tasa</th>
                                <th class="text-center">Prom. Horas</th>
                                <th class="text-center">SLA Respuesta</th>
                                <th class="text-center">SLA Resolución</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($techSlaPerformance)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Sin datos de analistas</td></tr>
                        <?php else: ?>
                        <?php foreach ($techSlaPerformance as $tName => $tp): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:30px;height:30px;background:var(--primary-soft);color:var(--primary-dark);font-weight:600;font-size:0.7rem;"><?= strtoupper(substr($tName, 0, 1)) ?></div>
                                    <span class="fw-semibold small"><?= htmlspecialchars($tName) ?></span>
                                </div>
                            </td>
                            <td class="text-center fw-bold"><?= $tp['asignados'] ?></td>
                            <td class="text-center"><span class="text-success fw-bold"><?= $tp['resueltos'] ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $tp['tasa'] >= 80 ? 'success' : ($tp['tasa'] >= 50 ? 'warning' : 'danger') ?>"><?= $tp['tasa'] ?>%</span>
                            </td>
                            <td class="text-center small"><?= $tp['avg_horas'] ?? '-' ?>h</td>
                            <td class="text-center">
                                <div>
                                    <span class="badge bg-<?= $tp['sla_response_pct'] >= 80 ? 'success' : ($tp['sla_response_pct'] >= 50 ? 'warning' : 'danger') ?>"><?= $tp['sla_response_pct'] ?>%</span>
                                    <div style="font-size:0.6rem; color: #94a3b8;" class="mt-1"><?= $tp['sla_response_ok'] ?> ok / <?= $tp['sla_response_fail'] ?> fail</div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div>
                                    <span class="badge bg-<?= $tp['sla_resolution_pct'] >= 80 ? 'success' : ($tp['sla_resolution_pct'] >= 50 ? 'warning' : 'danger') ?>"><?= $tp['sla_resolution_pct'] ?>%</span>
                                    <div style="font-size:0.6rem; color: #94a3b8;" class="mt-1"><?= $tp['sla_resolution_ok'] ?> ok / <?= $tp['sla_resolution_fail'] ?> fail</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== FILA 5: Registros de Auditoría ===== -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-info-circle me-2"></i>¿Qué son los Registros de Auditoría?</h5>
                </div>
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <p class="small mb-2">Los <strong>registros de auditoría</strong> son un log cronológico de todas las acciones realizadas en el sistema. Cada vez que un usuario inicia sesión, crea un ticket, cambia un estado, asigna un técnico o cualquier otra operación, queda registrado con fecha, hora, usuario y detalles.</p>
                            <p class="small mb-0 text-muted">Esto permite trazabilidad completa, detección de anomalías y cumplimiento de políticas internas de seguridad.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex align-items-center gap-2"><span class="badge bg-success">Login</span> <small class="text-muted">Inicio de sesión</small></div>
                                <div class="d-flex align-items-center gap-2"><span class="badge bg-primary">Create</span> <small class="text-muted">Creación de recurso</small></div>
                                <div class="d-flex align-items-center gap-2"><span class="badge bg-info">Update</span> <small class="text-muted">Modificación de datos</small></div>
                                <div class="d-flex align-items-center gap-2"><span class="badge bg-danger">Delete</span> <small class="text-muted">Eliminación de recurso</small></div>
                                <div class="d-flex align-items-center gap-2"><span class="badge bg-secondary">Logout</span> <small class="text-muted">Cierre de sesión</small></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas de registros -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= number_format($auditStats['total']) ?></div>
                                <div class="stat-label">Total Registros</div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['today'] ?></div>
                                <div class="stat-label">Registros Hoy</div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['week'] ?></div>
                                <div class="stat-label">Últimos 7 días</div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['unique_users'] ?></div>
                                <div class="stat-label">Usuarios Activos</div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-funnel me-2"></i>Filtros de Búsqueda</h5>
                </div>
                <div class="p-4">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page" value="auditoria">
                        <div class="col-md-3">
                            <label class="form-label small">Usuario</label>
                            <input type="text" name="audit_user" class="form-control form-control-sm" value="<?= htmlspecialchars($auditFilterUser) ?>" placeholder="Nombre o email">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Acción</label>
                            <select name="audit_action" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($auditActions as $action): ?>
                                <option value="<?= $action ?>" <?= $auditFilterAction === $action ? 'selected' : '' ?>><?= ucfirst($action) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Entidad</label>
                            <select name="audit_entity" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($auditEntities as $entity): ?>
                                <option value="<?= $entity ?>" <?= $auditFilterEntity === $entity ? 'selected' : '' ?>><?= ucfirst($entity) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="audit_date_from" class="form-control form-control-sm" value="<?= $auditFilterDateFrom ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="audit_date_to" class="form-control form-control-sm" value="<?= $auditFilterDateTo ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Tabla de logs -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="card-title-custom mb-0"><i class="bi bi-list-ul me-2"></i>Registros de Actividad</h5>
                            <span class="badge bg-primary"><?= number_format($totalAuditLogs) ?> registros</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="auditTable">
                                <thead>
                                    <tr>
                                        <th>Fecha/Hora</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                        <th>Entidad</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($auditLogs)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No hay registros que coincidan con los filtros</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td class="small">
                                            <div><?= date('d/m/Y', strtotime($log['created_at'])) ?></div>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                            <div class="user-info">
                                                <div class="user-info-avatar" style="width:30px;height:30px;font-size:0.75rem;"><?= strtoupper(substr($log['user_name'], 0, 1)) ?></div>
                                                <div>
                                                    <div class="small fw-semibold"><?= htmlspecialchars($log['user_name']) ?></div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">Sistema</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $actionColors = [
                                                'login' => 'success', 'logout' => 'secondary', 
                                                'create' => 'primary', 'update' => 'info', 
                                                'delete' => 'danger', 'view' => 'light'
                                            ];
                                            $color = $actionColors[$log['action']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst($log['action']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($log['entity_type']): ?>
                                            <span class="badge bg-outline-dark" style="border:1px solid #dee2e6;background:transparent;color:#495057;">
                                                <?= ucfirst($log['entity_type']) ?>
                                                <?= $log['entity_id'] ? '#' . $log['entity_id'] : '' ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php if ($log['details']): ?>
                                            <span class="text-muted" title="<?= htmlspecialchars($log['details']) ?>" style="max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?= htmlspecialchars(substr($log['details'], 0, 50)) ?><?= strlen($log['details']) > 50 ? '...' : '' ?>
                                            </span>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalAuditPages > 1): ?>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <small class="text-muted">Página <?= $auditPage ?> de <?= $totalAuditPages ?></small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($auditPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=auditoria&audit_page=<?= $auditPage - 1 ?>&audit_user=<?= urlencode($auditFilterUser) ?>&audit_action=<?= urlencode($auditFilterAction) ?>&audit_entity=<?= urlencode($auditFilterEntity) ?>&audit_date_from=<?= $auditFilterDateFrom ?>&audit_date_to=<?= $auditFilterDateTo ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $startPage = max(1, $auditPage - 2);
                                    $endPage = min($totalAuditPages, $auditPage + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                    ?>
                                    <li class="page-item <?= $i === $auditPage ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=auditoria&audit_page=<?= $i ?>&audit_user=<?= urlencode($auditFilterUser) ?>&audit_action=<?= urlencode($auditFilterAction) ?>&audit_entity=<?= urlencode($auditFilterEntity) ?>&audit_date_from=<?= $auditFilterDateFrom ?>&audit_date_to=<?= $auditFilterDateTo ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($auditPage < $totalAuditPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=auditoria&audit_page=<?= $auditPage + 1 ?>&audit_user=<?= urlencode($auditFilterUser) ?>&audit_action=<?= urlencode($auditFilterAction) ?>&audit_entity=<?= urlencode($auditFilterEntity) ?>&audit_date_from=<?= $auditFilterDateFrom ?>&audit_date_to=<?= $auditFilterDateTo ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Panel lateral -->
                <div class="col-lg-4">
                    <!-- Top Acciones -->
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-trophy me-2"></i>Top Acciones (30 días)</h5>
                        </div>
                        <div class="p-3">
                            <?php if (empty($topActions)): ?>
                            <p class="text-muted text-center py-3">Sin datos</p>
                            <?php else: ?>
                            <?php 
                            $maxAction = !empty($topActions) ? $topActions[0]['count'] : 1;
                            foreach ($topActions as $action): 
                                $actionPct = $maxAction > 0 ? round(($action['count'] / $maxAction) * 100) : 0;
                                $aColor = $actionColors[$action['action']] ?? 'secondary';
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-<?= $aColor ?>"><?= ucfirst($action['action']) ?></span>
                                    <span class="fw-bold small"><?= number_format($action['count']) ?></span>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar bg-<?= $aColor ?>" style="width: <?= $actionPct ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Gráfico de actividad 7 días -->
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-graph-up me-2"></i>Actividad (7 días)</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container">
                                <canvas id="auditChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Resolución por prioridad -->
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-stopwatch me-2"></i>Tiempo Resolución por Prioridad</h5>
                        </div>
                        <div class="p-3">
                            <?php if (empty($avgResByPriority)): ?>
                            <p class="text-muted text-center py-3 small">Sin datos</p>
                            <?php else: ?>
                            <?php foreach ($avgResByPriority as $pr): ?>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid #f1f5f9;">
                                <div>
                                    <span class="badge bg-<?= $priorityColors[$pr['priority']] ?? 'secondary' ?>"><?= ucfirst($pr['priority']) ?></span>
                                    <small class="text-muted ms-1">(<?= $pr['total'] ?> tickets)</small>
                                </div>
                                <span class="fw-bold small"><?= $pr['avg_horas'] ?>h prom.</span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // ===== Gráfico: Tickets Creados vs Resueltos por Mes =====
                const monthlyCtx = document.getElementById('monthlyChart');
                if (monthlyCtx) {
                    new Chart(monthlyCtx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode(array_column($monthlyTickets, 'mes_label')) ?>,
                            datasets: [
                                {
                                    label: 'Creados',
                                    data: <?= json_encode(array_map('intval', array_column($monthlyTickets, 'creados'))) ?>,
                                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                    borderRadius: 6,
                                    barPercentage: 0.7
                                },
                                {
                                    label: 'Resueltos',
                                    data: <?= json_encode(array_map('intval', array_column($monthlyTickets, 'resueltos'))) ?>,
                                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                    borderRadius: 6,
                                    barPercentage: 0.7
                                },
                                {
                                    label: 'Resueltos en 24h',
                                    data: <?= json_encode(array_map('intval', array_column($monthlyTickets, 'resueltos_24h'))) ?>,
                                    backgroundColor: 'rgba(6, 182, 212, 0.5)',
                                    borderRadius: 6,
                                    barPercentage: 0.7
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } } },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                // ===== Gráfico: Distribución por Estado =====
                const statusCtx = document.getElementById('auditStatusChart');
                if (statusCtx) {
                    <?php $statusColorMapChart = ['asignado' => '#0ea5e9', 'abierto' => '#3b82f6', 'en_proceso' => '#f59e0b', 'pendiente' => '#6b7280', 'resuelto' => '#10b981', 'cerrado' => '#1f2937']; ?>
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: [<?php foreach($ticketsByStatus as $s) echo "'" . ($statusLabels[$s['status']] ?? ucfirst($s['status'])) . "',"; ?>],
                            datasets: [{ 
                                data: [<?php foreach($ticketsByStatus as $s) echo $s['count'] . ','; ?>], 
                                backgroundColor: [<?php foreach($ticketsByStatus as $s) echo "'" . ($statusColorMapChart[$s['status']] ?? '#94a3b8') . "',"; ?>],
                                borderWidth: 2, borderColor: '#fff' 
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
                    });
                }

                // ===== Gráfico: Categorías =====
                const catCtx = document.getElementById('auditCategoryChart');
                if (catCtx) {
                    const catColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                    new Chart(catCtx, {
                        type: 'doughnut',
                        data: {
                            labels: [<?php foreach($ticketsByCategory as $c) echo "'" . ($categoryLabels[$c['category']] ?? ucfirst($c['category'])) . "',"; ?>],
                            datasets: [{ 
                                data: [<?php foreach($ticketsByCategory as $c) echo $c['count'] . ','; ?>], 
                                backgroundColor: catColors.slice(0, <?= count($ticketsByCategory) ?>),
                                borderWidth: 2, borderColor: '#fff'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
                    });
                }

                // ===== Gráfico: Horas Pico =====
                const peakCtx = document.getElementById('peakHoursChart');
                if (peakCtx) {
                    const hoursData = <?= json_encode(array_values($hoursData)) ?>;
                    const hoursLabels = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');
                    const maxHour = Math.max(...hoursData);
                    new Chart(peakCtx, {
                        type: 'bar',
                        data: {
                            labels: hoursLabels,
                            datasets: [{
                                data: hoursData,
                                backgroundColor: hoursData.map(v => v === maxHour && maxHour > 0 ? '#ef4444' : 'rgba(59, 130, 246, 0.6)'),
                                borderRadius: 3,
                                barPercentage: 0.9
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => ctx.parsed.y + ' tickets' } } },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                                x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 90 } }
                            }
                        }
                    });
                }

                // ===== Gráfico: Actividad 7 días =====
                const auditCtx = document.getElementById('auditChart');
                if (auditCtx) {
                    new Chart(auditCtx, {
                        type: 'line',
                        data: {
                            labels: [<?php foreach($auditChartData as $d) echo '"' . date('d/m', strtotime($d['date'])) . '",'; ?>],
                            datasets: [{
                                label: 'Actividades',
                                data: [<?php foreach($auditChartData as $d) echo $d['count'] . ','; ?>],
                                borderColor: '#0ea5e9',
                                backgroundColor: 'rgba(10,37,64,0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                }

                // ===== Gráfico: Cumplimiento SLA General =====
                const slaCtx = document.getElementById('slaComplianceChart');
                if (slaCtx) {
                    new Chart(slaCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Resp. OK', 'Resp. Incumplida', 'Asign. OK', 'Asign. Incumplida', 'Resol. OK', 'Resol. Incumplida'],
                            datasets: [{
                                data: [<?= $slaStats['within_response'] ?>, <?= $slaStats['breached_response'] ?>, <?= $slaStats['within_assignment'] ?>, <?= $slaStats['breached_assignment'] ?>, <?= $slaStats['within_resolution'] ?>, <?= $slaStats['breached_resolution'] ?>],
                                backgroundColor: ['#10b981', '#f87171', '#3b82f6', '#fb923c', '#06b6d4', '#a78bfa'],
                                borderWidth: 2, borderColor: '#fff'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 }, padding: 6 } } } }
                    });
                }

                // ===== Gráfico: SLA por Prioridad =====
                const priCtx = document.getElementById('slaPriorityChart');
                if (priCtx) {
                    <?php
                    $priLabels = [];
                    $priResponseOk = [];
                    $priResponseFail = [];
                    $priResolutionOk = [];
                    $priResolutionFail = [];
                    $priorityOrder = ['urgente', 'alta', 'media', 'baja'];
                    $priLabelNames = ['urgente' => 'Urgente', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
                    foreach ($priorityOrder as $p) {
                        if (isset($slaStats['by_priority'][$p])) {
                            $priLabels[] = $priLabelNames[$p];
                            $d = $slaStats['by_priority'][$p];
                            $priResponseOk[] = $d['response_ok'];
                            $priResponseFail[] = $d['response_breached'];
                            $priResolutionOk[] = $d['resolution_ok'];
                            $priResolutionFail[] = $d['resolution_breached'];
                        }
                    }
                    ?>
                    new Chart(priCtx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($priLabels) ?>,
                            datasets: [
                                { label: 'Resp. OK', data: <?= json_encode($priResponseOk) ?>, backgroundColor: '#10b981' },
                                { label: 'Resp. Incumplida', data: <?= json_encode($priResponseFail) ?>, backgroundColor: '#f87171' },
                                { label: 'Resol. OK', data: <?= json_encode($priResolutionOk) ?>, backgroundColor: '#3b82f6' },
                                { label: 'Resol. Incumplida', data: <?= json_encode($priResolutionFail) ?>, backgroundColor: '#fb923c' }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                            scales: { x: { stacked: false }, y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                }

                // ===== Gráfico: Tiempos Promedio =====
                const avgCtx = document.getElementById('avgTimesChart');
                if (avgCtx) {
                    new Chart(avgCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Respuesta', 'Asignación', 'Resolución', 'Trabajo'],
                            datasets: [{
                                label: 'Horas promedio',
                                data: [<?= $slaStats['avg_response'] ?>, <?= $slaStats['avg_assignment'] ?>, <?= $slaStats['avg_resolution'] ?>, <?= $slaStats['avg_work'] ?>],
                                backgroundColor: ['#10b981', '#3b82f6', '#06b6d4', '#8b5cf6'],
                                borderRadius: 6,
                                barThickness: 28
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.x + ' horas'; } } } },
                            scales: { x: { beginAtZero: true, title: { display: true, text: 'Horas', font: { size: 11 } } } }
                        }
                    });
                }
            });

            // ===== DESCARGA CSV =====
            function downloadAuditCSV() {
                const table = document.getElementById('auditTable');
                if (!table) return;
                let csv = 'Fecha,Hora,Usuario,Acción,Entidad,Detalles\n';
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 5) {
                        const fecha = cells[0].textContent.trim().replace(/\s+/g, ' ');
                        const partes = fecha.split(' ');
                        const usuario = cells[1].textContent.trim().replace(/\s+/g, ' ');
                        const accion = cells[2].textContent.trim();
                        const entidad = cells[3].textContent.trim();
                        const detalles = cells[4].textContent.trim().replace(/"/g, '""');
                        csv += `"${partes[0] || ''}","${partes[1] || ''}","${usuario}","${accion}","${entidad}","${detalles}"\n`;
                    }
                });
                const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'auditoria_epco_' + new Date().toISOString().slice(0,10) + '.csv';
                link.click();
                URL.revokeObjectURL(link.href);
            }

            // ===== DESCARGA INFORME COMPLETO (HTML → Print/PDF) =====
            function downloadAuditReport() {
                const reportDate = new Date().toLocaleDateString('es-CL', { year: 'numeric', month: 'long', day: 'numeric' });

                // Capturar gráficos como imagen
                const charts = ['slaComplianceChart', 'slaPriorityChart', 'avgTimesChart', 'auditChart', 'monthlyChart', 'auditStatusChart', 'auditCategoryChart', 'peakHoursChart'];
                const chartImages = {};
                charts.forEach(id => {
                    const c = document.getElementById(id);
                    if (c) chartImages[id] = c.toDataURL('image/png');
                });

                // Construir tabla de registros
                let tableRows = '';
                const rows = document.querySelectorAll('#auditTable tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 5) {
                        const fecha = cells[0].textContent.trim().replace(/\s+/g, ' ');
                        const usuario = cells[1].textContent.trim().replace(/\s+/g, ' ');
                        const accion = cells[2].textContent.trim();
                        const entidad = cells[3].textContent.trim();
                        const detalles = cells[4].textContent.trim();
                        tableRows += '<tr><td>' + fecha + '</td><td>' + usuario + '</td><td>' + accion + '</td><td>' + entidad + '</td><td>' + detalles + '</td></tr>';
                    }
                });

                const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe de Auditoría - Empresa Portuaria Coquimbo</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Lato:wght@300;400;700&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Lato', sans-serif; color: #1e293b; padding: 30px 40px; background: #fff; line-height: 1.5; }
    h1 { font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 700; color: #0c4a6e; margin-bottom: 8px; border-bottom: 3px solid #0369a1; padding-bottom: 10px; }
    h2 { font-family: 'Montserrat', sans-serif; font-size: 16px; font-weight: 700; color: #0c4a6e; margin: 24px 0 12px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; }
    h3 { font-size: 12px; color: #475569; margin-bottom: 6px; }
    .header-info { display: flex; justify-content: space-between; color: #64748b; font-size: 13px; margin-bottom: 20px; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
    .stat-box .number { font-size: 22px; font-weight: 700; color: #0369a1; }
    .stat-box .label { font-size: 11px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    .sla-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
    .sla-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; text-align: center; }
    .sla-box.warn { background: #fffbeb; border-color: #fde68a; }
    .sla-box.danger { background: #fef2f2; border-color: #fecaca; }
    .sla-box .pct { font-size: 24px; font-weight: 700; }
    .sla-box .pct.ok { color: #16a34a; }
    .sla-box .pct.warn { color: #d97706; }
    .sla-box .pct.danger { color: #dc2626; }
    .sla-box .label { font-size: 12px; color: #475569; font-weight: 600; margin-top: 4px; }
    .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
    .charts-grid div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
    .charts-grid img { max-width: 100%; height: auto; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
    th { background: #0c4a6e; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
    tr:nth-child(even) { background: #f8fafc; }
    tr:hover { background: #f1f5f9; }
    .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 11px; }
    .footer p { margin: 3px 0; }
    .no-print button { background: #0c5a8a; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    @media print {
        body { padding: 15px; }
        .no-print { display: none !important; }
        h1 { font-size: 18px; }
        .stats-grid { grid-template-columns: repeat(4, 1fr); }
        .charts-grid { grid-template-columns: repeat(2, 1fr); }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="no-print" style="text-align:right;margin-bottom:15px;">
    <button onclick="window.print()" style="background:#0c5a8a;color:white;border:none;padding:10px 25px;border-radius:6px;cursor:pointer;font-size:13px;">
        Imprimir / Guardar como PDF
    </button>
</div>
<h1>Informe de Auditoría del Sistema</h1>
<div class="header-info">
    <span>Empresa Portuaria Coquimbo — Soporte TI</span>
    <span>Generado el ${reportDate}</span>
</div>

<h2>Métricas de Tickets</h2>
<div class="stats-grid">
    <div class="stat-box"><div class="number"><?= number_format($ticketMetrics['total']) ?></div><div class="label">Total Tickets</div></div>
    <div class="stat-box"><div class="number" style="color:#16a34a;"><?= number_format($ticketMetrics['resueltos']) ?></div><div class="label">Resueltos</div></div>
    <div class="stat-box"><div class="number" style="color:#0ea5e9;"><?= $ticketMetrics['tasa_resolucion'] ?>%</div><div class="label">Tasa Resolución</div></div>
    <div class="stat-box"><div class="number" style="color:#dc2626;"><?= $ticketMetrics['urgentes_abiertos'] ?></div><div class="label">Urgentes Abiertos</div></div>
</div>
<div class="stats-grid">
    <div class="stat-box"><div class="number"><?= $ticketMetrics['creados_mes'] ?></div><div class="label">Creados (mes)</div></div>
    <div class="stat-box"><div class="number"><?= $ticketMetrics['resueltos_mes'] ?></div><div class="label">Resueltos (mes)</div></div>
    <div class="stat-box"><div class="number"><?= $ticketMetrics['creados_semana'] ?></div><div class="label">Creados (semana)</div></div>
    <div class="stat-box"><div class="number"><?= $ticketMetrics['resueltos_semana'] ?></div><div class="label">Resueltos (semana)</div></div>
</div>

<h2>Cumplimiento SLA (últimos 30 días)</h2>
<div class="sla-grid">
    <div class="sla-box <?= $slaStats['response_compliance'] >= 80 ? '' : ($slaStats['response_compliance'] >= 50 ? 'warn' : 'danger') ?>">
        <div class="pct <?= $slaStats['response_compliance'] >= 80 ? 'ok' : ($slaStats['response_compliance'] >= 50 ? 'warn' : 'danger') ?>"><?= $slaStats['response_compliance'] ?>%</div>
        <div class="label">Primera Respuesta</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px;"><?= $slaStats['within_response'] ?> ok / <?= $slaStats['breached_response'] ?> incumplidos</div>
    </div>
    <div class="sla-box <?= $slaStats['assignment_compliance'] >= 80 ? '' : ($slaStats['assignment_compliance'] >= 50 ? 'warn' : 'danger') ?>">
        <div class="pct <?= $slaStats['assignment_compliance'] >= 80 ? 'ok' : ($slaStats['assignment_compliance'] >= 50 ? 'warn' : 'danger') ?>"><?= $slaStats['assignment_compliance'] ?>%</div>
        <div class="label">Asignación</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px;"><?= $slaStats['within_assignment'] ?> ok / <?= $slaStats['breached_assignment'] ?> incumplidos</div>
    </div>
    <div class="sla-box <?= $slaStats['resolution_compliance'] >= 80 ? '' : ($slaStats['resolution_compliance'] >= 50 ? 'warn' : 'danger') ?>">
        <div class="pct <?= $slaStats['resolution_compliance'] >= 80 ? 'ok' : ($slaStats['resolution_compliance'] >= 50 ? 'warn' : 'danger') ?>"><?= $slaStats['resolution_compliance'] ?>%</div>
        <div class="label">Resolución</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px;"><?= $slaStats['within_resolution'] ?> ok / <?= $slaStats['breached_resolution'] ?> incumplidos</div>
    </div>
</div>
<p style="color:#64748b;font-size:11px;">Tiempos promedio: Respuesta <strong><?= $slaStats['avg_response'] ?>h</strong> · Asignación <strong><?= $slaStats['avg_assignment'] ?>h</strong> · Resolución <strong><?= $slaStats['avg_resolution'] ?>h</strong> · Trabajo <strong><?= $slaStats['avg_work'] ?>h</strong></p>

<h2>Gráficos</h2>
<div class="charts-grid">
    ${chartImages['monthlyChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Creados vs Resueltos Mensual</h3><img src="' + chartImages['monthlyChart'] + '"></div>' : ''}
    ${chartImages['auditStatusChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Estado de Tickets</h3><img src="' + chartImages['auditStatusChart'] + '"></div>' : ''}
    ${chartImages['slaComplianceChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Cumplimiento SLA</h3><img src="' + chartImages['slaComplianceChart'] + '"></div>' : ''}
    ${chartImages['slaPriorityChart'] ? '<div><h3 style="font-size:12px;color:#475467;">SLA por Prioridad</h3><img src="' + chartImages['slaPriorityChart'] + '"></div>' : ''}
    ${chartImages['avgTimesChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Tiempos Promedio</h3><img src="' + chartImages['avgTimesChart'] + '"></div>' : ''}
    ${chartImages['peakHoursChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Horas Pico</h3><img src="' + chartImages['peakHoursChart'] + '"></div>' : ''}
    ${chartImages['auditCategoryChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Categorías</h3><img src="' + chartImages['auditCategoryChart'] + '"></div>' : ''}
    ${chartImages['auditChart'] ? '<div><h3 style="font-size:12px;color:#475467;">Actividad Reciente</h3><img src="' + chartImages['auditChart'] + '"></div>' : ''}
</div>

<h2>Estadísticas de Auditoría</h2>
<div class="stats-grid">
    <div class="stat-box"><div class="number"><?= number_format($auditStats['total']) ?></div><div class="label">Total Registros</div></div>
    <div class="stat-box"><div class="number"><?= $auditStats['today'] ?></div><div class="label">Registros Hoy</div></div>
    <div class="stat-box"><div class="number"><?= $auditStats['week'] ?></div><div class="label">Últimos 7 Días</div></div>
    <div class="stat-box"><div class="number"><?= $auditStats['unique_users'] ?></div><div class="label">Usuarios Activos</div></div>
</div>

<h2>Rendimiento por Analista (últimos 30 días)</h2>
<table>
<thead><tr><th>Analista</th><th>Asignados</th><th>Resueltos</th><th>Tasa</th><th>Prom. Horas</th><th>SLA Respuesta</th><th>SLA Resolución</th></tr></thead>
<tbody>
<?php if (empty($techSlaPerformance)): ?>
<tr><td colspan="7" style="text-align:center;">Sin datos</td></tr>
<?php else: ?>
<?php foreach ($techSlaPerformance as $tName => $tp): ?>
<tr>
    <td><?= htmlspecialchars($tName) ?></td>
    <td style="text-align:center;"><?= $tp['asignados'] ?></td>
    <td style="text-align:center;"><?= $tp['resueltos'] ?></td>
    <td style="text-align:center;color:<?= $tp['tasa'] >= 80 ? '#16a34a' : ($tp['tasa'] >= 50 ? '#d97706' : '#dc2626') ?>;font-weight:700;"><?= $tp['tasa'] ?>%</td>
    <td style="text-align:center;"><?= $tp['avg_horas'] ?? '-' ?>h</td>
    <td style="text-align:center;color:<?= $tp['sla_response_pct'] >= 80 ? '#16a34a' : ($tp['sla_response_pct'] >= 50 ? '#d97706' : '#dc2626') ?>;font-weight:700;"><?= $tp['sla_response_pct'] ?>% <small>(<?= $tp['sla_response_ok'] ?>/<?= $tp['sla_response_ok'] + $tp['sla_response_fail'] ?>)</small></td>
    <td style="text-align:center;color:<?= $tp['sla_resolution_pct'] >= 80 ? '#16a34a' : ($tp['sla_resolution_pct'] >= 50 ? '#d97706' : '#dc2626') ?>;font-weight:700;"><?= $tp['sla_resolution_pct'] ?>% <small>(<?= $tp['sla_resolution_ok'] ?>/<?= $tp['sla_resolution_ok'] + $tp['sla_resolution_fail'] ?>)</small></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<h2>Registros de Actividad (página actual)</h2>
<table>
<thead><tr><th>Fecha/Hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalles</th></tr></thead>
<tbody>${tableRows || '<tr><td colspan="5" style="text-align:center;padding:20px;">Sin registros</td></tr>'}</tbody>
</table>

<div class="footer">
    <p>Empresa Portuaria Coquimbo — Sistema de Soporte TI</p>
    <p>Informe generado automáticamente · ${reportDate}</p>
</div>
</body></html>`;

                const w = window.open('', '_blank');
                w.document.write(html);
                w.document.close();
            }
            </script>
            
            <?php elseif ($page === 'notificaciones'): ?>
            <!-- ========== GESTIÓN DE DESTINATARIOS DE NOTIFICACIONES ========== -->
            
            <!-- Estadísticas rápidas -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['total'] ?></div>
                        <div class="stat-label">Total Registrados</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['active'] ?></div>
                        <div class="stat-label">Activos</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['ticket_created'] ?></div>
                        <div class="stat-label">Reciben "Ticket Creado"</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['ticket_updated'] ?></div>
                        <div class="stat-label">Reciben "Ticket Actualizado"</div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Formulario para agregar correo -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Agregar Destinatario</h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <form method="POST" id="addNotifForm">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="add_notification_email">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Correo Electrónico <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="notif_email" class="form-control" placeholder="correo@ejemplo.cl" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nombre (opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                        <input type="text" name="notif_name" class="form-control" placeholder="Nombre del destinatario">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Tipo de Evento</label>
                                    <select name="notif_event" class="form-select">
                                        <option value="all">Todas las notificaciones</option>
                                        <option value="ticket_created">Solo tickets creados</option>
                                        <option value="ticket_updated">Solo tickets actualizados</option>
                                    </select>
                                    <div class="form-text small">Selecciona qué tipo de notificaciones recibirá</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Agregar Correo
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Enviar correo de prueba -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-send me-2 text-info"></i>Enviar Correo de Prueba</h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <form method="POST">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="send_test_email">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Enviar prueba a:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-envelope-paper"></i></span>
                                        <input type="email" name="test_email" class="form-control" placeholder="correo@ejemplo.cl" required>
                                    </div>
                                    <div class="form-text small">Se enviará un ticket de prueba para verificar la configuración SMTP</div>
                                </div>
                                <button type="submit" class="btn btn-outline-info w-100">
                                    <i class="bi bi-send me-1"></i> Enviar Prueba
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de destinatarios -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Destinatarios Registrados</h5>
                            <span class="badge bg-primary rounded-pill"><?= count($notificationRecipients) ?> total</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($notificationRecipients)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-envelope-x text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No hay destinatarios registrados.<br>Agrega correos para recibir notificaciones.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Correo</th>
                                            <th>Nombre</th>
                                            <th>Evento</th>
                                            <th>Estado</th>
                                            <th>Registrado</th>
                                            <th class="text-end pe-4">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notificationRecipients as $nr): ?>
                                        <tr class="<?= !$nr['is_active'] ? 'table-secondary opacity-75' : '' ?>">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center <?= $nr['is_active'] ? 'bg-success' : 'bg-secondary' ?> bg-opacity-10" style="width: 36px; height: 36px;">
                                                        <i class="bi bi-envelope <?= $nr['is_active'] ? 'text-success' : 'text-secondary' ?>"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-semibold small"><?= htmlspecialchars($nr['email']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small"><?= htmlspecialchars($nr['name'] ?: '—') ?></td>
                                            <td>
                                                <?php
                                                $eventLabels = ['all' => 'Todas', 'ticket_created' => 'Ticket Creado', 'ticket_updated' => 'Ticket Actualizado'];
                                                $eventColors = ['all' => 'primary', 'ticket_created' => 'warning', 'ticket_updated' => 'info'];
                                                ?>
                                                <span class="badge bg-<?= $eventColors[$nr['event_type']] ?? 'secondary' ?> bg-opacity-10 text-<?= $eventColors[$nr['event_type']] ?? 'secondary' ?>">
                                                    <?= $eventLabels[$nr['event_type']] ?? $nr['event_type'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($nr['is_active']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i>Activo</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-pause-circle me-1"></i>Pausado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= date('d/m/Y', strtotime($nr['created_at'])) ?></td>
                                            <td class="text-end pe-4">
                                                <form method="POST" class="d-inline">
            <?= csrfInput() ?>
                                                    <input type="hidden" name="action" value="toggle_notification_email">
                                                    <input type="hidden" name="notif_id" value="<?= $nr['id'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= $nr['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $nr['is_active'] ? 'Pausar' : 'Activar' ?>">
                                                        <i class="bi <?= $nr['is_active'] ? 'bi-pause' : 'bi-play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este destinatario?')">
            <?= csrfInput() ?>
                                                    <input type="hidden" name="action" value="delete_notification_email">
                                                    <input type="hidden" name="notif_id" value="<?= $nr['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
            <?= csrfInput() ?>
                                                    <input type="hidden" name="action" value="send_test_email">
                                                    <input type="hidden" name="test_email" value="<?= htmlspecialchars($nr['email']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Enviar correo de prueba">
                                                        <i class="bi bi-send"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Info SMTP actual -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-info-circle me-2 text-secondary"></i>Configuración SMTP Actual</h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <?php 
                            $smtpEnabled = filter_var(getenv('SMTP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
                            $smtpHost = getenv('SMTP_HOST') ?: 'No configurado';
                            $smtpUser = getenv('SMTP_USER') ?: 'No configurado';
                            $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'Sin nombre';
                            ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-<?= $smtpEnabled ? 'success' : 'danger' ?> bg-opacity-10 rounded">
                                        <div class="small text-muted mb-1">Estado</div>
                                        <div class="fw-bold text-<?= $smtpEnabled ? 'success' : 'danger' ?>">
                                            <i class="bi <?= $smtpEnabled ? 'bi-check-circle' : 'bi-x-circle' ?> me-1"></i>
                                            <?= $smtpEnabled ? 'ACTIVO' : 'DESACTIVADO' ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <div class="small text-muted mb-1">Servidor SMTP</div>
                                        <div class="fw-bold small"><?= htmlspecialchars($smtpHost) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <div class="small text-muted mb-1">Correo Remitente</div>
                                        <div class="fw-bold small"><?= htmlspecialchars($smtpUser) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php if (!$smtpEnabled): ?>
                            <div class="alert alert-warning mt-3 mb-0 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                El envío de correos está <strong>desactivado</strong>. Configure las variables de entorno SMTP en Portainer para activarlo.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </main>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($page === 'sla' || $page === 'cumplimiento'): ?>
    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Abiertos', 'En Proceso', 'Pendientes', 'Resueltos'],
                datasets: [{ data: [<?= $stats['abiertos'] ?>, <?= $stats['en_proceso'] ?>, <?= $stats['pendientes'] ?>, <?= $stats['resueltos'] ?>], backgroundColor: ['#0d6efd', '#ffc107', '#0dcaf0', '#198754'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } } } }
        });
        
        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($c) use ($categoryLabels) { return "'" . ($categoryLabels[$c['category']] ?? $c['category']) . "'"; }, $categoryStats)) ?>],
                datasets: [{ data: [<?= implode(',', array_column($categoryStats, 'count')) ?>], backgroundColor: '#0ea5e9', borderRadius: 6 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } }
        });
        
        // Gráfico de Tendencia Semanal
        const trendCtx = document.getElementById('weeklyTrendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($trendLabels) ?>,
                    datasets: [
                        {
                            label: 'Creados',
                            data: <?= json_encode($trendCreados) ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#3b82f6'
                        },
                        {
                            label: 'Resueltos',
                            data: <?= json_encode($trendResueltos) ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#10b981'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { usePointStyle: true, padding: 15, font: { size: 11 } } },
                        tooltip: { backgroundColor: 'rgba(10, 37, 64, 0.9)', padding: 12, cornerRadius: 8 }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        new Chart(document.getElementById('slaChart'), {
            type: 'doughnut',
            data: {
                labels: ['Respuesta OK', 'Asignación OK', 'Resolución OK', 'SLA Excedido'],
                datasets: [{
                    data: [
                        <?= $slaStats['within_response'] ?>,
                        <?= $slaStats['within_assignment'] ?>,
                        <?= $slaStats['within_resolution'] ?>,
                        <?= $slaStats['breached_response'] + $slaStats['breached_assignment'] + $slaStats['breached_resolution'] ?>
                    ],
                    backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 12, usePointStyle: true, font: { size: 11 } }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Script del Reloj Chile -->
    <script>
    function updateChileTime() {
        const options = {
            timeZone: 'America/Santiago',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        const chileTime = new Date().toLocaleTimeString('es-CL', options);
        const clockEl = document.getElementById('chileTime');
        if (clockEl) clockEl.textContent = chileTime;
        const clockElTopbar = document.getElementById('chileTimeTopbar');
        if (clockElTopbar) clockElTopbar.textContent = chileTime;
    }
    updateChileTime();
    setInterval(updateChileTime, 1000);
    </script>

    <!-- Script de selección masiva de tickets -->
    <script>
    function toggleSelectAll(master, tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const checks = table.querySelectorAll('tbody .ticket-check');
        checks.forEach(cb => {
            const row = cb.closest('tr');
            if (row && row.style.display !== 'none') {
                cb.checked = master.checked;
                row.classList.toggle('selected-row', master.checked);
            }
        });
        updateBulkBar();
    }

    function updateBulkBar() {
        const allChecked = document.querySelectorAll('.ticket-check:checked');
        const count = allChecked.length;
        
        // Actualizar ambas barras (la que esté visible)
        const bars = [
            { bar: document.getElementById('bulkBar'), count: document.getElementById('bulkCount'), container: document.getElementById('bulkIdsContainer') },
            { bar: document.getElementById('bulkBarMy'), count: document.getElementById('bulkCountMy'), container: document.getElementById('bulkIdsContainerMy') }
        ];
        
        bars.forEach(b => {
            if (!b.bar) return;
            b.bar.style.display = count > 0 ? 'block' : 'none';
            if (b.count) b.count.textContent = count + ' seleccionado' + (count !== 1 ? 's' : '');
            if (b.container) {
                b.container.innerHTML = '';
                allChecked.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ticket_ids[]';
                    input.value = cb.value;
                    b.container.appendChild(input);
                });
            }
        });

        // Actualizar estado de los selectAll
        ['ticketsTable', 'myTicketsTable'].forEach(tid => {
            const table = document.getElementById(tid);
            if (!table) return;
            const master = table.querySelector('thead .form-check-input');
            if (!master) return;
            const visibleChecks = [...table.querySelectorAll('tbody .ticket-check')].filter(cb => cb.closest('tr').style.display !== 'none');
            const checkedVisible = visibleChecks.filter(cb => cb.checked);
            master.checked = visibleChecks.length > 0 && checkedVisible.length === visibleChecks.length;
            master.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleChecks.length;
        });

        // Resaltar filas
        document.querySelectorAll('.ticket-check').forEach(cb => {
            const row = cb.closest('tr');
            if (row) row.classList.toggle('selected-row', cb.checked);
        });
    }

    function clearSelection() {
        document.querySelectorAll('.ticket-check').forEach(cb => {
            cb.checked = false;
            const row = cb.closest('tr');
            if (row) row.classList.remove('selected-row');
        });
        ['selectAllTickets', 'selectAllMyTickets'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.checked = false; el.indeterminate = false; }
        });
        updateBulkBar();
    }
    </script>

</body>
</html>

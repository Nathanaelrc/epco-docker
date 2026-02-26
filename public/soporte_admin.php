<?php
/**
 * EPCO - Dashboard Admin Soporte TI
 * Sistema completo con pestañas
 */
require_once '../includes/bootstrap.php';

// Verificar autenticación
requireAuth('login.php?redirect=soporte_admin.php');

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
        <title>EPCO - Acceso Denegado</title>
        <link rel="icon" type="image/png" href="img/Logo01.png">
        <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            * { font-family: 'Barlow', sans-serif; }
            body { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0ea5e9 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
            body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); opacity: 0.5; pointer-events: none; z-index: 0; }
            .error-card { background: white; border-radius: 20px; padding: 50px; text-align: center; max-width: 450px; box-shadow: 0 25px 80px rgba(0,0,0,0.4); position: relative; z-index: 1; }
            .error-icon { width: 100px; height: 100px; background: linear-gradient(135deg, #dc2626, #f87171); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
            .error-icon i { font-size: 3rem; color: white; }
        </style>
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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
                        require_once __DIR__ . '/../includes/MailService.php';
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
                    require_once __DIR__ . '/../includes/MailService.php';
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
        
        $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $page . ($filter ? '&filter=' . $filter : '') . '&msg=status_updated';
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
                require_once __DIR__ . '/../includes/MailService.php';
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
    
    // Agregar comentario
    if ($action === 'add_comment') {
        $ticketId = (int)$_POST['ticket_id'];
        $comment = sanitize($_POST['comment']);
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        
        $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$ticketId, $user['id'], $user['name'], $comment, $isInternal]);
        logActivity($user['id'], 'comment_added', 'tickets', $ticketId, 'Comentario agregado al ticket');
        
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
                require_once __DIR__ . '/../includes/MailService.php';
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
                require_once __DIR__ . '/../includes/MailService.php';
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
        SUM(CASE WHEN status = 'resuelto' THEN 1 ELSE 0 END) as resueltos,
        SUM(CASE WHEN status = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN priority = 'urgente' AND status NOT IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as urgentes
    FROM tickets
")->fetch();

// Tickets según filtro y página
$where = '';
if ($page === 'tickets' || $page === 'dashboard' || $page === 'sla') {
    if ($filter === 'open') $where = "WHERE t.status IN ('abierto', 'en_proceso', 'pendiente')";
    elseif ($filter === 'closed') $where = "WHERE t.status IN ('resuelto', 'cerrado')";
    elseif ($filter === 'urgent') $where = "WHERE t.priority = 'urgente' AND t.status NOT IN ('resuelto', 'cerrado')";
    elseif ($filter === 'mine') $where = "WHERE t.assigned_to = " . $user['id'];
}

$tickets = $pdo->query("
    SELECT t.*, 
           COALESCE(u.name, t.user_name) as user_name, 
           a.name as assigned_name,
           (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count,
           (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id AND tc.comment LIKE '%Archivos adjuntos%') as comment_attachments
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id
    $where
    ORDER BY 
        CASE t.priority WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END,
        t.created_at DESC
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
$statusColors = ['abierto' => 'primary', 'asignado' => 'info', 'en_proceso' => 'warning', 'pendiente' => 'secondary', 'resuelto' => 'success', 'cerrado' => 'dark'];
$statusLabels = ['abierto' => 'Abierto', 'asignado' => 'Asignado', 'en_proceso' => 'En Proceso', 'pendiente' => 'Pendiente', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Admin Soporte TI</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { 
            --primary-dark: #0c5a8a; 
            --primary-light: #094a72; 
            --primary-soft: #e8f4fc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        body.has-sidebar { background: var(--gray-100); }
        
        .main-content { min-height: calc(100vh - 60px); padding-top: 0; }
        .top-header { 
            background: white; 
            padding: 20px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid var(--gray-200);
            position: sticky; 
            top: 60px; 
            z-index: 100; 
        }
        .header-title h1 { font-size: 1.4rem; font-weight: 600; color: var(--gray-900); margin: 0; letter-spacing: -0.02em; }
        .header-title p { color: var(--gray-500); font-size: 0.85rem; margin: 0; }
        
        .content-area { padding: 30px; }
        
        /* Tarjetas de estadísticas - Estilo formal */
        .stat-card { 
            background: white; 
            border-radius: 8px; 
            padding: 24px; 
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
        }
        .stat-card:hover { 
            border-color: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(12, 90, 138, 0.08);
        }
        .stat-card.urgent { 
            background: white;
            border-left: 4px solid #dc2626;
        }
        .stat-card.urgent .stat-value { color: #dc2626; }
        .stat-card.urgent .stat-label { color: var(--gray-500); }
        .stat-value { font-size: 2rem; font-weight: 700; line-height: 1; color: var(--gray-900); }
        .stat-label { color: var(--gray-500); font-size: 0.8rem; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; }
        .stat-icon { 
            width: 48px; 
            height: 48px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.25rem; 
            background: var(--primary-soft);
            color: var(--primary-dark);
        }
        
        /* Tarjetas generales */
        .card-custom { 
            background: white; 
            border-radius: 8px; 
            border: 1px solid var(--gray-200);
            overflow: hidden; 
        }
        .card-header-custom { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--gray-200); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 12px;
            background: var(--gray-50);
        }
        .card-title-custom { font-size: 0.95rem; font-weight: 600; color: var(--gray-900); margin: 0; }
        
        /* Filtros */
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-tab { 
            padding: 8px 16px; 
            border-radius: 6px; 
            font-size: 0.8rem; 
            font-weight: 500; 
            text-decoration: none; 
            color: var(--gray-500); 
            background: white;
            border: 1px solid var(--gray-200);
            transition: all 0.15s ease;
        }
        .filter-tab:hover { border-color: var(--primary-dark); color: var(--primary-dark); }
        .filter-tab.active { background: var(--primary-dark); color: white; border-color: var(--primary-dark); }
        
        /* Tabla */
        .table th { 
            background: var(--gray-50); 
            font-weight: 600; 
            color: var(--gray-700); 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            padding: 14px 20px; 
            border: none;
            border-bottom: 1px solid var(--gray-200);
        }
        .table td { padding: 16px 20px; vertical-align: middle; border-color: var(--gray-100); font-size: 0.875rem; color: var(--gray-700); }
        .table tbody tr:hover { background: var(--gray-50); }
        .ticket-number { font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace; font-size: 0.8rem; color: var(--primary-dark); font-weight: 600; }
        
        .btn-action { 
            width: 34px; 
            height: 34px; 
            border-radius: 6px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0; 
            font-size: 0.85rem;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-500);
            transition: all 0.15s ease;
        }
        .btn-action:hover { border-color: var(--primary-dark); color: var(--primary-dark); background: var(--primary-soft); }
        
        /* Gráficos */
        .chart-card { 
            background: white; 
            border-radius: 8px; 
            padding: 24px; 
            border: 1px solid var(--gray-200);
        }
        .chart-title { font-size: 0.95rem; font-weight: 600; color: var(--gray-900); margin-bottom: 20px; }
        .chart-container { position: relative; height: 200px; width: 100%; }
        .chart-container-lg { position: relative; height: 250px; width: 100%; }
        
        /* Info de usuario */
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-info-avatar { 
            width: 32px; 
            height: 32px; 
            background: var(--primary-soft); 
            border-radius: 6px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 0.75rem; 
            color: var(--primary-dark); 
            font-weight: 600; 
        }
        
        /* Acciones rápidas */
        .quick-action { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            padding: 16px 18px; 
            background: var(--gray-50); 
            border: 1px solid var(--gray-200);
            border-radius: 8px; 
            text-decoration: none; 
            color: var(--gray-700); 
            transition: all 0.15s ease; 
            margin-bottom: 0;
        }
        .quick-action:hover { 
            background: var(--primary-soft); 
            border-color: var(--primary-dark);
            color: var(--primary-dark);
        }
        .quick-action-icon { 
            width: 42px; 
            height: 42px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.1rem; 
            background: white;
            border: 1px solid var(--gray-200);
        }
        
        /* Alertas SLA - Estilo limpio */
        .sla-alert-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid;
        }
        .sla-alert-item.overdue {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .sla-alert-item.warning {
            background: #fffbeb;
            border-color: #fde68a;
        }
        .sla-alert-item.unassigned {
            background: var(--primary-soft);
            border-color: #bae6fd;
        }
        .sla-alert-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sla-alert-item.overdue .sla-alert-icon { background: #fee2e2; color: #dc2626; }
        .sla-alert-item.warning .sla-alert-icon { background: #fef3c7; color: #d97706; }
        .sla-alert-item.unassigned .sla-alert-icon { background: #dbeafe; color: var(--primary-dark); }
        .sla-alert-content { flex-grow: 1; min-width: 0; }
        .sla-alert-title { font-weight: 600; font-size: 0.8rem; color: var(--gray-900); margin-bottom: 2px; }
        .sla-alert-desc { font-size: 0.75rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sla-alert-status { font-size: 0.7rem; font-weight: 600; margin-top: 4px; }
        .sla-alert-item.overdue .sla-alert-status { color: #dc2626; }
        .sla-alert-item.warning .sla-alert-status { color: #d97706; }
        .sla-alert-btn {
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 500;
            border-radius: 4px;
            text-decoration: none;
            white-space: nowrap;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
        }
        .sla-alert-btn:hover { border-color: var(--primary-dark); color: var(--primary-dark); }
        
        /* Tip del día */
        .tip-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .tip-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 1.1rem;
        }
        .tip-content small { color: var(--gray-500); font-weight: 500; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .tip-content div { color: var(--gray-700); font-size: 0.875rem; margin-top: 2px; }
        
        /* Modal */
        .modal-header { background: var(--primary-dark); color: white; }
        .modal-header .btn-close { filter: invert(1); }
        
        /* Badges */
        .badge { font-weight: 500; padding: 5px 10px; font-size: 0.7rem; letter-spacing: 0.02em; }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/sidebar_soporte.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-title">
                <h1><?= $page === 'dashboard' ? 'Dashboard' : ($page === 'tickets' ? 'Gestión de Tickets' : ($page === 'usuarios' ? 'Gestión de Usuarios' : ($page === 'nuevo_ticket' ? 'Nuevo Ticket' : ($page === 'sla' ? 'SLA - Acuerdos de Nivel de Servicio' : ($page === 'auditoria' ? 'Auditoría del Sistema' : ($page === 'notificaciones' ? 'Destinatarios de Notificaciones' : 'Soporte TI')))))) ?></h1>
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
            <!-- ========== DASHBOARD ========== -->
            
            <!-- Tip del día -->
            <div class="tip-banner">
                <div class="tip-icon">
                    <i class="bi bi-lightbulb"></i>
                </div>
                <div class="tip-content">
                    <small>Consejo del día</small>
                    <div><?= $tipOfDay['tip'] ?></div>
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Tickets</div></div>
                            <div class="stat-icon"><i class="bi bi-ticket-detailed"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $stats['abiertos'] + $stats['en_proceso'] ?></div><div class="stat-label">Pendientes</div></div>
                            <div class="stat-icon"><i class="bi bi-inbox-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $avgResponseHours ?><small class="fs-6">h</small></div><div class="stat-label">Tiempo Respuesta</div></div>
                            <div class="stat-icon"><i class="bi bi-stopwatch"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stat-card urgent">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $stats['urgentes'] ?></div><div class="stat-label">Urgentes</div></div>
                            <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Segunda fila: Métricas adicionales -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid #059669;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label text-muted mb-1">Resueltos esta semana</div>
                                <div class="stat-value" style="color:#059669"><?= $weekResolved ?></div>
                            </div>
                            <i class="bi bi-check-circle" style="font-size: 1.8rem; color: #05966930;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid var(--primary-dark);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label text-muted mb-1">Hoy creados</div>
                                <div class="stat-value" style="color:var(--primary-dark)"><?= $todayTickets ?></div>
                            </div>
                            <i class="bi bi-calendar-plus" style="font-size: 1.8rem; color: var(--primary-soft);"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid #6366f1;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label text-muted mb-1">Tiempo resolución</div>
                                <div class="stat-value" style="color: #6366f1;"><?= $avgResolutionHours ?><small class="fs-6">h</small></div>
                            </div>
                            <i class="bi bi-hourglass-split" style="font-size: 1.8rem; color: #6366f130;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid #d97706;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label text-muted mb-1">Sin asignar</div>
                                <div class="stat-value" style="color: #d97706;"><?= $stats['abiertos'] ?></div>
                            </div>
                            <i class="bi bi-person-dash" style="font-size: 1.8rem; color: #d9770630;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tickets Recientes -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-clock-history me-2"></i>Tickets Recientes</h5>
                    <a href="?page=tickets" class="btn btn-sm btn-outline-dark">Ver todos</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Ticket</th><th>Título</th><th>Usuario</th><th>Prioridad</th><th>Estado</th><th>Evidencia</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($tickets, 0, 10) as $t): ?>
                        <tr>
                            <td><span class="ticket-number"><?= $t['ticket_number'] ?></span></td>
                            <td><?= htmlspecialchars(substr($t['title'], 0, 40)) ?><?= strlen($t['title']) > 40 ? '...' : '' ?></td>
                            <td><div class="user-info"><div class="user-info-avatar"><?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?></div><?= htmlspecialchars($t['user_name'] ?? '-') ?></div></td>
                            <td><span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></td>
                            <td class="text-center">
                                <?php 
                                    $hasEvidence = (($t['attachment_count'] ?? 0) > 0) || (($t['comment_attachments'] ?? 0) > 0) || is_dir(__DIR__ . '/uploads/tickets/' . $t['ticket_number']);
                                ?>
                                <?php if ($hasEvidence): ?>
                                    <a href="#" class="badge bg-success text-decoration-none" title="Ver evidencia adjunta" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>"><i class="bi bi-paperclip me-1"></i>Ver</a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted" title="Sin evidencia"><i class="bi bi-x-circle me-1"></i>No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                            <td><button class="btn-action btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>"><i class="bi bi-eye"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="chart-card">
                        <h5 class="chart-title mb-3">Acciones Rápidas</h5>
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <a href="?page=nuevo_ticket" class="quick-action">
                                    <div class="quick-action-icon"><i class="bi bi-plus-lg" style="color:var(--primary-dark)"></i></div>
                                    <div><div class="fw-semibold">Crear Ticket</div><small class="text-muted">Nuevo ticket</small></div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="?page=tickets&filter=urgent" class="quick-action">
                                    <div class="quick-action-icon"><i class="bi bi-exclamation-triangle" style="color:#dc2626"></i></div>
                                    <div><div class="fw-semibold">Ver Urgentes</div><small class="text-muted"><?= $stats['urgentes'] ?> pendientes</small></div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="?page=tickets&filter=mine" class="quick-action">
                                    <div class="quick-action-icon"><i class="bi bi-person-badge" style="color:var(--primary-dark)"></i></div>
                                    <div><div class="fw-semibold">Mis Tickets</div><small class="text-muted">Asignados a mí</small></div>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="?page=sla" class="quick-action">
                                    <div class="quick-action-icon"><i class="bi bi-speedometer2" style="color:var(--primary-dark)"></i></div>
                                    <div><div class="fw-semibold">Métricas SLA</div><small class="text-muted">Rendimiento</small></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos y Alertas -->
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
                
                <!-- Panel de Alertas SLA -->
                <div class="col-lg-4">
                    <div class="chart-card" style="height: 100%;">
                        <h5 class="chart-title"><i class="bi bi-clock-history me-2"></i>Alertas de Tiempo</h5>
                        <?php if (empty($slaAlerts) && empty($unassignedAlerts)): ?>
                            <div class="text-center py-4">
                                <div class="sla-alert-icon mx-auto mb-3" style="width:56px;height:56px;background:var(--primary-soft);color:var(--primary-dark);">
                                    <i class="bi bi-check-lg" style="font-size:1.5rem"></i>
                                </div>
                                <p class="text-muted mb-0" style="font-size:0.85rem">Todo en orden<br><small>No hay alertas pendientes</small></p>
                            </div>
                        <?php else: ?>
                            <div class="alert-list" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($slaAlerts as $alert): 
                                    $percentage = round(($alert['elapsed_minutes'] / $alert['sla_limit_minutes']) * 100);
                                    $isOverdue = $percentage >= 100;
                                ?>
                                <div class="sla-alert-item <?= $isOverdue ? 'overdue' : 'warning' ?>">
                                    <div class="sla-alert-icon">
                                        <i class="bi bi-<?= $isOverdue ? 'exclamation-circle' : 'clock' ?>"></i>
                                    </div>
                                    <div class="sla-alert-content">
                                        <div class="sla-alert-title"><?= $alert['ticket_number'] ?></div>
                                        <div class="sla-alert-desc"><?= htmlspecialchars(substr($alert['title'] ?? '', 0, 30)) ?>...</div>
                                        <div class="sla-alert-status">
                                            <?= $isOverdue ? 'SLA vencido' : (100 - $percentage) . '% tiempo restante' ?>
                                        </div>
                                    </div>
                                    <a href="?page=tickets&filter=all" class="sla-alert-btn">Ver</a>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php foreach ($unassignedAlerts as $alert): ?>
                                <div class="sla-alert-item unassigned">
                                    <div class="sla-alert-icon">
                                        <i class="bi bi-person-dash"></i>
                                    </div>
                                    <div class="sla-alert-content">
                                        <div class="sla-alert-title"><?= $alert['ticket_number'] ?></div>
                                        <div class="sla-alert-desc">Sin asignar hace <?= $alert['hours_waiting'] ?>h</div>
                                    </div>
                                    <a href="?page=tickets&filter=open" class="sla-alert-btn">Asignar</a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <!-- Gráfico Estado -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title">Estado de Tickets</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Gráfico Categoría -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title">Por Categoría</h5>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Tickets Asignados por Técnico -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title"><i class="bi bi-person-badge me-2"></i>Tickets por Técnico</h5>
                        <?php if (empty($ticketsPerTechnician) || array_sum(array_column($ticketsPerTechnician, 'tickets_asignados')) == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">Sin tickets asignados</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                            <?php foreach ($ticketsPerTechnician as $tech): 
                                $pendientes = $tech['abiertos'] + $tech['en_progreso'];
                            ?>
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
            
            <!-- Guía de Uso del Sistema -->
            <div class="card-custom mt-4">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="bi bi-book me-2"></i>Guía de Uso del Sistema de Soporte</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-4 col-md-6">
                            <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; font-size: 1.2rem; font-weight: 700;">1</div>
                                <h6 class="fw-bold mb-2">Crear un Ticket</h6>
                                <p class="text-muted small mb-0">El usuario hace clic en <strong>"Nuevo Ticket"</strong> y completa categoría, prioridad y descripción. Se genera un código de seguimiento único.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 1.2rem; font-weight: 700;">2</div>
                                <h6 class="fw-bold mb-2">Consultar Estado</h6>
                                <p class="text-muted small mb-0">Con el botón <strong>"Consultar Ticket"</strong> el usuario ingresa su código y ve el estado, comentarios públicos y resolución.</p>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 1.2rem; font-weight: 700;">3</div>
                                <h6 class="fw-bold mb-2">Tiempos SLA</h6>
                                <p class="text-muted small mb-0"><strong>Urgente:</strong> 4h &middot; <strong>Alta:</strong> 8h &middot; <strong>Media:</strong> 24h &middot; <strong>Baja:</strong> 48h. Se envía correo de confirmación al crear el ticket.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 p-3 rounded-3" style="background: linear-gradient(135deg, #eff6ff, #f0fdf4); border: 1px solid #e2e8f0;">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-lightbulb me-3" style="font-size: 1.5rem; color: #f59e0b;"></i>
                            <div>
                                <h6 class="fw-bold mb-1 small">Consejos para el equipo TI</h6>
                                <ul class="text-muted small mb-0" style="list-style: none; padding: 0;">
                                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Responde los tickets dentro del SLA asignado para mantener el cumplimiento.</li>
                                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Usa comentarios internos para notas del equipo (no visibles para el usuario).</li>
                                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Adjunta evidencia de la solución antes de marcar como resuelto.</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Escala tickets urgentes que superen las 2 horas sin atención.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($page === 'tickets'): ?>
            <!-- ========== TICKETS ========== -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div></div>
                <a href="?page=nuevo_ticket" class="btn btn-dark"><i class="bi bi-plus-lg me-2"></i>Nuevo Ticket</a>
            </div>
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-ticket-detailed me-2"></i>Gestión de Tickets</h5>
                    <div class="filter-tabs">
                        <a href="?page=tickets&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Todos</a>
                        <a href="?page=tickets&filter=open" class="filter-tab <?= $filter === 'open' ? 'active' : '' ?>">Abiertos</a>
                        <a href="?page=tickets&filter=urgent" class="filter-tab <?= $filter === 'urgent' ? 'active' : '' ?>">Urgentes</a>
                        <a href="?page=tickets&filter=mine" class="filter-tab <?= $filter === 'mine' ? 'active' : '' ?>">Míos</a>
                        <a href="?page=tickets&filter=closed" class="filter-tab <?= $filter === 'closed' ? 'active' : '' ?>">Cerrados</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Ticket</th><th>Título</th><th>Usuario</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Evidencia</th><th>Asignado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2.5rem"></i><p class="mt-2 mb-0">No hay tickets</p></td></tr>
                        <?php else: foreach ($tickets as $t): ?>
                        <tr>
                            <td><span class="ticket-number"><?= $t['ticket_number'] ?></span></td>
                            <td><?= htmlspecialchars(substr($t['title'], 0, 35)) ?><?= strlen($t['title']) > 35 ? '...' : '' ?></td>
                            <td><div class="user-info"><div class="user-info-avatar"><?= strtoupper(substr($t['user_name'] ?? 'U', 0, 1)) ?></div><span class="small"><?= htmlspecialchars($t['user_name'] ?? '-') ?></span></div></td>
                            <td><span class="badge bg-light text-dark"><?= $categoryLabels[$t['category']] ?? $t['category'] ?></span></td>
                            <td><span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></td>
                            <td class="text-center">
                                <?php 
                                    $hasEvidence = (($t['attachment_count'] ?? 0) > 0) || (($t['comment_attachments'] ?? 0) > 0) || is_dir(__DIR__ . '/uploads/tickets/' . $t['ticket_number']);
                                ?>
                                <?php if ($hasEvidence): ?>
                                    <a href="#" class="badge bg-success text-decoration-none" title="Ver evidencia adjunta" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>"><i class="bi bi-paperclip me-1"></i>Ver</a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted" title="Sin evidencia"><i class="bi bi-x-circle me-1"></i>No</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="small text-muted"><?= $t['assigned_name'] ?? '-' ?></span></td>
                            <td class="small text-muted"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <button class="btn-action btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>" title="Ver"><i class="bi bi-eye"></i></button>
                                <button class="btn-action btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editTicketModal<?= $t['id'] ?>" title="Editar"><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar el ticket <?= $t['ticket_number'] ?>? Esta acción no se puede deshacer.')">
                                    <input type="hidden" name="action" value="delete_ticket">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-action btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php if (!$t['assigned_to'] && !$isAdmin): ?>
                                <form method="POST" class="d-inline"><input type="hidden" name="action" value="self_assign"><input type="hidden" name="ticket_id" value="<?= $t['id'] ?>"><button type="submit" class="btn-action btn btn-outline-success btn-sm" title="Asignarme"><i class="bi bi-person-plus"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($page === 'nuevo_ticket'): ?>
            <!-- ========== NUEVO TICKET ========== -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom"><h5 class="card-title-custom"><i class="bi bi-plus-circle me-2"></i>Crear Nuevo Ticket</h5></div>
                        <div class="p-4">
                            <form method="POST" enctype="multipart/form-data">
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
            
            <?php elseif ($page === 'sla'): ?>
            <!-- ========== SLA - ACUERDOS DE NIVEL DE SERVICIO ========== -->
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
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="bi bi-chat-dots text-white"></i></div>
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
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"><i class="bi bi-person-check text-white"></i></div>
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
                            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);"><i class="bi bi-check2-circle text-white"></i></div>
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
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="bi bi-hourglass-split text-white"></i></div>
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
                                    <a href="#" class="text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>"><?= $t['ticket_number'] ?></a>
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
            
            <?php elseif ($page === 'auditoria'): ?>
            <!-- ========== AUDITORÍA DEL SISTEMA ========== -->
            
            <!-- Estadísticas -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= number_format($auditStats['total']) ?></div>
                                <div class="stat-label">Total Registros</div>
                            </div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);"><i class="bi bi-journal-text text-white"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['today'] ?></div>
                                <div class="stat-label">Hoy</div>
                            </div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="bi bi-calendar-check text-white"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['week'] ?></div>
                                <div class="stat-label">Últimos 7 días</div>
                            </div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"><i class="bi bi-graph-up text-white"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-value"><?= $auditStats['unique_users'] ?></div>
                                <div class="stat-label">Usuarios Activos</div>
                            </div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="bi bi-people text-white"></i></div>
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
                            <label class="form-label">Usuario</label>
                            <input type="text" name="audit_user" class="form-control" value="<?= htmlspecialchars($auditFilterUser) ?>" placeholder="Nombre o email">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Acción</label>
                            <select name="audit_action" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($auditActions as $action): ?>
                                <option value="<?= $action ?>" <?= $auditFilterAction === $action ? 'selected' : '' ?>><?= ucfirst($action) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Entidad</label>
                            <select name="audit_entity" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($auditEntities as $entity): ?>
                                <option value="<?= $entity ?>" <?= $auditFilterEntity === $entity ? 'selected' : '' ?>><?= ucfirst($entity) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Desde</label>
                            <input type="date" name="audit_date_from" class="form-control" value="<?= $auditFilterDateFrom ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="audit_date_to" class="form-control" value="<?= $auditFilterDateTo ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-search"></i></button>
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
                            <table class="table table-hover mb-0">
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
                            <?php foreach ($topActions as $action): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary"><?= ucfirst($action['action']) ?></span>
                                <span class="fw-bold"><?= number_format($action['count']) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Gráfico de actividad -->
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom"><i class="bi bi-graph-up me-2"></i>Actividad (7 días)</h5>
                        </div>
                        <div class="p-3">
                            <div class="chart-container">
                                <canvas id="auditChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('auditChart');
                if (ctx) {
                    new Chart(ctx, {
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
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            }
                        }
                    });
                }
            });
            </script>
            
            });
            </script>
            
            <?php elseif ($page === 'notificaciones'): ?>
            <!-- ========== GESTIÓN DE DESTINATARIOS DE NOTIFICACIONES ========== -->
            
            <!-- Estadísticas rápidas -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['total'] ?></div>
                        <div class="stat-label">Total Registrados</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['active'] ?></div>
                        <div class="stat-label">Activos</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <div class="stat-number"><?= $notifStats['ticket_created'] ?></div>
                        <div class="stat-label">Reciben "Ticket Creado"</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
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
                                                    <input type="hidden" name="action" value="toggle_notification_email">
                                                    <input type="hidden" name="notif_id" value="<?= $nr['id'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= $nr['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $nr['is_active'] ? 'Pausar' : 'Activar' ?>">
                                                        <i class="bi <?= $nr['is_active'] ? 'bi-pause' : 'bi-play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este destinatario?')">
                                                    <input type="hidden" name="action" value="delete_notification_email">
                                                    <input type="hidden" name="notif_id" value="<?= $nr['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
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
    
    <!-- Unir tickets de todas las fuentes para generar modales -->
    <?php 
    $allModalTickets = [];
    foreach ($tickets as $t) $allModalTickets[$t['id']] = $t;
    if (!empty($slaTickets)) { foreach ($slaTickets as $t) { if (!isset($allModalTickets[$t['id']])) $allModalTickets[$t['id']] = $t; } }
    ?>

    <!-- Modales de Edición de Tickets -->
    <?php foreach ($allModalTickets as $t): ?>
    <div class="modal fade" id="editTicketModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar <?= $t['ticket_number'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nombre del solicitante</label>
                                <input type="text" name="user_name" class="form-control" value="<?= htmlspecialchars($t['user_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email del solicitante</label>
                                <input type="email" name="user_email" class="form-control" value="<?= htmlspecialchars($t['user_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Categoría *</label>
                                <select name="category" class="form-select" required>
                                    <option value="hardware" <?= $t['category'] === 'hardware' ? 'selected' : '' ?>>Hardware</option>
                                    <option value="software" <?= $t['category'] === 'software' ? 'selected' : '' ?>>Software</option>
                                    <option value="red" <?= $t['category'] === 'red' ? 'selected' : '' ?>>Red</option>
                                    <option value="acceso" <?= $t['category'] === 'acceso' ? 'selected' : '' ?>>Acceso</option>
                                    <option value="otro" <?= $t['category'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prioridad *</label>
                                <select name="priority" class="form-select" required>
                                    <option value="baja" <?= $t['priority'] === 'baja' ? 'selected' : '' ?>>Baja</option>
                                    <option value="media" <?= $t['priority'] === 'media' ? 'selected' : '' ?>>Media</option>
                                    <option value="alta" <?= $t['priority'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                    <option value="urgente" <?= $t['priority'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Título *</label>
                                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($t['title']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Descripción *</label>
                                <textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($t['description']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Modales de Tickets (Ver detalles) -->
    <?php foreach ($allModalTickets as $t): 
        $ticketComments = $pdo->prepare('SELECT * FROM ticket_comments WHERE ticket_id = ? ORDER BY created_at ASC');
        $ticketComments->execute([$t['id']]);
        $comments = $ticketComments->fetchAll();
    ?>
    <div class="modal fade" id="ticketModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-ticket-detailed me-2"></i><?= $t['ticket_number'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <h5 class="fw-bold"><?= htmlspecialchars($t['title']) ?></h5>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
                            
                            <?php if ($t['resolution']): ?>
                            <div class="alert alert-success"><strong>Resolución:</strong><br><?= nl2br(htmlspecialchars($t['resolution'])) ?></div>
                            <?php endif; ?>
                            
                            <?php
                            // ===== EVIDENCIA ADJUNTA =====
                            $ticketNum = $t['ticket_number'];
                            $evidenceDir = __DIR__ . '/uploads/tickets/' . $ticketNum;
                            $evidenceFiles = [];
                            
                            // 1) Archivos en el filesystem
                            if (is_dir($evidenceDir)) {
                                $scan = array_diff(scandir($evidenceDir), ['.', '..', '.gitkeep']);
                                foreach ($scan as $f) {
                                    $evidenceFiles[$f] = 'uploads/tickets/' . $ticketNum . '/' . $f;
                                }
                            }
                            
                            // 2) Archivos referenciados en comentarios (fallback si scandir no encuentra)
                            $commentFiles = [];
                            foreach ($comments as $c) {
                                if (preg_match('/Archivos adjuntos:\s*(.+)$/m', $c['comment'], $m)) {
                                    $names = array_map('trim', explode(',', $m[1]));
                                    foreach ($names as $fname) {
                                        $fname = trim($fname);
                                        if ($fname && !isset($evidenceFiles[$fname])) {
                                            $commentFiles[$fname] = 'uploads/tickets/' . $ticketNum . '/' . $fname;
                                            $evidenceFiles[$fname] = $commentFiles[$fname];
                                        }
                                    }
                                }
                            }
                            
                            $totalEvidence = count($evidenceFiles);
                            ?>
                            <?php if ($totalEvidence > 0): ?>
                            <div class="mb-3">
                                <h6 class="fw-bold mb-2"><i class="bi bi-paperclip me-1"></i>Evidencia Adjunta (<?= $totalEvidence ?>)</h6>
                                <div class="row g-2">
                                    <?php foreach ($evidenceFiles as $fname => $furl):
                                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $displayName = preg_replace('/^[a-f0-9]+_/', '', $fname);
                                        $fullDiskPath = __DIR__ . '/' . $furl;
                                        $fileExists = file_exists($fullDiskPath);
                                    ?>
                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-2 d-flex align-items-center gap-2" style="background: #f8fafc;">
                                            <?php if ($isImage && $fileExists): ?>
                                            <a href="javascript:void(0)" onclick="openLightbox('<?= htmlspecialchars($furl, ENT_QUOTES) ?>', '<?= htmlspecialchars($displayName, ENT_QUOTES) ?>')" title="Ver imagen" style="flex-shrink:0;">
                                                <img src="<?= htmlspecialchars($furl) ?>" alt="<?= htmlspecialchars($displayName) ?>" style="width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; cursor: zoom-in;">
                                            </a>
                                            <?php elseif ($isImage): ?>
                                            <div class="d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: #dbeafe; border-radius: 6px; flex-shrink:0;">
                                                <i class="bi bi-image" style="font-size: 1.3rem; color: #3b82f6;"></i>
                                            </div>
                                            <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: #e2e8f0; border-radius: 6px; flex-shrink:0;">
                                                <i class="bi bi-file-earmark-<?= $ext === 'pdf' ? 'pdf' : ($ext === 'doc' || $ext === 'docx' ? 'word' : 'text') ?>" style="font-size: 1.3rem; color: #64748b;"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <p class="mb-0 small fw-semibold text-truncate" title="<?= htmlspecialchars($displayName) ?>"><?= htmlspecialchars($displayName) ?></p>
                                                <p class="mb-0 text-muted" style="font-size: 0.7rem;">
                                                    <?= strtoupper($ext) ?>
                                                    <?php if ($fileExists): ?>
                                                        · <?= round(filesize($fullDiskPath) / 1024) ?> KB
                                                    <?php else: ?>
                                                        · <span class="text-warning">Archivo en servidor</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <?php if ($isImage && $fileExists): ?>
                                            <a href="javascript:void(0)" onclick="openLightbox('<?= htmlspecialchars($furl, ENT_QUOTES) ?>', '<?= htmlspecialchars($displayName, ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-primary" title="Ver imagen" style="flex-shrink:0;"><i class="bi bi-eye"></i></a>
                                            <?php elseif ($fileExists): ?>
                                            <a href="<?= htmlspecialchars($furl) ?>" download class="btn btn-sm btn-outline-primary" title="Descargar archivo" style="flex-shrink:0;"><i class="bi bi-download"></i></a>
                                            <?php endif; ?>
                                            <?php if ($fileExists): ?>
                                            <a href="<?= htmlspecialchars($furl) ?>" download class="btn btn-sm btn-outline-secondary" title="Descargar" style="flex-shrink:0;"><i class="bi bi-download"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!empty($evidenceFiles)): ?>
                                <?php 
                                    // Mostrar preview grande de imágenes
                                    $imageFiles = array_filter($evidenceFiles, function($fname) {
                                        return in_array(strtolower(pathinfo($fname, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    }, ARRAY_FILTER_USE_KEY);
                                ?>
                                <?php if (!empty($imageFiles)): ?>
                                <div class="mt-2">
                                    <div class="row g-2">
                                        <?php foreach ($imageFiles as $fname => $furl): 
                                            if (!file_exists(__DIR__ . '/' . $furl)) continue;
                                            $imgDisplayName = preg_replace('/^[a-f0-9]+_/', '', $fname);
                                        ?>
                                        <div class="col-md-4">
                                            <a href="javascript:void(0)" onclick="openLightbox('<?= htmlspecialchars($furl, ENT_QUOTES) ?>', '<?= htmlspecialchars($imgDisplayName, ENT_QUOTES) ?>')">
                                                <img src="<?= htmlspecialchars($furl) ?>" class="img-fluid rounded-3 border" alt="<?= htmlspecialchars($imgDisplayName) ?>" style="max-height: 180px; width: 100%; object-fit: cover; cursor: zoom-in;">
                                            </a>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            <h6 class="fw-bold mb-3">Comentarios</h6>
                            <?php if (empty($comments)): ?>
                            <p class="text-muted small">Sin comentarios</p>
                            <?php else: foreach ($comments as $c): ?>
                            <div class="bg-light rounded p-3 mb-2">
                                <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($c['user_name'] ?? 'Sistema') ?></strong><small class="text-muted"><?= date('d/m H:i', strtotime($c['created_at'])) ?></small></div>
                                <p class="mb-0 mt-1"><?php
                                    $commentText = htmlspecialchars($c['comment']);
                                    // Convertir nombres de archivo en links clickeables
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
                                                $linkedNames[] = '<a href="javascript:void(0)" onclick="openLightbox(\'' . htmlspecialchars($fileUrl, ENT_QUOTES) . '\', \'' . htmlspecialchars($cleanName, ENT_QUOTES) . '\')" class="text-primary" style="cursor:pointer"><i class="bi bi-image me-1"></i>' . htmlspecialchars($cleanName) . '</a>';
                                            } else {
                                                $linkedNames[] = '<a href="' . htmlspecialchars($fileUrl) . '" download class="text-primary"><i class="bi bi-file-earmark me-1"></i>' . htmlspecialchars($cleanName) . '</a>';
                                            }
                                        }
                                        $commentText = str_replace(htmlspecialchars($cm[0]), 'Archivos adjuntos: ' . implode(', ', $linkedNames), $commentText);
                                    }
                                    echo nl2br($commentText);
                                ?></p>
                                <?php if ($c['is_internal']): ?><span class="badge bg-warning text-dark">Interno</span><?php endif; ?>
                            </div>
                            <?php endforeach; endif; ?>
                            
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <textarea name="comment" class="form-control mb-2" rows="2" placeholder="Agregar comentario..." required></textarea>
                                <div class="d-flex justify-content-between">
                                    <div class="form-check"><input type="checkbox" name="is_internal" class="form-check-input" id="internal<?= $t['id'] ?>"><label class="form-check-label small" for="internal<?= $t['id'] ?>">Nota interna</label></div>
                                    <button type="submit" class="btn btn-sm btn-dark">Enviar</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-3">
                                <p class="mb-2"><strong>Estado:</strong> <span class="badge bg-<?= $statusColors[$t['status']] ?>"><?= $statusLabels[$t['status']] ?></span></p>
                                <p class="mb-2"><strong>Prioridad:</strong> <span class="badge bg-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></p>
                                <p class="mb-2"><strong>Categoría:</strong> <?= $categoryLabels[$t['category']] ?? $t['category'] ?></p>
                                <p class="mb-2"><strong>Usuario:</strong> <?= htmlspecialchars($t['user_name'] ?? '-') ?></p>
                                <p class="mb-2"><strong>Asignado:</strong> <?= htmlspecialchars($t['assigned_name'] ?? 'Sin asignar') ?></p>
                                <p class="mb-0"><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></p>
                            </div>
                            
                            <hr>
                            
                            <!-- Asignar/Desasignar Ticket (admin y soporte) -->
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="assign_ticket">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <label class="form-label fw-semibold small">Asignar a:</label>
                                <select name="assign_to" class="form-select form-select-sm mb-2">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['id'] ?>" <?= $t['assigned_to'] == $tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?> (<?= $tech['role'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-dark w-100">Asignar</button>
                            </form>
                            
                            <?php if ($t['assigned_to']): ?>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="unassign_ticket">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('¿Desasignar este ticket?')">
                                    <i class="bi bi-person-dash me-1"></i>Desasignar
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <!-- Cambiar Estado -->
                            <form method="POST">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <label class="form-label fw-semibold small">Cambiar estado:</label>
                                <select name="new_status" class="form-select form-select-sm mb-2">
                                    <option value="abierto" <?= $t['status'] === 'abierto' ? 'selected' : '' ?>>Abierto</option>
                                    <option value="en_proceso" <?= $t['status'] === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                                    <option value="pendiente" <?= $t['status'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="resuelto" <?= $t['status'] === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                                    <option value="cerrado" <?= $t['status'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                                </select>
                                <textarea name="resolution" class="form-control form-control-sm mb-2" rows="2" placeholder="Resolución (opcional)"><?= htmlspecialchars($t['resolution'] ?? '') ?></textarea>
                                <button type="submit" class="btn btn-sm btn-dark w-100">Actualizar Estado</button>
                            </form>
                            
                            <hr>
                            
                            <!-- Acciones rápidas -->
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-warning w-100" data-bs-dismiss="modal" onclick="setTimeout(function(){ new bootstrap.Modal(document.getElementById('editTicketModal<?= $t['id'] ?>')).show(); }, 300)">
                                    <i class="bi bi-pencil me-1"></i>Editar Ticket
                                </button>
                                <form method="POST" onsubmit="return confirm('¿Eliminar el ticket <?= $t['ticket_number'] ?>? Esta acción no se puede deshacer.')">
                                    <input type="hidden" name="action" value="delete_ticket">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                        <i class="bi bi-trash me-1"></i>Eliminar Ticket
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($page === 'dashboard'): ?>
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
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { 
                                usePointStyle: true, 
                                padding: 15,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(10, 37, 64, 0.9)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
    
    <?php if ($page === 'sla'): ?>
    <script>
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

    <!-- Modal Lightbox para imágenes -->
    <div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-header border-0 pb-0" style="background: rgba(0,0,0,0.7); border-radius: 12px 12px 0 0;">
                    <h6 class="modal-title text-white" id="lightboxTitle"></h6>
                    <div class="d-flex gap-2 align-items-center">
                        <a id="lightboxDownload" href="#" download class="btn btn-sm btn-outline-light" title="Descargar"><i class="bi bi-download"></i></a>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                </div>
                <div class="modal-body text-center p-0" style="background: rgba(0,0,0,0.85); border-radius: 0 0 12px 12px;">
                    <img id="lightboxImage" src="" alt="" style="max-width: 100%; max-height: 80vh; object-fit: contain; border-radius: 0 0 12px 12px;">
                </div>
            </div>
        </div>
    </div>

    <script>
    function openLightbox(src, title) {
        // Cerrar cualquier modal abierto primero
        document.querySelectorAll('.modal.show').forEach(function(m) {
            var inst = bootstrap.Modal.getInstance(m);
            if (inst) inst.hide();
        });
        setTimeout(function() {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('lightboxImage').alt = title;
            document.getElementById('lightboxTitle').textContent = title;
            document.getElementById('lightboxDownload').href = src;
            var modal = new bootstrap.Modal(document.getElementById('imageLightboxModal'));
            modal.show();
        }, 300);
    }
    </script>
</body>
</html>

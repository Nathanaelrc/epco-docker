<?php
/**
 * EPCO - Funciones Helper para Sistema de Tickets y SLA
 * Incluye cálculos de SLA, formateo de tiempos, y utilidades
 */

// Prevenir acceso directo
if (!defined('EPCO_APP')) {
    die('Acceso directo no permitido');
}

// =============================================
// FUNCIONES DE CÁLCULO SLA
// =============================================

/**
 * Obtener configuración SLA desde la base de datos
 */
function getSlaSettings() {
    global $pdo;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $pdo->query("SELECT * FROM sla_settings WHERE is_active = 1");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['priority']] = [
                'first_response' => (int)$row['first_response_minutes'],
                'assignment' => (int)$row['assignment_minutes'],
                'resolution' => (int)$row['resolution_minutes']
            ];
        }
    }
    
    return $settings;
}

/**
 * Obtener objetivos SLA para una prioridad específica
 */
function getSlaTargets($priority) {
    $settings = getSlaSettings();
    return $settings[$priority] ?? $settings['media'];
}

/**
 * Calcular tiempo transcurrido en minutos (excluyendo pausas)
 */
function calculateElapsedMinutes($startTime, $endTime = null, $pausedMinutes = 0) {
    if (!$startTime) return 0;
    
    $start = new DateTime($startTime);
    $end = $endTime ? new DateTime($endTime) : new DateTime();
    
    $diff = $end->getTimestamp() - $start->getTimestamp();
    $minutes = max(0, floor($diff / 60) - $pausedMinutes);
    
    return $minutes;
}

/**
 * Calcular porcentaje de SLA consumido
 */
function calculateSlaPercentage($elapsedMinutes, $targetMinutes) {
    if ($targetMinutes <= 0) return 100;
    return min(100, round(($elapsedMinutes / $targetMinutes) * 100, 1));
}

/**
 * Determinar estado del SLA (verde, amarillo, rojo)
 */
function getSlaStatus($elapsedMinutes, $targetMinutes) {
    $percentage = calculateSlaPercentage($elapsedMinutes, $targetMinutes);
    
    if ($percentage >= 100) {
        return ['status' => 'breached', 'color' => 'danger', 'icon' => 'bi-x-circle-fill', 'label' => 'Incumplido'];
    } elseif ($percentage >= 75) {
        return ['status' => 'warning', 'color' => 'warning', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'En riesgo'];
    } elseif ($percentage >= 50) {
        return ['status' => 'attention', 'color' => 'info', 'icon' => 'bi-info-circle-fill', 'label' => 'Atención'];
    } else {
        return ['status' => 'ok', 'color' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'En tiempo'];
    }
}

/**
 * Calcular métricas SLA completas para un ticket
 */
function calculateTicketSlaMetrics($ticket) {
    $targets = getSlaTargets($ticket['priority']);
    $now = new DateTime();
    
    $metrics = [
        'response' => [
            'target_minutes' => $targets['first_response'],
            'elapsed_minutes' => 0,
            'remaining_minutes' => $targets['first_response'],
            'percentage' => 0,
            'status' => null,
            'met' => null,
            'timestamp' => null
        ],
        'assignment' => [
            'target_minutes' => $targets['assignment'],
            'elapsed_minutes' => 0,
            'remaining_minutes' => $targets['assignment'],
            'percentage' => 0,
            'status' => null,
            'met' => null,
            'timestamp' => null
        ],
        'resolution' => [
            'target_minutes' => $targets['resolution'],
            'elapsed_minutes' => 0,
            'remaining_minutes' => $targets['resolution'],
            'percentage' => 0,
            'status' => null,
            'met' => null,
            'timestamp' => null
        ]
    ];
    
    $pausedMinutes = (int)($ticket['sla_paused_minutes'] ?? 0);
    
    // Calcular tiempo de primera respuesta
    if ($ticket['first_response_at']) {
        $metrics['response']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            $ticket['first_response_at']
        );
        $metrics['response']['met'] = $metrics['response']['elapsed_minutes'] <= $targets['first_response'];
        $metrics['response']['timestamp'] = $ticket['first_response_at'];
    } else {
        $metrics['response']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            null, 
            $pausedMinutes
        );
    }
    $metrics['response']['percentage'] = calculateSlaPercentage(
        $metrics['response']['elapsed_minutes'], 
        $targets['first_response']
    );
    $metrics['response']['remaining_minutes'] = max(0, $targets['first_response'] - $metrics['response']['elapsed_minutes']);
    $metrics['response']['status'] = getSlaStatus($metrics['response']['elapsed_minutes'], $targets['first_response']);
    
    // Calcular tiempo de asignación
    if ($ticket['assigned_at']) {
        $metrics['assignment']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            $ticket['assigned_at']
        );
        $metrics['assignment']['met'] = $metrics['assignment']['elapsed_minutes'] <= $targets['assignment'];
        $metrics['assignment']['timestamp'] = $ticket['assigned_at'];
    } else {
        $metrics['assignment']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            null, 
            $pausedMinutes
        );
    }
    $metrics['assignment']['percentage'] = calculateSlaPercentage(
        $metrics['assignment']['elapsed_minutes'], 
        $targets['assignment']
    );
    $metrics['assignment']['remaining_minutes'] = max(0, $targets['assignment'] - $metrics['assignment']['elapsed_minutes']);
    $metrics['assignment']['status'] = getSlaStatus($metrics['assignment']['elapsed_minutes'], $targets['assignment']);
    
    // Calcular tiempo de resolución
    $resolutionEndTime = $ticket['resolved_at'] ?? ($ticket['closed_at'] ?? null);
    if ($resolutionEndTime) {
        $metrics['resolution']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            $resolutionEndTime,
            $pausedMinutes
        );
        $metrics['resolution']['met'] = $metrics['resolution']['elapsed_minutes'] <= $targets['resolution'];
        $metrics['resolution']['timestamp'] = $resolutionEndTime;
    } else {
        $metrics['resolution']['elapsed_minutes'] = calculateElapsedMinutes(
            $ticket['created_at'], 
            null, 
            $pausedMinutes
        );
    }
    $metrics['resolution']['percentage'] = calculateSlaPercentage(
        $metrics['resolution']['elapsed_minutes'], 
        $targets['resolution']
    );
    $metrics['resolution']['remaining_minutes'] = max(0, $targets['resolution'] - $metrics['resolution']['elapsed_minutes']);
    $metrics['resolution']['status'] = getSlaStatus($metrics['resolution']['elapsed_minutes'], $targets['resolution']);
    
    return $metrics;
}

/**
 * Obtener estadísticas SLA globales
 */
function getGlobalSlaStats($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN sla_response_met = 1 THEN 1 ELSE 0 END) as response_met,
            SUM(CASE WHEN sla_response_met = 0 THEN 1 ELSE 0 END) as response_breached,
            SUM(CASE WHEN sla_resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met,
            SUM(CASE WHEN sla_resolution_met = 0 THEN 1 ELSE 0 END) as resolution_breached,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(first_response_at, NOW()))) as avg_response_minutes,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) ELSE NULL END) as avg_resolution_minutes
        FROM tickets 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$days]);
    $stats = $stmt->fetch();
    
    // Calcular porcentajes de cumplimiento
    $stats['response_compliance'] = $stats['total'] > 0 
        ? round(($stats['response_met'] / $stats['total']) * 100, 1) 
        : 0;
    
    $resolvedTotal = $stats['resolution_met'] + $stats['resolution_breached'];
    $stats['resolution_compliance'] = $resolvedTotal > 0 
        ? round(($stats['resolution_met'] / $resolvedTotal) * 100, 1) 
        : 0;
    
    return $stats;
}

/**
 * Obtener estadísticas SLA por prioridad
 */
function getSlsStatsByPriority($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            priority,
            COUNT(*) as total,
            SUM(CASE WHEN sla_response_met = 1 THEN 1 ELSE 0 END) as response_met,
            SUM(CASE WHEN sla_response_met = 0 THEN 1 ELSE 0 END) as response_breached,
            SUM(CASE WHEN sla_resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met,
            SUM(CASE WHEN sla_resolution_met = 0 THEN 1 ELSE 0 END) as resolution_breached,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(first_response_at, NOW()))) as avg_response
        FROM tickets 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY priority
        ORDER BY FIELD(priority, 'urgente', 'alta', 'media', 'baja')
    ");
    $stmt->execute([$days]);
    
    return $stmt->fetchAll();
}

// =============================================
// FUNCIONES DE FORMATO
// =============================================

/**
 * Formatear minutos a formato legible
 */
function formatMinutes($minutes) {
    if ($minutes < 0) return 'N/A';
    
    if ($minutes < 60) {
        return $minutes . ' min';
    } elseif ($minutes < 1440) { // menos de 24 horas
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours . 'h ' . ($mins > 0 ? $mins . 'm' : '');
    } else {
        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        return $days . 'd ' . ($hours > 0 ? $hours . 'h' : '');
    }
}

/**
 * Formatear fecha relativa (hace X tiempo)
 */
function formatTimeAgo($datetime) {
    if (!$datetime) return 'N/A';
    
    $time = is_string($datetime) ? strtotime($datetime) : $datetime;
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Hace un momento';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return 'Hace ' . $mins . ' ' . ($mins == 1 ? 'minuto' : 'minutos');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return 'Hace ' . $hours . ' ' . ($hours == 1 ? 'hora' : 'horas');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return 'Hace ' . $days . ' ' . ($days == 1 ? 'día' : 'días');
    } else {
        return date('d/m/Y H:i', $time);
    }
}

/**
 * Formatear fecha completa
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// =============================================
// FUNCIONES DE TICKET
// =============================================

/**
 * Registrar cambio de estado en historial
 */
function logTicketHistory($ticketId, $action, $oldValue = null, $newValue = null, $description = null, $userId = null) {
    global $pdo;
    
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    
    $stmt = $pdo->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$ticketId, $userId, $action, $oldValue, $newValue, $description]);
}

/**
 * Asignar ticket y actualizar SLA
 */
function assignTicket($ticketId, $assignedTo, $assignedBy = null) {
    global $pdo;
    
    $assignedBy = $assignedBy ?? ($_SESSION['user_id'] ?? null);
    
    // Obtener nombre del asignado
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$assignedTo]);
    $assignedUser = $stmt->fetch();
    
    // Actualizar ticket
    $stmt = $pdo->prepare("
        UPDATE tickets SET 
            assigned_to = ?,
            assigned_by = ?,
            assigned_at = NOW(),
            status = 'asignado',
            first_response_at = COALESCE(first_response_at, NOW())
        WHERE id = ?
    ");
    $stmt->execute([$assignedTo, $assignedBy, $ticketId]);
    
    // Verificar SLA de respuesta
    updateTicketSlaFlags($ticketId);
    
    // Registrar en historial
    logTicketHistory($ticketId, 'assigned', null, $assignedUser['name'], 'Ticket asignado');
    logTicketHistory($ticketId, 'status_change', null, 'asignado', 'Estado cambiado a asignado');
    
    return true;
}

/**
 * Desasignar ticket
 */
function unassignTicket($ticketId, $userId = null) {
    global $pdo;
    
    // Obtener info actual
    $stmt = $pdo->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if ($ticket['assigned_to']) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$ticket['assigned_to']]);
        $oldAssigned = $stmt->fetch();
        
        // Actualizar ticket
        $stmt = $pdo->prepare("
            UPDATE tickets SET 
                assigned_to = NULL,
                assigned_by = NULL,
                assigned_at = NULL,
                status = 'abierto'
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);
        
        // Registrar en historial
        logTicketHistory($ticketId, 'unassigned', $oldAssigned['name'], null, 'Asignación removida');
        logTicketHistory($ticketId, 'status_change', 'asignado', 'abierto', 'Estado cambiado a abierto');
    }
    
    return true;
}

/**
 * Cambiar estado de ticket
 */
function changeTicketStatus($ticketId, $newStatus, $resolution = null) {
    global $pdo;
    
    // Obtener estado actual
    $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    $oldStatus = $ticket['status'];
    
    // Preparar campos adicionales según el estado
    $extraFields = '';
    $params = [$newStatus, $ticketId];
    
    switch ($newStatus) {
        case 'en_proceso':
            $extraFields = ', work_started_at = COALESCE(work_started_at, NOW())';
            break;
        case 'resuelto':
            $extraFields = ', resolved_at = NOW(), resolution = ?';
            array_splice($params, 1, 0, [$resolution]);
            break;
        case 'cerrado':
            $extraFields = ', closed_at = NOW()';
            break;
        case 'pendiente':
        case 'en_pausa':
            // Pausar SLA cuando está pendiente o en pausa
            $extraFields = ', sla_paused_at = NOW()';
            break;
    }
    
    // Si volvemos de pendiente/en_pausa, calcular tiempo pausado
    if (in_array($oldStatus, ['pendiente', 'en_pausa']) && !in_array($newStatus, ['pendiente', 'en_pausa'])) {
        $stmt = $pdo->prepare("
            UPDATE tickets SET 
                sla_paused_minutes = sla_paused_minutes + TIMESTAMPDIFF(MINUTE, sla_paused_at, NOW()),
                sla_paused_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);
    }
    
    // Actualizar estado
    $sql = "UPDATE tickets SET status = ? $extraFields WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Actualizar flags SLA
    updateTicketSlaFlags($ticketId);
    
    // Registrar en historial
    logTicketHistory($ticketId, 'status_change', $oldStatus, $newStatus, "Estado cambiado de $oldStatus a $newStatus");
    
    return true;
}

/**
 * Actualizar flags de SLA del ticket
 */
function updateTicketSlaFlags($ticketId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) return false;
    
    $metrics = calculateTicketSlaMetrics($ticket);
    
    $updates = [];
    $params = [];
    
    // Actualizar SLA de respuesta si se completó
    if ($ticket['first_response_at'] && $ticket['sla_response_met'] === null) {
        $updates[] = 'sla_response_met = ?';
        $params[] = $metrics['response']['met'] ? 1 : 0;
    }
    
    // Actualizar SLA de resolución si se completó
    if (in_array($ticket['status'], ['resuelto', 'cerrado']) && $ticket['sla_resolution_met'] === null) {
        $updates[] = 'sla_resolution_met = ?';
        $params[] = $metrics['resolution']['met'] ? 1 : 0;
    }
    
    if (!empty($updates)) {
        $params[] = $ticketId;
        $sql = "UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    return true;
}

/**
 * Agregar comentario a ticket
 */
function addTicketComment($ticketId, $comment, $isInternal = false, $userId = null) {
    global $pdo;
    
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    $userName = $_SESSION['user_name'] ?? 'Sistema';
    
    // Verificar si es primera respuesta
    $stmt = $pdo->prepare("SELECT first_response_at FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    $isFirstResponse = !$ticket['first_response_at'] && $userId;
    
    // Insertar comentario
    $stmt = $pdo->prepare("
        INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal, is_first_response)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$ticketId, $userId, $userName, $comment, $isInternal ? 1 : 0, $isFirstResponse ? 1 : 0]);
    
    // Si es primera respuesta, actualizar ticket
    if ($isFirstResponse) {
        $stmt = $pdo->prepare("UPDATE tickets SET first_response_at = NOW() WHERE id = ? AND first_response_at IS NULL");
        $stmt->execute([$ticketId]);
        
        // Actualizar flag SLA
        updateTicketSlaFlags($ticketId);
    }
    
    // Registrar en historial
    logTicketHistory($ticketId, 'comment', null, null, 'Comentario agregado' . ($isInternal ? ' (interno)' : ''));
    
    return $pdo->lastInsertId();
}

// =============================================
// FUNCIONES DE VALIDACIÓN
// =============================================

/**
 * Validar datos de ticket
 */
function validateTicketData($data) {
    $errors = [];
    
    if (empty($data['title']) || strlen($data['title']) < 5) {
        $errors[] = 'El título debe tener al menos 5 caracteres';
    }
    
    if (empty($data['description']) || strlen($data['description']) < 20) {
        $errors[] = 'La descripción debe tener al menos 20 caracteres';
    }
    
    $validCategories = ['hardware', 'software', 'red', 'acceso', 'otro'];
    if (!in_array($data['category'] ?? '', $validCategories)) {
        $errors[] = 'Categoría no válida';
    }
    
    $validPriorities = ['baja', 'media', 'alta', 'urgente'];
    if (!in_array($data['priority'] ?? '', $validPriorities)) {
        $errors[] = 'Prioridad no válida';
    }
    
    return $errors;
}

/**
 * Crear nuevo ticket con SLA configurado
 */
function createTicket($data, $userId = null) {
    global $pdo;
    
    // Validar datos
    $errors = validateTicketData($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Obtener configuración SLA
    $slaTargets = getSlaTargets($data['priority']);
    
    // Generar número de ticket
    $ticketNumber = generateTicketNumber();
    
    $stmt = $pdo->prepare("
        INSERT INTO tickets (
            ticket_number, user_id, user_name, user_email, user_department, user_phone,
            category, priority, title, description,
            sla_response_target, sla_resolution_target
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $ticketNumber,
        $userId,
        sanitize($data['user_name'] ?? ''),
        sanitize($data['user_email'] ?? ''),
        sanitize($data['user_department'] ?? ''),
        sanitize($data['user_phone'] ?? ''),
        $data['category'],
        $data['priority'],
        sanitize($data['title']),
        sanitize($data['description']),
        $slaTargets['first_response'],
        $slaTargets['resolution']
    ]);
    
    $ticketId = $pdo->lastInsertId();
    
    // Registrar en historial
    logTicketHistory($ticketId, 'created', null, 'abierto', 'Ticket creado');
    
    return [
        'success' => true,
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber
    ];
}

// =============================================
// FUNCIONES DE ENVIO DE CORREO
// =============================================

/**
 * Enviar correo electronico
 * @param string $to Destinatario
 * @param string $subject Asunto
 * @param string $htmlBody Cuerpo HTML
 * @param string $fromName Nombre del remitente (opcional)
 * @return bool
 */
function sendEmail($to, $subject, $htmlBody, $fromName = 'Empresa Portuaria Coquimbo Intranet') {
    $fromEmail = 'noreply@puertocoquimbo.cl';
    
    // Headers para correo HTML
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Intentar enviar el correo
    $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    
    // Log del intento
    error_log("Email " . ($sent ? "enviado" : "fallido") . " a: $to - Asunto: $subject");
    
    return $sent;
}

/**
 * Notificar nueva denuncia al Comite de Etica
 * @param array $complaint Datos de la denuncia
 * @param string $complaintNumber Numero de denuncia
 * @return int Cantidad de correos enviados
 */
function notifyNewComplaint($complaint, $complaintNumber) {
    global $pdo;
    
    // Obtener usuarios con rol admin y denuncia
    $stmt = $pdo->query("SELECT email, name FROM users WHERE role IN ('admin', 'denuncia') AND is_active = 1");
    $recipients = $stmt->fetchAll();
    
    if (empty($recipients)) {
        return 0;
    }
    
    $typeLabels = [
        'acoso_laboral' => 'Acoso Laboral',
        'acoso_sexual' => 'Acoso Sexual',
        'violencia_laboral' => 'Violencia Laboral',
        'discriminacion' => 'Discriminacion',
        'otro' => 'Otro'
    ];
    
    $typeLabel = $typeLabels[$complaint['type']] ?? $complaint['type'];
    $isAnonymous = $complaint['is_anonymous'] ? 'Si' : 'No';
    $incidentDate = date('d/m/Y', strtotime($complaint['incident_date']));
    
    $subject = "[URGENTE] Nueva Denuncia Ley Karin - $complaintNumber";
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #0a2540, #1e3a5f); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .footer { background: #0a2540; color: rgba(255,255,255,0.7); padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 12px 12px; }
            .alert { background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
            .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .info-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .info-table td:first-child { font-weight: bold; width: 40%; color: #64748b; }
            .btn { display: inline-block; background: #0a2540; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
            .badge-urgent { background: #dc2626; color: white; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin:0;font-size:24px;">Empresa Portuaria Coquimbo - Canal de Denuncias</h1>
                <p style="margin:10px 0 0;opacity:0.9;">Ley 21.643 (Ley Karin)</p>
            </div>
            <div class="content">
                <div class="alert">
                    <strong><span class="badge badge-urgent">NUEVA DENUNCIA</span></strong>
                    <p style="margin:10px 0 0;">Se ha registrado una nueva denuncia que requiere atencion inmediata.</p>
                </div>
                
                <table class="info-table">
                    <tr>
                        <td>Numero de Denuncia</td>
                        <td><strong>' . htmlspecialchars($complaintNumber) . '</strong></td>
                    </tr>
                    <tr>
                        <td>Tipo</td>
                        <td>' . htmlspecialchars($typeLabel) . '</td>
                    </tr>
                    <tr>
                        <td>Fecha del Incidente</td>
                        <td>' . $incidentDate . '</td>
                    </tr>
                    <tr>
                        <td>Denuncia Anonima</td>
                        <td>' . $isAnonymous . '</td>
                    </tr>
                    <tr>
                        <td>Persona Denunciada</td>
                        <td>' . htmlspecialchars($complaint['accused_name'] ?? 'No especificado') . '</td>
                    </tr>
                    <tr>
                        <td>Ubicacion</td>
                        <td>' . htmlspecialchars($complaint['location'] ?? 'No especificado') . '</td>
                    </tr>
                </table>
                
                <p><strong>Descripcion:</strong></p>
                <p style="background:#fff;padding:15px;border-radius:8px;border:1px solid #e2e8f0;">' . nl2br(htmlspecialchars(substr($complaint['description'], 0, 500))) . (strlen($complaint['description']) > 500 ? '...' : '') . '</p>
                
                <p style="color:#64748b;font-size:14px;margin-top:20px;">
                    <strong>Importante:</strong> Segun la Ley 21.643, debe iniciarse la investigacion dentro de los 3 dias habiles siguientes a la recepcion de la denuncia.
                </p>
                
                <center>
                    <a href="' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/denuncias_admin" class="btn">
                        Ver en Panel de Denuncias
                    </a>
                </center>
            </div>
            <div class="footer">
                <p>Este es un mensaje automatico del sistema Empresa Portuaria Coquimbo.<br>Por favor no responda a este correo.</p>
                <p>Fecha de notificacion: ' . date('d/m/Y H:i') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    $sent = 0;
    foreach ($recipients as $recipient) {
        if (sendEmail($recipient['email'], $subject, $htmlBody, 'Empresa Portuaria Coquimbo Denuncias')) {
            $sent++;
        }
    }
    
    return $sent;
}

// =============================================
// FUNCIONES DE GENERACIÓN DE NÚMEROS ÚNICOS
// =============================================

/**
 * Generar número de ticket único
 */
function generateTicketNumber() {
    return 'TK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/**
 * Generar número de denuncia único
 */
function generateComplaintNumber() {
    return 'DN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

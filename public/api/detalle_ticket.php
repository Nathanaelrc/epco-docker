<?php
/**
 * API para obtener detalle de ticket
 */
require_once '../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user = getCurrentUser();
$ticketId = (int)($_GET['id'] ?? 0);

if ($ticketId === 0) {
    echo json_encode(['error' => 'ID de ticket inválido']);
    exit;
}

// Obtener ticket (solo si pertenece al usuario o es admin/soporte)
$isSupport = in_array($user['role'], ['admin', 'soporte']);

if ($isSupport) {
    $stmt = $pdo->prepare('
        SELECT t.*, u.name as assigned_name 
        FROM tickets t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.id = ?
    ');
    $stmt->execute([$ticketId]);
} else {
    $stmt = $pdo->prepare('
        SELECT t.*, u.name as assigned_name 
        FROM tickets t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.id = ? AND t.user_id = ?
    ');
    $stmt->execute([$ticketId, $user['id']]);
}

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(['error' => 'Ticket no encontrado']);
    exit;
}

// Obtener comentarios (excluir internos si no es soporte)
if ($isSupport) {
    $stmt = $pdo->prepare('
        SELECT * FROM ticket_comments 
        WHERE ticket_id = ? 
        ORDER BY created_at ASC
    ');
    $stmt->execute([$ticketId]);
} else {
    $stmt = $pdo->prepare('
        SELECT * FROM ticket_comments 
        WHERE ticket_id = ? AND is_internal = 0
        ORDER BY created_at ASC
    ');
    $stmt->execute([$ticketId]);
}

$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar campos sensibles antes de devolver
$safeTicketFields = ['id', 'ticket_number', 'title', 'description', 'category', 'priority', 'status', 'created_at', 'updated_at', 'assigned_name'];
if ($isSupport) {
    $safeTicketFields = array_merge($safeTicketFields, ['user_name', 'user_email', 'assigned_to', 'resolution', 'sla_first_response', 'sla_resolution']);
}
$filteredTicket = array_intersect_key($ticket, array_flip($safeTicketFields));

$safeCommentFields = ['id', 'user_name', 'comment', 'created_at'];
$filteredComments = array_map(function($c) use ($safeCommentFields) {
    return array_intersect_key($c, array_flip($safeCommentFields));
}, $comments);

echo json_encode([
    'ticket' => $filteredTicket,
    'comments' => $filteredComments
]);

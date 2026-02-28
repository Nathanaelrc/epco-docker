<?php
/**
 * API pública para buscar ticket por número de seguimiento
 * No requiere autenticación - usado por el portal de soporte
 */
require_once '../../includes/bootstrap.php';

header('Content-Type: application/json');

$ticketNumber = sanitize($_GET['ticket_number'] ?? $_POST['ticket_number'] ?? '');

if (empty($ticketNumber)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un número de ticket']);
    exit;
}

// Buscar ticket
$stmt = $pdo->prepare('
    SELECT t.id, t.ticket_number, t.title, t.category, t.priority, t.status,
           t.description, t.resolution, t.created_at, t.updated_at,
           COALESCE(u.name, t.user_name) as user_name,
           a.name as assigned_name
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN users a ON t.assigned_to = a.id
    WHERE t.ticket_number = ?
');
$stmt->execute([$ticketNumber]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'No se encontró ningún ticket con ese número.']);
    exit;
}

// Obtener comentarios públicos (no internos)
$stmt = $pdo->prepare('
    SELECT tc.comment, tc.created_at, 
           COALESCE(u2.name, tc.user_name) as author_name
    FROM ticket_comments tc 
    LEFT JOIN users u2 ON tc.user_id = u2.id 
    WHERE tc.ticket_id = ? AND tc.is_internal = 0
    ORDER BY tc.created_at ASC
');
$stmt->execute([$ticket['id']]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No exponer el ID interno
unset($ticket['id']);

echo json_encode([
    'success' => true,
    'ticket' => $ticket,
    'comments' => $comments
]);

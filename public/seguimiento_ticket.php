<?php
/**
 * EPCO - Seguimiento de Tickets
 */
require_once '../includes/bootstrap.php';

// Detectar origen para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'panel_intranet.php' : 'soporte.php';
$backText = $fromIntranet ? 'Volver a Intranet' : 'Volver a Soporte TI';

$ticket = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ticket'])) {
    $ticketNumber = sanitize($_POST['ticket_number'] ?? $_GET['ticket'] ?? '');
    
    if (!empty($ticketNumber)) {
        $stmt = $pdo->prepare('
            SELECT t.*, 
                   COALESCE(u.name, t.user_name) as user_name, 
                   COALESCE(u.email, t.user_email) as user_email,
                   a.name as assigned_name
            FROM tickets t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.ticket_number = ?
        ');
        $stmt->execute([$ticketNumber]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            $error = 'No se encontró ningún ticket con ese número.';
        } else {
            // Obtener comentarios
            $stmt = $pdo->prepare('
                SELECT tc.*, COALESCE(u.name, tc.user_name) as author_name 
                FROM ticket_comments tc 
                LEFT JOIN users u ON tc.user_id = u.id 
                WHERE tc.ticket_id = ? AND tc.is_internal = 0
                ORDER BY tc.created_at ASC
            ');
            $stmt->execute([$ticket['id']]);
            $comments = $stmt->fetchAll();
        }
    }
}

$statusColors = [
    'abierto' => 'primary',
    'en_proceso' => 'warning',
    'pendiente' => 'info',
    'resuelto' => 'success',
    'cerrado' => 'secondary'
];

$statusLabels = [
    'abierto' => 'Abierto',
    'en_proceso' => 'En Proceso',
    'pendiente' => 'Pendiente',
    'resuelto' => 'Resuelto',
    'cerrado' => 'Cerrado'
];

$pageTitle = 'Seguimiento de Tickets';
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
    <title>Empresa Portuaria Coquimbo - Seguimiento de Tickets</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/seguimiento-ticket.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto03.webp" : "img/Puerto03.jpg" ?>') center/cover no-repeat fixed; }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Search Card -->
                <div class="search-card mb-4">
                    <div class="search-header">
                        <i class="bi bi-search text-white mb-2" style="font-size: 3rem;"></i>
                        <h2 class="text-white fw-bold mb-0">Seguimiento de Tickets</h2>
                    </div>
                    
                    <div class="p-4">
                        <form method="POST" action="" class="d-flex gap-3">
                            <input type="text" name="ticket_number" class="form-control" placeholder="Ingresa tu número de ticket (ej: TK-20260117-XXXXX)" value="<?= htmlspecialchars($_POST['ticket_number'] ?? $_GET['ticket'] ?? '') ?>">
                            <button type="submit" class="btn btn-search text-white px-4">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger rounded-4">
                    <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                </div>
                <?php endif; ?>
                
                <?php if ($ticket): ?>
                <!-- Ticket Info -->
                <div class="ticket-card fade-in">
                    <div class="p-4 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-<?= $statusColors[$ticket['status']] ?> mb-2"><?= $statusLabels[$ticket['status']] ?></span>
                                <h4 class="fw-bold mb-1"><?= htmlspecialchars($ticket['title']) ?></h4>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-ticket me-1"></i><?= $ticket['ticket_number'] ?>
                                    <span class="mx-2">·</span>
                                    <i class="bi bi-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                </p>
                            </div>
                            <span class="badge bg-<?= $ticket['priority'] === 'urgente' ? 'danger' : ($ticket['priority'] === 'alta' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($ticket['priority']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Categoría</p>
                                <p class="fw-semibold mb-0"><?= ucfirst($ticket['category']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Asignado a</p>
                                <p class="fw-semibold mb-0"><?= $ticket['assigned_name'] ?? 'Sin asignar' ?></p>
                            </div>
                            <div class="col-12">
                                <p class="text-muted small mb-1">Descripción</p>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                            </div>
                            
                            <?php if ($ticket['resolution']): ?>
                            <div class="col-12">
                                <div class="bg-success bg-opacity-10 rounded-4 p-3">
                                    <p class="text-success small mb-1"><i class="bi bi-check-circle me-1"></i>Resolución</p>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['resolution'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($comments)): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2"></i>Historial de Comentarios</h6>
                        <div class="timeline">
                            <?php foreach ($comments as $comment): ?>
                            <div class="timeline-item">
                                <p class="fw-semibold mb-1"><?= htmlspecialchars($comment['author_name']) ?></p>
                                <p class="text-muted small mb-2"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></p>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="<?php echo $backUrl; ?>" class="text-white-50 text-decoration-none">
                        <i class="bi bi-arrow-left me-2"></i><?php echo $backText; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>

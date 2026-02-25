<?php
/**
 * EPCO - Seguimiento de Tickets
 */
require_once '../includes/bootstrap.php';

// Detectar origen para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'intranet_dashboard.php' : 'soporte.php';
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
    <title>EPCO - Seguimiento de Tickets</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * { font-family: 'Barlow', sans-serif; }
        body {
            background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%),
                        url('img/Puerto03.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }
        .search-card, .ticket-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4), 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .search-header {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 30px;
            text-align: center;
        }
        .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
        }
        .form-control:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(10,37,64,0.1);
        }
        .btn-search {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            padding-bottom: 20px;
            border-left: 2px solid #e5e7eb;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0ea5e9;
        }
        .timeline-item:last-child {
            border-left: none;
        }
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        gsap.from('.search-card', { y: 50, opacity: 0, duration: 0.8, ease: 'power3.out' });
        gsap.from('.fade-in', { y: 30, opacity: 0, duration: 0.6, delay: 0.3, ease: 'power2.out' });
    </script>
</body>
</html>

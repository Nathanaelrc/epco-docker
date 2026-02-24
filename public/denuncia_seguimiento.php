<?php
/**
 * EPCO - Seguimiento de Denuncias
 */
require_once '../includes/bootstrap.php';

// Detectar origen para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'denuncias.php?from=intranet' : 'denuncias.php';
$backText = 'Volver al Canal de Denuncias';

$complaint = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['complaint'])) {
    $complaintNumber = sanitize($_POST['complaint_number'] ?? $_GET['complaint'] ?? '');
    
    if (!empty($complaintNumber)) {
        $stmt = $pdo->prepare('SELECT * FROM complaints WHERE complaint_number = ?');
        $stmt->execute([$complaintNumber]);
        $complaint = $stmt->fetch();
        
        if (!$complaint) {
            $error = 'No se encontró ninguna denuncia con ese número.';
        } else {
            // Obtener logs
            $stmt = $pdo->prepare('
                SELECT * FROM complaint_logs 
                WHERE complaint_id = ? AND is_confidential = 0
                ORDER BY created_at ASC
            ');
            $stmt->execute([$complaint['id']]);
            $logs = $stmt->fetchAll();
        }
    }
}

$statusColors = [
    'recibida' => 'primary',
    'en_investigacion' => 'warning',
    'resolucion' => 'info',
    'cerrada' => 'success',
    'archivada' => 'secondary'
];

$statusLabels = [
    'recibida' => 'Recibida',
    'en_investigacion' => 'En Investigación',
    'resolucion' => 'En Resolución',
    'cerrada' => 'Cerrada',
    'archivada' => 'Archivada'
];

$typeLabels = [
    'acoso_laboral' => 'Acoso Laboral',
    'acoso_sexual' => 'Acoso Sexual',
    'violencia_laboral' => 'Violencia Laboral',
    'discriminacion' => 'Discriminación',
    'otro' => 'Otro'
];

$pageTitle = 'Seguimiento de Denuncias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Seguimiento de Denuncias</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * { font-family: 'Barlow', sans-serif; }
        body {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0ea5e9 100%);
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
        .search-card, .complaint-card {
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
                        <h2 class="text-white fw-bold mb-0">Seguimiento de Denuncias</h2>
                    </div>
                    
                    <div class="p-4">
                        <form method="POST" action="" class="d-flex gap-3">
                            <input type="text" name="complaint_number" class="form-control" placeholder="Ingresa tu número de denuncia (ej: DN-20260117-XXXXX)" value="<?= htmlspecialchars($_POST['complaint_number'] ?? $_GET['complaint'] ?? '') ?>">
                            <button type="submit" class="btn text-white px-4" style="background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 12px;">
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
                
                <?php if ($complaint): ?>
                <!-- Complaint Info -->
                <div class="complaint-card fade-in">
                    <div class="p-4 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-<?= $statusColors[$complaint['status']] ?> mb-2"><?= $statusLabels[$complaint['status']] ?></span>
                                <h4 class="fw-bold mb-1"><?= $typeLabels[$complaint['complaint_type']] ?></h4>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-file-earmark me-1"></i><?= $complaint['complaint_number'] ?>
                                    <span class="mx-2">·</span>
                                    <i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($complaint['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Fecha del Incidente</p>
                                <p class="fw-semibold mb-0"><?= date('d/m/Y', strtotime($complaint['incident_date'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Lugar</p>
                                <p class="fw-semibold mb-0"><?= htmlspecialchars($complaint['incident_location'] ?? 'No especificado') ?></p>
                            </div>
                        </div>
                        
                        <?php if ($complaint['resolution']): ?>
                        <div class="mt-4">
                            <div class="bg-success bg-opacity-10 rounded-4 p-3">
                                <p class="text-success small mb-1"><i class="bi bi-check-circle me-1"></i>Resolución</p>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($complaint['resolution'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($logs)): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-4"><i class="bi bi-clock-history me-2"></i>Historial del Proceso</h6>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <p class="fw-semibold mb-1"><?= htmlspecialchars($log['action']) ?></p>
                                <p class="text-muted small mb-2"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></p>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($log['description'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="bg-light rounded-4 p-4 mt-4">
                            <p class="mb-0 small text-muted">
                                <i class="bi bi-shield-lock me-2"></i>
                                <strong>Confidencialidad:</strong> Toda la información de esta denuncia es tratada de forma confidencial según lo establecido en la Ley 21.643.
                            </p>
                        </div>
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

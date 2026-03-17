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
    
    <!-- Preconnect CDNs -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <title>Empresa Portuaria Coquimbo - Seguimiento de Denuncias</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/denuncia-seguimiento.css" rel="stylesheet">
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
                        <h2 class="text-white fw-bold mb-0">Seguimiento de Denuncias</h2>
                    </div>
                    
                    <div class="p-4">
                        <form method="POST" action="" class="d-flex gap-3">
            <?= csrfInput() ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>

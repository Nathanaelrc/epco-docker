<?php
/**
 * EPCO - Panel de Administración de Denuncias Ley Karin
 * Acceso exclusivo para usuarios con rol 'denuncia' y 'admin'
 */
require_once '../includes/bootstrap.php';

// Verificar autenticación
requireAuth('iniciar_sesion.php?redirect=denuncias_admin.php');

$user = getCurrentUser();

// Solo admin y denuncia pueden acceder
if (!in_array($user['role'], ['admin', 'denuncia'])) {
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
        <link href="css/denuncias-admin.css?v=2" rel="stylesheet">
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="bi bi-shield-x"></i></div>
            <h2 class="fw-bold mb-3">Acceso Restringido</h2>
            <p class="text-muted mb-4">No tienes permisos para acceder al Panel de Denuncias. Esta área es confidencial y exclusiva para el Comité de Ética.</p>
            <a href="index.php" class="btn btn-dark btn-lg px-5"><i class="bi bi-house me-2"></i>Volver al Inicio</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$isAdmin = $user['role'] === 'admin';
$page = $_GET['page'] ?? 'dashboard';
$filter = $_GET['filter'] ?? 'all';

// Procesar mensajes
$message = '';
$messageType = '';

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'status_updated':
            $message = 'Estado de denuncia actualizado';
            $messageType = 'success';
            break;
        case 'assigned':
            $message = 'Investigador asignado correctamente';
            $messageType = 'success';
            break;
        case 'updated':
            $message = 'Denuncia actualizada correctamente';
            $messageType = 'success';
            break;
        case 'deleted':
            $message = 'Denuncia eliminada correctamente';
            $messageType = 'success';
            break;
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforcePostCsrf();
    $action = $_POST['action'] ?? '';
    
    // Cambiar estado de denuncia
    if ($action === 'change_status') {
        $complaintId = (int)$_POST['complaint_id'];
        $newStatus = sanitize($_POST['new_status']);
        $resolution = sanitize($_POST['resolution'] ?? '');
        
        $stmt = $pdo->prepare('UPDATE complaints SET status = ?, resolution = ? WHERE id = ?');
        $stmt->execute([$newStatus, $resolution, $complaintId]);
        
        // Registrar en historial
        $stmt = $pdo->prepare('INSERT INTO complaint_logs (complaint_id, action, description, user_id, is_confidential) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$complaintId, 'status_change', "Estado cambiado a: $newStatus", $user['id']]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=denuncias&filter=all&msg=status_updated");
        exit;
    }
    
    // Asignar investigador
    if ($action === 'assign_investigator') {
        $complaintId = (int)$_POST['complaint_id'];
        $investigatorId = (int)$_POST['investigator_id'];
        
        $stmt = $pdo->prepare('UPDATE complaints SET investigator_id = ?, status = "en_investigacion" WHERE id = ?');
        $stmt->execute([$investigatorId ?: null, $complaintId]);
        
        // Registrar en historial
        $stmt = $pdo->prepare('INSERT INTO complaint_logs (complaint_id, action, description, user_id, is_confidential) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$complaintId, 'assigned', "Investigador asignado", $user['id']]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=denuncias&filter=all&msg=assigned");
        exit;
    }
    
    // Agregar nota confidencial
    if ($action === 'add_note') {
        $complaintId = (int)$_POST['complaint_id'];
        $note = sanitize($_POST['note']);
        
        $stmt = $pdo->prepare('INSERT INTO complaint_logs (complaint_id, action, description, user_id, is_confidential) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$complaintId, 'note', $note, $user['id']]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=detalle&id=$complaintId");
        exit;
    }
    
    // Editar denuncia
    if ($action === 'edit_complaint') {
        $complaintId = (int)$_POST['complaint_id'];
        $complaintType = sanitize($_POST['complaint_type']);
        $description = sanitize($_POST['description']);
        $incidentDate = !empty($_POST['incident_date']) ? $_POST['incident_date'] : null;
        $incidentLocation = sanitize($_POST['incident_location'] ?? '');
        $accusedName = sanitize($_POST['accused_name'] ?? '');
        $accusedPosition = sanitize($_POST['accused_position'] ?? '');
        $accusedDepartment = sanitize($_POST['accused_department'] ?? '');
        $witnesses = sanitize($_POST['witnesses'] ?? '');
        $evidenceDescription = sanitize($_POST['evidence_description'] ?? '');
        
        $stmt = $pdo->prepare('
            UPDATE complaints SET 
                complaint_type = ?, description = ?, incident_date = ?, incident_location = ?,
                accused_name = ?, accused_position = ?, accused_department = ?,
                witnesses = ?, evidence_description = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([
            $complaintType, $description, $incidentDate, $incidentLocation,
            $accusedName, $accusedPosition, $accusedDepartment,
            $witnesses, $evidenceDescription, $complaintId
        ]);
        
        // Registrar en historial
        $stmt = $pdo->prepare('INSERT INTO complaint_logs (complaint_id, action, description, user_id, is_confidential) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$complaintId, 'edited', 'Denuncia editada por ' . $user['name'], $user['id']]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=detalle&id=$complaintId&msg=updated");
        exit;
    }
    
    // Eliminar denuncia
    if ($action === 'delete_complaint') {
        $complaintId = (int)$_POST['complaint_id'];
        
        // Primero eliminar logs relacionados
        $stmt = $pdo->prepare('DELETE FROM complaint_logs WHERE complaint_id = ?');
        $stmt->execute([$complaintId]);
        
        // Luego eliminar la denuncia
        $stmt = $pdo->prepare('DELETE FROM complaints WHERE id = ?');
        $stmt->execute([$complaintId]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=denuncias&filter=all&msg=deleted");
        exit;
    }
}

// Estadísticas
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as recibidas,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN status = 'archivada' THEN 1 ELSE 0 END) as archivadas
    FROM complaints
")->fetch();

// Filtrar denuncias
$where = '';
if ($filter === 'recibida') $where = "WHERE status = 'recibida'";
elseif ($filter === 'en_investigacion') $where = "WHERE status = 'en_investigacion'";
elseif ($filter === 'resuelta') $where = "WHERE status = 'resuelta'";
elseif ($filter === 'archivada') $where = "WHERE status = 'archivada'";

$complaints = $pdo->query("
    SELECT c.*, u.name as investigator_name
    FROM complaints c
    LEFT JOIN users u ON c.investigator_id = u.id
    $where
    ORDER BY 
        CASE c.status WHEN 'recibida' THEN 1 WHEN 'en_investigacion' THEN 2 ELSE 3 END,
        c.created_at DESC
")->fetchAll();

// Investigadores disponibles (admin y denuncia)
$investigators = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin', 'denuncia') AND is_active = 1")->fetchAll();

// Estadísticas por tipo
$typeStats = $pdo->query("SELECT complaint_type, COUNT(*) as count FROM complaints GROUP BY complaint_type")->fetchAll();

// Labels
$statusColors = ['recibida' => 'warning', 'en_investigacion' => 'info', 'resuelta' => 'success', 'archivada' => 'secondary'];
$statusLabels = ['recibida' => 'Recibida', 'en_investigacion' => 'En Investigación', 'resuelta' => 'Resuelta', 'archivada' => 'Archivada'];
$typeLabels = [
    'acoso_laboral' => 'Acoso Laboral',
    'acoso_sexual' => 'Acoso Sexual',
    'violencia_laboral' => 'Violencia Laboral',
    'discriminacion' => 'Discriminación',
    'otro' => 'Otro'
];
$typeColors = [
    'acoso_laboral' => '#f59e0b',
    'acoso_sexual' => '#ef4444',
    'violencia_laboral' => '#dc2626',
    'discriminacion' => '#8b5cf6',
    'otro' => '#64748b'
];

// Detalle de denuncia
$complaint = null;
$complaintLogs = [];
if (in_array($page, ['detalle', 'editar']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as investigator_name
        FROM complaints c
        LEFT JOIN users u ON c.investigator_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([(int)$_GET['id']]);
    $complaint = $stmt->fetch();
    
    if ($complaint) {
        $stmt = $pdo->prepare("
            SELECT cl.*, u.name as user_name
            FROM complaint_logs cl
            LEFT JOIN users u ON cl.user_id = u.id
            WHERE cl.complaint_id = ?
            ORDER BY cl.created_at DESC
        ");
        $stmt->execute([$complaint['id']]);
        $complaintLogs = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Panel de Denuncias</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/denuncias-admin.css?v=2" rel="stylesheet">
    
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral_denuncias.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-title">
                <h1>
                    <?php if ($page === 'dashboard'): ?>Dashboard
                    <?php elseif ($page === 'denuncias'): ?>Gestión de Denuncias
                    <?php elseif ($page === 'detalle'): ?>Detalle de Denuncia
                    <?php else: ?>Panel de Denuncias<?php endif; ?>
                </h1>
                <p><i class="bi bi-shield-lock me-1"></i>Información Confidencial - Ley Karin</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="confidential-badge"><i class="bi bi-lock me-1"></i>CONFIDENCIAL</span>
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> py-2 px-3 mb-0 small"><?= $message ?></div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content-area">
            <?php if ($page === 'dashboard'): ?>
            <!-- ========== DASHBOARD ========== -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Denuncias</div></div>
                            <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card urgent">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value"><?= $stats['recibidas'] ?></div><div class="stat-label">Pendientes</div></div>
                            <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid #3b82f6;">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value" style="color:#3b82f6"><?= $stats['en_investigacion'] ?></div><div class="stat-label">En Investigación</div></div>
                            <div class="stat-icon" style="background:#dbeafe;color:#3b82f6"><i class="bi bi-search"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card" style="border-left: 3px solid #059669;">
                        <div class="d-flex justify-content-between">
                            <div><div class="stat-value" style="color:#059669"><?= $stats['resueltas'] ?></div><div class="stat-label">Resueltas</div></div>
                            <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="mb-3">Por Tipo de Denuncia</h5>
                        <div class="chart-container">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="mb-3">Por Estado</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Denuncias recientes -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-clock-history me-2"></i>Denuncias Recientes</h5>
                    <a href="?page=denuncias&filter=all" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Código</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Investigador</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($complaints, 0, 5) as $c): ?>
                        <tr>
                            <td><span class="complaint-number"><?= $c['complaint_number'] ?></span></td>
                            <td><span class="badge" style="background: <?= $typeColors[$c['complaint_type']] ?? '#64748b' ?>;"><?= $typeLabels[$c['complaint_type']] ?? $c['complaint_type'] ?></span></td>
                            <td><span class="badge bg-<?= $statusColors[$c['status']] ?>"><?= $statusLabels[$c['status']] ?></span></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            <td class="small"><?= $c['investigator_name'] ?? '<span class="text-muted">Sin asignar</span>' ?></td>
                            <td><a href="?page=detalle&id=<?= $c['id'] ?>" class="btn-action btn btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No hay denuncias registradas</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
            new Chart(document.getElementById('typeChart'), {
                type: 'doughnut',
                data: {
                    labels: [<?= implode(',', array_map(function($t) use ($typeLabels) { return "'" . ($typeLabels[$t['complaint_type']] ?? $t['complaint_type']) . "'"; }, $typeStats)) ?>],
                    datasets: [{
                        data: [<?= implode(',', array_column($typeStats, 'count')) ?>],
                        backgroundColor: [<?= implode(',', array_map(function($t) use ($typeColors) { return "'" . ($typeColors[$t['complaint_type']] ?? '#64748b') . "'"; }, $typeStats)) ?>],
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
            
            new Chart(document.getElementById('statusChart'), {
                type: 'bar',
                data: {
                    labels: ['Recibidas', 'En Investigación', 'Resueltas', 'Archivadas'],
                    datasets: [{
                        data: [<?= $stats['recibidas'] ?>, <?= $stats['en_investigacion'] ?>, <?= $stats['resueltas'] ?>, <?= $stats['archivadas'] ?>],
                        backgroundColor: ['#f59e0b', '#3b82f6', '#22c55e', '#64748b'],
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            </script>
            
            <?php elseif ($page === 'denuncias'): ?>
            <!-- ========== LISTA DE DENUNCIAS ========== -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-list-ul me-2"></i>Denuncias</h5>
                    <div class="filter-tabs">
                        <a href="?page=denuncias&filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Todas</a>
                        <a href="?page=denuncias&filter=recibida" class="filter-tab <?= $filter === 'recibida' ? 'active' : '' ?>">Recibidas</a>
                        <a href="?page=denuncias&filter=en_investigacion" class="filter-tab <?= $filter === 'en_investigacion' ? 'active' : '' ?>">En Investigación</a>
                        <a href="?page=denuncias&filter=resuelta" class="filter-tab <?= $filter === 'resuelta' ? 'active' : '' ?>">Resueltas</a>
                        <a href="?page=denuncias&filter=archivada" class="filter-tab <?= $filter === 'archivada' ? 'active' : '' ?>">Archivadas</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Tipo</th>
                                <th>Anónima</th>
                                <th>Fecha Incidente</th>
                                <th>Estado</th>
                                <th>Investigador</th>
                                <th>Creada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2.5rem"></i><p class="mt-2 mb-0">No hay denuncias</p></td></tr>
                        <?php else: foreach ($complaints as $c): ?>
                        <tr>
                            <td><span class="complaint-number"><?= $c['complaint_number'] ?></span></td>
                            <td><span class="badge" style="background: <?= $typeColors[$c['complaint_type']] ?? '#64748b' ?>;"><?= $typeLabels[$c['complaint_type']] ?? $c['complaint_type'] ?></span></td>
                            <td><?= $c['is_anonymous'] ? '<i class="bi bi-incognito text-muted"></i> Sí' : '<i class="bi bi-person"></i> No' ?></td>
                            <td class="small"><?= $c['incident_date'] ? date('d/m/Y', strtotime($c['incident_date'])) : '-' ?></td>
                            <td><span class="badge bg-<?= $statusColors[$c['status']] ?>"><?= $statusLabels[$c['status']] ?></span></td>
                            <td class="small"><?= $c['investigator_name'] ?? '<span class="text-warning">Sin asignar</span>' ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?page=detalle&id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                    <a href="?page=editar&id=<?= $c['id'] ?>" class="btn btn-outline-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="confirmDelete(<?= $c['id'] ?>, '<?= $c['complaint_number'] ?>')"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($page === 'detalle' && $complaint): ?>
            <!-- ========== DETALLE DE DENUNCIA ========== -->
            <div class="mb-3">
                <a href="?page=denuncias&filter=all" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- Info principal -->
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <div>
                                <h5 class="card-title-custom mb-1"><?= $complaint['complaint_number'] ?></h5>
                                <span class="badge" style="background: <?= $typeColors[$complaint['complaint_type']] ?? '#64748b' ?>;"><?= $typeLabels[$complaint['complaint_type']] ?? $complaint['complaint_type'] ?></span>
                                <span class="badge bg-<?= $statusColors[$complaint['status']] ?> ms-1"><?= $statusLabels[$complaint['status']] ?></span>
                            </div>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($complaint['created_at'])) ?></small>
                        </div>
                        <div class="p-4">
                            <div class="detail-section">
                                <div class="detail-label">Descripción del incidente</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($complaint['description'])) ?></div>
                            </div>
                            
                            <?php if ($complaint['incident_date'] || $complaint['incident_location']): ?>
                            <div class="row g-3 mb-3">
                                <?php if ($complaint['incident_date']): ?>
                                <div class="col-md-6">
                                    <div class="detail-section mb-0">
                                        <div class="detail-label">Fecha del incidente</div>
                                        <div class="detail-value"><?= date('d/m/Y', strtotime($complaint['incident_date'])) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($complaint['incident_location']): ?>
                                <div class="col-md-6">
                                    <div class="detail-section mb-0">
                                        <div class="detail-label">Lugar</div>
                                        <div class="detail-value"><?= htmlspecialchars($complaint['incident_location']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($complaint['accused_name']): ?>
                            <div class="detail-section">
                                <div class="detail-label">Persona denunciada</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($complaint['accused_name']) ?>
                                    <?php if ($complaint['accused_position']): ?> - <?= htmlspecialchars($complaint['accused_position']) ?><?php endif; ?>
                                    <?php if ($complaint['accused_department']): ?> (<?= htmlspecialchars($complaint['accused_department']) ?>)<?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($complaint['witnesses']): ?>
                            <div class="detail-section">
                                <div class="detail-label">Testigos</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($complaint['witnesses'])) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($complaint['evidence_description']): ?>
                            <div class="detail-section">
                                <div class="detail-label">Evidencia</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($complaint['evidence_description'])) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($complaint['resolution']): ?>
                            <div class="detail-section" style="background: #dcfce7; border: 1px solid #22c55e;">
                                <div class="detail-label text-success">Resolución</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($complaint['resolution'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Denunciante -->
                    <?php if (!$complaint['is_anonymous']): ?>
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <h6 class="card-title-custom"><i class="bi bi-person me-2"></i>Datos del Denunciante</h6>
                        </div>
                        <div class="p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="detail-label">Nombre</div>
                                    <div class="detail-value"><?= htmlspecialchars($complaint['reporter_name'] ?? 'No proporcionado') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Email</div>
                                    <div class="detail-value"><?= htmlspecialchars($complaint['reporter_email'] ?? 'No proporcionado') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Teléfono</div>
                                    <div class="detail-value"><?= htmlspecialchars($complaint['reporter_phone'] ?? 'No proporcionado') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Departamento</div>
                                    <div class="detail-value"><?= htmlspecialchars($complaint['reporter_department'] ?? 'No proporcionado') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-secondary">
                        <i class="bi bi-incognito me-2"></i>Esta denuncia fue realizada de forma <strong>anónima</strong>.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Historial -->
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h6 class="card-title-custom"><i class="bi bi-clock-history me-2"></i>Historial de Actividad</h6>
                        </div>
                        <div class="p-4">
                            <?php if (empty($complaintLogs)): ?>
                            <p class="text-muted text-center">Sin actividad registrada</p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($complaintLogs as $log): ?>
                                <div class="timeline-item">
                                    <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> - <?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></div>
                                    <div class="fw-semibold"><?= ucfirst(str_replace('_', ' ', $log['action'])) ?></div>
                                    <?php if ($log['description']): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($log['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Agregar nota -->
                            <form method="POST" class="mt-4 pt-3 border-top">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="add_note">
                                <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                                <div class="mb-2">
                                    <textarea name="note" class="form-control" rows="2" placeholder="Agregar nota confidencial..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus me-1"></i>Agregar Nota</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Panel lateral -->
                <div class="col-lg-4">
                    <!-- Acciones -->
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <h6 class="card-title-custom"><i class="bi bi-gear me-2"></i>Acciones</h6>
                        </div>
                        <div class="p-4">
                            <!-- Asignar investigador -->
                            <form method="POST" class="mb-4">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="assign_investigator">
                                <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                                <label class="form-label small fw-semibold">Investigador Asignado</label>
                                <select name="investigator_id" class="form-select form-select-sm mb-2">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($investigators as $inv): ?>
                                    <option value="<?= $inv['id'] ?>" <?= $complaint['investigator_id'] == $inv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($inv['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Asignar</button>
                            </form>
                            
                            <!-- Cambiar estado -->
                            <form method="POST">
            <?= csrfInput() ?>
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                                <label class="form-label small fw-semibold">Estado</label>
                                <select name="new_status" class="form-select form-select-sm mb-2">
                                    <option value="recibida" <?= $complaint['status'] === 'recibida' ? 'selected' : '' ?>>Recibida</option>
                                    <option value="en_investigacion" <?= $complaint['status'] === 'en_investigacion' ? 'selected' : '' ?>>En Investigación</option>
                                    <option value="resuelta" <?= $complaint['status'] === 'resuelta' ? 'selected' : '' ?>>Resuelta</option>
                                    <option value="archivada" <?= $complaint['status'] === 'archivada' ? 'selected' : '' ?>>Archivada</option>
                                </select>
                                <textarea name="resolution" class="form-control form-control-sm mb-2" rows="3" placeholder="Resolución (requerido para cerrar)"><?= htmlspecialchars($complaint['resolution'] ?? '') ?></textarea>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Actualizar Estado</button>
                            </form>
                            
                            <hr class="my-3">
                            
                            <!-- Botones Editar/Eliminar -->
                            <div class="d-grid gap-2">
                                <a href="?page=editar&id=<?= $complaint['id'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-pencil me-1"></i>Editar Denuncia
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $complaint['id'] ?>, '<?= $complaint['complaint_number'] ?>')">
                                    <i class="bi bi-trash me-1"></i>Eliminar Denuncia
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info rápida -->
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h6 class="card-title-custom"><i class="bi bi-info-circle me-2"></i>Información</h6>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <div class="detail-label">Código</div>
                                <div class="detail-value"><?= $complaint['complaint_number'] ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-label">Tipo</div>
                                <div class="detail-value"><?= $typeLabels[$complaint['complaint_type']] ?? $complaint['complaint_type'] ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="detail-label">Creada</div>
                                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($complaint['created_at'])) ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Última actualización</div>
                                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($complaint['updated_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($page === 'editar' && $complaint): ?>
            <!-- ========== EDITAR DENUNCIA ========== -->
            <div class="mb-3">
                <a href="?page=detalle&id=<?= $complaint['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver al Detalle</a>
            </div>
            
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom"><i class="bi bi-pencil me-2"></i>Editar Denuncia <?= $complaint['complaint_number'] ?></h5>
                </div>
                <div class="p-4">
                    <form method="POST">
            <?= csrfInput() ?>
                        <input type="hidden" name="action" value="edit_complaint">
                        <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tipo de Denuncia</label>
                                <select name="complaint_type" class="form-select" required>
                                    <option value="acoso_laboral" <?= $complaint['complaint_type'] === 'acoso_laboral' ? 'selected' : '' ?>>Acoso Laboral</option>
                                    <option value="acoso_sexual" <?= $complaint['complaint_type'] === 'acoso_sexual' ? 'selected' : '' ?>>Acoso Sexual</option>
                                    <option value="violencia_laboral" <?= $complaint['complaint_type'] === 'violencia_laboral' ? 'selected' : '' ?>>Violencia Laboral</option>
                                    <option value="discriminacion" <?= $complaint['complaint_type'] === 'discriminacion' ? 'selected' : '' ?>>Discriminacion</option>
                                    <option value="otro" <?= $complaint['complaint_type'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha del Incidente</label>
                                <input type="date" name="incident_date" class="form-control" value="<?= $complaint['incident_date'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Descripcion del Incidente *</label>
                                <textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($complaint['description']) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Lugar del Incidente</label>
                                <input type="text" name="incident_location" class="form-control" value="<?= htmlspecialchars($complaint['incident_location'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mt-4 mb-3 text-muted">Persona Denunciada</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="accused_name" class="form-control" value="<?= htmlspecialchars($complaint['accused_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cargo</label>
                                <input type="text" name="accused_position" class="form-control" value="<?= htmlspecialchars($complaint['accused_position'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Departamento</label>
                                <input type="text" name="accused_department" class="form-control" value="<?= htmlspecialchars($complaint['accused_department'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mt-4 mb-3 text-muted">Informacion Adicional</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Testigos</label>
                                <textarea name="witnesses" class="form-control" rows="3"><?= htmlspecialchars($complaint['witnesses'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Descripcion de Evidencia</label>
                                <textarea name="evidence_description" class="form-control" rows="3"><?= htmlspecialchars($complaint['evidence_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
                            <a href="?page=detalle&id=<?= $complaint['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>Denuncia no encontrada.
                <a href="?page=denuncias&filter=all">Volver a la lista</a>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Modal Confirmar Eliminacion -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminacion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Esta seguro de eliminar la denuncia <strong id="deleteNumber"></strong>?</p>
                    <p class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Esta accion no se puede deshacer. Se eliminara toda la informacion y el historial asociado.</p>
                </div>
                <div class="modal-footer border-0">
                    <form method="POST" id="deleteForm">
            <?= csrfInput() ?>
                        <input type="hidden" name="action" value="delete_complaint">
                        <input type="hidden" name="complaint_id" id="deleteId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete(id, number) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteNumber').textContent = number;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    </script>
</body>
</html>

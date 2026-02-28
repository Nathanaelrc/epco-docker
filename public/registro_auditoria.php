<?php
/**
 * EPCO - Panel de Logs de Auditoría
 * Solo accesible para administradores
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
if (!$user || $user['role'] !== 'admin') {
    header('Location: iniciar_sesion.php');
    exit;
}

// Filtros
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterEntity = $_GET['entity'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Construir consulta
$where = '1=1';
$params = [];

if (!empty($filterUser)) {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}

if (!empty($filterAction)) {
    $where .= ' AND al.action = ?';
    $params[] = $filterAction;
}

if (!empty($filterEntity)) {
    $where .= ' AND al.entity_type = ?';
    $params[] = $filterEntity;
}

if (!empty($filterDateFrom)) {
    $where .= ' AND DATE(al.created_at) >= ?';
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $where .= ' AND DATE(al.created_at) <= ?';
    $params[] = $filterDateTo;
}

// Contar total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE $where
");
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Obtener logs
$stmt = $pdo->prepare("
    SELECT al.*, u.name as user_name, u.email as user_email 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE $where 
    ORDER BY al.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener acciones únicas para filtro
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener entidades únicas para filtro
$entitiesStmt = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type");
$entities = $entitiesStmt->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
        COUNT(CASE WHEN created_at >= NOW() - INTERVAL 7 DAY THEN 1 END) as week
    FROM activity_logs
");
$stats = $statsStmt->fetch();

// Acciones por día (últimos 7 días)
$chartStmt = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= NOW() - INTERVAL 7 DAY 
    GROUP BY DATE(created_at) 
    ORDER BY date
");
$chartData = $chartStmt->fetchAll();

// Iconos y colores por tipo de acción
$actionMeta = [
    'login' => ['icon' => 'box-arrow-in-right', 'color' => 'success', 'label' => 'Inicio sesión'],
    'logout' => ['icon' => 'box-arrow-right', 'color' => 'secondary', 'label' => 'Cierre sesión'],
    'login_failed' => ['icon' => 'x-circle', 'color' => 'danger', 'label' => 'Login fallido'],
    'ticket_created' => ['icon' => 'ticket-perforated', 'color' => 'primary', 'label' => 'Ticket creado'],
    'ticket_updated' => ['icon' => 'pencil', 'color' => 'info', 'label' => 'Ticket actualizado'],
    'ticket_closed' => ['icon' => 'check-circle', 'color' => 'success', 'label' => 'Ticket cerrado'],
    'ticket_assigned' => ['icon' => 'person-check', 'color' => 'info', 'label' => 'Ticket asignado'],
    'user_created' => ['icon' => 'person-plus', 'color' => 'success', 'label' => 'Usuario creado'],
    'user_updated' => ['icon' => 'person-gear', 'color' => 'info', 'label' => 'Usuario actualizado'],
    'user_deleted' => ['icon' => 'person-x', 'color' => 'danger', 'label' => 'Usuario eliminado'],
    'password_changed' => ['icon' => 'key', 'color' => 'warning', 'label' => 'Contraseña cambiada'],
    'password_reset' => ['icon' => 'arrow-clockwise', 'color' => 'warning', 'label' => 'Contraseña reseteada'],
    'document_uploaded' => ['icon' => 'cloud-upload', 'color' => 'success', 'label' => 'Documento subido'],
    'document_downloaded' => ['icon' => 'cloud-download', 'color' => 'info', 'label' => 'Documento descargado'],
    'document_deleted' => ['icon' => 'trash', 'color' => 'danger', 'label' => 'Documento eliminado'],
    'news_created' => ['icon' => 'newspaper', 'color' => 'success', 'label' => 'Noticia creada'],
    'news_updated' => ['icon' => 'pencil-square', 'color' => 'info', 'label' => 'Noticia actualizada'],
    'kb_article_created' => ['icon' => 'book', 'color' => 'success', 'label' => 'Artículo KB creado'],
    'event_created' => ['icon' => 'calendar-plus', 'color' => 'success', 'label' => 'Evento creado'],
    'profile_updated' => ['icon' => 'person', 'color' => 'info', 'label' => 'Perfil actualizado'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Logs de Auditoría</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { background: #f1f5f9; min-height: 100vh; }
        .navbar-epco { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .stat-card {
            padding: 20px;
            border-radius: 16px;
            background: white;
        }
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .log-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.2s;
        }
        .log-item:hover {
            background: #f8f9fa;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
        }
        .pagination .page-item.active .page-link {
            background: var(--primary);
        }
    </style>
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1"><i class="bi bi-journal-text me-2"></i>Logs de Auditoría</h3>
                <p class="text-muted mb-0">Historial de actividad del sistema</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= number_format($stats['total']) ?></div>
                            <small class="text-muted">Total Registros</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= number_format($stats['today']) ?></div>
                            <small class="text-muted">Hoy</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= number_format($stats['week']) ?></div>
                            <small class="text-muted">Últimos 7 días</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold"><?= number_format($stats['unique_users']) ?></div>
                            <small class="text-muted">Usuarios Únicos</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Chart -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Actividad - Últimos 7 días</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Usuario</label>
                                <input type="text" name="user" class="form-control" placeholder="Nombre o email" value="<?= htmlspecialchars($filterUser) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Acción</label>
                                <select name="action" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($actions as $a): ?>
                                    <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                                        <?= $actionMeta[$a]['label'] ?? $a ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Entidad</label>
                                <select name="entity" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($entities as $e): ?>
                                    <option value="<?= $e ?>" <?= $filterEntity === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Desde</label>
                                <input type="date" name="date_from" class="form-control" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Hasta</label>
                                <input type="date" name="date_to" class="form-control" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="registro_auditoria.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs List -->
        <div class="card mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Registros (<?= number_format($totalLogs) ?>)</h6>
                <span class="badge bg-primary">Página <?= $page ?> de <?= $totalPages ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted">No se encontraron registros</p>
                </div>
                <?php else: ?>
                <?php foreach ($logs as $log): 
                    $meta = $actionMeta[$log['action']] ?? ['icon' => 'dot', 'color' => 'secondary', 'label' => $log['action']];
                ?>
                <div class="log-item d-flex align-items-start">
                    <div class="action-icon bg-<?= $meta['color'] ?> bg-opacity-10 text-<?= $meta['color'] ?> me-3">
                        <i class="bi bi-<?= $meta['icon'] ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="text-dark"><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></strong>
                                <span class="text-muted mx-1">•</span>
                                <span class="badge bg-<?= $meta['color'] ?> bg-opacity-10 text-<?= $meta['color'] ?>"><?= $meta['label'] ?></span>
                            </div>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </small>
                        </div>
                        <?php if ($log['details']): ?>
                        <p class="mb-1 text-muted small"><?= htmlspecialchars($log['details']) ?></p>
                        <?php endif; ?>
                        <div class="small text-muted">
                            <?php if ($log['entity_type']): ?>
                            <span class="me-3"><i class="bi bi-box me-1"></i><?= ucfirst($log['entity_type']) ?> #<?= $log['entity_id'] ?></span>
                            <?php endif; ?>
                            <?php if ($log['ip_address']): ?>
                            <span class="me-3"><i class="bi bi-globe me-1"></i><?= $log['ip_address'] ?></span>
                            <?php endif; ?>
                            <?php if ($log['user_email']): ?>
                            <span><i class="bi bi-envelope me-1"></i><?= $log['user_email'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d['date'])), $chartData)) ?>,
                datasets: [{
                    label: 'Actividad',
                    data: <?= json_encode(array_column($chartData, 'count')) ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(10, 37, 64, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>

<?php
/**
 * EPCO - Exportador de Reportes
 * Genera reportes en Excel/CSV/PDF
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
if (!$user || !in_array($user['role'], ['admin', 'soporte'])) {
    header('Location: login.php');
    exit;
}

$reportType = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Generar reporte
if (!empty($reportType) && isset($_GET['download'])) {
    $data = [];
    $headers = [];
    $filename = "reporte_{$reportType}_" . date('Y-m-d');
    
    switch ($reportType) {
        case 'tickets':
            $headers = ['ID', 'Título', 'Usuario', 'Categoría', 'Prioridad', 'Estado', 'Técnico', 'Creado', 'Cerrado', 'Tiempo Resolución'];
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, u.name as user_name, t.category, t.priority, t.status, 
                       tech.name as tech_name, t.created_at, t.closed_at
                FROM tickets t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN users tech ON t.assigned_to = tech.id
                WHERE DATE(t.created_at) BETWEEN ? AND ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            while ($row = $stmt->fetch()) {
                $resolutionTime = '';
                if ($row['closed_at']) {
                    $diff = strtotime($row['closed_at']) - strtotime($row['created_at']);
                    $hours = round($diff / 3600, 1);
                    $resolutionTime = $hours . ' horas';
                }
                $data[] = [
                    $row['id'],
                    $row['title'],
                    $row['user_name'],
                    ucfirst($row['category']),
                    ucfirst($row['priority']),
                    ucfirst($row['status']),
                    $row['tech_name'] ?? 'Sin asignar',
                    $row['created_at'],
                    $row['closed_at'] ?? '-',
                    $resolutionTime
                ];
            }
            break;
            
        case 'users':
            $headers = ['ID', 'Nombre', 'Email', 'Rol', 'Departamento', 'Estado', 'Tickets Creados', 'Último Login', 'Creado'];
            $stmt = $pdo->query("
                SELECT u.*, 
                       (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count,
                       (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id AND action = 'login') as last_login
                FROM users u
                ORDER BY u.name
            ");
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    ucfirst($row['role']),
                    $row['department'] ?? '-',
                    $row['is_active'] ? 'Activo' : 'Inactivo',
                    $row['ticket_count'],
                    $row['last_login'] ?? 'Nunca',
                    $row['created_at']
                ];
            }
            break;
            
        case 'activity':
            $headers = ['ID', 'Usuario', 'Acción', 'Entidad', 'Detalles', 'IP', 'Fecha'];
            $stmt = $pdo->prepare("
                SELECT al.*, u.name as user_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE DATE(al.created_at) BETWEEN ? AND ?
                ORDER BY al.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['id'],
                    $row['user_name'] ?? 'Sistema',
                    $row['action'],
                    $row['entity_type'] ? "{$row['entity_type']} #{$row['entity_id']}" : '-',
                    $row['details'] ?? '-',
                    $row['ip_address'] ?? '-',
                    $row['created_at']
                ];
            }
            break;
            
        case 'surveys':
            $headers = ['Ticket', 'Usuario', 'Calificación', 'Tiempo', 'Resolución', 'Comunicación', 'Recomendaría', 'Comentarios', 'Fecha'];
            $stmt = $pdo->prepare("
                SELECT ts.*, t.title, u.name as user_name
                FROM ticket_surveys ts
                JOIN tickets t ON ts.ticket_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE DATE(ts.created_at) BETWEEN ? AND ?
                ORDER BY ts.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            while ($row = $stmt->fetch()) {
                $data[] = [
                    "#{$row['ticket_id']} - {$row['title']}",
                    $row['user_name'],
                    $row['rating'] . '/5',
                    $row['response_time_rating'] . '/5',
                    $row['resolution_rating'] . '/5',
                    $row['communication_rating'] . '/5',
                    $row['would_recommend'] ? 'Sí' : 'No',
                    $row['comments'] ?? '-',
                    $row['created_at']
                ];
            }
            break;
            
        case 'sla':
            $headers = ['Ticket', 'Prioridad', 'Respuesta SLA', 'Respuesta Real', 'Cumplió Respuesta', 'Resolución SLA', 'Resolución Real', 'Cumplió Resolución'];
            $stmt = $pdo->prepare("
                SELECT t.*, 
                       TIMESTAMPDIFF(HOUR, t.created_at, t.first_response_at) as response_hours,
                       TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at) as resolution_hours
                FROM tickets t
                WHERE DATE(t.created_at) BETWEEN ? AND ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            
            $slaTargets = [
                'urgent' => ['response' => 1, 'resolution' => 4],
                'high' => ['response' => 4, 'resolution' => 8],
                'medium' => ['response' => 8, 'resolution' => 24],
                'low' => ['response' => 24, 'resolution' => 72]
            ];
            
            while ($row = $stmt->fetch()) {
                $targets = $slaTargets[$row['priority']] ?? $slaTargets['medium'];
                $data[] = [
                    "#{$row['id']}",
                    ucfirst($row['priority']),
                    $targets['response'] . 'h',
                    $row['response_hours'] ? $row['response_hours'] . 'h' : '-',
                    $row['response_hours'] && $row['response_hours'] <= $targets['response'] ? '✓' : '✗',
                    $targets['resolution'] . 'h',
                    $row['resolution_hours'] ? $row['resolution_hours'] . 'h' : '-',
                    $row['resolution_hours'] && $row['resolution_hours'] <= $targets['resolution'] ? '✓' : '✗'
                ];
            }
            break;
    }
    
    // Exportar según formato
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}.csv");
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($output, $headers);
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename={$filename}.xls");
        
        echo "<html><head><meta charset='utf-8'></head><body>";
        echo "<table border='1'>";
        echo "<tr>";
        foreach ($headers as $h) {
            echo "<th style='background:#0ea5e9;color:white;font-weight:bold;'>" . htmlspecialchars($h) . "</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table></body></html>";
        exit;
    }
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header("Content-Disposition: attachment; filename={$filename}.json");
        
        $jsonData = [];
        foreach ($data as $row) {
            $item = [];
            foreach ($headers as $i => $h) {
                $item[$h] = $row[$i];
            }
            $jsonData[] = $item;
        }
        echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Estadísticas rápidas para el dashboard
$statsTickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$statsUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$statsSurveys = $pdo->query("SELECT COUNT(*) FROM ticket_surveys")->fetchColumn();
$statsLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Exportar Reportes</title>
    <link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { background: #f1f5f9; min-height: 100vh; }
        .navbar-epco { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .report-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .report-card.selected {
            border: 2px solid var(--primary);
        }
        .report-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .format-btn {
            padding: 15px 25px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .format-btn:hover {
            transform: scale(1.02);
        }
        .format-btn.active {
            background: var(--primary);
            color: white;
        }
    </style>
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/sidebar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar Reportes</h3>
                <p class="text-muted mb-0">Genera reportes en diferentes formatos</p>
            </div>
        </div>

        <form method="GET" id="exportForm">
            <!-- Tipo de reporte -->
            <h5 class="mb-3">1. Selecciona el tipo de reporte</h5>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card report-card h-100" data-type="tickets">
                        <div class="card-body text-center p-4">
                            <div class="report-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                                <i class="bi bi-ticket-perforated"></i>
                            </div>
                            <h5>Tickets</h5>
                            <p class="text-muted small mb-2">Listado completo de tickets con detalles</p>
                            <span class="badge bg-primary"><?= number_format($statsTickets) ?> registros</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card h-100" data-type="users">
                        <div class="card-body text-center p-4">
                            <div class="report-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5>Usuarios</h5>
                            <p class="text-muted small mb-2">Directorio de usuarios del sistema</p>
                            <span class="badge bg-success"><?= number_format($statsUsers) ?> registros</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card h-100" data-type="activity">
                        <div class="card-body text-center p-4">
                            <div class="report-icon bg-info bg-opacity-10 text-info mx-auto mb-3">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <h5>Actividad</h5>
                            <p class="text-muted small mb-2">Logs de auditoría del sistema</p>
                            <span class="badge bg-info"><?= number_format($statsLogs) ?> registros</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card h-100" data-type="surveys">
                        <div class="card-body text-center p-4">
                            <div class="report-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                                <i class="bi bi-star"></i>
                            </div>
                            <h5>Encuestas</h5>
                            <p class="text-muted small mb-2">Resultados de satisfacción</p>
                            <span class="badge bg-warning text-dark"><?= number_format($statsSurveys) ?> registros</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card h-100" data-type="sla">
                        <div class="card-body text-center p-4">
                            <div class="report-icon bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <h5>SLA</h5>
                            <p class="text-muted small mb-2">Cumplimiento de tiempos de servicio</p>
                            <span class="badge bg-danger">Análisis</span>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="type" id="reportType" value="">

            <!-- Rango de fechas -->
            <h5 class="mb-3">2. Rango de fechas</h5>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Desde</label>
                            <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('week')">Esta semana</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('month')">Este mes</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('year')">Este año</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formato -->
            <h5 class="mb-3">3. Formato de exportación</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-primary format-btn w-100 active" data-format="csv">
                        <i class="bi bi-filetype-csv fs-3 d-block mb-2"></i>
                        CSV
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-success format-btn w-100" data-format="excel">
                        <i class="bi bi-file-earmark-excel fs-3 d-block mb-2"></i>
                        Excel
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-info format-btn w-100" data-format="json">
                        <i class="bi bi-filetype-json fs-3 d-block mb-2"></i>
                        JSON
                    </button>
                </div>
            </div>
            <input type="hidden" name="format" id="exportFormat" value="csv">
            <input type="hidden" name="download" value="1">

            <!-- Botón exportar -->
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5" id="exportBtn" disabled>
                    <i class="bi bi-download me-2"></i>Exportar Reporte
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Seleccionar tipo de reporte
        document.querySelectorAll('.report-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.report-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('reportType').value = this.dataset.type;
                document.getElementById('exportBtn').disabled = false;
            });
        });
        
        // Seleccionar formato
        document.querySelectorAll('.format-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('exportFormat').value = this.dataset.format;
            });
        });
        
        // Presets de fecha
        function setDateRange(range) {
            const today = new Date();
            let from, to;
            
            if (range === 'week') {
                const monday = new Date(today);
                monday.setDate(today.getDate() - today.getDay() + 1);
                from = monday.toISOString().split('T')[0];
                to = today.toISOString().split('T')[0];
            } else if (range === 'month') {
                from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                to = today.toISOString().split('T')[0];
            } else if (range === 'year') {
                from = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                to = today.toISOString().split('T')[0];
            }
            
            document.querySelector('input[name="date_from"]').value = from;
            document.querySelector('input[name="date_to"]').value = to;
        }
    </script>
</body>
</html>

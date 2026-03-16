<?php
/**
 * EPCO - Calendario de Eventos
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
if (!$user) {
    header('Location: iniciar_sesion.php');
    exit;
}

$isAdmin = in_array($user['role'], ['admin', 'social']);

$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' && $isAdmin) {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'];
        $eventTime = $_POST['event_time'] ?? '00:00';
        $endDateInput = $_POST['end_date'] ?: null;
        $location = sanitize($_POST['location'] ?? '');
        $type = sanitize($_POST['type'] ?? 'other');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Combinar fecha y hora para start_date
        $startDateTime = $eventDate . ' ' . ($isAllDay ? '00:00:00' : $eventTime . ':00');
        $endDateTime = $endDateInput ? $endDateInput . ' 23:59:59' : null;
        
        $stmt = $pdo->prepare('
            INSERT INTO events (title, description, start_date, end_date, location, event_type, all_day, is_public, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$title, $description, $startDateTime, $endDateTime, $location, $type, $isAllDay, $isPublic, $user['id']]);
        
        $message = 'Evento creado exitosamente';
        $messageType = 'success';
    }
    
    if ($action === 'update' && $isAdmin) {
        $eventId = (int)$_POST['event_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'];
        $eventTime = $_POST['event_time'] ?? '00:00';
        $endDateInput = $_POST['end_date'] ?: null;
        $location = sanitize($_POST['location'] ?? '');
        $type = sanitize($_POST['type'] ?? 'other');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Combinar fecha y hora para start_date
        $startDateTime = $eventDate . ' ' . ($isAllDay ? '00:00:00' : $eventTime . ':00');
        $endDateTime = $endDateInput ? $endDateInput . ' 23:59:59' : null;
        
        $stmt = $pdo->prepare('
            UPDATE events SET title=?, description=?, start_date=?, end_date=?, location=?, event_type=?, all_day=?, is_public=? 
            WHERE id=?
        ');
        $stmt->execute([$title, $description, $startDateTime, $endDateTime, $location, $type, $isAllDay, $isPublic, $eventId]);
        
        $message = 'Evento actualizado';
        $messageType = 'success';
    }
    
    if ($action === 'delete' && $isAdmin) {
        $eventId = (int)$_POST['event_id'];
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        
        $message = 'Evento eliminado';
        $messageType = 'success';
    }
}

// Obtener mes/año actual o seleccionado
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// Validar
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startingDay = date('N', $firstDay); // 1 = Lunes

// Obtener eventos del mes
$startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$daysInMonth";

$stmt = $pdo->prepare('SELECT * FROM events WHERE DATE(start_date) BETWEEN ? AND ? ORDER BY start_date');
$stmt->execute([$startDate, $endDate]);
$events = $stmt->fetchAll();

// Agrupar por día
$eventsByDay = [];
foreach ($events as $event) {
    $day = (int)date('j', strtotime($event['start_date']));
    $eventsByDay[$day][] = $event;
}

// Próximos eventos
$stmt = $pdo->prepare('SELECT * FROM events WHERE DATE(start_date) >= CURDATE() ORDER BY start_date LIMIT 5');
$stmt->execute();
$upcomingEvents = $stmt->fetchAll();

// Tipos de eventos (coincide con ENUM de BD)
$eventTypes = [
    'meeting' => ['name' => 'Reunión', 'color' => '#0d6efd', 'icon' => 'people'],
    'birthday' => ['name' => 'Cumpleaños', 'color' => '#e91e8c', 'icon' => 'balloon'],
    'holiday' => ['name' => 'Feriado', 'color' => '#ffc107', 'icon' => 'sun'],
    'training' => ['name' => 'Capacitación', 'color' => '#198754', 'icon' => 'mortarboard'],
    'corporate' => ['name' => 'Corporativo', 'color' => '#6f42c1', 'icon' => 'building'],
    'other' => ['name' => 'Otro', 'color' => '#6c757d', 'icon' => 'calendar-event'],
];

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Calendario de Eventos</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    <style>
        .calendar-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
        }
        .calendar-day-header {
            background: var(--primary);
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 8px;
            position: relative;
        }
        .calendar-day.other-month {
            background: #f8f9fa;
            opacity: 0.5;
        }
        .calendar-day.today {
            background: #e3f2fd;
        }
        .calendar-day .day-number {
            font-weight: 600;
            color: var(--primary);
        }
        .calendar-day.weekend .day-number {
            color: #dc3545;
        }
        .calendar-event {
            font-size: 0.75rem;
            padding: 3px 6px;
            border-radius: 4px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            color: white;
        }
        .calendar-event:hover {
            transform: scale(1.02);
        }
        .event-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .event-card:hover {
            transform: translateX(5px);
        }
        .btn-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        .btn-nav:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <div class="container py-4">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Calendario -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="calendar-header d-flex justify-content-between align-items-center">
                        <a href="?year=<?= $month == 1 ? $year-1 : $year ?>&month=<?= $month == 1 ? 12 : $month-1 ?>" class="btn-nav">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <div class="text-center">
                            <h4 class="mb-0"><?= $monthNames[$month] ?> <?= $year ?></h4>
                        </div>
                        <a href="?year=<?= $month == 12 ? $year+1 : $year ?>&month=<?= $month == 12 ? 1 : $month+1 ?>" class="btn-nav">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    
                    <div class="calendar-grid">
                        <?php foreach ($dayNames as $day): ?>
                        <div class="calendar-day-header"><?= $day ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        // Días del mes anterior
                        $prevMonth = $month == 1 ? 12 : $month - 1;
                        $prevYear = $month == 1 ? $year - 1 : $year;
                        $daysInPrevMonth = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
                        
                        for ($i = $startingDay - 1; $i > 0; $i--):
                            $day = $daysInPrevMonth - $i + 1;
                        ?>
                        <div class="calendar-day other-month">
                            <span class="day-number"><?= $day ?></span>
                        </div>
                        <?php endfor; ?>
                        
                        <?php
                        // Días del mes actual
                        $today = date('Y-m-d');
                        for ($day = 1; $day <= $daysInMonth; $day++):
                            $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $isToday = $currentDate === $today;
                            $dayOfWeek = date('N', mktime(0, 0, 0, $month, $day, $year));
                            $isWeekend = $dayOfWeek >= 6;
                        ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $isWeekend ? 'weekend' : '' ?>">
                            <span class="day-number"><?= $day ?></span>
                            <?php if (isset($eventsByDay[$day])): ?>
                                <?php foreach ($eventsByDay[$day] as $event): ?>
                                <div class="calendar-event" 
                                     style="background: <?= $eventTypes[$event['event_type']]['color'] ?? '#6c757d' ?>;"
                                     data-bs-toggle="tooltip"
                                     title="<?= htmlspecialchars($event['title']) ?>">
                                    <?= htmlspecialchars($event['title']) ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                        
                        <?php
                        // Días del mes siguiente
                        $totalCells = $startingDay - 1 + $daysInMonth;
                        $remainingCells = 7 - ($totalCells % 7);
                        if ($remainingCells < 7):
                            for ($day = 1; $day <= $remainingCells; $day++):
                        ?>
                        <div class="calendar-day other-month">
                            <span class="day-number"><?= $day ?></span>
                        </div>
                        <?php endfor; endif; ?>
                    </div>
                </div>

                <!-- Leyenda -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="mb-3">Tipos de Eventos</h6>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($eventTypes as $type => $info): ?>
                            <span class="badge" style="background: <?= $info['color'] ?>;">
                                <i class="bi bi-<?= $info['icon'] ?> me-1"></i><?= $info['name'] ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary w-100 mb-4" data-bs-toggle="modal" data-bs-target="#eventModal">
                    <i class="bi bi-plus-lg me-2"></i>Nuevo Evento
                </button>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Próximos Eventos</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcomingEvents)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-2 mb-2 d-block"></i>
                            No hay eventos próximos
                        </div>
                        <?php else: ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                        <div class="event-card p-3 border-bottom" style="border-color: <?= $eventTypes[$event['event_type']]['color'] ?? '#6c757d' ?> !important;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($event['title']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?= date('d/m/Y', strtotime($event['start_date'])) ?>
                                        <?php if (!$event['all_day']): ?>
                                        <i class="bi bi-clock ms-2 me-1"></i>
                                        <?= date('H:i', strtotime($event['start_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($event['location']): ?>
                                    <br><small class="text-muted">
                                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($event['location']) ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge" style="background: <?= $eventTypes[$event['event_type']]['color'] ?? '#6c757d' ?>;">
                                    <i class="bi bi-<?= $eventTypes[$event['event_type']]['icon'] ?? 'calendar-event' ?>"></i>
                                </span>
                            </div>
                            <?php if ($event['description']): ?>
                            <p class="small text-muted mt-2 mb-0"><?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...</p>
                            <?php endif; ?>
                            
                            <?php if ($isAdmin): ?>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-secondary edit-event" 
                                        data-event='<?= json_encode($event) ?>'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este evento?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ir a fecha -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Ir a fecha</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-6">
                                <select name="month" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="year" class="form-select">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Ir
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-outline-primary">
                        <i class="bi bi-house me-2"></i>Hoy
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Modal Evento -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white;">
                    <h5 class="modal-title" id="modalTitle">Nuevo Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                </div>
                <form method="POST" id="eventForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="event_id" id="eventId">
                        
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" id="eventTitle" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" id="eventDescription" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha *</label>
                                <input type="date" name="event_date" id="eventDate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora</label>
                                <input type="time" name="event_time" id="eventTime" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fecha fin (opcional)</label>
                            <input type="date" name="end_date" id="eventEndDate" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="location" id="eventLocation" class="form-control" placeholder="Ej: Sala de reuniones">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="type" id="eventType" class="form-select">
                                <?php foreach ($eventTypes as $type => $info): ?>
                                <option value="<?= $type ?>"><?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_all_day" class="form-check-input" id="isAllDay">
                                    <label class="form-check-label" for="isAllDay">Todo el día</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_public" class="form-check-input" id="isPublic" checked>
                                    <label class="form-check-label" for="isPublic">Evento público</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
        
        // Editar evento
        document.querySelectorAll('.edit-event').forEach(btn => {
            btn.addEventListener('click', function() {
                const event = JSON.parse(this.dataset.event);
                document.getElementById('modalTitle').textContent = 'Editar Evento';
                document.getElementById('formAction').value = 'update';
                document.getElementById('eventId').value = event.id;
                document.getElementById('eventTitle').value = event.title;
                document.getElementById('eventDescription').value = event.description || '';
                document.getElementById('eventDate').value = event.start_date ? event.start_date.substring(0, 10) : '';
                document.getElementById('eventTime').value = event.start_date ? event.start_date.substring(11, 16) : '';
                document.getElementById('eventEndDate').value = event.end_date ? event.end_date.substring(0, 10) : '';
                document.getElementById('eventLocation').value = event.location || '';
                document.getElementById('eventType').value = event.event_type || 'other';
                document.getElementById('isAllDay').checked = event.all_day == 1;
                document.getElementById('isPublic').checked = event.is_public == 1;
                
                new bootstrap.Modal(document.getElementById('eventModal')).show();
            });
        });
        
        // Reset modal
        document.getElementById('eventModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalTitle').textContent = 'Nuevo Evento';
            document.getElementById('formAction').value = 'create';
            document.getElementById('eventForm').reset();
        });
    </script>
</body>
</html>

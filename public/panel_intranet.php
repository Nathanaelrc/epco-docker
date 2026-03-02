<?php
/**
 * EPCO - Intranet Corporativa
 */
require_once '../includes/bootstrap.php';

requireAuth('iniciar_sesion.php?redirect=panel_intranet.php');

$user = getCurrentUser();

// Usuarios de soporte tienen acceso completo como admin
$isAdminOrSoporte = in_array($user['role'], ['admin', 'soporte']);

// Acceso a panel de denuncias
$canViewDenuncias = in_array($user['role'], ['admin', 'denuncia']);

// Obtener noticias (4 cards)
$news = $pdo->query('SELECT n.*, u.name as author_name FROM news n LEFT JOIN users u ON n.author_id = u.id WHERE n.is_published = 1 ORDER BY n.created_at DESC LIMIT 4')->fetchAll();

// Verificar si el usuario puede gestionar noticias (admin, social o soporte)
$canManageNews = in_array($user['role'], ['admin', 'social', 'soporte']);

// Verificar si puede gestionar boletines (admin, social)
$canManageBulletins = in_array($user['role'], ['admin', 'social']);

// Obtener boletines activos desde la base de datos
$bulletins = [];
try {
    $bulletins = $pdo->query("
        SELECT *, 
            COALESCE(event_date, deadline_date) as countdown_date
        FROM bulletins 
        WHERE is_active = 1 
        AND (expires_at IS NULL OR expires_at >= CURDATE())
        ORDER BY is_pinned DESC, priority = 'high' DESC, created_at DESC
        LIMIT 6
    ")->fetchAll();
} catch (Exception $e) {
    // Tabla no existe aún, usar datos de ejemplo
    $bulletins = [];
}

// Categorías de boletines
$bulletinCategories = [
    'urgent' => ['label' => 'Obligatorio', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    'event' => ['label' => 'Evento', 'color' => '#0891b2', 'bg' => 'rgba(8,145,178,0.1)'],
    'info' => ['label' => 'Info', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
    'maintenance' => ['label' => 'TI', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
    'celebration' => ['label' => 'Celebración', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)']
];

// Estadísticas rápidas
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$openTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('abierto', 'en_proceso')")->fetchColumn();

// Fecha actual formateada en español (sin dependencias de extensiones)
$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fechaHoy = $diasSemana[date('w')] . ', ' . date('d') . ' de ' . $meses[date('n')] . ' de ' . date('Y');

// Áreas de la empresa con enlaces a SharePoint (modificables después)
$areas = [
    [
        'name' => 'Finanzas y Administración',
        'icon' => 'bi-calculator',
        'color' => '#0ea5e9',
        'description' => 'Gestión financiera, contabilidad y recursos',
        'link' => '#' // Cambiar por enlace SharePoint
    ],
    [
        'name' => 'Ingeniería y Proyectos',
        'icon' => 'bi-gear',
        'color' => '#0891b2',
        'description' => 'Desarrollo técnico y gestión de proyectos',
        'link' => '#' // Cambiar por enlace SharePoint
    ],
    [
        'name' => 'Concesiones y Desarrollo',
        'icon' => 'bi-building',
        'color' => '#059669',
        'description' => 'Gestión de concesiones y desarrollo de negocios',
        'link' => '#' // Cambiar por enlace SharePoint
    ],
    [
        'name' => 'Sostenibilidad y Cumplimiento',
        'icon' => 'bi-leaf',
        'color' => '#65a30d',
        'description' => 'Responsabilidad ambiental y normativas',
        'link' => '#' // Cambiar por enlace SharePoint
    ],
    [
        'name' => 'Control de Gestión, Riesgos y Sistemas',
        'icon' => 'bi-shield-check',
        'color' => '#7c3aed',
        'description' => 'Gestión de riesgos y tecnología de la información',
        'link' => '#' // Cambiar por enlace SharePoint
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Intranet</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    
    <style>
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        
        /* Hero Section */
        .hero-welcome {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-welcome::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-text {
            font-size: 2.5rem;
            font-weight: 300;
        }
        
        .welcome-name {
            font-weight: 700;
        }
        
        .welcome-date {
            color: rgba(255,255,255,0.7);
            font-size: 1.1rem;
            text-transform: capitalize;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.1);
            padding: 15px 25px;
            border-radius: 12px;
        }
        
        .stat-item i {
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        /* Main Content */
        .main-content {
            padding: 40px 0;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-light);
        }
        
        /* Area Cards */
        .area-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
            border: 2px solid transparent;
        }
        
        .area-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            color: inherit;
            border-color: var(--primary);
        }
        
        .area-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }
        
        .area-card h5 {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1.05rem;
        }
        
        .area-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .area-arrow {
            margin-top: 15px;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* News Cards */
        .news-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            border: 2px solid transparent;
        }
        
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }
        
        .news-image {
            height: 140px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .news-image i {
            font-size: 3rem;
            color: rgba(255,255,255,0.3);
        }
        
        .news-content {
            padding: 20px;
        }
        
        .news-date {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .news-card h5 {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .news-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-read-more {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 12px;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .news-read-more:hover {
            color: var(--primary-light);
            gap: 8px;
        }
        
        .news-read-more i {
            font-size: 0.75rem;
            transition: transform 0.3s;
        }
        
        .news-read-more:hover i {
            transform: translateX(3px);
        }
        
        /* News Modal Styles */
        #newsDetailModal .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        
        #newsDetailModal .modal-header {
            border-bottom: none;
            padding: 20px 24px;
        }
        
        #newsDetailModal .modal-body {
            padding: 24px;
        }
        
        #newsDetailModal .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
        }
        
        .news-modal-image {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .news-modal-meta {
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .news-modal-content {
            font-size: 1rem;
            line-height: 1.7;
            color: #334155;
        }
        
        .news-modal-content p {
            margin-bottom: 1rem;
        }
        
        /* Quick Links */
        .quick-link {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quick-link:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .quick-link-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
        }
        
        .quick-link-text h6 {
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--primary);
        }
        
        .quick-link-text p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }
        
        /* Bulletin Board - Mejorado */
        .bulletin-board {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .bulletin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .bulletin-tab {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .bulletin-tab:hover {
            background: #e2e8f0;
        }
        
        .bulletin-tab.active {
            background: var(--primary);
            color: white;
        }
        
        .bulletin-item {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            gap: 15px;
            background: #f8fafc;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .bulletin-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }
        
        .bulletin-item:last-child {
            margin-bottom: 0;
        }
        
        .bulletin-item.priority-urgent {
            border-left-color: #dc2626;
            background: linear-gradient(90deg, rgba(220,38,38,0.05) 0%, #f8fafc 100%);
        }
        
        .bulletin-item.priority-event {
            border-left-color: #0891b2;
        }
        
        .bulletin-item.priority-info {
            border-left-color: #059669;
        }
        
        .bulletin-item.priority-maintenance {
            border-left-color: #7c3aed;
        }
        
        .bulletin-item.unread::before {
            content: '';
            position: absolute;
            top: 12px;
            right: 12px;
            width: 10px;
            height: 10px;
            background: #3b82f6;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        
        .bulletin-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.2rem;
        }
        
        .bulletin-content {
            flex: 1;
            min-width: 0;
        }
        
        .bulletin-header {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        
        .bulletin-content h6 {
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            font-size: 0.95rem;
        }
        
        .bulletin-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bulletin-badge.urgent {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .bulletin-badge.event {
            background: #ecfeff;
            color: #0891b2;
        }
        
        .bulletin-badge.info {
            background: #ecfdf5;
            color: #059669;
        }
        
        .bulletin-badge.maintenance {
            background: #f5f3ff;
            color: #7c3aed;
        }
        
        .bulletin-content p {
            color: #64748b;
            font-size: 0.85rem;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }
        
        .bulletin-footer {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .bulletin-date {
            font-size: 0.75rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .bulletin-countdown {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .bulletin-countdown.urgent {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            animation: pulse-bg 2s infinite;
        }
        
        @keyframes pulse-bg {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .bulletin-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .bulletin-item:hover .bulletin-actions {
            opacity: 1;
        }
        
        .bulletin-action-btn {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .bulletin-action-btn.primary {
            background: var(--primary);
            color: white;
        }
        
        .bulletin-action-btn.primary:hover {
            background: var(--primary-light);
        }
        
        .bulletin-action-btn.secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .bulletin-action-btn.secondary:hover {
            background: #cbd5e1;
        }
        
        .bulletin-expand {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .bulletin-item.expanded .bulletin-expand {
            max-height: 200px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
        }
        
        .bulletin-expand-content {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.6;
        }
        
        /* Footer */
        .intranet-footer {
            background: var(--primary);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
        
        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            margin: 0 15px;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .welcome-text {
                font-size: 1.8rem;
            }
            .quick-stats {
                flex-direction: column;
                gap: 15px;
            }
            .header-nav {
                display: none;
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>
    
    <!-- Hero Welcome -->
    <section class="hero-welcome">
        <div class="container">
            <div class="hero-content">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <p class="welcome-date mb-2"><i class="bi bi-calendar3 me-2"></i><?= ucfirst($fechaHoy) ?></p>
                        <h1 class="welcome-text mb-0">
                            Bienvenido, <span class="welcome-name"><?= htmlspecialchars($user['name']) ?></span>
                        </h1>
                        <p class="mt-3 opacity-75" style="font-size: 1.1rem;">
                            Accede a todos los recursos y herramientas de EPCO desde un solo lugar.
                        </p>
                        
                        <div class="quick-stats">
                            <div class="stat-item">
                                <i class="bi bi-people"></i>
                                <div>
                                    <div class="stat-number"><?= $totalUsers ?></div>
                                    <div class="stat-label">Colaboradores</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <i class="bi bi-ticket"></i>
                                <div>
                                    <div class="stat-number"><?= $openTickets ?></div>
                                    <div class="stat-label">Tickets Abiertos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-center d-none d-lg-block">
                        <i class="bi bi-building" style="font-size: 8rem; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Quick Access -->
            <div class="row g-4 mb-5">
                <div class="col-lg-3 col-md-6">
                    <a href="documentos.php" class="quick-link">
                        <div class="quick-link-icon" style="background: linear-gradient(135deg, #059669, #34d399);">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                        <div class="quick-link-text">
                            <h6>Documentos</h6>
                            <p>Políticas y procedimientos</p>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="eventos.php" class="quick-link">
                        <div class="quick-link-icon" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="quick-link-text">
                            <h6>Calendario</h6>
                            <p>Eventos corporativos</p>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="perfil.php" class="quick-link">
                        <div class="quick-link-icon" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="quick-link-text">
                            <h6>Mi Perfil</h6>
                            <p>Configuración personal</p>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="buscar.php" class="quick-link">
                        <div class="quick-link-icon" style="background: linear-gradient(135deg, #64748b, #94a3b8);">
                            <i class="bi bi-search"></i>
                        </div>
                        <div class="quick-link-text">
                            <h6>Búsqueda</h6>
                            <p>Buscar en todo el portal</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Áreas de la Empresa -->
            <section id="areas" class="mb-5">
                <h2 class="section-title"><i class="bi bi-grid-3x3-gap"></i>Áreas de la Empresa</h2>
                <div class="row g-4">
                    <?php foreach ($areas as $area): ?>
                    <div class="col-lg-4 col-md-6">
                        <a href="<?= $area['link'] ?>" class="area-card" target="_blank">
                            <div class="area-icon" style="background: <?= $area['color'] ?>;">
                                <i class="bi <?= $area['icon'] ?>"></i>
                            </div>
                            <h5><?= $area['name'] ?></h5>
                            <p><?= $area['description'] ?></p>
                            <div class="area-arrow">
                                Acceder <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <div class="row g-4">
                <!-- Noticias - Ahora en fila completa con 4 cards -->
                <div class="col-12">
                    <section id="noticias">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="section-title mb-0"><i class="bi bi-newspaper"></i>Últimas Noticias</h2>
                            <?php if ($canManageNews): ?>
                            <a href="admin_noticias.php" class="btn btn-sm btn-outline-dark">
                                <i class="bi bi-plus-lg me-1"></i>Gestionar
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="row g-4">
                            <?php if (empty($news)): ?>
                            <div class="col-12">
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-newspaper" style="font-size: 3rem;"></i>
                                    <p class="mt-3">No hay noticias disponibles</p>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($news as $index => $item): ?>
                            <div class="col-lg-3 col-md-6">
                                <div class="news-card" onclick="showNewsDetail(<?= $index ?>)" style="cursor: pointer;">
                                    <div class="news-image">
                                        <?php if ($item['image_url']): ?>
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                        <i class="bi bi-megaphone"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="news-content">
                                        <div class="news-date">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                        </div>
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <p><?= htmlspecialchars(substr($item['content'], 0, 80)) ?>...</p>
                                        <span class="news-read-more">
                                            Ver noticia completa <i class="bi bi-arrow-right"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
            
            <div class="row g-4 mt-2">
                <!-- Boletín Interno -->
                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0"><i class="bi bi-pin-angle"></i>Boletín Interno</h2>
                        <?php if ($canManageBulletins): ?>
                        <a href="admin_boletines.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-gear"></i> Gestionar
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="bulletin-board mt-3">
                        <!-- Filtros -->
                        <div class="bulletin-tabs">
                            <button class="bulletin-tab active" data-filter="all">Todos</button>
                            <button class="bulletin-tab" data-filter="urgent">Urgente</button>
                            <button class="bulletin-tab" data-filter="event">Eventos</button>
                            <button class="bulletin-tab" data-filter="info">Info</button>
                        </div>
                        
                        <?php if (empty($bulletins)): ?>
                        <!-- Datos de ejemplo si no hay boletines en BD -->
                        <div class="bulletin-item priority-info" data-category="info" onclick="toggleBulletin(this)">
                            <div class="bulletin-icon" style="background: rgba(5,150,105,0.1); color: #059669;">
                                <i class="bi bi-info-circle-fill"></i>
                            </div>
                            <div class="bulletin-content">
                                <div class="bulletin-header">
                                    <h6>Bienvenido al Boletín</h6>
                                    <span class="bulletin-badge info"><i class="bi bi-info-circle"></i> Info</span>
                                </div>
                                <p>No hay boletines publicados aún. Los administradores pueden agregar nuevos anuncios.</p>
                                <div class="bulletin-footer">
                                    <span class="bulletin-date"><i class="bi bi-calendar3"></i> <?= date('d M Y') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($bulletins as $index => $bulletin): 
                            $category = $bulletin['category'] ?? 'info';
                            $catStyle = $bulletinCategories[$category] ?? $bulletinCategories['info'];
                            $isPinned = $bulletin['is_pinned'] ?? false;
                            $isHighPriority = ($bulletin['priority'] ?? '') === 'high';
                            $icon = $bulletin['icon'] ?? 'bi-megaphone-fill';
                        ?>
                        <div class="bulletin-item priority-<?= htmlspecialchars($category) ?><?= $isPinned ? ' pinned' : '' ?>" 
                             data-category="<?= htmlspecialchars($category) ?>" 
                             onclick="toggleBulletin(this)">
                            <div class="bulletin-icon" style="background: <?= $catStyle['bg'] ?>; color: <?= $catStyle['color'] ?>;">
                                <i class="bi <?= htmlspecialchars($icon) ?>"></i>
                                <?php if ($isPinned): ?>
                                <span class="pinned-indicator"><i class="bi bi-pin-fill"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="bulletin-content">
                                <div class="bulletin-header">
                                    <h6><?= htmlspecialchars($bulletin['title']) ?></h6>
                                    <span class="bulletin-badge <?= htmlspecialchars($category) ?>">
                                        <i class="bi bi-tag"></i> <?= htmlspecialchars($catStyle['label']) ?>
                                    </span>
                                </div>
                                <p><?= htmlspecialchars($bulletin['content']) ?></p>
                                <div class="bulletin-footer">
                                    <?php if (!empty($bulletin['countdown_date'])): ?>
                                    <span class="bulletin-date"><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($bulletin['countdown_date'])) ?></span>
                                    <span class="bulletin-countdown" data-date="<?= $bulletin['countdown_date'] ?>"><i class="bi bi-clock"></i> Calculando...</span>
                                    <?php else: ?>
                                    <span class="bulletin-date"><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($bulletin['created_at'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($bulletin['expanded_content']) || !empty($bulletin['action_url'])): ?>
                                <div class="bulletin-expand">
                                    <?php if (!empty($bulletin['expanded_content'])): ?>
                                    <div class="bulletin-expand-content">
                                        <?= nl2br(htmlspecialchars($bulletin['expanded_content'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($bulletin['action_url'])): ?>
                                    <div class="bulletin-actions">
                                        <a href="<?= htmlspecialchars($bulletin['action_url']) ?>" class="bulletin-action-btn primary" onclick="event.stopPropagation();">
                                            <i class="bi bi-arrow-right-circle"></i> <?= htmlspecialchars($bulletin['action_text'] ?? 'Ver más') ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Enlaces Útiles -->
                <div class="col-lg-6">
                    <h2 class="section-title"><i class="bi bi-link-45deg"></i>Enlaces Útiles</h2>
                    <div class="bulletin-board">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-microsoft me-2"></i>Microsoft 365
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-folder me-2"></i>SharePoint
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-chat-dots me-2"></i>Microsoft Teams
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-calendar-week me-2"></i>Calendario
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-book me-2"></i>Reglamento Interno
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-dark btn-sm text-start w-100">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>Documentos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal: Detalle de Noticia -->
    <div class="modal fade" id="newsDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-newspaper me-2"></i><span id="newsModalTitle">Noticia</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="newsModalBody">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer" id="newsModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Datos de noticias para JavaScript -->
    <script>
        const newsData = <?= json_encode(array_map(function($n) {
            return [
                'id' => $n['id'],
                'title' => $n['title'],
                'content' => $n['content'],
                'image_url' => $n['image_url'],
                'news_url' => $n['news_url'] ?? '',
                'author_name' => $n['author_name'] ?? 'EPCO',
                'created_at' => $n['created_at']
            ];
        }, $news)) ?>;
    </script>
    
    <!-- Footer -->
    <footer class="intranet-footer">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <span class="fw-bold">EPCO</span> - Portal Corporativo
                    <br><small class="opacity-75">© <?= date('Y') ?> Todos los derechos reservados</small>
                </div>
                <div class="col-md-6 text-center text-md-end footer-links">
                    <a href="crear_ticket.php?from=intranet">Crear Ticket</a>
                    <a href="denuncias.php?from=intranet">Denuncias</a>
                    <a href="cerrar_sesion.php">Salir</a>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // ========== MODAL DE NOTICIAS ==========
        function showNewsDetail(index) {
            if (!newsData || !newsData[index]) return;
            
            const news = newsData[index];
            const modal = new bootstrap.Modal(document.getElementById('newsDetailModal'));
            
            document.getElementById('newsModalTitle').textContent = news.title;
            
            // Formatear fecha
            const fecha = new Date(news.created_at);
            const fechaFormateada = fecha.toLocaleDateString('es-CL', { 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric' 
            });
            
            // Construir contenido del modal
            let content = '';
            
            // Imagen si existe
            if (news.image_url) {
                content += `
                    <div class="news-modal-image mb-4">
                        <img src="${news.image_url}" alt="" class="img-fluid rounded" style="width:100%; max-height:300px; object-fit:cover;">
                    </div>
                `;
            }
            
            // Metadatos
            content += `
                <div class="news-modal-meta d-flex gap-3 mb-3 text-muted small">
                    <span><i class="bi bi-calendar3 me-1"></i>${fechaFormateada}</span>
                    <span><i class="bi bi-person me-1"></i>${news.author_name}</span>
                </div>
            `;
            
            // Contenido completo (con saltos de línea)
            const contenidoFormateado = news.content.replace(/\n/g, '<br>');
            content += `<div class="news-modal-content">${contenidoFormateado}</div>`;
            
            document.getElementById('newsModalBody').innerHTML = content;
            
            // Footer con enlace externo si existe
            let footer = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>';
            if (news.news_url) {
                footer = `
                    <a href="${news.news_url}" target="_blank" class="btn btn-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Ver enlace externo
                    </a>
                    ${footer}
                `;
            }
            document.getElementById('newsModalFooter').innerHTML = footer;
            
            modal.show();
        }
        
        // ========== BOLETÍN INTERNO - FUNCIONALIDADES ==========
        
        // Expandir/colapsar items del boletín
        function toggleBulletin(item) {
            // Cerrar otros expandidos
            document.querySelectorAll('.bulletin-item.expanded').forEach(el => {
                if (el !== item) el.classList.remove('expanded');
            });
            // Toggle actual
            item.classList.toggle('expanded');
            // Marcar como leído
            item.classList.remove('unread');
        }
        
        // Filtrar por categoría
        document.querySelectorAll('.bulletin-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Activar tab
                document.querySelectorAll('.bulletin-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                document.querySelectorAll('.bulletin-item').forEach(item => {
                    if (filter === 'all' || item.dataset.category === filter) {
                        item.style.display = 'flex';
                        item.style.animation = 'fadeIn 0.3s ease';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
        
        // Calcular countdowns dinámicos
        function updateCountdowns() {
            const now = new Date();
            
            document.querySelectorAll('.bulletin-countdown[data-date]').forEach(el => {
                const targetDate = new Date(el.dataset.date);
                const diff = targetDate - now;
                const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
                
                if (diff > 0) {
                    if (days === 0) {
                        const hours = Math.floor(diff / (1000 * 60 * 60));
                        el.innerHTML = `<i class="bi bi-clock"></i> En ${hours} hora${hours !== 1 ? 's' : ''}`;
                    } else if (days === 1) {
                        el.innerHTML = `<i class="bi bi-clock"></i> Mañana`;
                    } else if (days <= 7) {
                        el.innerHTML = `<i class="bi bi-hourglass-split"></i> En ${days} días`;
                        el.classList.add('urgent');
                    } else {
                        el.innerHTML = `<i class="bi bi-clock"></i> En ${days} días`;
                    }
                } else {
                    el.innerHTML = `<i class="bi bi-check-circle"></i> Pasado`;
                }
            });
        }
        
        // Inicializar countdowns
        updateCountdowns();
        setInterval(updateCountdowns, 60000); // Actualizar cada minuto
        
        // Animación CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <!-- Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(err => console.log('SW:', err));
        }
    </script>
</body>
</html>

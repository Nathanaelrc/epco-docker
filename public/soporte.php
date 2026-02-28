<?php
/**
 * EPCO - Portal de Soporte TI
 * Diseño moderno inspirado en portales corporativos
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
$pageTitle = 'Soporte TI';

// Detectar origen
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'intranet_dashboard.php' : 'index.php';
$ticketCreateUrl = $fromIntranet ? 'ticket_create.php?from=intranet' : 'ticket_create.php';
$ticketSeguimientoUrl = $fromIntranet ? 'ticket_seguimiento.php?from=intranet' : 'ticket_seguimiento.php';

// Estadísticas rápidas (si está logueado)
$userTickets = ['total' => 0, 'abiertos' => 0, 'resueltos' => 0];
if ($user) {
    $stmt = $pdo->prepare('SELECT status, COUNT(*) as count FROM tickets WHERE user_id = ? GROUP BY status');
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetchAll();
    foreach ($stats as $s) {
        $userTickets['total'] += $s['count'];
        if (in_array($s['status'], ['abierto', 'en_progreso'])) $userTickets['abiertos'] += $s['count'];
        if (in_array($s['status'], ['resuelto', 'cerrado'])) $userTickets['resueltos'] += $s['count'];
    }
}

// FAQ items
$faqs = [
    ['q' => '¿Cómo puedo hacer seguimiento a mi ticket?', 'a' => 'Ingresa a "Consultar Ticket" con tu número de ticket y correo electrónico.', 'icon' => 'bi-search'],
    ['q' => '¿Cuánto tiempo demora la atención?', 'a' => 'Urgente: 4h | Alta: 8h | Media: 24h | Baja: 48h', 'icon' => 'bi-clock'],
    ['q' => '¿Qué información debo incluir?', 'a' => 'Descripción detallada, mensajes de error, pasos para reproducir y cambios recientes.', 'icon' => 'bi-info-circle'],
    ['q' => '¿Puedo adjuntar archivos?', 'a' => 'Sí, puedes adjuntar capturas de pantalla y documentos al crear el ticket.', 'icon' => 'bi-paperclip'],
    ['q' => '¿Qué hago en caso de emergencia?', 'a' => 'Contacta al interno 6479 o escribe a gismodes@puertocoquimbo.cl / asesorti@puertocoquimbo.cl', 'icon' => 'bi-exclamation-triangle'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Soporte TI</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/Logo01.png">
    <link rel="shortcut icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * { font-family: 'Barlow', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #f5f7fa;
            min-height: 100vh;
            padding-top: 60px;
        }
        
        /* ========== TOPBAR SIMPLE ========== */
        .epco-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .topbar-back-btn {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }
        
        .topbar-back-btn:hover {
            background: rgba(255,255,255,0.25);
            color: white;
            transform: translateX(-3px);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .topbar-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* ========== HEADER PRINCIPAL ========== */
        .main-header {
            background: linear-gradient(135deg, rgba(3,105,161,0.75) 0%, rgba(7,89,133,0.8) 50%, rgba(3,105,161,0.75) 100%),
                        url('img/Puerto01.jpeg') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        
        .header-content {
            padding: 60px 0 80px;
            position: relative;
            z-index: 2;
        }
        
        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 50px;
            color: white;
            font-size: 0.85rem;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .header-title {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 3px 15px rgba(0,0,0,0.4), 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .header-subtitle {
            font-size: 1.2rem;
            color: white;
            max-width: 600px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        
        /* Stats en Header */
        .header-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .header-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .header-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
        }
        
        .header-stat-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.8);
        }
        
        /* Cards de Acción */
        .action-cards {
            margin-top: -50px;
            position: relative;
            z-index: 10;
            padding-bottom: 40px;
        }
        
        .action-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: block;
            height: 100%;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9, #06b6d4);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
            border-color: #0ea5e9;
        }
        
        .action-card:hover::before {
            transform: scaleX(1);
        }
        
        .action-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.2rem;
            transition: all 0.4s ease;
        }
        
        .action-card:hover .action-icon {
            transform: scale(1.1);
        }
        
        .action-card.primary .action-icon {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }
        
        .action-card.secondary .action-icon {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .action-card.tertiary .action-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .action-card.quaternary .action-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .action-card h4 {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            font-size: 1.3rem;
        }
        
        .action-card p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .action-card.primary .action-btn { background: #0ea5e9; color: white; }
        .action-card.secondary .action-btn { background: #10b981; color: white; }
        .action-card.tertiary .action-btn { background: #8b5cf6; color: white; }
        .action-card.quaternary .action-btn { background: #f59e0b; color: white; }
        
        .action-card:hover .action-btn {
            transform: translateX(5px);
        }
        
        /* Categorías */
        .categories-section {
            background: white;
            padding: 80px 0;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 15px;
        }
        
        .section-header p {
            color: #64748b;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .category-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border-color: currentColor;
        }
        
        .category-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            color: white;
        }
        
        .category-card h5 {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }
        
        .category-card p {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        /* Process Steps */
        .process-step {
            background: white;
            border-radius: 20px;
            padding: 35px 25px;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .process-step:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 35px;
            height: 35px;
            background: #0f172a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .step-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 15px auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .process-step h5 {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }
        
        .process-step p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0;
            line-height: 1.5;
        }
        
        /* SLA Section */
        .sla-section {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .sla-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .sla-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 1;
        }
        
        .sla-priority {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .sla-priority.urgente { background: #fecaca; color: #b91c1c; }
        .sla-priority.alta { background: #fed7aa; color: #c2410c; }
        .sla-priority.media { background: #dbeafe; color: #1e40af; }
        .sla-priority.baja { background: #d1fae5; color: #047857; }
        
        .sla-time {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 5px;
        }
        
        .sla-label {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }
        
        /* FAQ Section */
        .faq-section {
            background: #f8fafc;
            padding: 80px 0;
        }
        
        .faq-item {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .faq-question {
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: #f1f5f9;
        }
        
        .faq-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .faq-question h6 {
            font-weight: 600;
            color: #0f172a;
            margin: 0;
            flex: 1;
        }
        
        .faq-question .arrow {
            color: #94a3b8;
            transition: transform 0.3s;
        }
        
        .faq-item.open .faq-question .arrow {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 25px 20px 85px;
            color: #64748b;
            display: none;
        }
        
        .faq-item.open .faq-answer {
            display: block;
        }
        
        /* Contact Section */
        .contact-section {
            background: white;
            padding: 60px 0;
        }
        
        .contact-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border-radius: 20px;
            padding: 40px;
            color: white;
        }
        
        .contact-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .contact-info:last-child {
            margin-bottom: 0;
        }
        
        .contact-info-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        
        .contact-info h6 {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .contact-info p {
            color: rgba(255,255,255,0.7);
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Footer */
        .site-footer {
            background: #0f172a;
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .site-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .site-footer a:hover {
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-title { font-size: 2rem; }
            .header-content { padding: 40px 0 60px; }
            .action-cards { margin-top: -30px; }
            .header-stats { justify-content: center; }
        }
        
        /* Animaciones */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeUp 0.6s ease forwards;
        }
        
        .fade-up:nth-child(1) { animation-delay: 0.1s; }
        .fade-up:nth-child(2) { animation-delay: 0.2s; }
        .fade-up:nth-child(3) { animation-delay: 0.3s; }
        .fade-up:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Topbar Simple -->
    <div class="epco-topbar">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="topbar-back-btn" title="Volver al Inicio">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="logo-text" style="font-size: 1.15rem; font-weight: 700; color: white;">Empresa Portuaria Coquimbo</span>
        </div>
        
        <div class="topbar-right">
            <span class="topbar-badge">
                <i class="bi bi-headset"></i>
                Soporte TI
            </span>
            
            <?php if ($user): ?>
            <a href="soporte_admin.php" class="btn btn-light btn-sm d-flex align-items-center gap-2" style="border-radius: 10px; font-weight: 600;">
                <i class="bi bi-speedometer2"></i>
                <span class="d-none d-sm-inline">Ir al Dashboard</span>
            </a>
            <?php else: ?>
            <a href="login.php?redirect=soporte_admin" class="btn btn-light btn-sm d-flex align-items-center gap-2" style="border-radius: 10px; font-weight: 600;">
                <i class="bi bi-box-arrow-in-right"></i>
                <span class="d-none d-sm-inline">Iniciar Sesión</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Header Principal -->
    <header class="main-header">
        <div class="header-content">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <img src="img/Logo01.png" alt="Logo EPCO" class="header-logo mb-3" style="width: 100px; height: auto; filter: drop-shadow(0 4px 15px rgba(0,0,0,0.3));">
                        <div class="header-badge">
                            <i class="bi bi-building"></i>
                            <span>Empresa Portuaria Coquimbo</span>
                        </div>
                        <h1 class="header-title">Centro de Soporte</h1>
                        <p class="header-subtitle">
                            ¿Tienes un problema técnico? Estamos aquí para ayudarte. Crea un ticket y nuestro equipo lo resolverá lo antes posible.
                        </p>
                    </div>
                    <div class="col-lg-5 text-center d-none d-lg-block">
                        <i class="bi bi-headset" style="font-size: 12rem; color: rgba(255,255,255,0.15);"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Cards de Acción -->
    <section class="action-cards">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="<?= $ticketCreateUrl ?>" class="action-card primary">
                        <div class="action-icon">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <h4>Crear Ticket</h4>
                        <p>Reporta un problema técnico o solicita asistencia de TI.</p>
                        <span class="action-btn">
                            Crear ahora <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
                
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="#" class="action-card secondary" data-bs-toggle="modal" data-bs-target="#consultarTicketModal">
                        <div class="action-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h4>Consultar Ticket</h4>
                        <p>Revisa el estado de tu ticket con tu código de seguimiento.</p>
                        <span class="action-btn">
                            Consultar <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
                
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="knowledge_base.php" class="action-card tertiary">
                        <div class="action-icon">
                            <i class="bi bi-book"></i>
                        </div>
                        <h4>Base de Conocimiento</h4>
                        <p>Encuentra soluciones a problemas comunes por tu cuenta.</p>
                        <span class="action-btn">
                            Explorar <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Cómo Funciona -->
    <section class="categories-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-lightning-charge me-2"></i>¿Cómo Funciona?</h2>
                <p>Proceso simple y rápido para resolver tu problema</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">1</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <h5>Describe el Problema</h5>
                        <p>Completa el formulario con los detalles de tu incidencia técnica.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">2</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <h5>Recibe tu Código</h5>
                        <p>Obtendrás un número único para dar seguimiento a tu solicitud.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">3</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <h5>Atención Técnica</h5>
                        <p>Un especialista será asignado y trabajará en tu caso.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">4</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h5>Problema Resuelto</h5>
                        <p>Recibirás notificación cuando tu ticket sea solucionado.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SLA / Tiempos de Respuesta -->
    <section class="sla-section" id="sla-section">
        <div class="container">
            <div class="section-header">
                <h2 class="text-white"><i class="bi bi-clock-history me-2"></i>Tiempos de Respuesta</h2>
                <p class="text-white-50">Nuestro compromiso según la prioridad de tu ticket</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="sla-card">
                        <span class="sla-priority urgente">URGENTE</span>
                        <div class="sla-time">4h</div>
                        <div class="sla-label">Primera respuesta</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="sla-card">
                        <span class="sla-priority alta">ALTA</span>
                        <div class="sla-time">8h</div>
                        <div class="sla-label">Primera respuesta</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="sla-card">
                        <span class="sla-priority media">MEDIA</span>
                        <div class="sla-time">24h</div>
                        <div class="sla-label">Primera respuesta</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="sla-card">
                        <span class="sla-priority baja">BAJA</span>
                        <div class="sla-time">48h</div>
                        <div class="sla-label">Primera respuesta</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ -->
    <section class="faq-section" id="faq-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-question-circle me-2"></i>Preguntas Frecuentes</h2>
                <p>Respuestas rápidas a las dudas más comunes</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php foreach ($faqs as $i => $faq): ?>
                    <div class="faq-item">
                        <div class="faq-question">
                            <div class="faq-icon">
                                <i class="bi <?= $faq['icon'] ?>"></i>
                            </div>
                            <h6><?= $faq['q'] ?></h6>
                            <i class="bi bi-chevron-down arrow"></i>
                        </div>
                        <div class="faq-answer">
                            <?= $faq['a'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contacto -->
    <section class="contact-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-card">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-3"><i class="bi bi-telephone-fill me-2"></i>¿Necesitas ayuda urgente?</h4>
                                <p class="text-white-50 mb-4">Para emergencias que afecten sistemas críticos o múltiples usuarios, contáctanos directamente.</p>
                            </div>
                            <div class="col-md-6">
                                <div class="contact-info">
                                    <div class="contact-info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div>
                                        <h6>Interno de Emergencias</h6>
                                        <p>6479</p>
                                    </div>
                                </div>
                                <div class="contact-info">
                                    <div class="contact-info-icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div>
                                        <h6>Correos de Soporte</h6>
                                        <p>gismodes@puertocoquimbo.cl<br>asesorti@puertocoquimbo.cl</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p class="mb-2">
                <strong>EPCO</strong> - Centro de Soporte TI
            </p>
            <p class="small text-white-50 mb-0">
                © <?= date('Y') ?> Todos los derechos reservados | 
                <a href="<?= $backUrl ?>">Volver al Portal</a>
            </p>
        </div>
    </footer>
    
    <!-- Modal Consultar Ticket -->
    <div class="modal fade" id="consultarTicketModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
                    <h5 class="modal-title text-white"><i class="bi bi-search me-2"></i>Consultar Estado de Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Formulario de búsqueda -->
                    <p class="text-muted mb-3">Ingresa tu código de seguimiento para consultar el estado de tu ticket.</p>
                    <form id="consultarTicketForm" onsubmit="buscarTicket(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Código de Ticket</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-ticket-perforated"></i></span>
                                <input type="text" name="ticket_number" id="ticketNumberInput" class="form-control form-control-lg" placeholder="Ej: TK-20260129-A1B2C" required style="border-radius: 0 10px 10px 0;">
                            </div>
                        </div>
                        <button type="submit" id="btnBuscarTicket" class="btn btn-lg w-100 text-white" style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px;">
                            <i class="bi bi-search me-2"></i>Consultar Ticket
                        </button>
                    </form>
                    
                    <!-- Resultado de la búsqueda -->
                    <div id="ticketResultado" class="mt-4" style="display:none;">
                        <hr>
                        <!-- Error -->
                        <div id="ticketError" class="alert alert-danger rounded-3" style="display:none;">
                            <i class="bi bi-exclamation-circle me-2"></i><span id="ticketErrorMsg"></span>
                        </div>
                        
                        <!-- Info del ticket -->
                        <div id="ticketInfo" style="display:none;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span id="tkStatus" class="badge mb-2"></span>
                                    <h5 class="fw-bold mb-1" id="tkTitle"></h5>
                                    <p class="text-muted mb-0 small">
                                        <i class="bi bi-ticket me-1"></i><span id="tkNumber"></span>
                                        <span class="mx-2">·</span>
                                        <i class="bi bi-calendar me-1"></i><span id="tkDate"></span>
                                    </p>
                                </div>
                                <span id="tkPriority" class="badge"></span>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3">
                                        <p class="text-muted small mb-1">Categoría</p>
                                        <p class="fw-semibold mb-0" id="tkCategory"></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3">
                                        <p class="text-muted small mb-1">Prioridad</p>
                                        <p class="fw-semibold mb-0" id="tkPriorityText"></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3">
                                        <p class="text-muted small mb-1">Asignado a</p>
                                        <p class="fw-semibold mb-0" id="tkAssigned"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-muted small mb-1">Descripción</p>
                                <div class="p-3 bg-light rounded-3">
                                    <p class="mb-0" id="tkDescription"></p>
                                </div>
                            </div>
                            
                            <!-- Resolución -->
                            <div id="tkResolutionWrap" class="mb-3" style="display:none;">
                                <div class="p-3 rounded-3" style="background: rgba(16,185,129,0.1);">
                                    <p class="small mb-1" style="color: #059669;"><i class="bi bi-check-circle me-1"></i>Resolución</p>
                                    <p class="mb-0" id="tkResolution"></p>
                                </div>
                            </div>
                            
                            <!-- Comentarios -->
                            <div id="tkCommentsWrap" style="display:none;">
                                <hr>
                                <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2"></i>Historial de Comentarios</h6>
                                <div id="tkComments"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // FAQ toggle
        document.querySelectorAll('.faq-question').forEach(q => {
            q.addEventListener('click', () => {
                const item = q.closest('.faq-item');
                document.querySelectorAll('.faq-item').forEach(i => {
                    if (i !== item) i.classList.remove('open');
                });
                item.classList.toggle('open');
            });
        });

        // Ticket lookup AJAX
        function buscarTicket(e) {
            e.preventDefault();
            const numero = document.getElementById('ticketNumberInput').value.trim();
            if (!numero) return;

            const btn = document.getElementById('btnBuscarTicket');
            const resultado = document.getElementById('ticketResultado');
            const errorDiv = document.getElementById('ticketError');
            const infoDiv = document.getElementById('ticketInfo');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Buscando...';
            resultado.style.display = 'none';
            errorDiv.style.display = 'none';
            infoDiv.style.display = 'none';

            fetch('api/ticket_lookup?ticket_number=' + encodeURIComponent(numero))
                .then(r => r.json())
                .then(data => {
                    resultado.style.display = 'block';

                    if (!data.success) {
                        errorDiv.style.display = 'block';
                        document.getElementById('ticketErrorMsg').textContent = data.error || 'No se encontró el ticket.';
                        return;
                    }

                    const tk = data.ticket;
                    infoDiv.style.display = 'block';

                    // Status badge
                    const statusMap = {
                        'abierto':     { label: 'Abierto',      bg: '#3b82f6' },
                        'en_progreso': { label: 'En Progreso',  bg: '#f59e0b' },
                        'pendiente':   { label: 'Pendiente',    bg: '#8b5cf6' },
                        'resuelto':    { label: 'Resuelto',     bg: '#10b981' },
                        'cerrado':     { label: 'Cerrado',      bg: '#6b7280' }
                    };
                    const st = statusMap[tk.status] || { label: tk.status, bg: '#6b7280' };
                    const stEl = document.getElementById('tkStatus');
                    stEl.textContent = st.label;
                    stEl.style.background = st.bg;
                    stEl.style.color = '#fff';
                    stEl.style.fontSize = '0.85rem';
                    stEl.style.padding = '6px 14px';
                    stEl.style.borderRadius = '20px';

                    // Priority badge
                    const prioMap = {
                        'baja':     { label: 'Baja',     bg: '#22c55e' },
                        'media':    { label: 'Media',    bg: '#f59e0b' },
                        'alta':     { label: 'Alta',     bg: '#ef4444' },
                        'urgente':  { label: 'Urgente',  bg: '#dc2626' }
                    };
                    const pr = prioMap[tk.priority] || { label: tk.priority, bg: '#6b7280' };
                    const prEl = document.getElementById('tkPriority');
                    prEl.textContent = pr.label;
                    prEl.style.background = pr.bg;
                    prEl.style.color = '#fff';
                    prEl.style.fontSize = '0.8rem';
                    prEl.style.padding = '5px 12px';
                    prEl.style.borderRadius = '20px';

                    document.getElementById('tkTitle').textContent = tk.title;
                    document.getElementById('tkNumber').textContent = tk.ticket_number;
                    document.getElementById('tkDate').textContent = formatDate(tk.created_at);
                    document.getElementById('tkCategory').textContent = tk.category || 'Sin categoría';
                    document.getElementById('tkPriorityText').textContent = pr.label;
                    document.getElementById('tkAssigned').textContent = tk.assigned_name || 'Sin asignar';
                    document.getElementById('tkDescription').textContent = tk.description;

                    // Resolution
                    const resWrap = document.getElementById('tkResolutionWrap');
                    if (tk.resolution) {
                        resWrap.style.display = 'block';
                        document.getElementById('tkResolution').textContent = tk.resolution;
                    } else {
                        resWrap.style.display = 'none';
                    }

                    // Comments
                    const comments = data.comments || [];
                    const commentsWrap = document.getElementById('tkCommentsWrap');
                    const commentsCont = document.getElementById('tkComments');
                    commentsCont.innerHTML = '';

                    if (comments.length > 0) {
                        commentsWrap.style.display = 'block';
                        comments.forEach(c => {
                            const div = document.createElement('div');
                            div.className = 'p-3 mb-2 bg-light rounded-3';
                            div.innerHTML = '<div class="d-flex justify-content-between mb-1">' +
                                '<span class="fw-semibold small">' + escHtml(c.author_name) + '</span>' +
                                '<span class="text-muted small">' + formatDate(c.created_at) + '</span>' +
                                '</div>' +
                                '<p class="mb-0 small">' + escHtml(c.comment) + '</p>';
                            commentsCont.appendChild(div);
                        });
                    } else {
                        commentsWrap.style.display = 'none';
                    }
                })
                .catch(() => {
                    resultado.style.display = 'block';
                    errorDiv.style.display = 'block';
                    document.getElementById('ticketErrorMsg').textContent = 'Error de conexión. Intenta nuevamente.';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-search me-2"></i>Consultar Ticket';
                });
        }

        function formatDate(str) {
            if (!str) return '';
            const d = new Date(str);
            return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // Reset modal on close
        document.getElementById('consultarTicketModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('ticketNumberInput').value = '';
            document.getElementById('ticketResultado').style.display = 'none';
            document.getElementById('ticketError').style.display = 'none';
            document.getElementById('ticketInfo').style.display = 'none';
        });
    </script>
</body>
</html>

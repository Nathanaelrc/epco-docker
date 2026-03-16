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
$backUrl = $fromIntranet ? 'panel_intranet.php' : 'index.php';
$ticketCreateUrl = $fromIntranet ? 'crear_ticket.php?from=intranet' : 'crear_ticket.php';
$ticketSeguimientoUrl = $fromIntranet ? 'seguimiento_ticket.php?from=intranet' : 'seguimiento_ticket.php';

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
    ['q' => '¿Cómo puedo hacer seguimiento a mi ticket?', 'a' => 'Ingresa a la sección <strong>"Consultar Ticket"</strong> desde esta misma página. Necesitarás el código de seguimiento con formato <code>TK-AAAAMMDD-XXXXX</code> que fue enviado a tu correo electrónico al momento de crear el ticket. Con ese código podrás ver el estado actual, la prioridad asignada, el técnico responsable y el historial de comentarios.', 'icon' => 'bi-search'],
    ['q' => '¿Cuánto tiempo demora la atención?', 'a' => 'Los tiempos de primera respuesta dependen de la prioridad asignada:<br><strong>Urgente:</strong> máximo 4 horas (sistemas críticos caídos que afectan a múltiples usuarios).<br><strong>Alta:</strong> máximo 8 horas (problemas que impiden trabajar a un usuario).<br><strong>Media:</strong> máximo 24 horas (problemas que no impiden el trabajo pero lo dificultan).<br><strong>Baja:</strong> máximo 48 horas (consultas generales o mejoras).', 'icon' => 'bi-clock'],
    ['q' => '¿Qué información debo incluir en mi ticket?', 'a' => 'Para una atención más rápida, incluye: <strong>1)</strong> Descripción clara del problema, <strong>2)</strong> Mensajes de error exactos que aparecen en pantalla, <strong>3)</strong> Pasos para reproducir el problema, <strong>4)</strong> Desde cuándo ocurre, <strong>5)</strong> Si realizaste algún cambio reciente en tu equipo. También puedes adjuntar capturas de pantalla que ayuden a entender mejor la situación.', 'icon' => 'bi-info-circle'],
    ['q' => '¿Qué tipos de problemas puedo reportar?', 'a' => 'Puedes reportar cualquier incidencia tecnológica: problemas con tu computador (lentitud, pantallazos azules, no enciende), software (errores en aplicaciones, Office, correo Outlook), red e internet (sin conexión, WiFi lento), accesos (contraseñas bloqueadas, permisos), impresoras, telefonía y cualquier otro equipo tecnológico de la empresa.', 'icon' => 'bi-motherboard'],
    ['q' => '¿Puedo adjuntar archivos a mi ticket?', 'a' => 'Sí, al crear el ticket puedes adjuntar archivos como capturas de pantalla, documentos o cualquier evidencia relevante. Los formatos aceptados incluyen imágenes (JPG, PNG), documentos (PDF, DOC) y otros archivos de uso común. Esto ayuda al equipo técnico a diagnosticar tu problema con mayor precisión.', 'icon' => 'bi-paperclip'],
    ['q' => '¿Cómo sé qué prioridad asignar a mi ticket?', 'a' => '<strong>Urgente:</strong> Sistemas críticos completamente caídos que afectan a varios usuarios (ej: servidor de correo no funciona, sistema ERP caído).<br><strong>Alta:</strong> No puedes realizar tu trabajo (ej: tu PC no enciende, sin acceso al sistema).<br><strong>Media:</strong> Puedes trabajar pero con dificultad (ej: programa lento, impresora atascada).<br><strong>Baja:</strong> Consultas, solicitudes de mejora o problemas menores (ej: cambio de tóner, consulta sobre software).', 'icon' => 'bi-flag'],
    ['q' => '¿Qué es la Base de Conocimiento?', 'a' => 'Es una biblioteca de artículos y guías que te permiten resolver problemas comunes por tu cuenta, sin necesidad de crear un ticket. Incluye soluciones para problemas de hardware, software, red, accesos y procedimientos de emergencia. Te recomendamos consultarla antes de crear un ticket, ya que podrías encontrar la solución de inmediato.', 'icon' => 'bi-book'],
    ['q' => '¿Qué hago en caso de emergencia?', 'a' => 'Si se trata de un problema crítico que afecta sistemas esenciales o a múltiples usuarios, contacta directamente al teléfono <strong>512406479</strong> o escribe a <strong>gismodes@puertocoquimbo.cl</strong> / <strong>asesorti@puertocoquimbo.cl</strong>. El equipo de soporte evaluará la situación y activará el protocolo de emergencia correspondiente.', 'icon' => 'bi-exclamation-triangle'],
];
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
    <title>Empresa Portuaria Coquimbo - Soporte TI</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link rel="shortcut icon" type="image/webp" href="img/Logo01.webp">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/soporte.css" rel="stylesheet">
    <style>
        .main-header { background: linear-gradient(135deg, rgba(3,105,161,0.75) 0%, rgba(7,89,133,0.8) 50%, rgba(3,105,161,0.75) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto01.webp" : "img/Puerto01.jpeg" ?>') center/cover no-repeat; }
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
            <a href="iniciar_sesion.php?redirect=soporte_admin" class="btn btn-light btn-sm d-flex align-items-center gap-2" style="border-radius: 10px; font-weight: 600;">
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
                        <picture>
                            <source srcset="img/Logo01.webp" type="image/webp">
                            <img src="img/Logo01.png" alt="Logo Empresa Portuaria Coquimbo" class="header-logo mb-3" style="width: 100px; height: auto; filter: drop-shadow(0 4px 15px rgba(0,0,0,0.3));" loading="eager" width="100" height="100">
                        </picture>
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
                            <i class="bi bi-file-earmark-plus"></i>
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
                            <i class="bi bi-clipboard2-check"></i>
                        </div>
                        <h4>Consultar Ticket</h4>
                        <p>Revisa el estado de tu ticket con tu código de seguimiento.</p>
                        <span class="action-btn">
                            Consultar <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
                
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="base_conocimiento.php" class="action-card tertiary">
                        <div class="action-icon">
                            <i class="bi bi-journal-bookmark"></i>
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
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h5>Describe el Problema</h5>
                        <p>Completa el formulario con los detalles de tu incidencia técnica.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">2</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                        <h5>Recibe tu Código</h5>
                        <p>Obtendrás un número único para dar seguimiento a tu solicitud.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">3</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                            <i class="bi bi-wrench-adjustable-circle"></i>
                        </div>
                        <h5>Atención Técnica</h5>
                        <p>Un especialista será asignado y trabajará en tu caso.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="process-step">
                        <div class="step-number">4</div>
                        <div class="step-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="bi bi-shield-check"></i>
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
                <div class="col-lg-9">
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
                                        <h6>Teléfono de Soporte</h6>
                                        <p>512406479</p>
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
                <strong>Empresa Portuaria Coquimbo</strong> - Centro de Soporte TI
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
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none;">
                <!-- Header con estilo portuario -->
                <div class="modal-header border-0 position-relative" style="background: linear-gradient(135deg, #0a2540 0%, #0369a1 50%, #075985 100%); padding: 28px 30px 24px; min-height: 110px;">
                    <div class="position-absolute w-100 h-100 top-0 start-0" style="background: url('img/Puerto01.webp') center/cover no-repeat; opacity: 0.12;"></div>
                    <div class="position-relative">
                        <div class="d-flex align-items-center mb-1">
                            <div style="width: 42px; height: 42px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 14px;">
                                <i class="bi bi-search" style="font-size: 1.2rem; color: #7dd3fc;"></i>
                            </div>
                            <div>
                                <h5 class="modal-title text-white fw-bold mb-0" style="font-size: 1.2rem;">Seguimiento de Ticket</h5>
                                <small style="color: rgba(255,255,255,0.6);">Mesa de Ayuda — Soporte TI</small>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-relative" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- Búsqueda -->
                    <div style="padding: 24px 28px; background: linear-gradient(180deg, #f0f9ff 0%, #fff 100%);">
                        <p class="text-muted mb-3" style="font-size: 0.92rem;">Ingresa el código que recibiste al crear tu ticket para consultar su estado actual.</p>
                        <form id="consultarTicketForm" onsubmit="buscarTicket(event)">
                            <div class="d-flex gap-2">
                                <div class="input-group flex-grow-1" style="border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                    <span class="input-group-text border-0" style="background: #0369a1; color: white; padding: 0 14px;">
                                        <i class="bi bi-ticket-perforated"></i>
                                    </span>
                                    <input type="text" name="ticket_number" id="ticketNumberInput" class="form-control border-0 py-3"
                                           placeholder="Ej: TK-20260316-A1B2C" required style="font-size: 1rem; font-weight: 500;">
                                </div>
                                <button type="submit" id="btnBuscarTicket" class="btn text-white px-4"
                                        style="background: linear-gradient(135deg, #0369a1, #075985); border-radius: 12px; white-space: nowrap;">
                                    <i class="bi bi-search me-1"></i> Buscar
                                </button>
                            </div>
                            <div class="d-flex align-items-center mt-2 gap-2">
                                <i class="bi bi-info-circle text-muted" style="font-size: 0.8rem;"></i>
                                <small class="text-muted" style="font-size: 0.78rem;">El código fue enviado a tu correo al crear el ticket. Formato: TK-AAAAMMDD-XXXXX</small>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Resultado de la búsqueda -->
                    <div id="ticketResultado" style="display:none; padding: 0 28px 24px;">
                        <hr class="mt-0">
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
                                    <div class="p-3 rounded-3" style="background: #f0f9ff;">
                                        <p class="text-muted small mb-1"><i class="bi bi-tag me-1"></i>Categoría</p>
                                        <p class="fw-semibold mb-0" id="tkCategory"></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background: #fef3c7;">
                                        <p class="text-muted small mb-1"><i class="bi bi-flag me-1"></i>Prioridad</p>
                                        <p class="fw-semibold mb-0" id="tkPriorityText"></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3" style="background: #f0fdf4;">
                                        <p class="text-muted small mb-1"><i class="bi bi-person me-1"></i>Asignado a</p>
                                        <p class="fw-semibold mb-0" id="tkAssigned"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-muted small mb-1"><i class="bi bi-card-text me-1"></i>Descripción</p>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    
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

            fetch('api/buscar_ticket?ticket_number=' + encodeURIComponent(numero))
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

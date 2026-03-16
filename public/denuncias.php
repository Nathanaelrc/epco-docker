<?php
/**
 * EPCO - Portal de Integridad / Canal de Denuncias
 * Diseño inspirado en portales corporativos de integridad
 */
require_once '../includes/bootstrap.php';
$pageTitle = 'Portal de Integridad';

$user = isLoggedIn() ? getCurrentUser() : null;

// Detectar origen para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'panel_intranet.php' : 'index.php';
$denunciaCreateUrl = $fromIntranet ? 'denuncia_create.php?from=intranet' : 'denuncia_create.php';
$denunciaSeguimientoUrl = $fromIntranet ? 'denuncia_seguimiento.php?from=intranet' : 'denuncia_seguimiento.php';
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
    <title>Empresa Portuaria Coquimbo - Portal de Integridad</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link rel="shortcut icon" type="image/webp" href="img/Logo01.webp">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/denuncias.css" rel="stylesheet">
    <style>
        .main-header { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(3,105,161,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto01.webp" : "img/Puerto01.jpeg" ?>') center/cover no-repeat; }
    </style>
</head>
<body>
    <!-- Topbar Portal de Integridad -->
    <div class="epco-topbar">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="topbar-back-btn" title="Volver al Inicio">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="logo-text" style="font-size: 1.15rem; font-weight: 700; color: white;">Empresa Portuaria Coquimbo</span>
        </div>
        
        <div class="topbar-right">
            <span class="topbar-badge">
                <i class="bi bi-shield-check"></i>
                Portal de Integridad
            </span>
            
            <a href="iniciar_sesion.php?redirect=denuncias_admin" class="btn btn-light btn-sm d-flex align-items-center gap-2" style="border-radius: 10px; font-weight: 600;">
                <i class="bi bi-box-arrow-in-right"></i>
                <span class="d-none d-sm-inline">Iniciar Sesión</span>
            </a>
        </div>
    </div>
    
    <!-- Header Principal -->
    <header class="main-header">
        <div class="header-content">
            <div class="container text-center">
                <picture>
                    <source srcset="img/Logo01.webp" type="image/webp">
                    <img src="img/Logo01.png" alt="Logo Empresa Portuaria Coquimbo" class="header-logo mb-3" style="width: 100px; height: auto; filter: drop-shadow(0 4px 15px rgba(0,0,0,0.3));" loading="eager" width="100" height="100">
                </picture>
                <div class="header-badge">
                    <i class="bi bi-building"></i>
                    <span>Empresa Portuaria Coquimbo</span>
                </div>
                <h1 class="header-title">Portal de Integridad</h1>
                <p class="header-subtitle mx-auto">
                    Canal seguro, anónimo y confidencial para reportar conductas que atenten contra la ética y normativa legal vigente.
                </p>
            </div>
        </div>
    </header>
    
    <!-- Cards de Acción -->
    <section class="action-cards">
        <div class="container">
            <!-- Card Principal - Realizar Denuncia -->
            <div class="row g-4 justify-content-center mb-4">
                <div class="col-lg-6 col-md-8 fade-up">
                    <a href="<?= $denunciaCreateUrl ?>" class="action-card primary">
                        <div class="action-icon">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <h4>Realizar Denuncia</h4>
                        <p>Presenta una denuncia de forma segura. Puedes hacerlo de manera anónima.</p>
                        <span class="action-btn">
                            Ir al formulario <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
            </div>
            
            <!-- Cards Secundarias - Instructivo, Información, Consultar Estado -->
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="#" class="action-card tertiary" data-bs-toggle="modal" data-bs-target="#instructivoModal">
                        <div class="action-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h4>Instructivo</h4>
                        <p>Conoce el paso a paso para realizar tu denuncia correctamente.</p>
                        <span class="action-btn">
                            Ver instrucciones <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
                
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="#" class="action-card quaternary" data-bs-toggle="modal" data-bs-target="#infoModal">
                        <div class="action-icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <h4>Información</h4>
                        <p>Conoce tus derechos y el marco legal que te protege.</p>
                        <span class="action-btn">
                            Más información <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
                
                <div class="col-lg-4 col-md-6 fade-up">
                    <a href="#" class="action-card secondary" data-bs-toggle="modal" data-bs-target="#consultarDenunciaModal">
                        <div class="action-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h4>Consultar Estado</h4>
                        <p>Revisa el estado de tu denuncia usando tu código de seguimiento.</p>
                        <span class="action-btn">
                            Consultar <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Introducción -->
    <section class="info-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="intro-card">
                        <h5><i class="bi bi-megaphone me-2"></i>Introducción</h5>
                        <p>
                            El canal de denuncias es un medio seguro, anónimo y confidencial para todos los colaboradores, proveedores, clientes y demás grupos de interés; para que quienes conozcan o cuenten con evidencia de alguna actividad sospechosa, conductas o prácticas potencialmente deshonestas que atenten contra la ética, la normativa legal vigente y los valores organizacionales que infrinjan el código de conducta y demás normas internas de la compañía, puedan notificarlo de forma segura y oportuna, de manera que su denuncia pueda ser analizada por personal altamente calificado y tomar las acciones que se consideren necesarias.
                        </p>
                    </div>
                    
                    <!-- Ley Karin Banner -->
                    <div class="law-banner">
                        <div class="law-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h5><i class="bi bi-bookmark-star me-2"></i>Ley Karin - Ley 21.643</h5>
                            <p>
                                Este canal cumple con la Ley Karin que modifica el Código del Trabajo en materia de prevención, investigación y sanción del acoso laboral, sexual y violencia en el trabajo. Garantizamos un ambiente laboral libre de violencia y tu derecho a denunciar sin represalias.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Conductas Denunciables -->
    <section class="conduct-section" id="conduct-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Conductas Denunciables</h2>
                <p>A través de este canal puedes reportar las siguientes situaciones</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(220, 38, 38, 0.1); color: #dc2626;">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <h5>Acoso Laboral</h5>
                        <p>Hostigamiento reiterado que menoscabe, maltrate o humille al trabajador, afectando su dignidad.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(219, 39, 119, 0.1); color: #db2777;">
                            <i class="bi bi-gender-ambiguous"></i>
                        </div>
                        <h5>Acoso Sexual</h5>
                        <p>Requerimientos de carácter sexual no consentidos que amenacen o perjudiquen la situación laboral.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                            <i class="bi bi-lightning-fill"></i>
                        </div>
                        <h5>Violencia Laboral</h5>
                        <p>Conductas que afecten física o psicológicamente al trabajador en el contexto laboral.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(124, 58, 237, 0.1); color: #7c3aed;">
                            <i class="bi bi-slash-circle-fill"></i>
                        </div>
                        <h5>Discriminación</h5>
                        <p>Trato desigual basado en raza, género, edad, religión u otras características protegidas.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <h5>Fraude o Corrupción</h5>
                        <p>Actos de deshonestidad, soborno, malversación de fondos o conflictos de interés.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(5, 150, 105, 0.1); color: #059669;">
                            <i class="bi bi-file-earmark-x-fill"></i>
                        </div>
                        <h5>Incumplimiento Normativo</h5>
                        <p>Violaciones al código de conducta, políticas internas o regulaciones legales.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <h5>Riesgos de Seguridad</h5>
                        <p>Situaciones que pongan en peligro la seguridad de personas, información o activos.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="conduct-card">
                        <div class="conduct-icon" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;">
                            <i class="bi bi-three-dots"></i>
                        </div>
                        <h5>Otras Conductas</h5>
                        <p>Cualquier otra conducta que atente contra la ética y valores organizacionales.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Garantías -->
    <section class="guarantees-section" id="guarantees-section">
        <div class="container">
            <div class="section-header">
                <h2 class="text-white"><i class="bi bi-shield-lock me-2"></i>Garantías del Proceso</h2>
                <p class="text-white-50">Tu seguridad y protección son nuestra prioridad</p>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="guarantee-item">
                        <div class="guarantee-icon">
                            <i class="bi bi-incognito"></i>
                        </div>
                        <h5>Anonimato</h5>
                        <p>Puedes realizar tu denuncia sin revelar tu identidad</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="guarantee-item">
                        <div class="guarantee-icon">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <h5>Confidencialidad</h5>
                        <p>Tu información es tratada con absoluta reserva</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="guarantee-item">
                        <div class="guarantee-icon">
                            <i class="bi bi-shield-fill-check"></i>
                        </div>
                        <h5>Protección</h5>
                        <p>Garantía de no represalias contra el denunciante</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="guarantee-item">
                        <div class="guarantee-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h5>Imparcialidad</h5>
                        <p>Investigación objetiva por personal calificado</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Proceso -->
    <section class="process-section" id="process-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-diagram-3 me-2"></i>Proceso de Investigación</h2>
                <p>Conoce las etapas del proceso una vez presentada tu denuncia</p>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="process-number">1</div>
                        <h5>Recepción</h5>
                        <p>Tu denuncia es recibida y registrada (hasta 3 días)</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="process-number">2</div>
                        <h5>Evaluación</h5>
                        <p>Se analiza la admisibilidad de la denuncia</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="process-number">3</div>
                        <h5>Investigación</h5>
                        <p>Proceso de investigación (hasta 30 días)</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="process-number">4</div>
                        <h5>Resolución</h5>
                        <p>Conclusiones y medidas correctivas</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p class="mb-2">
                <strong>Empresa Portuaria Coquimbo</strong> - Portal de Integridad
            </p>
            <p class="small text-white-50 mb-0">
                © <?= date('Y') ?> Todos los derechos reservados | 
                <a href="<?= $backUrl ?>">Volver al Portal</a>
            </p>
        </div>
    </footer>
    
    <!-- Modal Instructivo -->
    <div class="modal fade" id="instructivoModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6); border: none;">
                    <h5 class="modal-title text-white"><i class="bi bi-file-earmark-text me-2"></i>Instrucciones para Denunciar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">1</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Accede al formulario</h6>
                                    <p class="text-muted small mb-0">Haz clic en "Realizar Denuncia" para acceder al formulario seguro.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">2</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Elige si ser anónimo</h6>
                                    <p class="text-muted small mb-0">Puedes denunciar sin identificarte marcando la opción anónima.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">3</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Completa los datos</h6>
                                    <p class="text-muted small mb-0">Proporciona la mayor cantidad de detalles sobre los hechos.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">4</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Adjunta evidencias</h6>
                                    <p class="text-muted small mb-0">Si tienes documentos o imágenes que respalden tu denuncia.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">5</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Envía tu denuncia</h6>
                                    <p class="text-muted small mb-0">Revisa la información y envía. Recibirás un código de seguimiento.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 700;">✓</div>
                                </div>
                                <div>
                                    <h6 class="fw-bold">Guarda tu código</h6>
                                    <p class="text-muted small mb-0">Es importante para consultar el estado de tu denuncia.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-4 mb-0" style="border-radius: 12px;">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Importante:</strong> Proporciona información veraz y detallada. Las denuncias falsas pueden tener consecuencias legales.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Información -->
    <div class="modal fade" id="infoModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); border: none;">
                    <h5 class="modal-title text-white"><i class="bi bi-info-circle me-2"></i>Información Legal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-bookmark-star me-2"></i>Ley Karin - Ley 21.643</h6>
                    <p class="text-muted">
                        La Ley 21.643 (conocida como Ley Karin) modifica el Código del Trabajo y otros cuerpos legales en materia de prevención, investigación y sanción del acoso laboral, sexual y violencia en el trabajo.
                    </p>
                    
                    <h6 class="fw-bold text-primary mb-3 mt-4"><i class="bi bi-check2-square me-2"></i>Tus Derechos</h6>
                    <ul class="text-muted">
                        <li>Derecho a un ambiente laboral libre de violencia</li>
                        <li>Derecho a denunciar sin temor a represalias</li>
                        <li>Derecho a la confidencialidad durante todo el proceso</li>
                        <li>Derecho a conocer el resultado de la investigación</li>
                        <li>Derecho a medidas de protección si es necesario</li>
                    </ul>
                    
                    <h6 class="fw-bold text-primary mb-3 mt-4"><i class="bi bi-clock-history me-2"></i>Plazos Legales</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="bg-light rounded-3 p-3 text-center">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;">3 días</div>
                                <small class="text-muted">Recepción de denuncia</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded-3 p-3 text-center">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;">30 días</div>
                                <small class="text-muted">Investigación</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded-3 p-3 text-center">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;">15 días</div>
                                <small class="text-muted">Aplicación medidas</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Consultar Denuncia -->
    <!-- Modal Consultar Denuncia -->
    <div class="modal fade" id="consultarDenunciaModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
                    <h5 class="modal-title text-white"><i class="bi bi-search me-2"></i>Consultar Estado de Denuncia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-4">Ingresa tu código de seguimiento para consultar el estado de tu denuncia de forma confidencial.</p>
                    <form action="denuncia_seguimiento.php" method="POST" id="consultarDenunciaForm">
                        <?php if ($fromIntranet): ?>
                        <input type="hidden" name="from" value="intranet">
                        <?php endif; ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Código de Seguimiento</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-shield-lock"></i></span>
                                <input type="text" name="complaint_number" class="form-control form-control-lg" placeholder="Ej: DN-20260129-A1B2C" required style="border-radius: 0 10px 10px 0;">
                            </div>
                            <div class="form-text">El código tiene el formato DN-YYYYMMDD-XXXXX (ej: DN-20260129-A1B2C)</div>
                        </div>
                        <div class="alert alert-info d-flex align-items-center mb-4" style="border-radius: 12px;">
                            <i class="bi bi-shield-check me-2 fs-5"></i>
                            <small>Tu consulta es completamente confidencial y no deja registro de tu identidad.</small>
                        </div>
                        <button type="submit" class="btn btn-lg w-100 text-white" style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px;">
                            <i class="bi bi-search me-2"></i>Consultar Estado
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        // Smooth scroll para los enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && !href.includes('Modal')) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>

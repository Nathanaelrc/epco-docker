<?php
/**
 * EPCO - Crear Denuncia Ley Karin
 */
require_once '../includes/bootstrap.php';

// Detectar origen para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'denuncias.php?from=intranet' : 'denuncias.php';
$backText = 'Volver al Canal de Denuncias';

$success = '';
$error = '';
$complaintNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $complainantName = $isAnonymous ? null : sanitize($_POST['complainant_name'] ?? '');
    $complainantEmail = $isAnonymous ? null : sanitize($_POST['complainant_email'] ?? '');
    $complainantPhone = $isAnonymous ? null : sanitize($_POST['complainant_phone'] ?? '');
    $accusedName = sanitize($_POST['accused_name'] ?? '');
    $accusedDepartment = sanitize($_POST['accused_department'] ?? '');
    $incidentDate = sanitize($_POST['incident_date'] ?? '');
    $incidentLocation = sanitize($_POST['incident_location'] ?? '');
    $complaintType = sanitize($_POST['complaint_type'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $evidenceDescription = sanitize($_POST['evidence_description'] ?? '');
    $witnesses = sanitize($_POST['witnesses'] ?? '');
    
    if (empty($accusedName) || empty($incidentDate) || empty($complaintType) || empty($description)) {
        $error = 'Por favor complete todos los campos obligatorios.';
    } else {
        $complaintNumber = generateComplaintNumber();
        
        $stmt = $pdo->prepare('
            INSERT INTO complaints (complaint_number, reporter_name, reporter_email, reporter_phone, 
                is_anonymous, accused_name, accused_department, incident_date, incident_location, 
                complaint_type, description, evidence_description, witnesses) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $complaintNumber, $complainantName, $complainantEmail, $complainantPhone,
            $isAnonymous, $accusedName, $accusedDepartment, $incidentDate, $incidentLocation,
            $complaintType, $description, $evidenceDescription, $witnesses
        ]);
        
        // Log inicial
        $stmt = $pdo->prepare('INSERT INTO complaint_logs (complaint_id, action, description) VALUES (?, ?, ?)');
        $stmt->execute([$pdo->lastInsertId(), 'Denuncia recibida', 'La denuncia ha sido registrada en el sistema.']);
        
        // Enviar notificacion por correo al Comite de Etica
        $complaintData = [
            'type' => $complaintType,
            'is_anonymous' => $isAnonymous,
            'incident_date' => $incidentDate,
            'accused_name' => $accusedName,
            'location' => $incidentLocation,
            'description' => $description
        ];
        $emailsSent = notifyNewComplaint($complaintData, $complaintNumber);
        
        $success = "Denuncia registrada exitosamente. Tu numero de seguimiento es: <strong>$complaintNumber</strong>";
        if ($emailsSent > 0) {
            $success .= "<br><small class='text-muted'>Se ha notificado al Comite de Etica.</small>";
        }
    }
}

$pageTitle = 'Crear Denuncia';
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
    <title>Empresa Portuaria Coquimbo - Realizar Denuncia</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="css/denuncia-form.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto03.webp" : "img/Puerto03.jpg" ?>') center/cover no-repeat fixed; }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="form-card">
                    <div class="form-header">
                        <i class="bi bi-shield-check text-white mb-2" style="font-size: 3rem;"></i>
                        <h2 class="text-white fw-bold mb-0">Realizar Denuncia</h2>
                        <p class="text-white-50 mb-0">Ley Karin - Confidencial y Seguro</p>
                    </div>
                    
                    <div class="p-5">
                        <?php if ($success): ?>
                        <div class="alert alert-success rounded-4 mb-4">
                            <i class="bi bi-check-circle me-2"></i><?= $success ?>
                            <hr>
                            <p class="mb-0"><strong>Importante:</strong> Guarda este número para consultar el estado de tu denuncia. La información será tratada con absoluta confidencialidad.</p>
                        </div>
                        <div class="text-center">
                            <a href="denuncia_seguimiento.php" class="btn btn-submit text-white">
                                <i class="bi bi-search me-2"></i>Consultar seguimiento
                            </a>
                        </div>
                        <?php else: ?>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4 mb-4">
                            <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <!-- Opción anónima -->
                            <div class="bg-light rounded-4 p-4 mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_anonymous" id="is_anonymous" onchange="toggleAnonymous()">
                                    <label class="form-check-label fw-semibold" for="is_anonymous">
                                        <i class="bi bi-incognito me-2"></i>Deseo realizar esta denuncia de forma anónima
                                    </label>
                                </div>
                                <p class="text-muted small mt-2 mb-0">Si marcas esta opción, no se solicitarán tus datos personales.</p>
                            </div>
                            
                            <!-- Datos del denunciante -->
                            <div id="complainant-section">
                                <h5 class="section-title"><i class="bi bi-person me-2"></i>Datos del Denunciante</h5>
                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nombre Completo</label>
                                        <input type="text" name="complainant_name" class="form-control" placeholder="Tu nombre completo">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Correo Electrónico</label>
                                        <input type="email" name="complainant_email" class="form-control" placeholder="tu@correo.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Teléfono de Contacto</label>
                                        <input type="tel" name="complainant_phone" class="form-control" placeholder="+56 9 XXXX XXXX">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Datos del denunciado -->
                            <h5 class="section-title"><i class="bi bi-person-x me-2"></i>Datos del Denunciado</h5>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nombre del Denunciado *</label>
                                    <input type="text" name="accused_name" class="form-control" placeholder="Nombre de la persona denunciada" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Departamento / Área</label>
                                    <input type="text" name="accused_department" class="form-control" placeholder="Área donde trabaja">
                                </div>
                            </div>
                            
                            <!-- Datos del incidente -->
                            <h5 class="section-title"><i class="bi bi-calendar-event me-2"></i>Datos del Incidente</h5>
                            <div class="row g-4 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fecha del Incidente *</label>
                                    <input type="date" name="incident_date" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Lugar del Incidente</label>
                                    <input type="text" name="incident_location" class="form-control" placeholder="Ubicación">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Tipo de Denuncia *</label>
                                    <select name="complaint_type" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="acoso_laboral">Acoso Laboral</option>
                                        <option value="acoso_sexual">Acoso Sexual</option>
                                        <option value="violencia_laboral">Violencia Laboral</option>
                                        <option value="discriminacion">Discriminación</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <h5 class="section-title"><i class="bi bi-file-text me-2"></i>Descripción de los Hechos</h5>
                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Descripción Detallada *</label>
                                    <textarea name="description" class="form-control" rows="5" placeholder="Describe los hechos con el mayor detalle posible: qué ocurrió, cuándo, cómo, frecuencia, etc." required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Evidencias (descripción)</label>
                                    <textarea name="evidence_description" class="form-control" rows="3" placeholder="Describe si cuentas con evidencias: correos, mensajes, grabaciones, documentos, etc."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Testigos</label>
                                    <textarea name="witnesses" class="form-control" rows="2" placeholder="Indica si hay testigos de los hechos (nombres y relación)"></textarea>
                                </div>
                            </div>
                            
                            <!-- Submit -->
                            <div class="bg-light rounded-4 p-4 mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="accept_terms" required>
                                    <label class="form-check-label" for="accept_terms">
                                        Declaro que la información proporcionada es veraz y estoy en conocimiento de las implicancias legales de realizar una denuncia falsa.
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-submit btn-lg text-white w-100">
                                <i class="bi bi-send me-2"></i>Enviar Denuncia
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="<?php echo $backUrl; ?>" class="text-white-50 text-decoration-none">
                        <i class="bi bi-arrow-left me-2"></i><?php echo $backText; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleAnonymous() {
            const section = document.getElementById('complainant-section');
            const checkbox = document.getElementById('is_anonymous');
            section.style.display = checkbox.checked ? 'none' : 'block';
        }
    </script>
</body>
</html>

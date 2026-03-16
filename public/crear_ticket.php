<?php
/**
 * EPCO - Crear Ticket de Soporte con subida de archivos
 */
require_once '../includes/bootstrap.php';
require_once '../includes/ServicioCorreo.php';

$success = '';
$error = '';
$ticketNumber = '';

// Detectar desde dónde viene el usuario para el botón volver
$fromIntranet = isset($_GET['from']) && $_GET['from'] === 'intranet';
$backUrl = $fromIntranet ? 'panel_intranet.php' : 'soporte.php';
$backText = $fromIntranet ? 'Volver a Intranet' : 'Volver a Soporte';

// Configuración de uploads
$uploadDir = __DIR__ . '/uploads/tickets/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$maxFileSize = 5 * 1024 * 1024; // 5MB
$maxFiles = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $category = sanitize($_POST['category'] ?? 'otro');
    $priority = sanitize($_POST['priority'] ?? 'media');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (empty($name) || empty($email) || empty($title) || empty($description)) {
        $error = 'Por favor complete todos los campos obligatorios.';
    } else {
        // Verificar si el usuario existe, si no, crear uno temporal
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $tempPass = password_hash(uniqid(), PASSWORD_BCRYPT);
            // Generar username desde el nombre
            $username = generateUsername($name);
            // Si el username ya existe, agregar un número
            $baseUsername = $username;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if (!$stmt->fetch()) break;
                $username = $baseUsername . $counter;
                $counter++;
            }
            $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, "user")');
            $stmt->execute([$name, $username, $email, $tempPass]);
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $user['id'];
        }
        
        // Crear ticket
        $ticketNumber = generateTicketNumber();
        $stmt = $pdo->prepare('INSERT INTO tickets (ticket_number, user_id, user_name, user_email, category, priority, title, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ticketNumber, $userId, $name, $email, $category, $priority, $title, $description]);
        $ticketId = $pdo->lastInsertId();
        
        // Procesar archivos adjuntos
        $uploadedFiles = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            // Crear directorio del ticket
            $ticketDir = $uploadDir . $ticketNumber . '/';
            if (!is_dir($ticketDir)) {
                mkdir($ticketDir, 0755, true);
            }
            
            $fileCount = min(count($_FILES['attachments']['name']), $maxFiles);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['attachments']['tmp_name'][$i];
                    $fileName = $_FILES['attachments']['name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];
                    $fileType = $_FILES['attachments']['type'][$i];
                    
                    // Validar tipo y tamaño
                    if (in_array($fileType, $allowedTypes) && $fileSize <= $maxFileSize) {
                        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
                        $destination = $ticketDir . $newFileName;
                        
                        if (move_uploaded_file($tmpName, $destination)) {
                            // Comprimir imagen automáticamente si es una imagen
                            comprimirImagenSubida($destination);
                            $uploadedFiles[] = $newFileName;
                        }
                    }
                }
            }
            
            // Guardar referencia de archivos en la descripción o en comentario
            if (!empty($uploadedFiles)) {
                $filesText = "Archivos adjuntos: " . implode(", ", $uploadedFiles);
                $stmt = $pdo->prepare('INSERT INTO ticket_comments (ticket_id, user_name, comment, is_internal) VALUES (?, ?, ?, 0)');
                $stmt->execute([$ticketId, $name, $filesText]);
            }
        }
        
        // Enviar notificación por correo
        try {
            $mailService = new MailService();
            $ticketData = [
                'id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'subject' => $title,
                'category' => $category,
                'priority' => $priority,
                'status' => 'abierto',
                'user_name' => $name,
                'user_email' => $email,
                'user_phone' => $_POST['phone'] ?? '',
                'department' => $_POST['department'] ?? 'No especificado',
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $mailService->sendTicketCreatedNotification($ticketData);
            $mailService->sendTicketConfirmationToUser($ticketData);
        } catch (Exception $e) {
            // Log error but don't break the ticket creation
            error_log("Error enviando correo de notificación: " . $e->getMessage());
        }
        
        // Registrar en auditoría
        logActivity($userId, 'ticket_created', 'tickets', $ticketId, "Ticket $ticketNumber creado: $title");
        
        $success = "Ticket creado exitosamente. Tu número de ticket es: <strong>$ticketNumber</strong>";
        if (!empty($uploadedFiles)) {
            $success .= "<br><small class='text-muted'>Se adjuntaron " . count($uploadedFiles) . " archivo(s)</small>";
        }
    }
}

$pageTitle = 'Crear Ticket';
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
    <title>Empresa Portuaria Coquimbo - Nuevo Ticket de Soporte</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
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

        /* ========== TOPBAR ========== */
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

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-brand img {
            height: 34px;
        }

        .topbar-brand span {
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        /* ========== HERO HEADER ========== */
        .main-header {
            background: linear-gradient(135deg, rgba(3,105,161,0.75) 0%, rgba(7,89,133,0.8) 50%, rgba(3,105,161,0.75) 100%),
                        url('<?= WEBP_SUPPORT ? "img/Puerto01.webp" : "img/Puerto01.jpeg" ?>') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }

        .header-content {
            padding: 50px 0 80px;
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
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 3px 15px rgba(0,0,0,0.4), 0 1px 3px rgba(0,0,0,0.3);
        }

        .header-subtitle {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            max-width: 550px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }

        /* ========== FORM CARD ========== */
        .form-wrapper {
            max-width: 800px;
            margin: -50px auto 0;
            padding: 0 20px 60px;
            position: relative;
            z-index: 10;
        }

        .form-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1), 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .form-card-header {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
            padding: 28px 36px;
        }

        .form-card-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0c4a6e;
            margin: 0 0 4px 0;
        }

        .form-card-header p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
        }

        .form-card-body {
            padding: 36px;
        }

        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0f2fe;
        }

        .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 2px;
        }

        .form-control, .form-select {
            border-radius: 12px;
            padding: 11px 16px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.9rem;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #fff;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 130px;
        }

        .form-text {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 28px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }

        .file-upload-area:hover {
            border-color: #0ea5e9;
            background: #f0f9ff;
        }

        .file-upload-area.dragover {
            border-color: #0ea5e9;
            background: #e0f2fe;
        }

        .file-upload-area .upload-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #334155;
            margin-bottom: 4px;
        }

        .file-upload-area .upload-hint {
            font-size: 0.8rem;
            color: #94a3b8;
            margin: 0;
        }

        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .file-preview-item {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-preview-item .remove-btn {
            cursor: pointer;
            color: #94a3b8;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1;
            transition: color 0.2s;
        }

        .file-preview-item .remove-btn:hover {
            color: #dc3545;
        }

        .btn-primary-submit {
            background: linear-gradient(135deg, #0369a1, #075985);
            border: none;
            border-radius: 12px;
            padding: 12px 32px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            transition: all 0.3s;
        }

        .btn-primary-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(3,105,161,0.3);
            color: #fff;
        }

        .btn-cancel {
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 500;
            font-size: 0.95rem;
            color: #475569;
            background: #fff;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .alert-success-custom {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 24px 28px;
            color: #166534;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .alert-success-custom h5 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #15803d;
        }

        .alert-danger-custom {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 14px 20px;
            color: #991b1b;
            font-size: 0.9rem;
        }

        .actions-bar {
            padding: 22px 36px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-section + .form-section {
            margin-top: 30px;
        }

        @media (max-width: 576px) {
            .form-card-body { padding: 20px; }
            .form-card-header { padding: 20px; }
            .actions-bar { padding: 16px 20px; }
            .form-wrapper { padding: 0 12px 40px; }
            .header-title { font-size: 1.8rem; }
            .header-content { padding: 35px 0 65px; }
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="epco-topbar">
        <a href="<?= $backUrl ?>" class="topbar-back-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="topbar-brand">
            <img src="img/logo5-1.png" alt="Empresa Portuaria Coquimbo" loading="eager">
            <span>Mesa de Ayuda</span>
        </div>
        <div></div>
    </div>

    <!-- Hero Header -->
    <div class="main-header">
        <div class="container header-content">
            <div class="header-badge">Soporte TI</div>
            <h1 class="header-title">Nuevo Ticket de Soporte</h1>
            <p class="header-subtitle">Complete el formulario a continuación para registrar su solicitud. Nuestro equipo la atenderá a la brevedad.</p>
        </div>
    </div>

    <!-- Form -->
    <div class="form-wrapper">
        <?php if ($success): ?>
        <div class="alert-success-custom mb-4">
            <h5>Ticket registrado correctamente</h5>
            <p class="mb-3"><?= $success ?></p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="seguimiento_ticket.php?ticket=<?= $ticketNumber ?><?= $fromIntranet ? '&from=intranet' : '' ?>" class="btn btn-primary-submit">Ver ticket</a>
                <a href="<?= $backUrl ?>" class="btn btn-cancel"><?= $backText ?></a>
            </div>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert-danger-custom mb-4"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" id="ticketForm">
            <div class="form-card">
                <div class="form-card-header">
                    <h2>Formulario de Solicitud</h2>
                    <p>Complete el formulario para registrar su solicitud. Los campos marcados con (<span class="text-danger">*</span>) son obligatorios.</p>
                </div>

                <div class="form-card-body">
                    <!-- Sección: Información del solicitante -->
                    <div class="form-section">
                        <div class="section-title">Información del solicitante</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre completo <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="Ingrese su nombre completo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo electrónico <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" required placeholder="ejemplo@puertocoquimbo.cl">
                                <div class="form-text">Se utilizará para notificaciones sobre el estado de su ticket.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Clasificación -->
                    <div class="form-section">
                        <div class="section-title">Clasificación del problema</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Categoría <span class="required">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Seleccione --</option>
                                    <option value="hardware">Hardware (equipos, periféricos)</option>
                                    <option value="software">Software (programas, aplicaciones)</option>
                                    <option value="red">Red e Internet (conectividad)</option>
                                    <option value="acceso">Accesos y Permisos (contraseñas, cuentas)</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prioridad <span class="required">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <option value="baja">Baja — Puede esperar</option>
                                    <option value="media" selected>Media — Importancia normal</option>
                                    <option value="alta">Alta — Requiere atención pronta</option>
                                    <option value="urgente">Urgente — Impacto crítico en operaciones</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Detalle del problema -->
                    <div class="form-section">
                        <div class="section-title">Detalle del problema</div>
                        <div class="mb-3">
                            <label class="form-label">Asunto <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="Describa brevemente el problema" maxlength="200">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Descripción <span class="required">*</span></label>
                            <textarea name="description" class="form-control" rows="6" required placeholder="Proporcione la mayor cantidad de información posible:&#10;&#10;- ¿Qué estaba realizando cuando ocurrió el problema?&#10;- ¿Apareció algún mensaje de error?&#10;- ¿Desde cuándo se presenta la situación?&#10;- ¿Ha intentado alguna solución?"></textarea>
                        </div>
                    </div>

                    <!-- Sección: Archivos adjuntos -->
                    <div class="form-section">
                        <div class="section-title">Archivos adjuntos <span class="fw-normal text-muted" style="text-transform:none; letter-spacing:0;">(opcional)</span></div>
                        <div class="file-upload-area" id="dropZone" onclick="document.getElementById('fileInput').click()">
                            <p class="upload-label mb-1">Arrastre archivos aquí o haga clic para seleccionar</p>
                            <p class="upload-hint">Formatos permitidos: Imágenes, PDF, Word &mdash; Máx. 5 MB por archivo, hasta 5 archivos</p>
                        </div>
                        <input type="file" name="attachments[]" id="fileInput" multiple accept="image/*,.pdf,.doc,.docx" style="display:none" onchange="handleFiles(this.files)">
                        <div class="file-preview" id="filePreview"></div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="actions-bar">
                    <a href="<?= $backUrl ?>" class="btn btn-cancel">Cancelar</a>
                    <button type="submit" class="btn btn-primary-submit">Enviar Ticket</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div><!-- /form-wrapper -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const filePreview = document.getElementById('filePreview');
        const fileInput = document.getElementById('fileInput');
        let selectedFiles = [];
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });
        
        dropZone.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
        
        function handleFiles(files) {
            const maxFiles = 5;
            const maxSize = 5 * 1024 * 1024;
            
            for (let i = 0; i < files.length && selectedFiles.length < maxFiles; i++) {
                if (files[i].size <= maxSize) {
                    selectedFiles.push(files[i]);
                }
            }
            
            updatePreview();
            updateFileInput();
        }
        
        function updatePreview() {
            filePreview.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'file-preview-item';
                const name = file.name.length > 28 ? file.name.substring(0, 25) + '...' : file.name;
                const size = (file.size / 1024).toFixed(0) + ' KB';
                div.innerHTML = `<span>${name} <span class="text-muted">(${size})</span></span>
                    <span class="remove-btn" onclick="removeFile(${index})" title="Eliminar">&times;</span>`;
                filePreview.appendChild(div);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updatePreview();
            updateFileInput();
        }
        
        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }
    </script>
</body>
</html>

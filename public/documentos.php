<?php
/**
 * EPCO - Gestión de Documentos
 */
require_once '../includes/bootstrap.php';

requireAuth('iniciar_sesion.php?redirect=documentos.php');
$user = getCurrentUser();

$isAdmin = in_array($user['role'], ['admin', 'soporte']);
$message = '';
$messageType = '';

// Crear directorio de uploads si no existe
$uploadDir = __DIR__ . '/uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforcePostCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload' && $isAdmin) {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            $originalName = $file['name'];
            $fileType = $file['type'];
            $fileSize = $file['size'];
            
            // Validar tipo de archivo
            $allowedTypes = ['application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain', 'image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $message = 'Tipo de archivo no permitido';
                $messageType = 'danger';
            } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB max
                $message = 'El archivo excede el tamaño máximo de 10MB';
                $messageType = 'danger';
            } else {
                // Validar extensión real del archivo
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
                
                // Verificar MIME real con finfo
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $realMime = $finfo->file($file['tmp_name']);
                
                if (!in_array($extension, $allowedExtensions) || !in_array($realMime, $allowedTypes)) {
                    $message = 'Tipo de archivo no permitido (extensión o contenido inválido)';
                    $messageType = 'danger';
                } else {
                // Generar nombre único
                $filename = uniqid('doc_') . '_' . time() . '.' . $extension;
                $filePath = 'uploads/documents/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $stmt = $pdo->prepare('
                        INSERT INTO documents (title, description, file_path, file_type, file_size, category, uploaded_by, is_public) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$title, $description, $filePath, $fileType, $fileSize, $category, $user['id'], $isPublic]);
                    
                    logActivity($user['id'], 'document_uploaded', 'documents', $pdo->lastInsertId(), "Documento '$title' subido");
                    
                    $message = 'Documento subido exitosamente';
                    $messageType = 'success';
                } else {
                    $message = 'Error al guardar el archivo';
                    $messageType = 'danger';
                }
                } // end extension/mime validation
            }
        } else {
            $message = 'Selecciona un archivo para subir';
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete' && $isAdmin) {
        $docId = (int)$_POST['doc_id'];
        
        // Obtener ruta del archivo
        $stmt = $pdo->prepare('SELECT file_path, title FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // Eliminar archivo físico
            $fullPath = __DIR__ . '/' . $doc['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Eliminar de BD
            $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ?');
            $stmt->execute([$docId]);
            
            logActivity($user['id'], 'document_deleted', 'documents', $docId, "Documento '{$doc['title']}' eliminado");
            
            $message = 'Documento eliminado';
            $messageType = 'success';
        }
    }
    
    if ($action === 'update' && $isAdmin) {
        $docId = (int)$_POST['doc_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        $stmt = $pdo->prepare('UPDATE documents SET title=?, description=?, category=?, is_public=? WHERE id=?');
        $stmt->execute([$title, $description, $category, $isPublic, $docId]);
        
        $message = 'Documento actualizado';
        $messageType = 'success';
    }
}

// Registrar descarga
if (isset($_GET['download'])) {
    $docId = (int)$_GET['download'];
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    if ($doc && ($doc['is_public'] || $isAdmin)) {
        // Incrementar contador
        $stmt = $pdo->prepare('UPDATE documents SET downloads = downloads + 1 WHERE id = ?');
        $stmt->execute([$docId]);
        
        $filePath = __DIR__ . '/' . $doc['file_path'];
        
        // Protección contra path traversal
        $realPath = realpath($filePath);
        $allowedDir = realpath(__DIR__ . '/uploads/documents/');
        if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
            header('HTTP/1.1 403 Forbidden');
            exit('Acceso denegado');
        }
        
        if (file_exists($realPath)) {
            header('Content-Type: ' . $doc['file_type']);
            header('Content-Disposition: attachment; filename="' . basename($doc['title']) . '.' . pathinfo($doc['file_path'], PATHINFO_EXTENSION) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
}

// Obtener documentos
$category = sanitize($_GET['category'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = $isAdmin ? '1=1' : 'is_public = 1';
$params = [];

if (!empty($category)) {
    $where .= ' AND category = ?';
    $params[] = $category;
}

if (!empty($search)) {
    $where .= ' AND (title LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("SELECT d.*, u.name as uploader_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Categorías
$categories = [
    'general' => ['name' => 'General', 'icon' => 'file-earmark', 'color' => 'secondary'],
    'rrhh' => ['name' => 'Recursos Humanos', 'icon' => 'people', 'color' => 'primary'],
    'ti' => ['name' => 'Tecnología', 'icon' => 'laptop', 'color' => 'info'],
    'legal' => ['name' => 'Legal', 'icon' => 'shield-check', 'color' => 'danger'],
    'finanzas' => ['name' => 'Finanzas', 'icon' => 'currency-dollar', 'color' => 'success'],
    'procedimientos' => ['name' => 'Procedimientos', 'icon' => 'list-check', 'color' => 'warning'],
];

// Estadísticas
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'downloads' => $pdo->query("SELECT SUM(downloads) FROM documents")->fetchColumn() ?: 0,
];

// Helper para iconos de tipo de archivo
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return 'file-earmark-pdf text-danger';
    if (strpos($mimeType, 'word') !== false) return 'file-earmark-word text-primary';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) return 'file-earmark-excel text-success';
    if (strpos($mimeType, 'powerpoint') !== false || strpos($mimeType, 'presentation') !== false) return 'file-earmark-ppt text-warning';
    if (strpos($mimeType, 'image') !== false) return 'file-earmark-image text-info';
    return 'file-earmark text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Documentos</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/documentos.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><i class="bi bi-folder me-3"></i>Documentos</h1>
                    <p class="mb-0 opacity-75">Biblioteca de documentos corporativos</p>
                </div>
                <?php if ($isAdmin): ?>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-cloud-upload me-2"></i>Subir Documento
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container pb-5" style="padding-top: 50px;">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <!-- Stats -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Total documentos</span>
                            <span class="fw-bold"><?= $stats['total'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total descargas</span>
                            <span class="fw-bold"><?= number_format($stats['downloads']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Categorías</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="documentos.php" class="list-group-item list-group-item-action <?= empty($category) ? 'active' : '' ?>">
                            <i class="bi bi-grid me-2"></i>Todas
                        </a>
                        <?php foreach ($categories as $catKey => $cat): ?>
                        <a href="?category=<?= $catKey ?>" class="list-group-item list-group-item-action <?= $category === $catKey ? 'active' : '' ?>">
                            <i class="bi bi-<?= $cat['icon'] ?> me-2"></i><?= $cat['name'] ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="col-lg-9">
                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-3">
                            <?php if ($category): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Buscar documentos..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Buscar</button>
                        </form>
                    </div>
                </div>

                <!-- Documents Grid -->
                <div class="row g-4">
                    <?php if (empty($documents)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-folder-x fs-1 text-muted mb-3 d-block"></i>
                                <h5 class="text-muted">No hay documentos</h5>
                                <p class="text-muted">No se encontraron documentos en esta categoría</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                    <div class="col-md-6">
                        <div class="card doc-card h-100 position-relative">
                            <span class="category-badge badge bg-<?= $categories[$doc['category']]['color'] ?? 'secondary' ?>">
                                <?= $categories[$doc['category']]['name'] ?? 'General' ?>
                            </span>
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="doc-icon bg-light me-3">
                                        <i class="bi bi-<?= getFileIcon($doc['file_type']) ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?= htmlspecialchars($doc['title']) ?></h5>
                                        <small class="text-muted">
                                            <?= formatFileSize($doc['file_size']) ?> • 
                                            <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if ($doc['description']): ?>
                                <p class="text-muted small mb-3"><?= htmlspecialchars(substr($doc['description'], 0, 100)) ?>...</p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-download me-1"></i><?= $doc['downloads'] ?> descargas
                                    </small>
                                    <div>
                                        <a href="?download=<?= $doc['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-download me-1"></i>Descargar
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDoc(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['title']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Subir Documento -->
    <?php if ($isAdmin): ?>
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Subir Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
            <?= csrfInput() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select name="category" class="form-select">
                                <?php foreach ($categories as $catKey => $cat): ?>
                                <option value="<?= $catKey ?>"><?= $cat['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Archivo *</label>
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-cloud-upload fs-1 text-muted mb-2 d-block"></i>
                                <p class="mb-1">Haz clic o arrastra un archivo aquí</p>
                                <small class="text-muted">PDF, Word, Excel, PowerPoint, Imágenes (máx. 10MB)</small>
                            </div>
                            <input type="file" name="document" id="fileInput" class="d-none" required>
                            <div id="fileName" class="mt-2 text-primary fw-semibold"></div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" name="is_public" class="form-check-input" id="isPublic" checked>
                            <label class="form-check-label" for="isPublic">Visible para todos los usuarios</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form para eliminar -->
    <form id="deleteForm" method="POST" class="d-none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="doc_id" id="deleteDocId">
    </form>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar nombre del archivo seleccionado
        document.getElementById('fileInput')?.addEventListener('change', function() {
            document.getElementById('fileName').textContent = this.files[0]?.name || '';
        });
        
        // Drag and drop
        const uploadArea = document.querySelector('.upload-area');
        if (uploadArea) {
            ['dragenter', 'dragover'].forEach(e => {
                uploadArea.addEventListener(e, () => uploadArea.classList.add('dragover'));
            });
            ['dragleave', 'drop'].forEach(e => {
                uploadArea.addEventListener(e, () => uploadArea.classList.remove('dragover'));
            });
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                document.getElementById('fileInput').files = e.dataTransfer.files;
                document.getElementById('fileName').textContent = e.dataTransfer.files[0]?.name || '';
            });
            uploadArea.addEventListener('dragover', e => e.preventDefault());
        }
        
        function deleteDoc(id, title) {
            if (confirm(`¿Eliminar el documento "${title}"?`)) {
                document.getElementById('deleteDocId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>

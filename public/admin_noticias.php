<?php
/**
 * EPCO - Gestión de Noticias (Admin/Social)
 */
require_once '../includes/bootstrap.php';

requireAuth('iniciar_sesion.php?redirect=admin_noticias.php');

$user = getCurrentUser();

// Admin, social y soporte pueden acceder
if (!in_array($user['role'], ['admin', 'social', 'soporte'])) {
    header("Location: panel_intranet.php");
    exit;
}

$success = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $imageUrl = sanitize($_POST['image_url'] ?? '');
        $newsUrl = sanitize($_POST['news_url'] ?? '');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            $error = 'El título y contenido son obligatorios.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO news (title, content, image_url, news_url, author_id, is_published) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $content, $imageUrl ?: null, $newsUrl ?: null, $user['id'], $isPublished]);
            $success = 'Noticia creada exitosamente.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $imageUrl = sanitize($_POST['image_url'] ?? '');
        $newsUrl = sanitize($_POST['news_url'] ?? '');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        
        if (empty($title) || empty($content)) {
            $error = 'El título y contenido son obligatorios.';
        } else {
            $stmt = $pdo->prepare('UPDATE news SET title = ?, content = ?, image_url = ?, news_url = ?, is_published = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $content, $imageUrl ?: null, $newsUrl ?: null, $isPublished, $id]);
            $success = 'Noticia actualizada exitosamente.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM news WHERE id = ?');
        $stmt->execute([$id]);
        $success = 'Noticia eliminada exitosamente.';
    } elseif ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE news SET is_published = NOT is_published WHERE id = ?');
        $stmt->execute([$id]);
        $success = 'Estado de publicación actualizado.';
    }
}

// Obtener todas las noticias
$news = $pdo->query('SELECT n.*, u.name as author_name FROM news n LEFT JOIN users u ON n.author_id = u.id ORDER BY n.created_at DESC')->fetchAll();

// Obtener noticia para editar si se solicita
$editNews = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editNews = $stmt->fetch();
}

// Estadísticas
$totalNews = count($news);
$publishedNews = count(array_filter($news, fn($n) => $n['is_published']));
$draftNews = $totalNews - $publishedNews;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Gestión de Noticias</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        
        body { background: #f1f5f9; }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .news-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .news-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .news-image {
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .news-image i {
            font-size: 2.5rem;
            color: rgba(255,255,255,0.3);
        }
        
        .news-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .news-content {
            padding: 15px;
        }
        
        .news-content h5 {
            font-weight: 600;
            color: var(--primary);
            font-size: 1rem;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-meta {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 25px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 12px 16px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10,37,64,0.1);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            transform: translateY(-1px);
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.2s;
        }
        
        .nav-tabs .nav-link {
            color: #64748b;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: transparent;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 fw-bold mb-1">
                        <i class="bi bi-newspaper me-2"></i>Gestión de Noticias
                    </h1>
                    <p class="mb-0 opacity-75">Administra las noticias de la Intranet</p>
                </div>
            </div>
        </div>
    </div>
    
    <main class="container pb-5">
        <!-- Alertas -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalNews ?></div>
                    <div class="stat-label">Total Noticias</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number text-success"><?= $publishedNews ?></div>
                    <div class="stat-label">Publicadas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?= $draftNews ?></div>
                    <div class="stat-label">Borradores</div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= !$editNews ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-list">
                    <i class="bi bi-list-ul me-2"></i>Lista de Noticias
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $editNews ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-form">
                    <i class="bi bi-plus-lg me-2"></i><?= $editNews ? 'Editar Noticia' : 'Nueva Noticia' ?>
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Lista de Noticias -->
            <div class="tab-pane fade <?= !$editNews ? 'show active' : '' ?>" id="tab-list">
                <?php if (empty($news)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-newspaper text-muted" style="font-size: 4rem;"></i>
                    <p class="text-muted mt-3">No hay noticias. ¡Crea la primera!</p>
                    <a href="#tab-form" class="btn btn-primary-custom text-white" data-bs-toggle="tab">
                        <i class="bi bi-plus-lg me-2"></i>Nueva Noticia
                    </a>
                </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($news as $item): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="news-card h-100">
                            <div class="news-image">
                                <?php if ($item['image_url']): ?>
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
                                <?php else: ?>
                                <i class="bi bi-image"></i>
                                <?php endif; ?>
                                <span class="news-badge">
                                    <?php if ($item['is_published']): ?>
                                    <span class="badge bg-success">Publicada</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Borrador</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="news-content">
                                <h5><?= htmlspecialchars($item['title']) ?></h5>
                                <p class="text-muted small mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars(substr($item['content'], 0, 100)) ?>...
                                </p>
                                <div class="news-meta mb-3">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                    <?php if ($item['author_name']): ?>
                                    · <?= htmlspecialchars($item['author_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="?edit=<?= $item['id'] ?>" class="action-btn btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_publish">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="action-btn btn <?= $item['is_published'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $item['is_published'] ? 'Despublicar' : 'Publicar' ?>">
                                            <i class="bi bi-<?= $item['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta noticia?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="action-btn btn btn-outline-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Formulario -->
            <div class="tab-pane fade <?= $editNews ? 'show active' : '' ?>" id="tab-form">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <h4 class="fw-bold mb-4">
                                <i class="bi bi-<?= $editNews ? 'pencil' : 'plus-lg' ?> me-2"></i>
                                <?= $editNews ? 'Editar Noticia' : 'Nueva Noticia' ?>
                            </h4>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="<?= $editNews ? 'update' : 'create' ?>">
                                <?php if ($editNews): ?>
                                <input type="hidden" name="id" value="<?= $editNews['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Título *</label>
                                    <input type="text" name="title" class="form-control" placeholder="Título de la noticia" value="<?= htmlspecialchars($editNews['title'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Contenido *</label>
                                    <textarea name="content" class="form-control" rows="6" placeholder="Escribe el contenido de la noticia..." required><?= htmlspecialchars($editNews['content'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">URL de Imagen (opcional)</label>
                                    <input type="url" name="image_url" class="form-control" placeholder="https://ejemplo.com/imagen.jpg" value="<?= htmlspecialchars($editNews['image_url'] ?? '') ?>">
                                    <small class="text-muted">Ingresa la URL de una imagen para la noticia</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">URL de la Noticia (opcional)</label>
                                    <input type="url" name="news_url" class="form-control" placeholder="https://ejemplo.com/noticia-completa" value="<?= htmlspecialchars($editNews['news_url'] ?? '') ?>">
                                    <small class="text-muted">Enlace externo para "Leer más" (SharePoint, sitio web, etc.)</small>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_published" class="form-check-input" id="is_published" <?= ($editNews['is_published'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="is_published">
                                            <i class="bi bi-eye me-1"></i>Publicar inmediatamente
                                        </label>
                                    </div>
                                    <small class="text-muted">Si no marcas esta opción, la noticia se guardará como borrador.</small>
                                </div>
                                
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary-custom text-white">
                                        <i class="bi bi-check-lg me-2"></i><?= $editNews ? 'Guardar Cambios' : 'Crear Noticia' ?>
                                    </button>
                                    <?php if ($editNews): ?>
                                    <a href="admin_noticias.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-2"></i>Cancelar
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * EPCO - Base de Conocimiento
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
$isAdmin = $user && in_array($user['role'], ['admin', 'soporte']);

$message = '';
$messageType = '';

// Procesar acciones admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitize($_POST['title']);
        $slug = sanitize($_POST['slug'] ?? '');
        $content = $_POST['content']; // Permite HTML
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $tags = sanitize($_POST['tags'] ?? '');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Generar slug si no se proporciona
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
            $slug = trim($slug, '-');
        }
        
        // Verificar slug único
        $stmt = $pdo->prepare('SELECT id FROM knowledge_base WHERE slug = ?');
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug .= '-' . time();
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO knowledge_base (title, slug, content, excerpt, category, tags, author_id, is_published, is_featured) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$title, $slug, $content, $excerpt, $category, $tags, $user['id'], $isPublished, $isFeatured]);
        
        logActivity($user['id'], 'kb_article_created', 'knowledge_base', $pdo->lastInsertId(), "Artículo '$title' creado");
        
        $message = 'Artículo creado exitosamente';
        $messageType = 'success';
    }
    
    if ($action === 'update') {
        $articleId = (int)$_POST['article_id'];
        $title = sanitize($_POST['title']);
        $content = $_POST['content'];
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $tags = sanitize($_POST['tags'] ?? '');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        
        $stmt = $pdo->prepare('
            UPDATE knowledge_base SET title=?, content=?, excerpt=?, category=?, tags=?, is_published=?, is_featured=? 
            WHERE id=?
        ');
        $stmt->execute([$title, $content, $excerpt, $category, $tags, $isPublished, $isFeatured, $articleId]);
        
        $message = 'Artículo actualizado';
        $messageType = 'success';
    }
    
    if ($action === 'delete') {
        $articleId = (int)$_POST['article_id'];
        $stmt = $pdo->prepare('DELETE FROM knowledge_base WHERE id = ?');
        $stmt->execute([$articleId]);
        
        $message = 'Artículo eliminado';
        $messageType = 'success';
    }
}

// Marcar como útil
if (isset($_GET['helpful'])) {
    $articleId = (int)$_GET['article_id'];
    $helpful = $_GET['helpful'] === 'yes' ? 'helpful_yes' : 'helpful_no';
    $stmt = $pdo->prepare("UPDATE knowledge_base SET $helpful = $helpful + 1 WHERE id = ?");
    $stmt->execute([$articleId]);
    header('Location: base_conocimiento.php?article=' . $articleId . '&thanks=1');
    exit;
}

// Ver artículo
$article = null;
if (isset($_GET['article'])) {
    $articleId = (int)$_GET['article'];
    $stmt = $pdo->prepare('SELECT kb.*, u.name as author_name FROM knowledge_base kb LEFT JOIN users u ON kb.author_id = u.id WHERE kb.id = ?');
    $stmt->execute([$articleId]);
    $article = $stmt->fetch();
    
    if ($article && ($article['is_published'] || $isAdmin)) {
        // Incrementar vistas
        $stmt = $pdo->prepare('UPDATE knowledge_base SET views = views + 1 WHERE id = ?');
        $stmt->execute([$articleId]);
    } else {
        $article = null;
    }
}

// Ver por slug
if (isset($_GET['slug'])) {
    $slug = sanitize($_GET['slug']);
    $stmt = $pdo->prepare('SELECT kb.*, u.name as author_name FROM knowledge_base kb LEFT JOIN users u ON kb.author_id = u.id WHERE kb.slug = ?');
    $stmt->execute([$slug]);
    $article = $stmt->fetch();
    
    if ($article && ($article['is_published'] || $isAdmin)) {
        $stmt = $pdo->prepare('UPDATE knowledge_base SET views = views + 1 WHERE id = ?');
        $stmt->execute([$article['id']]);
    } else {
        $article = null;
    }
}

// Buscar artículos
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');

$where = $isAdmin ? '1=1' : 'is_published = 1';
$params = [];

if (!empty($search)) {
    $where .= ' AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where .= ' AND category = ?';
    $params[] = $category;
}

$stmt = $pdo->prepare("
    SELECT kb.*, u.name as author_name 
    FROM knowledge_base kb 
    LEFT JOIN users u ON kb.author_id = u.id 
    WHERE $where 
    ORDER BY is_featured DESC, views DESC, created_at DESC
");
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Artículos destacados
$featuredStmt = $pdo->query("SELECT * FROM knowledge_base WHERE is_featured = 1 AND is_published = 1 ORDER BY views DESC LIMIT 4");
$featured = $featuredStmt->fetchAll();

// Categorías
$categories = [
    'general' => ['name' => 'General', 'icon' => 'info-circle', 'color' => 'secondary'],
    'hardware' => ['name' => 'Hardware', 'icon' => 'pc-display', 'color' => 'primary'],
    'software' => ['name' => 'Software', 'icon' => 'window', 'color' => 'info'],
    'red' => ['name' => 'Red', 'icon' => 'wifi', 'color' => 'success'],
    'acceso' => ['name' => 'Acceso', 'icon' => 'key', 'color' => 'warning'],
    'procedimientos' => ['name' => 'Procedimientos', 'icon' => 'list-check', 'color' => 'danger'],
];

// Conteo de artículos por categoría
$countWhere = $isAdmin ? '1=1' : 'is_published = 1';
$catCountStmt = $pdo->query("SELECT category, COUNT(*) as total FROM knowledge_base WHERE $countWhere GROUP BY category");
$catCounts = [];
$totalArticles = 0;
while ($row = $catCountStmt->fetch()) {
    $catCounts[$row['category']] = (int)$row['total'];
    $totalArticles += (int)$row['total'];
}
$totalFeatured = count($featured);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Base de Conocimiento<?= $article ? ' - ' . htmlspecialchars($article['title']) : '' ?></title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { background: #f1f5f9; min-height: 100vh; }
        .navbar-epco { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .page-header {
            background: linear-gradient(135deg, rgba(3,105,161,0.75) 0%, rgba(7,89,133,0.8) 50%, rgba(3,105,161,0.75) 100%),
                        url('<?= WEBP_SUPPORT ? "img/Puerto01.webp" : "img/Puerto01.jpeg" ?>') center/cover no-repeat !important;
            color: white;
            padding: 50px 0;
            position: relative;
            overflow: hidden;
        }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .article-card {
            transition: all 0.3s ease;
        }
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .article-content {
            line-height: 1.8;
        }
        .article-content h4 { margin-top: 1.5rem; color: var(--primary); }
        .article-content pre {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
        .article-content ol, .article-content ul {
            padding-left: 1.5rem;
        }
        .helpful-btn {
            padding: 10px 20px;
            border-radius: 50px;
            border: 2px solid;
            background: transparent;
            font-weight: 500;
            transition: all 0.3s;
        }
        .helpful-btn:hover {
            transform: scale(1.05);
        }
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 5px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .search-box .form-control {
            border: none;
            padding: 15px 25px;
            border-radius: 50px;
        }
        .search-box .form-control:focus {
            box-shadow: none;
        }
        .search-box .btn {
            border-radius: 50px;
            padding: 12px 30px;
        }
        /* Sidebar styles */
        .kb-cat-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border-radius: 10px;
            color: #334155;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .kb-cat-link:hover { background: #f1f5f9; color: #0369a1; }
        .kb-cat-link.active { background: #e0f2fe; color: #0369a1; font-weight: 600; }
        .kb-cat-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.82rem;
            flex-shrink: 0;
        }
        .kb-cat-count {
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 24px;
            text-align: center;
        }
        .kb-cat-link.active .kb-cat-count { background: #0369a1; color: white; }
        .kb-quick-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            color: #334155;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .kb-quick-link:hover { background: #f1f5f9; color: #0369a1; }
    </style>
</head>
<body class="<?= $user ? 'has-sidebar' : '' ?>">
    <?php if ($user): ?>
        <?php include '../includes/barra_lateral.php'; ?>
    <?php else: ?>
        <!-- Topbar público para usuarios no logueados (igual que soporte.php) -->
        <div class="epco-topbar">
            <div class="d-flex align-items-center gap-3">
                <a href="soporte.php" class="topbar-back-btn" title="Volver a Soporte">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="logo-text" style="font-size: 1.15rem; font-weight: 700; color: white;">Empresa Portuaria Coquimbo</span>
            </div>
            
            <div class="topbar-right">
                <span class="topbar-badge">
                    <i class="bi bi-book"></i>
                    Base de Conocimiento
                </span>
                
                <a href="iniciar_sesion.php" class="btn btn-light btn-sm d-flex align-items-center gap-2" style="border-radius: 10px; font-weight: 600;">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span class="d-none d-sm-inline">Iniciar Sesión</span>
                </a>
            </div>
        </div>
        <style>
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
            body { padding-top: 60px !important; }
            .page-header { margin-top: 0 !important; }
        </style>
    <?php endif; ?>

    <?php if ($article): ?>
    <!-- Vista de Artículo -->
    <div class="page-header" style="padding: 30px 0;">
        <div class="container position-relative">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="base_conocimiento.php" class="text-white-50">Base de Conocimiento</a></li>
                    <li class="breadcrumb-item"><a href="?category=<?= $article['category'] ?>" class="text-white-50"><?= $categories[$article['category']]['name'] ?? 'General' ?></a></li>
                    <li class="breadcrumb-item active text-white"><?= htmlspecialchars($article['title']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container py-5">
        <?php if (isset($_GET['thanks'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-heart-fill me-2"></i>¡Gracias por tu feedback!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center mb-4">
                            <span class="badge bg-<?= $categories[$article['category']]['color'] ?? 'secondary' ?> me-2">
                                <i class="bi bi-<?= $categories[$article['category']]['icon'] ?? 'info-circle' ?> me-1"></i>
                                <?= $categories[$article['category']]['name'] ?? 'General' ?>
                            </span>
                            <small class="text-muted">
                                <i class="bi bi-eye me-1"></i><?= number_format($article['views']) ?> vistas
                            </small>
                        </div>
                        
                        <h1 class="fw-bold mb-4"><?= htmlspecialchars($article['title']) ?></h1>
                        
                        <div class="article-content">
                            <?= $article['content'] ?>
                        </div>
                        
                        <?php if ($article['tags']): ?>
                        <div class="mt-4 pt-4 border-top">
                            <i class="bi bi-tags me-2"></i>
                            <?php foreach (explode(',', $article['tags']) as $tag): ?>
                            <a href="?search=<?= urlencode(trim($tag)) ?>" class="badge bg-light text-dark me-1"><?= htmlspecialchars(trim($tag)) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Feedback -->
                        <div class="mt-5 pt-4 border-top text-center">
                            <h5 class="mb-3">¿Te fue útil este artículo?</h5>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="?helpful=yes&article_id=<?= $article['id'] ?>" class="helpful-btn border-success text-success">
                                    <i class="bi bi-hand-thumbs-up me-2"></i>Sí (<?= $article['helpful_yes'] ?>)
                                </a>
                                <a href="?helpful=no&article_id=<?= $article['id'] ?>" class="helpful-btn border-danger text-danger">
                                    <i class="bi bi-hand-thumbs-down me-2"></i>No (<?= $article['helpful_no'] ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Información</h6>
                        <p class="mb-2"><i class="bi bi-person me-2"></i><?= htmlspecialchars($article['author_name'] ?? 'Sistema') ?></p>
                        <p class="mb-2"><i class="bi bi-calendar me-2"></i><?= date('d/m/Y', strtotime($article['created_at'])) ?></p>
                        <p class="mb-0"><i class="bi bi-clock me-2"></i>Actualizado: <?= date('d/m/Y', strtotime($article['updated_at'])) ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">¿Necesitas más ayuda?</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Si no encontraste la solución, crea un ticket de soporte.</p>
                        <a href="crear_ticket.php" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-2"></i>Crear Ticket
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Lista de Artículos -->
    <div class="page-header">
        <div class="container position-relative text-center">
            <h1 class="mb-3"><i class="bi bi-book me-3"></i>Base de Conocimiento</h1>
            <p class="mb-4 opacity-75">Encuentra respuestas a las preguntas más frecuentes</p>
            
            <!-- Search -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <form method="GET" class="search-box d-flex">
                        <input type="text" name="search" class="form-control" placeholder="Buscar artículos..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="mb-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#articleModal">
                <i class="bi bi-plus-lg me-2"></i>Nuevo Artículo
            </button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <!-- Estadísticas -->
                <div class="card mb-3" style="overflow: hidden;">
                    <div class="card-body p-0">
                        <div style="background: linear-gradient(135deg, #0369a1, #075985); padding: 20px; color: white;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-book" style="font-size: 1.3rem;"></i>
                                <span class="fw-bold" style="font-size: 1.1rem;">Base de Conocimiento</span>
                            </div>
                            <p class="mb-0 opacity-75" style="font-size: 0.82rem;">Resuelve problemas por tu cuenta</p>
                        </div>
                        <div class="d-flex text-center border-bottom">
                            <div class="flex-fill py-3 border-end">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;"><?= $totalArticles ?></div>
                                <small class="text-muted">Artículos</small>
                            </div>
                            <div class="flex-fill py-3">
                                <div class="fw-bold text-warning" style="font-size: 1.5rem;"><?= $totalFeatured ?></div>
                                <small class="text-muted">Destacados</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categorías -->
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-muted mb-3" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="bi bi-tag me-1"></i>Categorías</h6>
                        <div class="d-flex flex-column gap-1">
                            <a href="base_conocimiento.php" class="kb-cat-link <?= empty($category) ? 'active' : '' ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kb-cat-icon" style="background: rgba(100,116,139,0.1); color: #64748b;"><i class="bi bi-grid"></i></div>
                                    <span>Todas</span>
                                </div>
                                <span class="kb-cat-count"><?= $totalArticles ?></span>
                            </a>
                            <?php foreach ($categories as $catKey => $cat): ?>
                            <?php $count = $catCounts[$catKey] ?? 0; ?>
                            <a href="?category=<?= $catKey ?>" class="kb-cat-link <?= $category === $catKey ? 'active' : '' ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kb-cat-icon" style="background: var(--bs-<?= $cat['color'] ?>); color: white;"><i class="bi bi-<?= $cat['icon'] ?>"></i></div>
                                    <span><?= $cat['name'] ?></span>
                                </div>
                                <span class="kb-cat-count"><?= $count ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Accesos rápidos -->
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-muted mb-3" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="bi bi-lightning me-1"></i>Accesos Rápidos</h6>
                        <div class="d-flex flex-column gap-2">
                            <a href="crear_ticket.php" class="kb-quick-link">
                                <i class="bi bi-plus-circle text-primary"></i>
                                <span>Crear Ticket de Soporte</span>
                            </a>
                            <a href="soporte.php" class="kb-quick-link">
                                <i class="bi bi-headset text-success"></i>
                                <span>Mesa de Ayuda</span>
                            </a>
                            <a href="soporte.php#faq-section" class="kb-quick-link">
                                <i class="bi bi-question-circle text-info"></i>
                                <span>Preguntas Frecuentes</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Contacto rápido -->
                <div class="card" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; overflow: hidden;">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-2" style="font-size: 0.85rem;"><i class="bi bi-telephone me-2"></i>¿No encuentras lo que buscas?</h6>
                        <p class="mb-3 opacity-75" style="font-size: 0.78rem;">Contacta al equipo de soporte directamente.</p>
                        <div class="d-flex flex-column gap-2" style="font-size: 0.82rem;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-telephone-fill opacity-50"></i>
                                <span>Interno 6479</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-envelope-fill opacity-50"></i>
                                <span>gismodes@puertocoquimbo.cl</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Articles -->
            <div class="col-lg-9">
                <?php if (!empty($featured) && empty($search) && empty($category)): ?>
                <h5 class="mb-3"><i class="bi bi-star-fill text-warning me-2"></i>Artículos Destacados</h5>
                <div class="row g-3 mb-5">
                    <?php foreach ($featured as $feat): ?>
                    <div class="col-md-6">
                        <a href="?article=<?= $feat['id'] ?>" class="card article-card h-100 text-decoration-none" style="border-left: 4px solid var(--bs-<?= $categories[$feat['category']]['color'] ?? 'secondary' ?>); overflow: hidden;">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2 gap-2">
                                    <span class="badge bg-<?= $categories[$feat['category']]['color'] ?? 'secondary' ?>">
                                        <i class="bi bi-<?= $categories[$feat['category']]['icon'] ?? 'info-circle' ?> me-1"></i>
                                        <?= $categories[$feat['category']]['name'] ?? 'General' ?>
                                    </span>
                                    <small class="text-warning"><i class="bi bi-star-fill"></i></small>
                                </div>
                                <h6 class="text-dark fw-bold mb-2"><?= htmlspecialchars($feat['title']) ?></h6>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($feat['excerpt'] ?: substr(strip_tags($feat['content']), 0, 100)) ?></p>
                                <small class="text-muted"><i class="bi bi-eye me-1"></i><?= $feat['views'] ?> vistas</small>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <h5 class="mb-3"><?= $search ? 'Resultados de búsqueda' : ($category ? $categories[$category]['name'] ?? 'Artículos' : 'Todos los Artículos') ?></h5>
                
                <?php if (empty($articles)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No se encontraron artículos</h5>
                        <p class="text-muted">Intenta con otros términos de búsqueda</p>
                    </div>
                </div>
                <?php else: ?>
                
                <?php if (empty($search) && empty($category)): ?>
                    <?php
                    // Agrupar artículos por categoría
                    $grouped = [];
                    foreach ($articles as $art) {
                        $grouped[$art['category']][] = $art;
                    }
                    // Ordenar categorías según el orden definido
                    $orderedGroups = [];
                    foreach (array_keys($categories) as $catKey) {
                        if (isset($grouped[$catKey])) {
                            $orderedGroups[$catKey] = $grouped[$catKey];
                        }
                    }
                    ?>
                    <?php foreach ($orderedGroups as $groupCat => $groupArticles): ?>
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?= $categories[$groupCat]['color'] ?? 'secondary' ?> bg-opacity-10" style="width: 36px; height: 36px;">
                                <i class="bi bi-<?= $categories[$groupCat]['icon'] ?? 'info-circle' ?> text-<?= $categories[$groupCat]['color'] ?? 'secondary' ?>"></i>
                            </div>
                            <h6 class="mb-0 fw-bold"><?= $categories[$groupCat]['name'] ?? 'General' ?></h6>
                            <span class="badge bg-<?= $categories[$groupCat]['color'] ?? 'secondary' ?> bg-opacity-10 text-<?= $categories[$groupCat]['color'] ?? 'secondary' ?> ms-1"><?= count($groupArticles) ?></span>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($groupArticles as $art): ?>
                            <div class="col-md-6">
                                <a href="?article=<?= $art['id'] ?>" class="card article-card h-100 text-decoration-none" style="border-left: 3px solid var(--bs-<?= $categories[$art['category']]['color'] ?? 'secondary' ?>);">
                                    <div class="card-body py-3 px-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="bg-<?= $categories[$art['category']]['color'] ?? 'secondary' ?> bg-opacity-10 text-<?= $categories[$art['category']]['color'] ?? 'secondary' ?> rounded-3 p-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-<?= $categories[$art['category']]['icon'] ?? 'info-circle' ?>"></i>
                                            </div>
                                            <div class="flex-grow-1 min-width-0">
                                                <h6 class="text-dark mb-1 fw-semibold" style="font-size: 0.92rem;"><?= htmlspecialchars($art['title']) ?></h6>
                                                <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($art['excerpt'] ?: substr(strip_tags($art['content']), 0, 120)) ?></p>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 mt-2 pt-2 border-top" style="font-size: 0.75rem;">
                                            <span class="text-muted"><i class="bi bi-eye me-1"></i><?= $art['views'] ?></span>
                                            <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($art['created_at'])) ?></span>
                                            <?php if ($art['is_featured']): ?><span class="text-warning"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($articles as $art): ?>
                    <div class="col-md-6">
                        <a href="?article=<?= $art['id'] ?>" class="card article-card h-100 text-decoration-none" style="border-left: 3px solid var(--bs-<?= $categories[$art['category']]['color'] ?? 'secondary' ?>);">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="bg-<?= $categories[$art['category']]['color'] ?? 'secondary' ?> bg-opacity-10 text-<?= $categories[$art['category']]['color'] ?? 'secondary' ?> rounded-3 p-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-<?= $categories[$art['category']]['icon'] ?? 'info-circle' ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-<?= $categories[$art['category']]['color'] ?? 'secondary' ?>" style="font-size: 0.7rem;"><?= $categories[$art['category']]['name'] ?? 'General' ?></span>
                                            <?php if ($art['is_featured']): ?><span class="text-warning"><i class="bi bi-star-fill" style="font-size: 0.7rem;"></i></span><?php endif; ?>
                                        </div>
                                        <h6 class="text-dark mb-1 fw-semibold" style="font-size: 0.92rem;"><?= htmlspecialchars($art['title']) ?></h6>
                                        <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($art['excerpt'] ?: substr(strip_tags($art['content']), 0, 120)) ?></p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 mt-2 pt-2 border-top" style="font-size: 0.75rem;">
                                    <span class="text-muted"><i class="bi bi-eye me-1"></i><?= $art['views'] ?></span>
                                    <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($art['created_at'])) ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <!-- Modal Crear Artículo -->
    <div class="modal fade" id="articleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white;">
                    <h5 class="modal-title">Nuevo Artículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Categoría</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($categories as $catKey => $cat): ?>
                                    <option value="<?= $catKey ?>"><?= $cat['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tags (separados por coma)</label>
                                <input type="text" name="tags" class="form-control" placeholder="vpn, conexión, remoto">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Resumen</label>
                            <input type="text" name="excerpt" class="form-control" placeholder="Breve descripción del artículo">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contenido *</label>
                            <textarea name="content" class="form-control" rows="10" required placeholder="Puedes usar HTML para dar formato"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_published" class="form-check-input" id="isPublished" checked>
                                    <label class="form-check-label" for="isPublished">Publicar inmediatamente</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured">
                                    <label class="form-check-label" for="isFeatured">Destacar artículo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Crear Artículo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

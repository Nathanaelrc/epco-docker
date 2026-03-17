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
    enforcePostCsrf();
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/intranet.css?v=<?= filemtime(__DIR__ . '/css/intranet.css') ?>" rel="stylesheet">
    <link href="css/base-conocimiento.css?v=<?= filemtime(__DIR__ . '/css/base-conocimiento.css') ?>" rel="stylesheet">
    <style>
        .page-header { background: linear-gradient(135deg, rgba(3,105,161,0.75) 0%, rgba(7,89,133,0.8) 50%, rgba(3,105,161,0.75) 100%), url('<?= WEBP_SUPPORT ? "img/Puerto01.webp" : "img/Puerto01.jpeg" ?>') center/cover no-repeat !important; }
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

        <!-- Barra de stats + accesos rápidos compacta -->
        <div class="kb-topbar-info mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="kb-stat-pill">
                            <i class="bi bi-book text-primary"></i>
                            <span><strong><?= $totalArticles ?></strong> artículos</span>
                        </div>
                        <div class="kb-stat-pill">
                            <i class="bi bi-star-fill text-warning"></i>
                            <span><strong><?= $totalFeatured ?></strong> destacados</span>
                        </div>
                        <div class="kb-stat-pill">
                            <i class="bi bi-collection text-info"></i>
                            <span><strong><?= count(array_filter($catCounts)) ?></strong> categorías</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <a href="crear_ticket.php" class="kb-action-link"><i class="bi bi-plus-circle"></i> Crear Ticket</a>
                        <a href="soporte.php" class="kb-action-link"><i class="bi bi-headset"></i> Mesa de Ayuda</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtro de categorías horizontal -->
        <div class="kb-category-pills mb-4">
            <a href="base_conocimiento.php" class="kb-pill <?= empty($category) && empty($search) ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> Todas
            </a>
            <?php foreach ($categories as $catKey => $cat): ?>
            <?php $count = $catCounts[$catKey] ?? 0; ?>
            <?php if ($count > 0): ?>
            <a href="?category=<?= $catKey ?>" class="kb-pill <?= $category === $catKey ? 'active' : '' ?>" data-color="<?= $cat['color'] ?>">
                <i class="bi bi-<?= $cat['icon'] ?>"></i> <?= $cat['name'] ?> <span class="kb-pill-count"><?= $count ?></span>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (empty($articles)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No se encontraron artículos</h5>
                <p class="text-muted">Intenta con otros términos de búsqueda</p>
            </div>
        </div>
        <?php else: ?>
        
        <?php if (!empty($search) || !empty($category)): ?>
        <!-- Resultados de búsqueda/filtro -->
        <h5 class="mb-3"><?= $search ? 'Resultados de búsqueda' : ($categories[$category]['name'] ?? 'Artículos') ?></h5>
        <div class="row g-3">
            <?php foreach ($articles as $art): ?>
            <div class="col-lg-4 col-md-6">
                <a href="?article=<?= $art['id'] ?>" class="card article-card h-100 text-decoration-none">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="kb-art-icon bg-<?= $categories[$art['category']]['color'] ?? 'secondary' ?> bg-opacity-10 text-<?= $categories[$art['category']]['color'] ?? 'secondary' ?>">
                                <i class="bi bi-<?= $categories[$art['category']]['icon'] ?? 'info-circle' ?>"></i>
                            </div>
                            <span class="badge bg-<?= $categories[$art['category']]['color'] ?? 'secondary' ?>" style="font-size: 0.7rem;"><?= $categories[$art['category']]['name'] ?? 'General' ?></span>
                            <?php if ($art['is_featured']): ?><i class="bi bi-star-fill text-warning" style="font-size: 0.7rem;"></i><?php endif; ?>
                        </div>
                        <h6 class="text-dark mb-1 fw-semibold" style="font-size: 0.92rem;"><?= htmlspecialchars($art['title']) ?></h6>
                        <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($art['excerpt'] ?: substr(strip_tags($art['content']), 0, 120)) ?></p>
                        <div class="d-flex align-items-center gap-3 mt-2 pt-2 border-top" style="font-size: 0.75rem;">
                            <span class="text-muted"><i class="bi bi-eye me-1"></i><?= $art['views'] ?></span>
                            <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($art['created_at'])) ?></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Vista principal: Acordeón por categorías -->
        <?php
        $grouped = [];
        foreach ($articles as $art) {
            $grouped[$art['category']][] = $art;
        }
        $orderedGroups = [];
        foreach (array_keys($categories) as $catKey) {
            if (isset($grouped[$catKey])) {
                $orderedGroups[$catKey] = $grouped[$catKey];
            }
        }
        $accordionIndex = 0;
        ?>

        <?php if (!empty($featured)): ?>
        <div class="kb-featured-section mb-4">
            <h6 class="kb-section-title"><i class="bi bi-star-fill text-warning me-2"></i>Destacados</h6>
            <div class="row g-3">
                <?php foreach ($featured as $feat): ?>
                <div class="col-lg-3 col-md-6">
                    <a href="?article=<?= $feat['id'] ?>" class="card article-card h-100 text-decoration-none kb-featured-card">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="kb-art-icon bg-<?= $categories[$feat['category']]['color'] ?? 'secondary' ?> bg-opacity-10 text-<?= $categories[$feat['category']]['color'] ?? 'secondary' ?>">
                                    <i class="bi bi-<?= $categories[$feat['category']]['icon'] ?? 'info-circle' ?>"></i>
                                </div>
                                <i class="bi bi-star-fill text-warning" style="font-size: 0.65rem;"></i>
                            </div>
                            <h6 class="text-dark fw-semibold mb-1" style="font-size: 0.88rem;"><?= htmlspecialchars($feat['title']) ?></h6>
                            <p class="text-muted mb-0" style="font-size: 0.78rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($feat['excerpt'] ?: substr(strip_tags($feat['content']), 0, 80)) ?></p>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="accordion kb-accordion" id="kbAccordion">
            <?php foreach ($orderedGroups as $groupCat => $groupArticles): ?>
            <?php $cat = $categories[$groupCat] ?? ['name' => 'General', 'icon' => 'info-circle', 'color' => 'secondary']; ?>
            <div class="accordion-item kb-accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button kb-accordion-btn <?= $accordionIndex > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#kbCat<?= $accordionIndex ?>" aria-expanded="<?= $accordionIndex === 0 ? 'true' : 'false' ?>">
                        <div class="kb-acc-icon bg-<?= $cat['color'] ?>">
                            <i class="bi bi-<?= $cat['icon'] ?>"></i>
                        </div>
                        <span class="kb-acc-title"><?= $cat['name'] ?></span>
                        <span class="kb-acc-count"><?= count($groupArticles) ?> artículo<?= count($groupArticles) !== 1 ? 's' : '' ?></span>
                    </button>
                </h2>
                <div id="kbCat<?= $accordionIndex ?>" class="accordion-collapse collapse <?= $accordionIndex === 0 ? 'show' : '' ?>" data-bs-parent="#kbAccordion">
                    <div class="accordion-body p-3">
                        <div class="row g-3">
                            <?php foreach ($groupArticles as $art): ?>
                            <div class="col-lg-4 col-md-6">
                                <a href="?article=<?= $art['id'] ?>" class="kb-article-item">
                                    <div class="kb-article-item-icon text-<?= $cat['color'] ?>">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                    <div class="kb-article-item-body">
                                        <h6><?= htmlspecialchars($art['title']) ?></h6>
                                        <p><?= htmlspecialchars($art['excerpt'] ?: substr(strip_tags($art['content']), 0, 100)) ?></p>
                                        <div class="kb-article-item-meta">
                                            <span><i class="bi bi-eye"></i> <?= $art['views'] ?></span>
                                            <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($art['created_at'])) ?></span>
                                            <?php if ($art['is_featured']): ?><span class="text-warning"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $accordionIndex++; ?>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
        <?php endif; ?>

        <!-- Contacto compacto -->
        <div class="kb-contact-bar mt-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-telephone-fill text-primary"></i>
                    <span class="fw-semibold" style="font-size: 0.88rem;">¿No encuentras lo que buscas?</span>
                </div>
                <div class="d-flex flex-wrap gap-3" style="font-size: 0.85rem;">
                    <span><i class="bi bi-telephone me-1"></i>512406479</span>
                    <span><i class="bi bi-envelope me-1"></i>gismodes@puertocoquimbo.cl</span>
                    <a href="crear_ticket.php" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Crear Ticket</a>
                </div>
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
            <?= csrfInput() ?>
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

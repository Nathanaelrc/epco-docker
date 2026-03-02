<?php
/**
 * EPCO - Búsqueda Global
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
$query = sanitize($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';

$results = [
    'tickets' => [],
    'news' => [],
    'documents' => [],
    'knowledge' => [],
    'users' => [],
];

$totalResults = 0;

if (!empty($query) && strlen($query) >= 2) {
    $searchTerm = "%$query%";
    
    // Buscar en tickets (solo propios o si es admin/soporte)
    if ($user && ($type === 'all' || $type === 'tickets')) {
        if (in_array($user['role'], ['admin', 'soporte'])) {
            $stmt = $pdo->prepare('
                SELECT t.*, u.name as user_name 
                FROM tickets t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.title LIKE ? OR t.description LIKE ?
                ORDER BY t.created_at DESC 
                LIMIT 10
            ');
            $stmt->execute([$searchTerm, $searchTerm]);
        } else {
            $stmt = $pdo->prepare('
                SELECT t.*, u.name as user_name 
                FROM tickets t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.user_id = ? AND (t.title LIKE ? OR t.description LIKE ?)
                ORDER BY t.created_at DESC 
                LIMIT 10
            ');
            $stmt->execute([$user['id'], $searchTerm, $searchTerm]);
        }
        $results['tickets'] = $stmt->fetchAll();
        $totalResults += count($results['tickets']);
    }
    
    // Buscar en noticias
    if ($type === 'all' || $type === 'news') {
        $stmt = $pdo->prepare('
            SELECT * FROM news 
            WHERE is_published = 1 AND (title LIKE ? OR content LIKE ?)
            ORDER BY published_at DESC 
            LIMIT 10
        ');
        $stmt->execute([$searchTerm, $searchTerm]);
        $results['news'] = $stmt->fetchAll();
        $totalResults += count($results['news']);
    }
    
    // Buscar en documentos
    if ($user && ($type === 'all' || $type === 'documents')) {
        $stmt = $pdo->prepare('
            SELECT * FROM documents 
            WHERE is_active = 1 AND (title LIKE ? OR description LIKE ? OR original_name LIKE ?)
            ORDER BY created_at DESC 
            LIMIT 10
        ');
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results['documents'] = $stmt->fetchAll();
        $totalResults += count($results['documents']);
    }
    
    // Buscar en base de conocimiento
    if ($type === 'all' || $type === 'knowledge') {
        $wherePublished = ($user && in_array($user['role'], ['admin', 'soporte'])) ? '' : 'AND is_published = 1';
        $stmt = $pdo->prepare("
            SELECT * FROM knowledge_base 
            WHERE (title LIKE ? OR content LIKE ? OR tags LIKE ?) $wherePublished
            ORDER BY views DESC 
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results['knowledge'] = $stmt->fetchAll();
        $totalResults += count($results['knowledge']);
    }
    
    // Buscar usuarios (solo admin)
    if ($user && $user['role'] === 'admin' && ($type === 'all' || $type === 'users')) {
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, department, position, is_active 
            FROM users 
            WHERE name LIKE ? OR email LIKE ? OR department LIKE ?
            ORDER BY name 
            LIMIT 10
        ');
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results['users'] = $stmt->fetchAll();
        $totalResults += count($results['users']);
    }
}

// Highlight function
function highlightText($text, $query) {
    if (empty($query)) return htmlspecialchars($text);
    return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
}

$statusColors = [
    'open' => 'warning',
    'in_progress' => 'info',
    'pending' => 'secondary',
    'closed' => 'success'
];
$statusLabels = [
    'open' => 'Abierto',
    'in_progress' => 'En Progreso',
    'pending' => 'Pendiente',
    'closed' => 'Cerrado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Búsqueda: <?= htmlspecialchars($query) ?></title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { background: #f1f5f9; min-height: 100vh; }
        .navbar-epco { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .search-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 40px 0;
            position: relative;
        }
        .search-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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
            font-size: 1.1rem;
        }
        .search-box .form-control:focus { box-shadow: none; }
        
        .result-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .result-card:hover {
            transform: translateX(5px);
            border-left-color: var(--primary);
        }
        .result-card mark {
            background: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        .filter-btn {
            border-radius: 50px;
            padding: 8px 20px;
        }
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .result-type-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
    </style>
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <!-- Search Header -->
    <div class="search-header">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h4 class="text-center mb-4"><i class="bi bi-search me-2"></i>Búsqueda Global</h4>
                    <form method="GET" class="search-box d-flex">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                        <input type="text" name="q" class="form-control" placeholder="Buscar tickets, noticias, documentos, artículos..." value="<?= htmlspecialchars($query) ?>" autofocus>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <?php if (!empty($query)): ?>
        <!-- Filtros -->
        <div class="mb-4 d-flex flex-wrap gap-2 justify-content-center">
            <a href="?q=<?= urlencode($query) ?>&type=all" class="btn btn-outline-primary filter-btn <?= $type === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid me-1"></i>Todo (<?= $totalResults ?>)
            </a>
            <?php if ($user): ?>
            <a href="?q=<?= urlencode($query) ?>&type=tickets" class="btn btn-outline-primary filter-btn <?= $type === 'tickets' ? 'active' : '' ?>">
                <i class="bi bi-ticket me-1"></i>Tickets (<?= count($results['tickets']) ?>)
            </a>
            <?php endif; ?>
            <a href="?q=<?= urlencode($query) ?>&type=news" class="btn btn-outline-primary filter-btn <?= $type === 'news' ? 'active' : '' ?>">
                <i class="bi bi-newspaper me-1"></i>Noticias (<?= count($results['news']) ?>)
            </a>
            <?php if ($user): ?>
            <a href="?q=<?= urlencode($query) ?>&type=documents" class="btn btn-outline-primary filter-btn <?= $type === 'documents' ? 'active' : '' ?>">
                <i class="bi bi-folder me-1"></i>Documentos (<?= count($results['documents']) ?>)
            </a>
            <?php endif; ?>
            <a href="?q=<?= urlencode($query) ?>&type=knowledge" class="btn btn-outline-primary filter-btn <?= $type === 'knowledge' ? 'active' : '' ?>">
                <i class="bi bi-book me-1"></i>Conocimiento (<?= count($results['knowledge']) ?>)
            </a>
            <?php if ($user && $user['role'] === 'admin'): ?>
            <a href="?q=<?= urlencode($query) ?>&type=users" class="btn btn-outline-primary filter-btn <?= $type === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people me-1"></i>Usuarios (<?= count($results['users']) ?>)
            </a>
            <?php endif; ?>
        </div>

        <?php if ($totalResults === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                <h4 class="text-muted">No se encontraron resultados</h4>
                <p class="text-muted">Intenta con otros términos de búsqueda</p>
            </div>
        </div>
        <?php else: ?>

        <!-- Resultados de Tickets -->
        <?php if (!empty($results['tickets']) && ($type === 'all' || $type === 'tickets')): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-ticket-perforated me-2"></i>Tickets</h5>
            <?php foreach ($results['tickets'] as $ticket): ?>
            <a href="ticket_view.php?id=<?= $ticket['id'] ?>" class="card result-card mb-2 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="result-type-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark"><?= highlightText($ticket['title'], $query) ?></h6>
                        <p class="text-muted small mb-0"><?= highlightText(substr($ticket['description'], 0, 150), $query) ?>...</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= $statusColors[$ticket['status']] ?>"><?= $statusLabels[$ticket['status']] ?></span>
                        <br><small class="text-muted">#<?= $ticket['id'] ?></small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Resultados de Noticias -->
        <?php if (!empty($results['news']) && ($type === 'all' || $type === 'news')): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-newspaper me-2"></i>Noticias</h5>
            <?php foreach ($results['news'] as $news): ?>
            <a href="news_view.php?id=<?= $news['id'] ?>" class="card result-card mb-2 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="result-type-icon bg-info bg-opacity-10 text-info me-3">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark"><?= highlightText($news['title'], $query) ?></h6>
                        <p class="text-muted small mb-0"><?= highlightText(substr(strip_tags($news['content']), 0, 150), $query) ?>...</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted"><?= date('d/m/Y', strtotime($news['published_at'])) ?></small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Resultados de Documentos -->
        <?php if (!empty($results['documents']) && ($type === 'all' || $type === 'documents')): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-folder me-2"></i>Documentos</h5>
            <?php foreach ($results['documents'] as $doc): ?>
            <a href="documentos.php?download=<?= $doc['id'] ?>" class="card result-card mb-2 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="result-type-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-file-earmark"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark"><?= highlightText($doc['title'], $query) ?></h6>
                        <p class="text-muted small mb-0"><?= highlightText($doc['description'] ?? $doc['original_name'], $query) ?></p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary"><?= strtoupper($doc['file_type']) ?></span>
                        <br><small class="text-muted"><?= number_format($doc['file_size'] / 1024, 1) ?> KB</small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Resultados de Base de Conocimiento -->
        <?php if (!empty($results['knowledge']) && ($type === 'all' || $type === 'knowledge')): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-book me-2"></i>Base de Conocimiento</h5>
            <?php foreach ($results['knowledge'] as $kb): ?>
            <a href="base_conocimiento.php?article=<?= $kb['id'] ?>" class="card result-card mb-2 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="result-type-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-lightbulb"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark"><?= highlightText($kb['title'], $query) ?></h6>
                        <p class="text-muted small mb-0"><?= highlightText(substr(strip_tags($kb['content']), 0, 150), $query) ?>...</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted"><i class="bi bi-eye me-1"></i><?= $kb['views'] ?></small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Resultados de Usuarios (solo admin) -->
        <?php if (!empty($results['users']) && ($type === 'all' || $type === 'users')): ?>
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-people me-2"></i>Usuarios</h5>
            <?php foreach ($results['users'] as $u): ?>
            <a href="admin_usuarios.php?search=<?= urlencode($u['email']) ?>" class="card result-card mb-2 text-decoration-none">
                <div class="card-body d-flex align-items-center">
                    <div class="result-type-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark"><?= highlightText($u['name'], $query) ?></h6>
                        <p class="text-muted small mb-0"><?= highlightText($u['email'], $query) ?> - <?= htmlspecialchars($u['department'] ?? 'Sin departamento') ?></p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>"><?= $u['is_active'] ? 'Activo' : 'Inactivo' ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        <?php else: ?>
        <!-- Sin búsqueda -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-search fs-1 text-primary mb-3 d-block"></i>
                <h4>Busca en todo el portal</h4>
                <p class="text-muted">Ingresa al menos 2 caracteres para buscar en tickets, noticias, documentos y más.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

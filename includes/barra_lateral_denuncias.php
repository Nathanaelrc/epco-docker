<?php
/**
 * EPCO - Sidebar para Dashboard Denuncias Ley Karin
 * Aparece al hacer click en el logo EPCO
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}

if (!$user) {
    header('Location: iniciar_sesion.php');
    exit;
}

$isAdmin = $user['role'] === 'admin';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Obtener estadísticas para los badges
global $pdo;
$denunciasStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status = 'nueva' THEN 1 ELSE 0 END) as nuevas
    FROM complaints
")->fetch();
?>

<!-- Sidebar Denuncias Styles -->
<link href="css/sidebar-denuncias.css" rel="stylesheet">

<!-- Topbar minimalista -->
<div class="epco-topbar">
    <button class="epco-logo-btn" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="bi bi-list menu-icon"></i>
        <span class="logo-text">Empresa Portuaria Coquimbo</span>
    </button>
    
    <div class="topbar-right">
        <div class="topbar-clock d-none d-md-flex">
            <i class="bi bi-clock"></i>
            <span id="chileTime">--:--:--</span>
            <small class="opacity-75">Chile</small>
        </div>
        
        <div class="topbar-user">
            <div class="topbar-user-info d-none d-md-block">
                <div class="topbar-user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="topbar-user-role"><?= ucfirst($user['role']) ?></div>
            </div>
            <div class="dropdown">
                <div class="topbar-avatar" role="button" data-bs-toggle="dropdown">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                    <!-- Intranet oculta temporalmente
                    <li><a class="dropdown-item" href="panel_intranet.php"><i class="bi bi-house me-2"></i>Intranet</a></li>
                    -->
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="cerrar_sesion.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar Denuncias Ley Karin -->
<aside class="epco-sidebar" id="epcoSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="sidebar-brand-text">
                <h4>Ley Karin</h4>
                <span>Panel Admin</span>
            </div>
        </div>
        <button class="sidebar-close" onclick="closeSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <!-- Principal -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=dashboard" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'dashboard') || !isset($_GET['page']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=nuevas" class="sidebar-nav-link <?= (isset($_GET['filter']) && $_GET['filter'] === 'nuevas') ? 'active' : '' ?>">
                        <i class="bi bi-bell"></i>
                        <span>Nuevas</span>
                        <?php if ($denunciasStats['nuevas'] > 0): ?>
                        <span class="badge alert"><?= $denunciasStats['nuevas'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=urgente" class="sidebar-nav-link <?= (isset($_GET['filter']) && $_GET['filter'] === 'urgente') ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Urgentes</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Gestión de Casos -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Casos</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=all" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'denuncias' && isset($_GET['filter']) && $_GET['filter'] === 'all') ? 'active' : '' ?>">
                        <i class="bi bi-folder"></i>
                        <span>Todas las Denuncias</span>
                        <span class="badge"><?= $denunciasStats['total'] ?></span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=pendiente" class="sidebar-nav-link <?= (isset($_GET['filter']) && $_GET['filter'] === 'pendiente') ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Pendientes</span>
                        <?php if ($denunciasStats['pendientes'] > 0): ?>
                        <span class="badge warning"><?= $denunciasStats['pendientes'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=en_investigacion" class="sidebar-nav-link <?= (isset($_GET['filter']) && $_GET['filter'] === 'en_investigacion') ? 'active' : '' ?>">
                        <i class="bi bi-search"></i>
                        <span>En Investigación</span>
                        <?php if ($denunciasStats['en_investigacion'] > 0): ?>
                        <span class="badge"><?= $denunciasStats['en_investigacion'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=denuncias&filter=cerrada" class="sidebar-nav-link <?= (isset($_GET['filter']) && $_GET['filter'] === 'cerrada') ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i>
                        <span>Cerradas</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Reportes y Configuración -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Administración</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=reportes" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'reportes') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=auditoria" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'auditoria') ? 'active' : '' ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Auditoría</span>
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php?page=configuracion" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'configuracion') ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i>
                        <span>Configuración</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Enlaces Rápidos -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Navegación</div>
            <ul class="sidebar-nav">
                <!-- Intranet oculta temporalmente
                <li class="sidebar-nav-item">
                    <a href="panel_intranet.php" class="sidebar-nav-link">
                        <i class="bi bi-house"></i>
                        <span>Intranet</span>
                    </a>
                </li>
                -->
                <li class="sidebar-nav-item">
                    <a href="index.php" class="sidebar-nav-link">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Portal Principal</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Info Legal -->
        <div class="sidebar-legal">
            <div class="sidebar-legal-title">Ley 21.643</div>
            <div class="sidebar-legal-text">
                Sistema de gestión de denuncias conforme a la normativa de prevención del acoso laboral, sexual y violencia en el trabajo.
            </div>
        </div>
    </div>
    
    <!-- Footer con usuario -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <h6><?= htmlspecialchars($user['name']) ?></h6>
                <span><?= ucfirst($user['role']) ?></span>
            </div>
            <a href="cerrar_sesion.php" class="sidebar-logout" title="Cerrar sesión">
                <i class="bi bi-box-arrow-left"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Scripts del sidebar -->
<script>
// Funciones del sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('epcoSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    toggleBtn.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('epcoSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    toggleBtn.classList.remove('active');
}

// Cerrar con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// Reloj de Chile
function updateChileTime() {
    const options = {
        timeZone: 'America/Santiago',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    const chileTime = new Date().toLocaleTimeString('es-CL', options);
    const clockEl = document.getElementById('chileTime');
    if (clockEl) clockEl.textContent = chileTime;
}
updateChileTime();
setInterval(updateChileTime, 1000);
</script>

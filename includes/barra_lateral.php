<?php
/**
 * EPCO - Sidebar Reutilizable
 * Aparece al hacer click en el logo EPCO
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}

if (!$user) {
    header('Location: iniciar_sesion.php');
    exit;
}

$isAdmin = in_array($user['role'], ['admin']);
$isAdminOrSoporte = in_array($user['role'], ['admin', 'soporte']);
$canManageNews = in_array($user['role'], ['admin', 'social']);
$canManageBulletins = in_array($user['role'], ['admin', 'social']);
$canViewDenuncias = in_array($user['role'], ['admin', 'denuncia']);

// Determinar página activa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar Styles -->
<link href="css/sidebar.css" rel="stylesheet">

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
                    <li><a class="dropdown-item" href="buscar.php"><i class="bi bi-search me-2"></i>Búsqueda</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="cerrar_sesion.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="epco-sidebar" id="epcoSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo">
                <i class="bi bi-building"></i>
            </div>
            <div class="sidebar-brand-text">
                <h4>Empresa Portuaria Coquimbo</h4>
                <span>Intranet</span>
            </div>
        </div>
        <button class="sidebar-close" onclick="closeSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <!-- Navegación Principal -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="panel_intranet.php" class="sidebar-nav-link <?= $currentPage === 'intranet_dashboard' ? 'active' : '' ?>">
                        <i class="bi bi-house-door"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="documentos.php" class="sidebar-nav-link <?= $currentPage === 'documents' ? 'active' : '' ?>">
                        <i class="bi bi-folder"></i>
                        <span>Documentos</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="base_conocimiento.php" class="sidebar-nav-link <?= $currentPage === 'knowledge_base' ? 'active' : '' ?>">
                        <i class="bi bi-book"></i>
                        <span>Base de Conocimiento</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="eventos.php" class="sidebar-nav-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-event"></i>
                        <span>Calendario</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Servicios -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Servicios</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="intranet_soporte.php" class="sidebar-nav-link <?= $currentPage === 'intranet_soporte' || $currentPage === 'soporte' ? 'active' : '' ?>">
                        <i class="bi bi-headset"></i>
                        <span>Soporte TI</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="denuncias.php?from=intranet" class="sidebar-nav-link">
                        <i class="bi bi-shield-check"></i>
                        <span>Canal de Integridad</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <?php if ($canManageNews || $canManageBulletins): ?>
        <!-- Gestión de Contenido -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Contenido</div>
            <ul class="sidebar-nav">
                <?php if ($canManageNews): ?>
                <li class="sidebar-nav-item">
                    <a href="admin_noticias.php" class="sidebar-nav-link <?= $currentPage === 'news_admin' ? 'active' : '' ?>">
                        <i class="bi bi-newspaper"></i>
                        <span>Gestionar Noticias</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canManageBulletins): ?>
                <li class="sidebar-nav-item">
                    <a href="admin_boletines.php" class="sidebar-nav-link <?= $currentPage === 'bulletin_admin' ? 'active' : '' ?>">
                        <i class="bi bi-pin-angle"></i>
                        <span>Gestionar Boletines</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($isAdminOrSoporte || $canViewDenuncias): ?>
        <!-- Administración -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Administración</div>
            <ul class="sidebar-nav">
                <?php if ($isAdminOrSoporte): ?>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php" class="sidebar-nav-link <?= $currentPage === 'soporte_admin' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Panel de Soporte</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="admin_usuarios.php" class="sidebar-nav-link <?= $currentPage === 'users_admin' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="registro_auditoria.php" class="sidebar-nav-link <?= $currentPage === 'audit_logs' ? 'active' : '' ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Auditoría</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="reportes.php" class="sidebar-nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                        <i class="bi bi-file-spreadsheet"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canViewDenuncias): ?>
                <li class="sidebar-nav-item">
                    <a href="denuncias_admin.php" class="sidebar-nav-link <?= $currentPage === 'denuncias_admin' ? 'active' : '' ?>">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>Panel Denuncias</span>
                        <span class="badge alert">Ley Karin</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Enlaces Externos -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Enlaces</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="index.php" class="sidebar-nav-link">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Portal Principal</span>
                    </a>
                </li>
            </ul>
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

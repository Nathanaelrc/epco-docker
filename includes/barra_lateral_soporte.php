<?php
/**
 * EPCO - Sidebar para Dashboard Soporte TI
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
$soporteStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN status = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN status IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN priority = 'urgente' AND status NOT IN ('resuelto', 'cerrado') THEN 1 ELSE 0 END) as urgentes
    FROM tickets
")->fetch();
$misTicketsCount = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE assigned_to = ?");
$misTicketsCount->execute([$user['id']]);
$soporteStats['mis_tickets'] = $misTicketsCount->fetch()['total'];
?>

<!-- Sidebar Soporte TI Styles -->
<link href="css/sidebar-soporte.css" rel="stylesheet">

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

<!-- Sidebar Soporte TI -->
<aside class="epco-sidebar" id="epcoSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo">
                <i class="bi bi-headset"></i>
            </div>
            <div class="sidebar-brand-text">
                <h4>Soporte TI</h4>
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
                    <a href="soporte_admin.php?page=dashboard" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'dashboard') || !isset($_GET['page']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=nuevo_ticket" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'nuevo_ticket') ? 'active' : '' ?>">
                        <i class="bi bi-plus-circle"></i>
                        <span>Nuevo Ticket</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=tickets&filter=urgent" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'tickets' && isset($_GET['filter']) && $_GET['filter'] === 'urgent') ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Urgentes</span>
                        <?php if ($soporteStats['urgentes'] > 0): ?>
                        <span class="badge urgent"><?= $soporteStats['urgentes'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Mis Tickets -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Mis Tickets</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=mis_tickets" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'mis_tickets') ? 'active' : '' ?>">
                        <i class="bi bi-person-badge"></i>
                        <span>Asignados a mí</span>
                        <?php if ($soporteStats['mis_tickets'] > 0): ?>
                        <span class="badge info"><?= $soporteStats['mis_tickets'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=mi_cumplimiento" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'mi_cumplimiento') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer"></i>
                        <span>Mi Cumplimiento SLA</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Clasificación de Tickets -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Clasificación de Tickets</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=tickets&filter=all" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'tickets' && (!isset($_GET['filter']) || $_GET['filter'] === 'all')) ? 'active' : '' ?>">
                        <i class="bi bi-ticket-detailed"></i>
                        <span>Todos los Tickets</span>
                        <span class="badge"><?= $soporteStats['total'] ?></span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=tickets&filter=open" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'tickets' && isset($_GET['filter']) && $_GET['filter'] === 'open') ? 'active' : '' ?>">
                        <i class="bi bi-inbox"></i>
                        <span>Abiertos</span>
                        <span class="badge info"><?= $soporteStats['abiertos'] + $soporteStats['en_proceso'] ?></span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=tickets&filter=closed" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'tickets' && isset($_GET['filter']) && $_GET['filter'] === 'closed') ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i>
                        <span>Cerrados</span>
                        <span class="badge"><?= $soporteStats['cerrados'] ?></span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Cumplimiento -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Cumplimiento</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=cumplimiento" class="sidebar-nav-link <?= (isset($_GET['page']) && in_array($_GET['page'], ['sla', 'cumplimiento'])) ? 'active' : '' ?>">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Cumplimiento</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Administración -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Administración</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=auditoria" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'auditoria') ? 'active' : '' ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Auditoría</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=notificaciones" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'notificaciones') ? 'active' : '' ?>">
                        <i class="bi bi-envelope-at"></i>
                        <span>Notificaciones</span>
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="sidebar-nav-item">
                    <a href="soporte_admin.php?page=usuarios" class="sidebar-nav-link <?= (isset($_GET['page']) && $_GET['page'] === 'usuarios') ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Navegación -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Navegación</div>
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

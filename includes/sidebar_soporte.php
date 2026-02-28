<?php
/**
 * EPCO - Sidebar para Dashboard Soporte TI
 * Aparece al hacer click en el logo EPCO
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}

if (!$user) {
    header('Location: login.php');
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
<style>
    /* Header minimalista con logo clickeable */
    .epco-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 50px;
        background: linear-gradient(135deg, #0c5a8a 0%, #094a72 100%);
        z-index: 1001;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .epco-logo-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255,255,255,0.1);
        border: none;
        padding: 6px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: white;
    }
    
    .epco-logo-btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .epco-logo-btn .logo-text {
        font-size: 0.95rem;
        font-weight: 600;
    }
    
    .epco-logo-btn .menu-icon {
        font-size: 1.1rem;
        transition: transform 0.2s ease;
    }
    
    .epco-logo-btn.active .menu-icon {
        transform: rotate(90deg);
    }
    
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .topbar-clock {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        padding: 5px 10px;
        border-radius: 6px;
        color: white;
        font-size: 0.8rem;
    }
    
    .topbar-user {
        display: flex;
        align-items: center;
        gap: 8px;
        color: white;
    }
    
    .topbar-user-info {
        text-align: right;
    }
    
    .topbar-user-name {
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .topbar-user-role {
        font-size: 0.7rem;
        opacity: 0.8;
    }
    
    .topbar-avatar {
        width: 32px;
        height: 32px;
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .topbar-avatar:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Overlay oscuro */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 1002;
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease;
        backdrop-filter: blur(3px);
    }
    
    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Sidebar principal */
    .epco-sidebar {
        position: fixed;
        top: 0;
        left: -280px;
        width: 280px;
        height: 100vh;
        background: linear-gradient(180deg, #0c5a8a 0%, #094a72 50%, #073a5a 100%);
        z-index: 1003;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 20px rgba(0,0,0,0.25);
    }
    
    .epco-sidebar.active {
        left: 0;
    }
    
    /* Header del sidebar */
    .sidebar-header {
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-brand-logo {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.15);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
    }
    
    .sidebar-brand-text {
        color: white;
    }
    
    .sidebar-brand-text h4 {
        margin: 0;
        font-weight: 700;
        font-size: 1.2rem;
        letter-spacing: -0.5px;
    }
    
    .sidebar-brand-text span {
        font-size: 0.65rem;
        opacity: 0.7;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    
    .sidebar-close {
        background: rgba(255,255,255,0.1);
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .sidebar-close:hover {
        background: rgba(255,255,255,0.2);
        transform: rotate(90deg);
    }
    
    /* Contenido del sidebar */
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 12px 0;
    }
    
    .sidebar-section {
        margin-bottom: 12px;
    }
    
    .sidebar-section-title {
        color: rgba(255,255,255,0.5);
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 0 18px;
        margin-bottom: 6px;
    }
    
    .sidebar-nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .sidebar-nav-item {
        margin: 1px 8px;
    }
    
    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 14px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.82rem;
        position: relative;
    }
    
    .sidebar-nav-link:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        transform: translateX(3px);
    }
    
    .sidebar-nav-link.active {
        background: rgba(255,255,255,0.15);
        color: white;
        box-shadow: inset 3px 0 0 white;
    }
    
    .sidebar-nav-link i {
        width: 18px;
        font-size: 1rem;
        text-align: center;
    }
    
    .sidebar-nav-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 0.65rem;
        padding: 2px 6px;
        border-radius: 10px;
    }
    
    .sidebar-nav-link .badge.urgent {
        background: #fbbf24;
        color: #854d0e;
    }
    
    .sidebar-nav-link .badge.alert {
        background: #ef4444;
    }
    
    .sidebar-nav-link .badge.info {
        background: #3b82f6;
    }
    
    /* Footer del sidebar */
    .sidebar-footer {
        padding: 12px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-user-card {
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-user-avatar {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.95rem;
        color: white;
    }
    
    .sidebar-user-info {
        flex: 1;
    }
    
    .sidebar-user-info h6 {
        color: white;
        margin: 0 0 1px 0;
        font-weight: 600;
        font-size: 0.82rem;
    }
    
    .sidebar-user-info span {
        color: rgba(255,255,255,0.6);
        font-size: 0.7rem;
    }
    
    .sidebar-logout {
        background: rgba(239, 68, 68, 0.2);
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: #fca5a5;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    
    .sidebar-logout:hover {
        background: #ef4444;
        color: white;
    }
    
    /* Ajustar contenido principal */
    body.has-sidebar {
        padding-top: 50px;
    }
    
    /* Responsive */
    @media (max-width: 576px) {
        .epco-sidebar {
            width: 100%;
            left: -100%;
        }
        
        .topbar-user-info,
        .topbar-clock {
            display: none;
        }
    }
    
    /* Scrollbar del sidebar */
    .sidebar-content::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar-content::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 4px;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.3);
    }
</style>

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
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                    <!-- Intranet oculta temporalmente
                    <li><a class="dropdown-item" href="intranet_dashboard.php"><i class="bi bi-house me-2"></i>Intranet</a></li>
                    -->
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
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
            <a href="logout.php" class="sidebar-logout" title="Cerrar sesión">
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

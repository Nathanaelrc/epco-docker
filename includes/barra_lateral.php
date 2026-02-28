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
<style>
    /* Header minimalista con logo clickeable */
    .epco-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: linear-gradient(135deg, #0c5a8a 0%, #094a72 100%);
        z-index: 1001;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }
    
    .epco-logo-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255,255,255,0.1);
        border: none;
        padding: 8px 16px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }
    
    .epco-logo-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.02);
    }
    
    .epco-logo-btn .logo-text {
        font-size: 1.1rem;
        font-weight: 700;
    }
    
    .epco-logo-btn .menu-icon {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }
    
    .epco-logo-btn.active .menu-icon {
        transform: rotate(90deg);
    }
    
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .topbar-clock {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.15);
        padding: 8px 15px;
        border-radius: 10px;
        color: white;
        font-size: 0.9rem;
    }
    
    .topbar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    
    .topbar-user-info {
        text-align: right;
    }
    
    .topbar-user-name {
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .topbar-user-role {
        font-size: 0.75rem;
        opacity: 0.8;
    }
    
    .topbar-avatar {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s;
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
        background: rgba(0,0,0,0.5);
        z-index: 1002;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        backdrop-filter: blur(4px);
    }
    
    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Sidebar principal */
    .epco-sidebar {
        position: fixed;
        top: 0;
        left: -320px;
        width: 320px;
        height: 100vh;
        background: linear-gradient(180deg, #0c5a8a 0%, #094a72 50%, #073a5a 100%);
        z-index: 1003;
        transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 5px 0 30px rgba(0,0,0,0.3);
    }
    
    .epco-sidebar.active {
        left: 0;
    }
    
    /* Header del sidebar */
    .sidebar-header {
        padding: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar-brand-logo {
        width: 45px;
        height: 45px;
        background: rgba(255,255,255,0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
    }
    
    .sidebar-brand-text {
        color: white;
    }
    
    .sidebar-brand-text h4 {
        margin: 0;
        font-weight: 800;
        font-size: 1.5rem;
        letter-spacing: -1px;
    }
    
    .sidebar-brand-text span {
        font-size: 0.75rem;
        opacity: 0.7;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .sidebar-close {
        background: rgba(255,255,255,0.1);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
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
        padding: 20px 0;
    }
    
    .sidebar-section {
        margin-bottom: 25px;
    }
    
    .sidebar-section-title {
        color: rgba(255,255,255,0.5);
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 2px;
        padding: 0 25px;
        margin-bottom: 10px;
    }
    
    .sidebar-nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .sidebar-nav-item {
        margin: 2px 12px;
    }
    
    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 18px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 0.95rem;
        position: relative;
    }
    
    .sidebar-nav-link:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        transform: translateX(5px);
    }
    
    .sidebar-nav-link.active {
        background: rgba(255,255,255,0.15);
        color: white;
        box-shadow: inset 4px 0 0 white;
    }
    
    .sidebar-nav-link i {
        width: 22px;
        font-size: 1.15rem;
        text-align: center;
    }
    
    .sidebar-nav-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 0.7rem;
        padding: 4px 8px;
        border-radius: 20px;
    }
    
    .sidebar-nav-link .badge.new {
        background: #22c55e;
    }
    
    .sidebar-nav-link .badge.alert {
        background: #ef4444;
    }
    
    /* Footer del sidebar */
    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-user-card {
        background: rgba(255,255,255,0.1);
        border-radius: 14px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar-user-avatar {
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        color: white;
    }
    
    .sidebar-user-info {
        flex: 1;
    }
    
    .sidebar-user-info h6 {
        color: white;
        margin: 0 0 2px 0;
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .sidebar-user-info span {
        color: rgba(255,255,255,0.6);
        font-size: 0.8rem;
    }
    
    .sidebar-logout {
        background: rgba(239, 68, 68, 0.2);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        color: #fca5a5;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .sidebar-logout:hover {
        background: #ef4444;
        color: white;
    }
    
    /* Ajustar contenido principal */
    body.has-sidebar {
        padding-top: 60px;
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
        width: 5px;
    }
    
    .sidebar-content::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
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
                <h4>EPCO</h4>
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

<?php
/**
 * EPCO - Navbar Reutilizable
 * Incluir en todas las páginas de la intranet
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

<!-- Navbar Styles -->
<style>
    .intranet-header {
        background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
        color: white;
        padding: 0;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .header-top {
        padding: 15px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .header-logo {
        font-size: 1.8rem;
        font-weight: 800;
        letter-spacing: -1px;
        color: white;
        text-decoration: none;
    }
    
    .header-logo:hover {
        color: white;
    }
    
    .header-nav {
        padding: 0;
    }
    
    .header-nav .nav-link {
        color: rgba(255,255,255,0.8);
        font-weight: 500;
        padding: 15px 20px;
        transition: all 0.3s;
        border-bottom: 3px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .header-nav .nav-link:hover, 
    .header-nav .nav-link.active {
        color: white;
        background: rgba(255,255,255,0.1);
        border-bottom-color: white;
    }
    
    .user-menu {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .user-avatar {
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
    
    .user-avatar:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .navbar-badge {
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 20px;
    }
    
    @media (max-width: 768px) {
        .header-nav .nav-link {
            padding: 12px 15px;
            font-size: 0.9rem;
        }
        .header-logo {
            font-size: 1.4rem;
        }
    }
</style>

<!-- Header -->
<header class="intranet-header">
    <div class="container">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="panel_intranet.php" class="header-logo text-decoration-none">EPCO</a>
                <span class="navbar-badge d-none d-sm-inline">Intranet</span>
            </div>
            <div class="user-menu">
                <div class="chile-clock d-none d-md-flex align-items-center me-3" style="background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 10px;">
                    <i class="bi bi-clock me-2"></i>
                    <span id="chileTime" style="font-weight: 600; font-size: 0.95rem;">--:--:--</span>
                    <small class="ms-2 opacity-75">Chile</small>
                </div>
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                    <small class="opacity-75"><?= ucfirst($user['role']) ?></small>
                </div>
                <div class="dropdown">
                    <div class="user-avatar" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="buscar.php"><i class="bi bi-search me-2"></i>Búsqueda</a></li>
                        <?php if ($isAdminOrSoporte): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Administración</li>
                        <li><a class="dropdown-item" href="soporte_admin.php"><i class="bi bi-speedometer2 me-2"></i>Panel Soporte</a></li>
                        <li><a class="dropdown-item" href="admin_usuarios.php"><i class="bi bi-people me-2"></i>Usuarios</a></li>
                        <li><a class="dropdown-item" href="registro_auditoria.php"><i class="bi bi-journal-text me-2"></i>Auditoría</a></li>
                        <li><a class="dropdown-item" href="reportes.php"><i class="bi bi-file-spreadsheet me-2"></i>Reportes</a></li>
                        <?php endif; ?>
                        <?php if ($canViewDenuncias): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Ley Karin</li>
                        <li><a class="dropdown-item" href="denuncias_admin.php"><i class="bi bi-shield-exclamation me-2"></i>Panel Denuncias</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="cerrar_sesion.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <nav class="header-nav">
        <div class="container">
            <div class="d-flex flex-wrap">
                <a href="panel_intranet.php" class="nav-link <?= $currentPage === 'intranet_dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i><span class="d-none d-sm-inline">Inicio</span>
                </a>
                <a href="documentos.php" class="nav-link <?= $currentPage === 'documents' ? 'active' : '' ?>">
                    <i class="bi bi-folder"></i><span class="d-none d-sm-inline">Documentos</span>
                </a>
                <a href="base_conocimiento.php" class="nav-link <?= $currentPage === 'knowledge_base' ? 'active' : '' ?>">
                    <i class="bi bi-book"></i><span class="d-none d-sm-inline">Conocimiento</span>
                </a>
                <a href="eventos.php" class="nav-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-event"></i><span class="d-none d-sm-inline">Calendario</span>
                </a>
                <a href="intranet_soporte.php" class="nav-link <?= $currentPage === 'intranet_soporte' || $currentPage === 'soporte' ? 'active' : '' ?>">
                    <i class="bi bi-headset"></i><span class="d-none d-sm-inline">Soporte</span>
                </a>
                <?php if ($canManageNews): ?>
                <a href="admin_noticias.php" class="nav-link <?= $currentPage === 'news_admin' ? 'active' : '' ?>">
                    <i class="bi bi-newspaper"></i><span class="d-none d-sm-inline">Noticias</span>
                </a>
                <?php endif; ?>
                <?php if ($canManageBulletins): ?>
                <a href="admin_boletines.php" class="nav-link <?= $currentPage === 'bulletin_admin' ? 'active' : '' ?>">
                    <i class="bi bi-pin-angle"></i><span class="d-none d-sm-inline">Boletines</span>
                </a>
                <?php endif; ?>
                <?php if ($canViewDenuncias): ?>
                <a href="denuncias_admin.php" class="nav-link <?= $currentPage === 'denuncias_admin' ? 'active' : '' ?>">
                    <i class="bi bi-shield-exclamation"></i><span class="d-none d-sm-inline">Denuncias</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Script del Reloj Chile -->
<script>
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

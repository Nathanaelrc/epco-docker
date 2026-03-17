<?php
/**
 * EPCO - Gestión de Usuarios (Solo Admin)
 */
require_once '../includes/bootstrap.php';

requireAuth('iniciar_sesion.php?redirect=admin_usuarios.php');
$user = getCurrentUser();

// Solo admin puede acceder
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';
$editUser = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforcePostCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitize($_POST['name']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize($_POST['role']);
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $birthday = $_POST['birthday'] ?? null;
        
        if (empty($name) || empty($email) || empty($password)) {
            $message = 'Nombre, email y contraseña son requeridos';
            $messageType = 'danger';
        } else {
            // Verificar email único
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'El email ya está registrado';
                $messageType = 'danger';
            } else {
                // Generar username si no se proporciona
                if (empty($username)) {
                    $username = strtolower(str_replace(' ', '.', $name));
                    $username = preg_replace('/[^a-z0-9.]/', '', $username);
                }
                
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    INSERT INTO users (name, username, email, password, role, department, position, phone, birthday) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$name, $username, $email, $hashedPassword, $role, $department, $position, $phone, $birthday ?: null]);
                
                // Log de actividad
                logActivity($user['id'], 'user_created', 'users', $pdo->lastInsertId(), "Usuario $name creado");
                
                $message = 'Usuario creado exitosamente';
                $messageType = 'success';
            }
        }
    }
    
    if ($action === 'update') {
        $userId = (int)$_POST['user_id'];
        $name = sanitize($_POST['name']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $birthday = $_POST['birthday'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Verificar que no sea el único admin
        if ($userId == $user['id'] && $role !== 'admin') {
            $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($adminCount <= 1) {
                $message = 'No puedes quitar el rol de admin, eres el único administrador activo';
                $messageType = 'danger';
            }
        }
        
        if (empty($message)) {
            if (!empty($_POST['password'])) {
                $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    UPDATE users SET name=?, username=?, email=?, password=?, role=?, department=?, position=?, phone=?, birthday=?, is_active=? 
                    WHERE id=?
                ');
                $stmt->execute([$name, $username, $email, $hashedPassword, $role, $department, $position, $phone, $birthday ?: null, $isActive, $userId]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE users SET name=?, username=?, email=?, role=?, department=?, position=?, phone=?, birthday=?, is_active=? 
                    WHERE id=?
                ');
                $stmt->execute([$name, $username, $email, $role, $department, $position, $phone, $birthday ?: null, $isActive, $userId]);
            }
            
            logActivity($user['id'], 'user_updated', 'users', $userId, "Usuario $name actualizado");
            
            $message = 'Usuario actualizado exitosamente';
            $messageType = 'success';
        }
    }
    
    if ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        
        if ($userId == $user['id']) {
            $message = 'No puedes eliminarte a ti mismo';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $deletedUser = $stmt->fetch();
            
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            
            logActivity($user['id'], 'user_deleted', 'users', $userId, "Usuario {$deletedUser['name']} eliminado");
            
            $message = 'Usuario eliminado';
            $messageType = 'success';
        }
    }
    
    if ($action === 'toggle_active') {
        $userId = (int)$_POST['user_id'];
        
        if ($userId == $user['id']) {
            $message = 'No puedes desactivarte a ti mismo';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$userId]);
            
            $message = 'Estado del usuario actualizado';
            $messageType = 'success';
        }
    }
}

// Cargar usuario para editar
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Obtener usuarios
$search = sanitize($_GET['search'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');

$where = '1=1';
$params = [];

if (!empty($search)) {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR username LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($roleFilter)) {
    $where .= ' AND role = ?';
    $params[] = $roleFilter;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Estadísticas
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'soporte' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'soporte'")->fetchColumn(),
];

$roleColors = ['admin' => 'danger', 'soporte' => 'warning', 'social' => 'info', 'user' => 'secondary'];
$roleLabels = ['admin' => 'Administrador', 'soporte' => 'Soporte TI', 'social' => 'Comunicaciones', 'user' => 'Usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Gestión de Usuarios</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/admin-usuarios.css" rel="stylesheet">
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Gestion de Usuarios</h2>
                <p class="text-muted mb-0">Administra los usuarios del sistema</p>
            </div>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                <i class="bi bi-plus-lg me-2"></i>Nuevo Usuario
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Usuarios</div>
                            <div class="fs-4 fw-bold"><?= $stats['total'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Activos</div>
                            <div class="fs-4 fw-bold"><?= $stats['active'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Administradores</div>
                            <div class="fs-4 fw-bold"><?= $stats['admins'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-headset"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Soporte TI</div>
                            <div class="fs-4 fw-bold"><?= $stats['soporte'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="table-card mb-4">
            <div class="p-4 border-bottom">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, email o usuario..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="soporte" <?= $roleFilter === 'soporte' ? 'selected' : '' ?>>Soporte TI</option>
                            <option value="social" <?= $roleFilter === 'social' ? 'selected' : '' ?>>Comunicaciones</option>
                            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Usuario</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                    <div class="col-md-2">
                        <a href="admin_usuarios.php" class="btn btn-outline-secondary w-100">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Departamento</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                        <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $roleColors[$u['role']] ?>">
                                    <?= $roleLabels[$u['role']] ?>
                                </span>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($u['department'] ?? '-') ?></td>
                            <td class="align-middle">
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Nunca' ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro?')">
            <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?> me-1" title="<?= $u['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario permanentemente?')">
            <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>
                                No se encontraron usuarios
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Usuario -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="userForm">
            <?= csrfInput() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="user_id" id="userId" value="">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre completo *</label>
                                <input type="text" name="name" id="userName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre de usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" name="username" id="userUsername" class="form-control" placeholder="Se genera automáticamente">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" id="userEmail" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <span id="pwdRequired">*</span></label>
                                <input type="password" name="password" id="userPassword" class="form-control">
                                <small class="text-muted" id="pwdHelp">Mínimo 6 caracteres</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rol *</label>
                                <select name="role" id="userRole" class="form-select" required>
                                    <option value="user">Usuario</option>
                                    <option value="soporte">Soporte TI</option>
                                    <option value="social">Comunicaciones</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Departamento</label>
                                <input type="text" name="department" id="userDepartment" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cargo</label>
                                <input type="text" name="position" id="userPosition" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="phone" id="userPhone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="birthday" id="userBirthday" class="form-control">
                            </div>
                            <div class="col-md-6" id="activeField" style="display: none;">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="checkbox" name="is_active" id="userActive" class="form-check-input" checked>
                                    <label class="form-check-label" for="userActive">Usuario activo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i><span id="submitText">Crear Usuario</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
            document.getElementById('formAction').value = 'create';
            document.getElementById('userId').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('userPassword').required = true;
            document.getElementById('pwdRequired').style.display = 'inline';
            document.getElementById('pwdHelp').textContent = 'Mínimo 6 caracteres';
            document.getElementById('activeField').style.display = 'none';
            document.getElementById('submitText').textContent = 'Crear Usuario';
        }
        
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Editar Usuario';
            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = user.id;
            document.getElementById('userName').value = user.name;
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userDepartment').value = user.department || '';
            document.getElementById('userPosition').value = user.position || '';
            document.getElementById('userPhone').value = user.phone || '';
            document.getElementById('userBirthday').value = user.birthday || '';
            document.getElementById('userActive').checked = user.is_active == 1;
            document.getElementById('userPassword').required = false;
            document.getElementById('userPassword').value = '';
            document.getElementById('pwdRequired').style.display = 'none';
            document.getElementById('pwdHelp').textContent = 'Dejar en blanco para mantener la actual';
            document.getElementById('activeField').style.display = 'block';
            document.getElementById('submitText').textContent = 'Guardar Cambios';
            
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }
        
        <?php if ($editUser): ?>
        editUser(<?= json_encode($editUser) ?>);
        <?php endif; ?>
    </script>
</body>
</html>

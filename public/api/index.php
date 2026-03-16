<?php
/**
 * EPCO - API REST
 * Endpoints para integración con sistemas externos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../includes/bootstrap.php';

// Obtener token de autorización
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$apiToken = str_replace('Bearer ', '', $authHeader);

// Validar token
function validateToken($pdo, $token) {
    if (empty($token)) return null;
    
    $stmt = $pdo->prepare('
        SELECT at.*, u.id as user_id, u.name, u.email, u.role 
        FROM api_tokens at 
        JOIN users u ON at.user_id = u.id 
        WHERE at.token = ? AND at.is_active = 1 AND (at.expires_at IS NULL OR at.expires_at > NOW())
    ');
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();
    
    if ($tokenData) {
        // Actualizar último uso
        $stmt = $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?');
        $stmt->execute([$tokenData['id']]);
    }
    
    return $tokenData;
}

// Respuesta de error
function errorResponse($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => true, 'message' => $message]);
    exit;
}

// Respuesta exitosa
function successResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Parsear ruta
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/public/api/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// Recursos públicos (sin autenticación)
$publicResources = ['health', 'version'];

// Validar autenticación para recursos protegidos
$user = null;
if (!in_array($resource, $publicResources)) {
    $user = validateToken($pdo, $apiToken);
    if (!$user) {
        errorResponse(401, 'Token de API inválido o expirado');
    }
}

// ============== ENDPOINTS ==============

// Health check
if ($resource === 'health') {
    successResponse(['status' => 'ok', 'timestamp' => date('c')]);
}

// Versión de la API
if ($resource === 'version') {
    successResponse(['version' => '1.0.0', 'name' => 'Empresa Portuaria Coquimbo API']);
}

// ============== TICKETS ==============
if ($resource === 'tickets') {
    
    // GET /tickets - Listar tickets
    if ($method === 'GET' && !$id) {
        $where = '1=1';
        $params = [];
        
        // Filtros
        if (isset($_GET['status'])) {
            $where .= ' AND t.status = ?';
            $params[] = $_GET['status'];
        }
        if (isset($_GET['priority'])) {
            $where .= ' AND t.priority = ?';
            $params[] = $_GET['priority'];
        }
        if (isset($_GET['category'])) {
            $where .= ' AND t.category = ?';
            $params[] = $_GET['category'];
        }
        
        // Solo admin/soporte ven todos, usuarios solo los suyos
        if (!in_array($user['role'], ['admin', 'soporte'])) {
            $where .= ' AND t.user_id = ?';
            $params[] = $user['user_id'];
        }
        
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT t.*, u.name as user_name, tech.name as assigned_name
            FROM tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users tech ON t.assigned_to = tech.id
            WHERE $where
            ORDER BY t.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $tickets = $stmt->fetchAll();
        
        successResponse(['tickets' => $tickets, 'count' => count($tickets)]);
    }
    
    // GET /tickets/{id} - Obtener ticket
    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('
            SELECT t.*, u.name as user_name, u.email as user_email, tech.name as assigned_name
            FROM tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users tech ON t.assigned_to = tech.id
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            errorResponse(404, 'Ticket no encontrado');
        }
        
        // Verificar permisos
        if (!in_array($user['role'], ['admin', 'soporte']) && $ticket['user_id'] != $user['user_id']) {
            errorResponse(403, 'No tienes permiso para ver este ticket');
        }
        
        // Obtener comentarios
        $stmt = $pdo->prepare('SELECT tc.*, u.name as author_name FROM ticket_comments tc LEFT JOIN users u ON tc.user_id = u.id WHERE tc.ticket_id = ? ORDER BY tc.created_at');
        $stmt->execute([$id]);
        $ticket['comments'] = $stmt->fetchAll();
        
        successResponse($ticket);
    }
    
    // POST /tickets - Crear ticket
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';
        $category = $input['category'] ?? 'general';
        $priority = $input['priority'] ?? 'medium';
        
        if (empty($title) || empty($description)) {
            errorResponse(400, 'Título y descripción son requeridos');
        }
        
        $stmt = $pdo->prepare('INSERT INTO tickets (user_id, title, description, category, priority) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['user_id'], $title, $description, $category, $priority]);
        $ticketId = $pdo->lastInsertId();
        
        successResponse(['id' => $ticketId, 'message' => 'Ticket creado exitosamente'], 201);
    }
    
    // PUT /tickets/{id} - Actualizar ticket
    if ($method === 'PUT' && $id) {
        if (!in_array($user['role'], ['admin', 'soporte'])) {
            errorResponse(403, 'No tienes permiso para actualizar tickets');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updates = [];
        $params = [];
        
        if (isset($input['status'])) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
            if ($input['status'] === 'closed') {
                $updates[] = 'closed_at = NOW()';
            }
        }
        if (isset($input['priority'])) {
            $updates[] = 'priority = ?';
            $params[] = $input['priority'];
        }
        if (isset($input['assigned_to'])) {
            $updates[] = 'assigned_to = ?';
            $params[] = $input['assigned_to'];
        }
        
        if (empty($updates)) {
            errorResponse(400, 'No hay campos para actualizar');
        }
        
        $params[] = $id;
        $stmt = $pdo->prepare('UPDATE tickets SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);
        
        successResponse(['message' => 'Ticket actualizado']);
    }
}

// ============== USERS ==============
if ($resource === 'users') {
    
    // Solo admin puede gestionar usuarios
    if ($user['role'] !== 'admin') {
        errorResponse(403, 'Solo administradores pueden acceder a este recurso');
    }
    
    // GET /users - Listar usuarios
    if ($method === 'GET' && !$id) {
        $stmt = $pdo->query('SELECT id, name, email, role, department, position, is_active, created_at FROM users ORDER BY name');
        $users = $stmt->fetchAll();
        successResponse(['users' => $users]);
    }
    
    // GET /users/{id} - Obtener usuario
    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT id, name, email, role, department, position, phone, is_active, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $userData = $stmt->fetch();
        
        if (!$userData) {
            errorResponse(404, 'Usuario no encontrado');
        }
        
        successResponse($userData);
    }
}

// ============== STATS ==============
if ($resource === 'stats') {
    
    if (!in_array($user['role'], ['admin', 'soporte'])) {
        errorResponse(403, 'No tienes permiso para ver estadísticas');
    }
    
    // Estadísticas generales
    $stats = [];
    
    // Tickets
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM tickets GROUP BY status");
    $stats['tickets_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM tickets WHERE status != 'closed' GROUP BY priority");
    $stats['open_tickets_by_priority'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()");
    $stats['tickets_today'] = $stmt->fetchColumn();
    
    // Usuarios
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetchColumn();
    
    // SLA
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, first_response_at) <= 
                CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 4 WHEN 'medium' THEN 8 ELSE 24 END 
                THEN 1 END) as met,
            COUNT(*) as total
        FROM tickets WHERE first_response_at IS NOT NULL
    ");
    $sla = $stmt->fetch();
    $stats['sla_compliance'] = $sla['total'] > 0 ? round(($sla['met'] / $sla['total']) * 100, 1) : 100;
    
    successResponse($stats);
}

// ============== TOKEN MANAGEMENT ==============
if ($resource === 'tokens') {
    
    // POST /tokens - Generar nuevo token (requiere login normal)
    if ($method === 'POST' && !$apiToken) {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $name = $input['name'] ?? 'API Token';
        
        // Verificar credenciales
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $userData = $stmt->fetch();
        
        if (!$userData || !password_verify($password, $userData['password'])) {
            errorResponse(401, 'Credenciales inválidas');
        }
        
        // Generar token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $pdo->prepare('INSERT INTO api_tokens (user_id, name, token, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userData['id'], $name, $token, $expiresAt]);
        
        successResponse([
            'token' => $token,
            'expires_at' => $expiresAt,
            'message' => 'Guarda este token, no podrás verlo de nuevo'
        ], 201);
    }
    
    // DELETE /tokens - Revocar token actual
    if ($method === 'DELETE' && $user) {
        $stmt = $pdo->prepare('UPDATE api_tokens SET is_active = 0 WHERE token = ?');
        $stmt->execute([$apiToken]);
        successResponse(['message' => 'Token revocado']);
    }
}

// Recurso no encontrado
errorResponse(404, 'Recurso no encontrado');

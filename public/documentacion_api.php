<?php
/**
 * EPCO - Documentación de API REST
 */
require_once '../includes/bootstrap.php';

$user = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Documentación API</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Fira+Code&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; }
        body { background: #f1f5f9; }
        .navbar-epco { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .endpoint { border-left: 4px solid; padding: 20px; margin-bottom: 20px; background: white; border-radius: 0 12px 12px 0; }
        .endpoint.get { border-color: #198754; }
        .endpoint.post { border-color: #0d6efd; }
        .endpoint.put { border-color: #ffc107; }
        .endpoint.delete { border-color: #dc3545; }
        .method-badge { font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 4px; }
        .method-badge.get { background: #d1e7dd; color: #0f5132; }
        .method-badge.post { background: #cfe2ff; color: #084298; }
        .method-badge.put { background: #fff3cd; color: #664d03; }
        .method-badge.delete { background: #f8d7da; color: #842029; }
        pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; }
        code { font-family: 'Fira Code', monospace; font-size: 0.85rem; }
        .sidebar { position: sticky; top: 20px; }
        .nav-link { color: #475569; padding: 8px 15px; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { background: #e2e8f0; color: var(--primary); }
    </style>
    <link href="css/intranet.css" rel="stylesheet">
</head>
<body class="has-sidebar">
    <?php include '../includes/barra_lateral.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sidebar">
                    <div class="card">
                        <div class="card-body p-2">
                            <nav class="nav flex-column">
                                <a class="nav-link" href="#introduccion">Introducción</a>
                                <a class="nav-link" href="#autenticacion">Autenticación</a>
                                <a class="nav-link" href="#tickets">Tickets</a>
                                <a class="nav-link" href="#users">Usuarios</a>
                                <a class="nav-link" href="#stats">Estadísticas</a>
                                <a class="nav-link" href="#errores">Códigos de Error</a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <!-- Introducción -->
                <section id="introduccion" class="mb-5">
                    <h2 class="mb-4"><i class="bi bi-book me-2"></i>API REST EPCO</h2>
                    <div class="card">
                        <div class="card-body">
                            <p>La API REST de EPCO permite integrar el sistema de soporte con aplicaciones externas. Soporta operaciones CRUD sobre tickets y consulta de usuarios y estadísticas.</p>
                            
                            <h5 class="mt-4">URL Base</h5>
                            <pre><code>https://tu-dominio.com/public/api/</code></pre>
                            
                            <h5 class="mt-4">Formato de Respuesta</h5>
                            <p>Todas las respuestas están en formato JSON:</p>
                            <pre><code>{
    "success": true,
    "data": { ... }
}

// En caso de error:
{
    "error": true,
    "message": "Descripción del error"
}</code></pre>
                        </div>
                    </div>
                </section>

                <!-- Autenticación -->
                <section id="autenticacion" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-key me-2"></i>Autenticación</h3>
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <p>La API usa tokens Bearer para autenticación. Incluye el token en el header de cada petición:</p>
                            <pre><code>Authorization: Bearer tu_token_aqui</code></pre>
                        </div>
                    </div>

                    <div class="endpoint post">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge post">POST</span>
                            <code>/tokens</code>
                        </div>
                        <p>Genera un nuevo token de API usando credenciales de usuario.</p>
                        
                        <h6>Request Body</h6>
                        <pre><code>{
    "email": "usuario@epco.cl",
    "password": "contraseña",
    "name": "Mi App Token"  // Opcional
}</code></pre>
                        
                        <h6>Response</h6>
                        <pre><code>{
    "success": true,
    "data": {
        "token": "abc123...",
        "expires_at": "2024-02-15 10:30:00",
        "message": "Guarda este token, no podrás verlo de nuevo"
    }
}</code></pre>
                    </div>

                    <div class="endpoint delete">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge delete">DELETE</span>
                            <code>/tokens</code>
                        </div>
                        <p>Revoca el token actual.</p>
                    </div>
                </section>

                <!-- Tickets -->
                <section id="tickets" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-ticket-perforated me-2"></i>Tickets</h3>

                    <div class="endpoint get">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge get">GET</span>
                            <code>/tickets</code>
                        </div>
                        <p>Lista todos los tickets. Admin/Soporte ven todos, usuarios solo los propios.</p>
                        
                        <h6>Query Parameters</h6>
                        <table class="table table-sm">
                            <tr><td><code>status</code></td><td>open, in_progress, pending, closed</td></tr>
                            <tr><td><code>priority</code></td><td>low, medium, high, urgent</td></tr>
                            <tr><td><code>category</code></td><td>hardware, software, red, acceso, otro</td></tr>
                            <tr><td><code>limit</code></td><td>Máximo de resultados (default: 50, max: 100)</td></tr>
                            <tr><td><code>offset</code></td><td>Offset para paginación</td></tr>
                        </table>
                    </div>

                    <div class="endpoint get">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge get">GET</span>
                            <code>/tickets/{id}</code>
                        </div>
                        <p>Obtiene un ticket específico con sus comentarios.</p>
                    </div>

                    <div class="endpoint post">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge post">POST</span>
                            <code>/tickets</code>
                        </div>
                        <p>Crea un nuevo ticket.</p>
                        
                        <h6>Request Body</h6>
                        <pre><code>{
    "title": "Problema con el equipo",
    "description": "El computador no enciende...",
    "category": "hardware",
    "priority": "high"
}</code></pre>
                    </div>

                    <div class="endpoint put">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge put">PUT</span>
                            <code>/tickets/{id}</code>
                        </div>
                        <p>Actualiza un ticket. Solo admin/soporte.</p>
                        
                        <h6>Request Body</h6>
                        <pre><code>{
    "status": "in_progress",
    "priority": "urgent",
    "assigned_to": 5
}</code></pre>
                    </div>
                </section>

                <!-- Users -->
                <section id="users" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-people me-2"></i>Usuarios</h3>
                    <p class="text-muted mb-4">Solo accesible para administradores.</p>

                    <div class="endpoint get">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge get">GET</span>
                            <code>/users</code>
                        </div>
                        <p>Lista todos los usuarios del sistema.</p>
                    </div>

                    <div class="endpoint get">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge get">GET</span>
                            <code>/users/{id}</code>
                        </div>
                        <p>Obtiene información de un usuario específico.</p>
                    </div>
                </section>

                <!-- Stats -->
                <section id="stats" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-graph-up me-2"></i>Estadísticas</h3>

                    <div class="endpoint get">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="method-badge get">GET</span>
                            <code>/stats</code>
                        </div>
                        <p>Obtiene estadísticas generales del sistema. Solo admin/soporte.</p>
                        
                        <h6>Response</h6>
                        <pre><code>{
    "success": true,
    "data": {
        "tickets_by_status": {
            "open": 15,
            "in_progress": 8,
            "closed": 120
        },
        "open_tickets_by_priority": {
            "urgent": 2,
            "high": 5,
            "medium": 10
        },
        "tickets_today": 3,
        "active_users": 45,
        "sla_compliance": 94.5
    }
}</code></pre>
                    </div>
                </section>

                <!-- Errores -->
                <section id="errores" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-exclamation-triangle me-2"></i>Códigos de Error</h3>
                    
                    <div class="card">
                        <div class="card-body">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><code>400</code></td><td>Bad Request - Datos de entrada inválidos</td></tr>
                                    <tr><td><code>401</code></td><td>Unauthorized - Token inválido o expirado</td></tr>
                                    <tr><td><code>403</code></td><td>Forbidden - Sin permisos para esta acción</td></tr>
                                    <tr><td><code>404</code></td><td>Not Found - Recurso no encontrado</td></tr>
                                    <tr><td><code>500</code></td><td>Internal Server Error - Error del servidor</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Ejemplo -->
                <section id="ejemplo" class="mb-5">
                    <h3 class="mb-4"><i class="bi bi-code-slash me-2"></i>Ejemplo de Uso</h3>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6>cURL</h6>
                            <pre><code># Generar token
curl -X POST https://tu-dominio.com/public/api/tokens \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@epco.cl","password":"password"}'

# Listar tickets
curl https://tu-dominio.com/public/api/tickets \
  -H "Authorization: Bearer tu_token"

# Crear ticket
curl -X POST https://tu-dominio.com/public/api/tickets \
  -H "Authorization: Bearer tu_token" \
  -H "Content-Type: application/json" \
  -d '{"title":"Mi ticket","description":"Descripción..."}'</code></pre>

                            <h6 class="mt-4">JavaScript (Fetch)</h6>
                            <pre><code>// Listar tickets
const response = await fetch('/public/api/tickets', {
    headers: {
        'Authorization': 'Bearer ' + token
    }
});
const data = await response.json();
console.log(data.data.tickets);</code></pre>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

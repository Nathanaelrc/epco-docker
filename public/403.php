<?php
/**
 * EPCO - Página 403 Forbidden
 */
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPCO - Acceso Denegado</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        body { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0ea5e9 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
        body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); opacity: 0.5; pointer-events: none; z-index: 0; }
        .error-card { background: white; border-radius: 20px; padding: 50px; text-align: center; max-width: 450px; box-shadow: 0 25px 80px rgba(0,0,0,0.4); position: relative; z-index: 1; }
        .error-code { font-size: 6rem; font-weight: 800; color: #dc2626; line-height: 1; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">403</div>
        <h2 class="fw-bold mb-3">Acceso Denegado</h2>
        <p class="text-muted mb-4">No tienes permisos para acceder a este recurso.</p>
        <a href="index" class="btn btn-dark btn-lg px-5">
            <i class="bi bi-house me-2"></i>Volver al Inicio
        </a>
    </div>
</body>
</html>

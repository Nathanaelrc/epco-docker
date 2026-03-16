<?php
/**
 * EPCO - Página 500 Internal Server Error
 */
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa Portuaria Coquimbo - Error del Servidor</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/errors.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, rgba(14,165,233,0.6) 0%, rgba(2,132,199,0.65) 50%, rgba(14,165,233,0.6) 100%), url('<?php echo (isset($_SERVER["HTTP_ACCEPT"]) && strpos($_SERVER["HTTP_ACCEPT"], "image/webp") !== false) ? "img/Puerto03.webp" : "img/Puerto03.jpg"; ?>') center/cover no-repeat fixed; min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">500</div>
        <h2 class="fw-bold mb-3">Error del Servidor</h2>
        <p class="text-muted mb-4">Ha ocurrido un error interno. Por favor intenta más tarde.</p>
        <a href="index" class="btn btn-dark btn-lg px-5">
            <i class="bi bi-house me-2"></i>Volver al Inicio
        </a>
    </div>
</body>
</html>

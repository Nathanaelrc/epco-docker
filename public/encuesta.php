<?php
/**
 * EPCO - Encuesta de Satisfacción de Ticket
 */
require_once '../includes/bootstrap.php';

$message = '';
$messageType = '';
$showForm = false;
$ticket = null;

// Verificar token de encuesta
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare('
        SELECT t.*, u.name as user_name, u.email as user_email 
        FROM tickets t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE t.survey_token = ? AND t.status = "closed"
    ');
    $stmt->execute([$token]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        // Verificar si ya respondió
        $stmt = $pdo->prepare('SELECT id FROM ticket_surveys WHERE ticket_id = ?');
        $stmt->execute([$ticket['id']]);
        if ($stmt->fetch()) {
            $message = 'Ya has respondido la encuesta para este ticket. ¡Gracias por tu feedback!';
            $messageType = 'info';
        } else {
            $showForm = true;
        }
    } else {
        $message = 'Enlace de encuesta inválido o el ticket no está cerrado.';
        $messageType = 'warning';
    }
} else {
    $message = 'Se requiere un token válido para acceder a la encuesta.';
    $messageType = 'warning';
}

// Procesar encuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $rating = (int)$_POST['rating'];
    $responseTime = (int)$_POST['response_time'];
    $resolution = (int)$_POST['resolution'];
    $communication = (int)$_POST['communication'];
    $wouldRecommend = isset($_POST['would_recommend']) ? 1 : 0;
    $comments = sanitize($_POST['comments'] ?? '');
    
    // Validar
    if ($rating < 1 || $rating > 5) {
        $message = 'Por favor, selecciona una calificación válida.';
        $messageType = 'danger';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO ticket_surveys (ticket_id, rating, response_time_rating, resolution_rating, communication_rating, would_recommend, comments) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$ticket['id'], $rating, $responseTime, $resolution, $communication, $wouldRecommend, $comments]);
        
        // Actualizar ticket
        $stmt = $pdo->prepare('UPDATE tickets SET survey_token = NULL, survey_sent_at = NOW() WHERE id = ?');
        $stmt->execute([$ticket['id']]);
        
        $showForm = false;
        $message = '¡Gracias por tu feedback! Tu opinión nos ayuda a mejorar.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Preconnect CDNs -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <title>EPCO - Encuesta de Satisfacción</title>
    <link rel="icon" type="image/webp" href="img/Logo01.webp"><link rel="icon" type="image/png" href="img/Logo01.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        :root { --primary: #0ea5e9; --primary-light: #0284c7; }
        body { 
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .survey-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        .survey-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 40px;
            text-align: center;
        }
        .survey-body {
            padding: 40px;
        }
        
        .rating-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .rating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid #e9ecef;
            background: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating-btn:hover {
            transform: scale(1.1);
            border-color: var(--primary);
        }
        .rating-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .rating-btn input {
            display: none;
        }
        
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 5px;
            font-size: 2rem;
        }
        .star-rating .star {
            cursor: pointer;
            color: #e9ecef;
            transition: all 0.2s;
        }
        .star-rating .star:hover,
        .star-rating .star.active {
            color: #ffc107;
            transform: scale(1.1);
        }
        
        .question-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        .question-section:last-of-type {
            border-bottom: none;
        }
        .question-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .emoji-rating {
            display: flex;
            justify-content: space-between;
            max-width: 300px;
            margin: 0 auto;
        }
        .emoji-btn {
            font-size: 2rem;
            cursor: pointer;
            opacity: 0.5;
            transition: all 0.3s;
            border: none;
            background: none;
        }
        .emoji-btn:hover,
        .emoji-btn.active {
            opacity: 1;
            transform: scale(1.2);
        }
        .emoji-btn input {
            display: none;
        }
        
        .scale-rating {
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }
        .scale-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        .scale-btn:hover {
            border-color: var(--primary);
        }
        .scale-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .scale-btn input {
            display: none;
        }
        
        .success-animation {
            text-align: center;
            padding: 60px 40px;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body>
    <div class="survey-card">
        <?php if (!$showForm): ?>
        <!-- Mensaje de estado -->
        <div class="success-animation">
            <?php if ($messageType === 'success'): ?>
            <div class="success-icon">
                <i class="bi bi-check-lg text-success fs-1"></i>
            </div>
            <h3 class="text-success mb-3">¡Encuesta Completada!</h3>
            <?php elseif ($messageType === 'info'): ?>
            <div class="success-icon" style="background: #d1ecf1;">
                <i class="bi bi-info-lg text-info fs-1"></i>
            </div>
            <h3 class="text-info mb-3">Encuesta ya Respondida</h3>
            <?php else: ?>
            <div class="success-icon" style="background: #fff3cd;">
                <i class="bi bi-exclamation-lg text-warning fs-1"></i>
            </div>
            <h3 class="text-warning mb-3">Enlace Inválido</h3>
            <?php endif; ?>
            <p class="text-muted mb-4"><?= $message ?></p>
            <a href="soporte.php" class="btn btn-primary">
                <i class="bi bi-headset me-2"></i>Ir a Soporte TI
            </a>
        </div>
        
        <?php else: ?>
        <!-- Formulario de encuesta -->
        <div class="survey-header">
            <i class="bi bi-clipboard-check fs-1 mb-3 d-block"></i>
            <h3 class="mb-2">Encuesta de Satisfacción</h3>
            <p class="mb-0 opacity-75">Ticket #<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['title']) ?></p>
        </div>
        
        <div class="survey-body">
            <form method="POST">
                <!-- Calificación general -->
                <div class="question-section">
                    <h5 class="question-title text-center">
                        <i class="bi bi-star me-2"></i>¿Cómo calificarías tu experiencia general?
                    </h5>
                    <div class="star-rating" id="mainRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-value="<?= $i ?>">
                            <i class="bi bi-star-fill"></i>
                        </span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <div class="text-center mt-2">
                        <small class="text-muted" id="ratingLabel">Selecciona una calificación</small>
                    </div>
                </div>
                
                <!-- Tiempo de respuesta -->
                <div class="question-section">
                    <h5 class="question-title">
                        <i class="bi bi-clock me-2"></i>¿Qué tan satisfecho estás con el tiempo de respuesta?
                    </h5>
                    <div class="scale-rating">
                        <?php foreach ([1 => 'Muy lento', 2 => 'Lento', 3 => 'Normal', 4 => 'Rápido', 5 => 'Muy rápido'] as $val => $label): ?>
                        <label class="scale-btn">
                            <input type="radio" name="response_time" value="<?= $val ?>" required>
                            <div><?= $val ?></div>
                            <small class="d-none d-md-block"><?= $label ?></small>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Resolución -->
                <div class="question-section">
                    <h5 class="question-title">
                        <i class="bi bi-check-circle me-2"></i>¿El problema fue resuelto completamente?
                    </h5>
                    <div class="scale-rating">
                        <?php foreach ([1 => 'No', 2 => 'Parcialmente', 3 => 'Casi todo', 4 => 'Sí', 5 => 'Superado'] as $val => $label): ?>
                        <label class="scale-btn">
                            <input type="radio" name="resolution" value="<?= $val ?>" required>
                            <div><?= $val ?></div>
                            <small class="d-none d-md-block"><?= $label ?></small>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Comunicación -->
                <div class="question-section">
                    <h5 class="question-title">
                        <i class="bi bi-chat-dots me-2"></i>¿Cómo fue la comunicación con el equipo de soporte?
                    </h5>
                    <div class="emoji-rating">
                        <label class="emoji-btn" title="Muy mala">
                            <input type="radio" name="communication" value="1" required>
                            😞
                        </label>
                        <label class="emoji-btn" title="Mala">
                            <input type="radio" name="communication" value="2">
                            😕
                        </label>
                        <label class="emoji-btn" title="Regular">
                            <input type="radio" name="communication" value="3">
                            😐
                        </label>
                        <label class="emoji-btn" title="Buena">
                            <input type="radio" name="communication" value="4">
                            🙂
                        </label>
                        <label class="emoji-btn" title="Excelente">
                            <input type="radio" name="communication" value="5">
                            😊
                        </label>
                    </div>
                </div>
                
                <!-- Recomendaría -->
                <div class="question-section">
                    <h5 class="question-title">
                        <i class="bi bi-hand-thumbs-up me-2"></i>¿Recomendarías nuestro servicio de soporte?
                    </h5>
                    <div class="d-flex justify-content-center gap-4">
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="would_recommend" class="form-check-input" id="wouldRecommend" value="1">
                            <label class="form-check-label" for="wouldRecommend">Sí, lo recomendaría</label>
                        </div>
                    </div>
                </div>
                
                <!-- Comentarios -->
                <div class="question-section">
                    <h5 class="question-title">
                        <i class="bi bi-chat-square-text me-2"></i>¿Tienes algún comentario adicional?
                    </h5>
                    <textarea name="comments" class="form-control" rows="4" placeholder="Cuéntanos cómo podemos mejorar..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-send me-2"></i>Enviar Encuesta
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        // Star rating
        const stars = document.querySelectorAll('#mainRating .star');
        const ratingInput = document.getElementById('ratingInput');
        const ratingLabel = document.getElementById('ratingLabel');
        const labels = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];
        
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const value = index + 1;
                ratingInput.value = value;
                ratingLabel.textContent = labels[value];
                
                stars.forEach((s, i) => {
                    s.classList.toggle('active', i <= index);
                });
            });
            
            star.addEventListener('mouseenter', () => {
                stars.forEach((s, i) => {
                    s.style.color = i <= index ? '#ffc107' : '#e9ecef';
                });
            });
        });
        
        document.getElementById('mainRating').addEventListener('mouseleave', () => {
            stars.forEach((s, i) => {
                s.style.color = s.classList.contains('active') ? '#ffc107' : '#e9ecef';
            });
        });
        
        // Scale buttons
        document.querySelectorAll('.scale-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.scale-rating').querySelectorAll('.scale-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Emoji buttons
        document.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.emoji-rating').querySelectorAll('.emoji-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>

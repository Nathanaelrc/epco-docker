<?php
/**
 * EPCO - Sistema de CAPTCHA Simple
 * Genera y valida captchas matemáticos
 */

class SimpleCaptcha {
    
    /**
     * Genera un nuevo captcha y lo guarda en sesión
     */
    public static function generate(): array {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operators = ['+', '-', '×'];
        $operator = $operators[array_rand($operators)];
        
        switch ($operator) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                // Asegurar que el resultado sea positivo
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
                break;
            case '×':
                // Usar números más pequeños para multiplicación
                $num1 = rand(1, 5);
                $num2 = rand(1, 5);
                $answer = $num1 * $num2;
                break;
        }
        
        $question = "$num1 $operator $num2 = ?";
        $token = bin2hex(random_bytes(16));
        
        // Guardar en sesión
        $_SESSION['captcha'] = [
            'answer' => $answer,
            'token' => $token,
            'created' => time()
        ];
        
        return [
            'question' => $question,
            'token' => $token
        ];
    }
    
    /**
     * Valida la respuesta del captcha
     */
    public static function validate(string $userAnswer, string $token): bool {
        if (!isset($_SESSION['captcha'])) {
            return false;
        }
        
        $captcha = $_SESSION['captcha'];
        
        // Verificar token
        if ($captcha['token'] !== $token) {
            return false;
        }
        
        // Verificar expiración (5 minutos)
        if (time() - $captcha['created'] > 300) {
            unset($_SESSION['captcha']);
            return false;
        }
        
        // Verificar respuesta
        $isValid = (int)$userAnswer === $captcha['answer'];
        
        // Limpiar captcha usado
        unset($_SESSION['captcha']);
        
        return $isValid;
    }
    
    /**
     * Genera HTML del captcha
     */
    public static function render(): string {
        $captcha = self::generate();
        
        return '
        <div class="captcha-container mb-3">
            <label class="form-label">Verificación de seguridad *</label>
            <div class="input-group">
                <span class="input-group-text bg-light fw-bold" style="min-width: 150px;">
                    <i class="bi bi-calculator me-2"></i>' . $captcha['question'] . '
                </span>
                <input type="number" name="captcha_answer" class="form-control" placeholder="Respuesta" required>
                <input type="hidden" name="captcha_token" value="' . $captcha['token'] . '">
            </div>
            <div class="form-text">Resuelve la operación matemática para continuar</div>
        </div>';
    }
    
    /**
     * Genera captcha como imagen SVG
     */
    public static function renderImage(): string {
        $captcha = self::generate();
        $question = htmlspecialchars($captcha['question']);
        
        // Generar SVG con ruido visual
        $lines = '';
        for ($i = 0; $i < 5; $i++) {
            $x1 = rand(0, 200);
            $y1 = rand(0, 50);
            $x2 = rand(0, 200);
            $y2 = rand(0, 50);
            $lines .= "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' stroke='#cbd5e1' stroke-width='1'/>";
        }
        
        $dots = '';
        for ($i = 0; $i < 30; $i++) {
            $cx = rand(0, 200);
            $cy = rand(0, 50);
            $dots .= "<circle cx='$cx' cy='$cy' r='1' fill='#94a3b8'/>";
        }
        
        $svg = "
        <svg xmlns='http://www.w3.org/2000/svg' width='200' height='50' viewBox='0 0 200 50'>
            <rect width='200' height='50' fill='#f1f5f9'/>
            $lines
            $dots
            <text x='100' y='32' font-family='Arial' font-size='20' font-weight='bold' fill='#0a2540' text-anchor='middle'>$question</text>
        </svg>";
        
        $svgBase64 = base64_encode($svg);
        
        return '
        <div class="captcha-container mb-3">
            <label class="form-label">Verificación de seguridad *</label>
            <div class="d-flex align-items-center gap-3 mb-2">
                <img src="data:image/svg+xml;base64,' . $svgBase64 . '" alt="Captcha" class="rounded border">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.closest(\'form\').querySelector(\'.captcha-refresh\').click()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <input type="number" name="captcha_answer" class="form-control" placeholder="Escribe el resultado" required>
            <input type="hidden" name="captcha_token" value="' . $captcha['token'] . '">
            <div class="form-text">Resuelve la operación matemática</div>
        </div>';
    }
}

/**
 * API endpoint para generar nuevo captcha via AJAX
 */
if (basename($_SERVER['PHP_SELF']) === 'captcha.php' && isset($_GET['action'])) {
    session_start();
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'generate') {
        $captcha = SimpleCaptcha::generate();
        echo json_encode($captcha);
        exit;
    }
    
    if ($_GET['action'] === 'validate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $answer = $_POST['answer'] ?? '';
        $token = $_POST['token'] ?? '';
        $isValid = SimpleCaptcha::validate($answer, $token);
        echo json_encode(['valid' => $isValid]);
        exit;
    }
}

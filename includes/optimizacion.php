<?php
/**
 * EPCO - Utilidades de Optimización de Rendimiento
 * Detección de WebP, helpers de imágenes optimizadas
 */

// Detectar soporte WebP del navegador via header Accept
if (!defined('WEBP_SUPPORT')) {
    define('WEBP_SUPPORT', 
        isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false
    );
}

/**
 * Retorna la URL de imagen optimizada (WebP si el navegador lo soporta)
 * Mantiene fallback al formato original si no existe la versión WebP
 * 
 * @param string $imagePath Ruta relativa de la imagen (ej: 'img/Logo01.png')
 * @return string Ruta optimizada
 */
function imgOptimizada($imagePath) {
    if (!WEBP_SUPPORT) {
        return $imagePath;
    }
    
    // Generar ruta WebP
    $pathInfo = pathinfo($imagePath);
    $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    
    // Verificar que existe el archivo WebP (ruta absoluta desde public/)
    $publicDir = defined('EPCO_ROOT') ? EPCO_ROOT . '/public/' : dirname(__DIR__) . '/public/';
    if (file_exists($publicDir . $webpPath)) {
        return $webpPath;
    }
    
    return $imagePath;
}

/**
 * Genera un tag <picture> con WebP y fallback
 * 
 * @param string $src Ruta de imagen original
 * @param string $alt Texto alternativo
 * @param string $attrs Atributos HTML adicionales (class, style, etc)
 * @return string HTML del tag <picture>
 */
function pictureTag($src, $alt = '', $attrs = '') {
    $pathInfo = pathinfo($src);
    $webpSrc = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    $publicDir = defined('EPCO_ROOT') ? EPCO_ROOT . '/public/' : dirname(__DIR__) . '/public/';
    
    $html = '<picture>';
    if (file_exists($publicDir . $webpSrc)) {
        $html .= '<source srcset="' . htmlspecialchars($webpSrc) . '" type="image/webp">';
    }
    $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '"';
    if ($attrs) {
        $html .= ' ' . $attrs;
    }
    $html .= ' loading="lazy">';
    $html .= '</picture>';
    
    return $html;
}

/**
 * Comprime una imagen subida por el usuario (JPEG/PNG)
 * Redimensiona si excede el tamaño máximo y comprime con GD
 * 
 * @param string $filePath Ruta absoluta del archivo ya guardado
 * @param int $maxWidth Ancho máximo (default 1920px)
 * @param int $maxHeight Alto máximo (default 1080px)
 * @param int $quality Calidad JPEG (0-100, default 80)
 * @return bool true si se comprimió, false si no aplica
 */
function comprimirImagenSubida($filePath, $maxWidth = 1920, $maxHeight = 1080, $quality = 80) {
    if (!file_exists($filePath) || !function_exists('imagecreatefromjpeg')) {
        return false;
    }
    
    $mime = mime_content_type($filePath);
    $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    if (!in_array($mime, $imageTypes)) {
        return false; // No es una imagen comprimible
    }
    
    // Obtener dimensiones originales
    $info = getimagesize($filePath);
    if (!$info) return false;
    
    $origWidth = $info[0];
    $origHeight = $info[1];
    
    // Crear imagen según tipo
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($filePath);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Redimensionar si excede dimensiones máximas
    $needsResize = ($origWidth > $maxWidth || $origHeight > $maxHeight);
    
    if ($needsResize) {
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparencia para PNG
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Guardar comprimida (sobreescribe original)
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($image, $filePath, $quality);
            break;
        case 'image/png':
            // PNG: nivel 6 de compresión (0=sin, 9=máximo)
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, $filePath, 6);
            break;
        case 'image/webp':
            imagewebp($image, $filePath, $quality);
            break;
    }
    
    imagedestroy($image);
    return true;
}

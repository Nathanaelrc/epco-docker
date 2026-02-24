<?php
/**
 * Script de prueba para envío de correos
 * ELIMINAR DESPUÉS DE PROBAR
 */

require_once '../includes/MailService.php';

echo "<h2>Test de envío de correo</h2>";

try {
    $mailService = new MailService();
    
    // Datos de ticket de prueba
    $ticketPrueba = [
        'id' => 999,
        'category' => 'Hardware',
        'priority' => 'alta',
        'subject' => 'Prueba de sistema de notificaciones',
        'description' => 'Este es un correo de prueba para verificar que el sistema de notificaciones funciona correctamente.',
        'contact_name' => 'Usuario de Prueba',
        'contact_email' => 'prueba@puertocoquimbo.cl',
        'contact_phone' => '+56 9 1234 5678',
        'location' => 'Oficina TI',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $resultado = $mailService->sendTicketCreatedNotification($ticketPrueba);
    
    if ($resultado) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; color: #155724;'>";
        echo "<h3>✅ Correo enviado exitosamente</h3>";
        echo "<p>Se ha enviado un correo de prueba a <strong>asesorti@puertocoquimbo.cl</strong></p>";
        echo "<p>Revisa tu bandeja de entrada (y spam) en unos minutos.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; color: #721c24;'>";
        echo "<h3>❌ Error al enviar correo</h3>";
        echo "<p>Revisa los logs del servidor para más detalles.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; color: #721c24;'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<br><p><a href='soporte.php'>← Volver al portal de soporte</a></p>";
echo "<p style='color: #666; font-size: 12px;'>⚠️ Recuerda eliminar este archivo después de probar.</p>";

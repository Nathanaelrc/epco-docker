<?php
/**
 * Configuración de correo para EPCO
 * Lee credenciales desde variables de entorno
 * 
 * Modos de envío:
 *   relay  - Usa el microservicio mailrelay (Postfix) en Docker (RECOMENDADO)
 *   direct - Conexión directa al servidor SMTP externo
 * 
 * Proveedores soportados (modo direct):
 *   Outlook: host=smtp-mail.outlook.com, port=587, encryption=tls
 *   Gmail:   host=smtp.gmail.com, port=587, encryption=tls
 *   Yahoo:   host=smtp.mail.yahoo.com, port=587, encryption=tls
 */

$smtpEnabled = getenv('SMTP_ENABLED') ?: 'true';
$smtpMode = getenv('SMTP_MODE') ?: 'relay';

return [
    'smtp' => [
        'use_smtp'    => filter_var($smtpEnabled, FILTER_VALIDATE_BOOLEAN),
        'mode'        => $smtpMode, // 'relay' o 'direct'
        
        // Relay interno (microservicio Postfix en Docker)
        'relay_host'  => getenv('SMTP_RELAY_HOST') ?: 'mailrelay',
        'relay_port'  => (int)(getenv('SMTP_RELAY_PORT') ?: 25),
        
        // SMTP externo directo (fallback)
        'host'        => getenv('SMTP_HOST') ?: 'smtp-mail.outlook.com',
        'port'        => (int)(getenv('SMTP_PORT') ?: 587),
        'username'    => getenv('SMTP_USER') ?: '',
        'password'    => getenv('SMTP_PASS') ?: '',
        'encryption'  => getenv('SMTP_ENCRYPTION') ?: 'tls',
        
        'from_email'  => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'noreply@epco.cl'),
        'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Soporte TI - EPCO',
    ],
    
    // Destinatarios de notificaciones (separados por coma para múltiples)
    'notifications' => [
        'ticket_created' => getenv('NOTIFY_TICKET_CREATED') ?: '',
        'ticket_updated' => getenv('NOTIFY_TICKET_UPDATED') ?: '',
    ]
];

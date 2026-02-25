<?php
/**
 * Configuración de correo para EPCO
 * Lee credenciales desde variables de entorno
 * 
 * Soporta: Gmail, Outlook/Office 365, o cualquier SMTP
 * 
 * Gmail:   host=smtp.gmail.com, port=587, encryption=tls
 * Outlook: host=smtp.office365.com, port=587, encryption=tls
 * Yahoo:   host=smtp.mail.yahoo.com, port=587, encryption=tls
 */

$smtpEnabled = getenv('SMTP_ENABLED') ?: 'false';

return [
    'smtp' => [
        'use_smtp'    => filter_var($smtpEnabled, FILTER_VALIDATE_BOOLEAN),
        'host'        => getenv('SMTP_HOST') ?: 'smtp-mail.outlook.com',
        'port'        => (int)(getenv('SMTP_PORT') ?: 587),
        'username'    => getenv('SMTP_USER') ?: '',
        'password'    => getenv('SMTP_PASS') ?: '',
        'encryption'  => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email'  => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'noreply@epco.cl'),
        'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Soporte TI - EPCO',
    ],
    
    // Destinatarios de notificaciones (separados por coma para múltiples)
    // Ej: "admin@epco.cl,soporte@epco.cl,jefe.ti@epco.cl"
    'notifications' => [
        'ticket_created' => getenv('NOTIFY_TICKET_CREATED') ?: '',
        'ticket_updated' => getenv('NOTIFY_TICKET_UPDATED') ?: '',
    ]
];

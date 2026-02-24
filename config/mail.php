<?php
/**
 * Configuración de correo para EPCO
 * Usa el servidor de correo local sin autenticación
 */

return [
    'smtp' => [
        'use_smtp' => false,                  // false = usar mail() local, true = usar SMTP
        'host' => 'localhost',                // Servidor local
        'port' => 25,                         // Puerto estándar
        'username' => '',                     // Sin usuario
        'password' => '',                     // Sin contraseña
        'encryption' => '',                   // Sin encriptación
        'from_email' => 'noreply@puertocoquimbo.cl',
        'from_name' => 'Soporte TI - Empresa Portuaria Coquimbo',
    ],
    
    'notifications' => [
        'ticket_created' => 'marcosn22rc@gmail.com',
        'ticket_updated' => 'marcosn22rc@gmail.com',
    ]
];

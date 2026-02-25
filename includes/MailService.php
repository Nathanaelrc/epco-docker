<?php
/**
 * Servicio de correo para EPCO
 * Utiliza PHPMailer para envío de correos (local o SMTP)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class MailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/mail.php';
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }
    
    private function configure() {
        $smtp = $this->config['smtp'];
        
        // Configuración según el modo (local o SMTP)
        if (!empty($smtp['use_smtp']) && $smtp['use_smtp'] === true) {
            // Modo SMTP con autenticación
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtp['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtp['username'];
            $this->mailer->Password = $smtp['password'];
            $this->mailer->SMTPSecure = $smtp['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $smtp['port'];
            
            // Seguridad: desactivar debug para no exponer credenciales en logs
            $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            
            // Timeout de conexión (segundos)
            $this->mailer->Timeout = 15;
        } else {
            // Modo local - usar mail() de PHP
            $this->mailer->isMail();
        }
        
        // Configuración del remitente
        $this->mailer->setFrom($smtp['from_email'], $smtp['from_name']);
        
        // Configuración de charset
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }
    
    /**
     * Enviar notificación de nuevo ticket creado
     * Envía a todos los destinatarios configurados en NOTIFY_TICKET_CREATED
     */
    public function sendTicketCreatedNotification($ticket) {
        $recipients = $this->getRecipients('ticket_created');
        
        if (empty($recipients)) {
            error_log("[EPCO Mail] No hay destinatarios configurados para ticket_created (NOTIFY_TICKET_CREATED)");
            return false;
        }
        
        $success = true;
        foreach ($recipients as $email) {
            try {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress(trim($email));
                
                $this->mailer->Subject = "🎫 Nuevo Ticket #{$ticket['ticket_number']} - {$ticket['category']} - {$ticket['priority']}";
                $this->mailer->Body = $this->getTicketCreatedTemplate($ticket);
                $this->mailer->AltBody = $this->getTicketCreatedPlainText($ticket);
                
                $this->mailer->send();
                error_log("[EPCO Mail] Notificación enviada a: $email para ticket #{$ticket['ticket_number']}");
            } catch (Exception $e) {
                error_log("[EPCO Mail] Error enviando a $email: " . $this->mailer->ErrorInfo);
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Obtener lista de destinatarios, soporta múltiples separados por coma
     */
    private function getRecipients($type) {
        $raw = $this->config['notifications'][$type] ?? '';
        if (empty($raw)) return [];
        
        $emails = array_map('trim', explode(',', $raw));
        return array_filter($emails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });
    }
    
    /**
     * Template HTML para notificación de ticket creado
     */
    private function getTicketCreatedTemplate($ticket) {
        $priorityColors = [
            'urgente' => '#dc2626',
            'alta' => '#f59e0b',
            'media' => '#3b82f6',
            'baja' => '#10b981'
        ];
        
        $priorityColor = $priorityColors[$ticket['priority']] ?? '#6b7280';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = htmlspecialchars($ticket['ticket_number'] ?? "#{$ticket['id']}", ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($ticket['subject'] ?? $ticket['title'] ?? 'Sin asunto', ENT_QUOTES, 'UTF-8');
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        
        // Sanitizar datos del ticket contra XSS en el HTML del correo
        $ticket = array_map(function($v) {
            return is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
        }, $ticket);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0c5a8a 0%, #0a4a6e 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700;">
                                🎫 Nuevo Ticket de Soporte
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                Empresa Portuaria Coquimbo - Mesa de Ayuda TI
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Ticket Info Banner -->
                    <tr>
                        <td style="padding: 30px 40px 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background-color: #f8fafc; border-radius: 12px; padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="50%">
                                                    <p style="margin: 0 0 5px; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Número de Ticket</p>
                                                    <p style="margin: 0; color: #0c5a8a; font-size: 28px; font-weight: 700;">#{$ticket['id']}</p>
                                                </td>
                                                <td width="50%" align="right">
                                                    <span style="display: inline-block; background-color: {$priorityColor}; color: #ffffff; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                                        {$ticket['priority']}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Ticket Details -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <h2 style="margin: 0 0 20px; color: #1e293b; font-size: 18px; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                                📋 Detalles del Ticket
                            </h2>
                            
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Asunto:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$ticket['subject']}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Categoría:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$ticket['category']}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Solicitante:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$ticket['user_name']}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Email:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$ticket['user_email']}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Departamento:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$ticket['department']}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        <strong style="color: #64748b; font-size: 13px; display: inline-block; width: 140px;">Fecha de Creación:</strong>
                                        <span style="color: #1e293b; font-size: 14px;">{$createdAt}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Description -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <h2 style="margin: 0 0 15px; color: #1e293b; font-size: 18px; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                                📝 Descripción del Problema
                            </h2>
                            <div style="background-color: #f8fafc; border-radius: 12px; padding: 20px; border-left: 4px solid #0c5a8a;">
                                <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.7; white-space: pre-wrap;">{$ticket['description']}</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Action Button -->
                    <tr>
                        <td style="padding: 0 40px 40px;" align="center">
                            <a href="{$appUrl}/soporte_admin.php" style="display: inline-block; background: linear-gradient(135deg, #0c5a8a 0%, #0a4a6e 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                Ver en Panel de Control →
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 25px 40px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 5px; color: #64748b; font-size: 13px;">
                                <strong>Empresa Portuaria Coquimbo</strong>
                            </p>
                            <p style="margin: 0; color: #94a3b8; font-size: 12px;">
                                Este es un correo automático del sistema de soporte TI. Por favor no responda a este mensaje.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Versión texto plano del correo
     */
    private function getTicketCreatedPlainText($ticket) {
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        
        return <<<TEXT
NUEVO TICKET DE SOPORTE - Empresa Portuaria Coquimbo
=====================================================

TICKET #{$ticket['id']}
Prioridad: {$ticket['priority']}

DETALLES:
- Asunto: {$ticket['subject']}
- Categoría: {$ticket['category']}
- Solicitante: {$ticket['user_name']}
- Email: {$ticket['user_email']}
- Departamento: {$ticket['department']}
- Fecha: {$createdAt}

DESCRIPCIÓN:
{$ticket['description']}

---
Para gestionar este ticket, ingrese al Panel de Control:
{$appUrl}/soporte_admin.php

Este es un correo automático. Por favor no responda a este mensaje.
TEXT;
    }
}

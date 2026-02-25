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
        $mode = $smtp['mode'] ?? 'relay';
        
        if (!empty($smtp['use_smtp']) && $smtp['use_smtp'] === true) {
            $this->mailer->isSMTP();
            $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            
            if ($mode === 'relay') {
                // ===== MODO RELAY (RECOMENDADO) =====
                // Envía al microservicio Postfix interno (sin autenticación)
                // Postfix se encarga del relay al servidor externo
                $this->mailer->Host = $smtp['relay_host'] ?? 'mailrelay';
                $this->mailer->Port = $smtp['relay_port'] ?? 25;
                $this->mailer->SMTPAuth = false;
                $this->mailer->SMTPSecure = false;
                $this->mailer->SMTPAutoTLS = false;
                $this->mailer->Timeout = 10;
                
                error_log("[EPCO Mail] Modo RELAY: enviando via {$this->mailer->Host}:{$this->mailer->Port}");
            } else {
                // ===== MODO DIRECTO =====
                // Conexión directa al SMTP externo (Outlook, Gmail, etc.)
                $this->mailer->Host = $smtp['host'];
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $smtp['username'];
                $this->mailer->Password = $smtp['password'];
                $this->mailer->SMTPSecure = $smtp['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $this->mailer->Port = $smtp['port'];
                $this->mailer->Timeout = 15;
                
                error_log("[EPCO Mail] Modo DIRECTO: enviando via {$smtp['host']}:{$smtp['port']}");
            }
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
                
                $this->mailer->Subject = "Nuevo Ticket #{$ticket['ticket_number']} - {$ticket['category']} - {$ticket['priority']}";
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
     * Obtener lista de destinatarios desde la base de datos
     * Fallback a variables de entorno si la tabla no existe
     */
    private function getRecipients($type) {
        // Intentar obtener desde la base de datos primero
        try {
            global $pdo;
            if ($pdo) {
                $sql = "SELECT DISTINCT email FROM notification_recipients WHERE is_active = 1 AND (event_type = ? OR event_type = 'all')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$type]);
                $dbEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($dbEmails)) {
                    return array_filter($dbEmails, function($email) {
                        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
                    });
                }
            }
        } catch (\Exception $e) {
            error_log("[EPCO Mail] Error leyendo destinatarios de BD: " . $e->getMessage());
        }
        
        // Fallback: leer desde variables de entorno
        $raw = $this->config['notifications'][$type] ?? '';
        if (empty($raw)) return [];
        
        $emails = array_map('trim', explode(',', $raw));
        return array_filter($emails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });
    }
    
    /**
     * Enviar correo de prueba directamente a una dirección específica
     */
    public function sendDirectEmail($toEmail, $ticket) {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress(trim($toEmail));
        
        $this->mailer->Subject = "Correo de Prueba - Notificaciones EPCO";
        $this->mailer->Body = $this->getTicketCreatedTemplate($ticket);
        $this->mailer->AltBody = $this->getTicketCreatedPlainText($ticket);
        
        $this->mailer->send();
        error_log("[EPCO Mail] Correo de prueba enviado a: $toEmail");
        return true;
    }
    
    /**
     * Template HTML para notificación de ticket creado
     */
    private function getTicketCreatedTemplate($ticket) {
        $priorityColors = [
            'urgente' => '#dc2626',
            'alta' => '#ea580c',
            'media' => '#2563eb',
            'baja' => '#16a34a'
        ];
        $priorityLabels = [
            'urgente' => 'URGENTE',
            'alta' => 'ALTA',
            'media' => 'MEDIA',
            'baja' => 'BAJA'
        ];
        $categoryLabels = [
            'hardware' => 'Hardware',
            'software' => 'Software',
            'red' => 'Red / Conectividad',
            'acceso' => 'Accesos / Permisos',
            'otro' => 'Otro'
        ];
        $statusLabels = [
            'abierto' => 'Abierto',
            'asignado' => 'Asignado',
            'en_proceso' => 'En Proceso',
            'pendiente' => 'Pendiente',
            'resuelto' => 'Resuelto',
            'cerrado' => 'Cerrado'
        ];
        
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $status = $ticket['status'] ?? 'abierto';
        $priorityColor = $priorityColors[$priority] ?? '#6b7280';
        $priorityLabel = $priorityLabels[$priority] ?? ucfirst($priority);
        $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
        $statusLabel = $statusLabels[$status] ?? ucfirst($status);
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = htmlspecialchars($ticket['ticket_number'] ?? "TK-{$ticket['id']}", ENT_QUOTES, 'UTF-8');
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        
        // Sanitizar datos contra XSS
        $safe = array_map(function($v) {
            return is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
        }, $ticket);
        
        $subject = $safe['subject'] ?? $safe['title'] ?? 'Sin asunto';
        $userName = $safe['user_name'] ?? 'No especificado';
        $userEmail = $safe['user_email'] ?? 'No especificado';
        $department = $safe['department'] ?? 'No especificado';
        $phone = $safe['user_phone'] ?? '';
        $description = $safe['description'] ?? 'Sin descripción';
        
        // Fila de teléfono (solo si existe)
        $phoneRow = '';
        if (!empty($phone)) {
            $phoneRow = <<<ROW
                                <tr>
                                    <td style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Tel&eacute;fono</strong>
                                    </td>
                                    <td style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$phone}</span>
                                    </td>
                                </tr>
ROW;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body, .body-wrapper { background-color: #f0f2f5 !important; }
        .email-container { background-color: #ffffff !important; }
        .header-bar { background-color: #0c5a8a !important; }
        .section-bg { background-color: #f9fafb !important; }
        .cell-label { background-color: #f9fafb !important; color: #344054 !important; }
        .cell-value { background-color: #ffffff !important; color: #1d2939 !important; }
        .text-primary { color: #1d2939 !important; }
        .text-secondary { color: #475467 !important; }
        .text-muted-em { color: #667085 !important; }
        .footer-bg { background-color: #f2f4f7 !important; }
        .desc-block { background-color: #f9fafb !important; border-left-color: #0c5a8a !important; }
        @media (prefers-color-scheme: dark) {
            body, .body-wrapper { background-color: #1a1a2e !important; }
            .email-container { background-color: #16213e !important; }
            .header-bar { background-color: #0a3d62 !important; }
            .section-bg { background-color: #1a1a2e !important; border-color: #2c3e6b !important; }
            .cell-label { background-color: #1a1a2e !important; color: #c4cdd5 !important; }
            .cell-value { background-color: #16213e !important; color: #e4e7ec !important; }
            .cell-value a { color: #7cb9e8 !important; }
            .text-primary { color: #e4e7ec !important; }
            .text-secondary { color: #c4cdd5 !important; }
            .text-muted-em { color: #98a2b3 !important; }
            .footer-bg { background-color: #0f1a30 !important; }
            .desc-block { background-color: #1a1a2e !important; border-left-color: #3b82f6 !important; }
            .desc-text { color: #c4cdd5 !important; }
            .summary-box { background-color: #1a1a2e !important; border-color: #2c3e6b !important; }
            .summary-divider { border-color: #2c3e6b !important; }
            .ticket-num { color: #7cb9e8 !important; }
            .priority-badge, .status-badge { opacity: 0.95; }
            .section-title { color: #e4e7ec !important; border-bottom-color: #3b82f6 !important; }
            .table-border { border-color: #2c3e6b !important; }
            .btn-action { background-color: #0a3d62 !important; }
            .footer-text { color: #c4cdd5 !important; }
            .footer-sub { color: #667085 !important; }
        }
    </style>
</head>
<body class="body-wrapper" style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="body-wrapper" style="background-color: #f0f2f5; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" class="email-container" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                    
                    <!-- ===== HEADER ===== -->
                    <tr>
                        <td class="header-bar" style="background-color: #0c5a8a; padding: 28px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;">
                                Nuevo Ticket de Soporte
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">
                                Empresa Portuaria Coquimbo &mdash; Mesa de Ayuda TI
                            </p>
                        </td>
                    </tr>
                    
                    <!-- ===== RESUMEN R&Aacute;PIDO ===== -->
                    <tr>
                        <td style="padding: 25px 40px 15px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="summary-box" style="background-color: #f9fafb; border-radius: 10px; border: 1px solid #d0d5dd;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <!-- N&uacute;mero de ticket -->
                                                <td width="33%" style="text-align: center; border-right: 1px solid #d0d5dd;" class="summary-divider">
                                                    <p class="text-muted-em" style="margin: 0 0 4px; color: #667085; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Ticket</p>
                                                    <p class="ticket-num" style="margin: 0; color: #0c5a8a; font-size: 22px; font-weight: 700;">{$ticketNumber}</p>
                                                </td>
                                                <!-- Prioridad -->
                                                <td width="34%" style="text-align: center; border-right: 1px solid #d0d5dd;" class="summary-divider">
                                                    <p class="text-muted-em" style="margin: 0 0 6px; color: #667085; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Prioridad</p>
                                                    <span class="priority-badge" style="display: inline-block; background-color: {$priorityColor}; color: #ffffff; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                        {$priorityLabel}
                                                    </span>
                                                </td>
                                                <!-- Estado -->
                                                <td width="33%" style="text-align: center;">
                                                    <p class="text-muted-em" style="margin: 0 0 6px; color: #667085; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Estado</p>
                                                    <span class="status-badge" style="display: inline-block; background-color: #d1d5db; color: #1d2939; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                        {$statusLabel}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- ===== INFORMACI&Oacute;N DEL SOLICITANTE ===== -->
                    <tr>
                        <td style="padding: 10px 40px 5px;">
                            <h2 class="section-title" style="margin: 0 0 12px; color: #1d2939; font-size: 15px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 8px; display: inline-block;">
                                Solicitante
                            </h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border-radius: 8px; overflow: hidden;">
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Nombre</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px; font-weight: 600;">{$userName}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Correo</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <a href="mailto:{$userEmail}" style="color: #0c5a8a; font-size: 14px; text-decoration: none;">{$userEmail}</a>
                                    </td>
                                </tr>
                                {$phoneRow}
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Departamento</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$department}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- ===== DETALLES DEL TICKET ===== -->
                    <tr>
                        <td style="padding: 20px 40px 5px;">
                            <h2 class="section-title" style="margin: 0 0 12px; color: #1d2939; font-size: 15px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 8px; display: inline-block;">
                                Detalles del Ticket
                            </h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border-radius: 8px; overflow: hidden;">
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Asunto</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px; font-weight: 600;">{$subject}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Categor&iacute;a</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$categoryLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="cell-label" style="padding: 10px 15px; width: 160px; vertical-align: top; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Fecha</strong>
                                    </td>
                                    <td class="cell-value" style="padding: 10px 15px; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$createdAt}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- ===== DESCRIPCI&Oacute;N ===== -->
                    <tr>
                        <td style="padding: 20px 40px 15px;">
                            <h2 class="section-title" style="margin: 0 0 12px; color: #1d2939; font-size: 15px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 8px; display: inline-block;">
                                Descripci&oacute;n del Problema
                            </h2>
                            <div class="desc-block" style="background-color: #f9fafb; border-radius: 8px; padding: 18px; border-left: 4px solid #0c5a8a; margin-top: 4px;">
                                <p class="desc-text" style="margin: 0; color: #344054; font-size: 14px; line-height: 1.8; white-space: pre-wrap;">{$description}</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- ===== BOT&Oacute;N DE ACCI&Oacute;N ===== -->
                    <tr>
                        <td style="padding: 15px 40px 35px;" align="center">
                            <a href="{$appUrl}/soporte_admin.php?page=tickets" class="btn-action" style="display: inline-block; background-color: #0c5a8a; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 14px; font-weight: 600; letter-spacing: 0.3px;">
                                Ver en Panel de Soporte
                            </a>
                        </td>
                    </tr>
                    
                    <!-- ===== FOOTER ===== -->
                    <tr>
                        <td class="footer-bg" style="background-color: #f2f4f7; padding: 20px 40px; text-align: center; border-top: 1px solid #d0d5dd;">
                            <p class="footer-text" style="margin: 0 0 4px; color: #344054; font-size: 13px; font-weight: 600;">
                                Empresa Portuaria Coquimbo
                            </p>
                            <p class="footer-sub" style="margin: 0; color: #667085; font-size: 11px;">
                                Correo autom&aacute;tico del Sistema de Soporte TI &middot; No responder a este mensaje
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
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $status = $ticket['status'] ?? 'abierto';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
        $phone = !empty($ticket['user_phone']) ? "\n- Teléfono: {$ticket['user_phone']}" : '';
        
        return <<<TEXT
═══════════════════════════════════════════════
  NUEVO TICKET DE SOPORTE
  Empresa Portuaria Coquimbo - Mesa de Ayuda TI
═══════════════════════════════════════════════

TICKET: {$ticketNumber}
PRIORIDAD: {$priority}
ESTADO: {$status}

───────────────────────────────────────────────
SOLICITANTE
───────────────────────────────────────────────
- Nombre: {$ticket['user_name']}
- Email: {$ticket['user_email']}{$phone}
- Departamento: {$ticket['department']}

───────────────────────────────────────────────
DETALLES DEL TICKET
───────────────────────────────────────────────
- Asunto: {$ticket['subject']}
- Categoría: {$category}
- Fecha de creación: {$createdAt}

───────────────────────────────────────────────
DESCRIPCIÓN
───────────────────────────────────────────────
{$ticket['description']}

───────────────────────────────────────────────
Ver en Panel de Soporte:
{$appUrl}/soporte_admin.php?page=tickets

Correo automático · No responder a este mensaje
TEXT;
    }
}

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
     * Enviar confirmación de ticket al usuario que lo creó
     * Se envía directamente al email del solicitante
     */
    public function sendTicketConfirmationToUser($ticket) {
        $userEmail = $ticket['user_email'] ?? '';
        if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("[EPCO Mail] No se puede enviar confirmación: email del usuario inválido o vacío");
            return false;
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(trim($userEmail));
            
            $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
            $this->mailer->Subject = "Confirmación Ticket #{$ticketNumber} - Soporte TI EPCO";
            $this->mailer->Body = $this->getUserConfirmationTemplate($ticket);
            $this->mailer->AltBody = $this->getUserConfirmationPlainText($ticket);
            
            $this->mailer->send();
            error_log("[EPCO Mail] Confirmación enviada al usuario: $userEmail para ticket #{$ticketNumber}");
            return true;
        } catch (Exception $e) {
            error_log("[EPCO Mail] Error enviando confirmación a $userEmail: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Template HTML de confirmación para el usuario que creó el ticket
     */
    private function getUserConfirmationTemplate($ticket) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $status = $ticket['status'] ?? 'abierto';
        
        $priorityLabels = [
            'urgente' => 'URGENTE', 'alta' => 'ALTA', 'media' => 'MEDIA', 'baja' => 'BAJA'
        ];
        $categoryLabels = [
            'hardware' => 'Hardware', 'software' => 'Software', 'red' => 'Red / Conectividad',
            'acceso' => 'Accesos / Permisos', 'otro' => 'Otro'
        ];
        $slaLabels = [
            'urgente' => '4 horas', 'alta' => '8 horas', 'media' => '24 horas', 'baja' => '48 horas'
        ];
        
        $priorityLabel = $priorityLabels[$priority] ?? ucfirst($priority);
        $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
        $slaLabel = $slaLabels[$priority] ?? '24 horas';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = htmlspecialchars($ticket['ticket_number'] ?? "TK-{$ticket['id']}", ENT_QUOTES, 'UTF-8');
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        
        $safe = array_map(function($v) {
            return is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
        }, $ticket);
        
        $subject = $safe['subject'] ?? $safe['title'] ?? 'Sin asunto';
        $userName = $safe['user_name'] ?? 'Usuario';
        $description = $safe['description'] ?? 'Sin descripción';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        :root { color-scheme: light dark; }
        @media (prefers-color-scheme: dark) {
            body, .body-wrap { background-color: #1a1a2e !important; }
            .email-box { background-color: #16213e !important; }
            .header-bg { background-color: #0a3d62 !important; }
            .label-cell { background-color: #1a1a2e !important; color: #c4cdd5 !important; }
            .value-cell { background-color: #16213e !important; color: #e4e7ec !important; }
            .section-head { color: #e4e7ec !important; border-bottom-color: #3b82f6 !important; }
            .desc-box { background-color: #1a1a2e !important; border-left-color: #3b82f6 !important; }
            .desc-box p { color: #c4cdd5 !important; }
            .footer-box { background-color: #0f1a30 !important; }
            .footer-main { color: #c4cdd5 !important; }
            .footer-sub { color: #667085 !important; }
            .sla-box { background-color: #1a1a2e !important; border-color: #2c3e6b !important; }
            .sla-text { color: #c4cdd5 !important; }
            .greeting { color: #e4e7ec !important; }
            .info-text { color: #c4cdd5 !important; }
            .ticket-ref { color: #7cb9e8 !important; }
            .divider { border-color: #2c3e6b !important; }
        }
    </style>
</head>
<body class="body-wrap" style="margin: 0; padding: 0; font-family: 'Arial Nova', Arial, Helvetica, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="body-wrap" style="background-color: #f0f2f5; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" class="email-box" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                    
                    <!-- HEADER -->
                    <tr>
                        <td class="header-bg" style="background-color: #0c5a8a; padding: 28px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700;">
                                Confirmaci&oacute;n de Ticket
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">
                                Empresa Portuaria Coquimbo &mdash; Mesa de Ayuda TI
                            </p>
                        </td>
                    </tr>
                    
                    <!-- SALUDO -->
                    <tr>
                        <td style="padding: 30px 40px 10px;">
                            <p class="greeting" style="margin: 0 0 10px; color: #1d2939; font-size: 16px; font-weight: 600;">
                                Estimado/a {$userName},
                            </p>
                            <p class="info-text" style="margin: 0; color: #475467; font-size: 14px; line-height: 1.6;">
                                Su ticket ha sido recibido y registrado correctamente. A continuaci&oacute;n le compartimos los datos de su solicitud:
                            </p>
                        </td>
                    </tr>
                    
                    <!-- DATOS DEL TICKET -->
                    <tr>
                        <td style="padding: 20px 40px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border-radius: 8px; overflow: hidden;">
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">N&ordm; de Ticket</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span class="ticket-ref" style="color: #0c5a8a; font-size: 16px; font-weight: 700;">{$ticketNumber}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Asunto</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px; font-weight: 600;">{$subject}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Categor&iacute;a</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$categoryLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Prioridad</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$priorityLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Fecha</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$createdAt}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- DESCRIPCIÓN -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <h2 class="section-head" style="margin: 0 0 10px; color: #1d2939; font-size: 14px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 6px; display: inline-block;">
                                Descripci&oacute;n
                            </h2>
                            <div class="desc-box" style="background-color: #f9fafb; border-radius: 8px; padding: 15px; border-left: 4px solid #0c5a8a;">
                                <p style="margin: 0; color: #344054; font-size: 13px; line-height: 1.7; white-space: pre-wrap;">{$description}</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- SLA INFO -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <div class="sla-box" style="background-color: #f0f9ff; border-radius: 8px; padding: 15px; border: 1px solid #bae6fd;">
                                <p class="sla-text" style="margin: 0; color: #344054; font-size: 13px; line-height: 1.6;">
                                    <strong>Tiempo estimado de resoluci&oacute;n:</strong> {$slaLabel}<br>
                                    Nuestro equipo de soporte atender&aacute; su solicitud dentro de este plazo. Puede consultar el estado de su ticket en cualquier momento utilizando el n&uacute;mero <strong>{$ticketNumber}</strong>.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- BOTÓN -->
                    <tr>
                        <td style="padding: 20px 40px 30px;" align="center">
                            <a href="{$appUrl}/ticket_seguimiento.php" style="display: inline-block; background-color: #0c5a8a; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                Consultar Estado del Ticket
                            </a>
                        </td>
                    </tr>
                    
                    <!-- FOOTER -->
                    <tr>
                        <td class="footer-box" style="background-color: #f2f4f7; padding: 20px 40px; text-align: center; border-top: 1px solid #d0d5dd;">
                            <p class="footer-main" style="margin: 0 0 4px; color: #344054; font-size: 13px; font-weight: 600;">
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
     * Versión texto plano de la confirmación al usuario
     */
    private function getUserConfirmationPlainText($ticket) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $slaLabels = [
            'urgente' => '4 horas', 'alta' => '8 horas', 'media' => '24 horas', 'baja' => '48 horas'
        ];
        $slaLabel = $slaLabels[$priority] ?? '24 horas';
        
        return <<<TEXT
═══════════════════════════════════════════════
  CONFIRMACIÓN DE TICKET
  Empresa Portuaria Coquimbo - Mesa de Ayuda TI
═══════════════════════════════════════════════

Estimado/a {$ticket['user_name']},

Su ticket ha sido recibido y registrado correctamente.

───────────────────────────────────────────────
DATOS DEL TICKET
───────────────────────────────────────────────
- N° de Ticket: {$ticketNumber}
- Asunto: {$ticket['subject']}
- Categoría: {$category}
- Prioridad: {$priority}
- Fecha: {$createdAt}

───────────────────────────────────────────────
DESCRIPCIÓN
───────────────────────────────────────────────
{$ticket['description']}

───────────────────────────────────────────────
Tiempo estimado de resolución: {$slaLabel}

Consultar estado del ticket:
{$appUrl}/ticket_seguimiento.php

Correo automático · No responder a este mensaje
TEXT;
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
<body class="body-wrapper" style="margin: 0; padding: 0; font-family: 'Arial Nova', Arial, Helvetica, sans-serif; background-color: #f0f2f5;">
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

    /**
     * Enviar notificación al técnico cuando se le asigna un ticket
     */
    public function sendTicketAssignedNotification($ticket, $assigneeName, $assigneeEmail) {
        if (empty($assigneeEmail) || !filter_var($assigneeEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("[EPCO Mail] No se puede enviar notificación de asignación: email inválido o vacío");
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(trim($assigneeEmail));

            $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
            $this->mailer->Subject = "Ticket #{$ticketNumber} asignado a ti - Soporte TI EPCO";
            $this->mailer->Body = $this->getTicketAssignedTemplate($ticket, $assigneeName);
            $this->mailer->AltBody = $this->getTicketAssignedPlainText($ticket, $assigneeName);

            $this->mailer->send();
            error_log("[EPCO Mail] Notificación de asignación enviada a: $assigneeEmail para ticket #{$ticketNumber}");
            return true;
        } catch (Exception $e) {
            error_log("[EPCO Mail] Error enviando notificación de asignación a $assigneeEmail: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Template HTML de notificación de asignación
     */
    private function getTicketAssignedTemplate($ticket, $assigneeName) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';

        $priorityLabels = [
            'urgente' => 'URGENTE', 'alta' => 'ALTA', 'media' => 'MEDIA', 'baja' => 'BAJA'
        ];
        $categoryLabels = [
            'hardware' => 'Hardware', 'software' => 'Software', 'red' => 'Red / Conectividad',
            'acceso' => 'Accesos / Permisos', 'otro' => 'Otro'
        ];
        $slaLabels = [
            'urgente' => '4 horas', 'alta' => '8 horas', 'media' => '24 horas', 'baja' => '48 horas'
        ];

        $priorityLabel = $priorityLabels[$priority] ?? ucfirst($priority);
        $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
        $slaLabel = $slaLabels[$priority] ?? '24 horas';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = htmlspecialchars($ticket['ticket_number'] ?? "TK-{$ticket['id']}", ENT_QUOTES, 'UTF-8');
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';

        $safe = array_map(function($v) {
            return is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
        }, $ticket);

        $subject = $safe['subject'] ?? $safe['title'] ?? 'Sin asunto';
        $userName = $safe['user_name'] ?? 'Usuario';
        $description = $safe['description'] ?? 'Sin descripci&oacute;n';
        $safeName = htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        :root { color-scheme: light dark; }
        @media (prefers-color-scheme: dark) {
            body, .body-wrap { background-color: #1a1a2e !important; }
            .email-box { background-color: #16213e !important; }
            .header-bg { background-color: #0a3d62 !important; }
            .label-cell { background-color: #1a1a2e !important; color: #c4cdd5 !important; }
            .value-cell { background-color: #16213e !important; color: #e4e7ec !important; }
            .section-head { color: #e4e7ec !important; border-bottom-color: #3b82f6 !important; }
            .desc-box { background-color: #1a1a2e !important; border-left-color: #3b82f6 !important; }
            .desc-box p { color: #c4cdd5 !important; }
            .footer-box { background-color: #0f1a30 !important; }
            .footer-main { color: #c4cdd5 !important; }
            .footer-sub { color: #667085 !important; }
            .sla-box { background-color: #1a1a2e !important; border-color: #2c3e6b !important; }
            .sla-text { color: #c4cdd5 !important; }
            .greeting { color: #e4e7ec !important; }
            .info-text { color: #c4cdd5 !important; }
            .ticket-ref { color: #7cb9e8 !important; }
            .divider { border-color: #2c3e6b !important; }
        }
    </style>
</head>
<body class="body-wrap" style="margin: 0; padding: 0; font-family: 'Arial Nova', Arial, Helvetica, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="body-wrap" style="background-color: #f0f2f5; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" class="email-box" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">

                    <!-- HEADER -->
                    <tr>
                        <td class="header-bg" style="background-color: #0c5a8a; padding: 28px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700;">
                                Ticket Asignado
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">
                                Empresa Portuaria Coquimbo &mdash; Mesa de Ayuda TI
                            </p>
                        </td>
                    </tr>

                    <!-- SALUDO -->
                    <tr>
                        <td style="padding: 30px 40px 10px;">
                            <p class="greeting" style="margin: 0 0 10px; color: #1d2939; font-size: 16px; font-weight: 600;">
                                Hola {$safeName},
                            </p>
                            <p class="info-text" style="margin: 0; color: #475467; font-size: 14px; line-height: 1.6;">
                                Se te ha asignado el siguiente ticket de soporte. Por favor rev&iacute;salo y gestiona su atenci&oacute;n dentro del plazo establecido.
                            </p>
                        </td>
                    </tr>

                    <!-- DATOS DEL TICKET -->
                    <tr>
                        <td style="padding: 20px 40px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border-radius: 8px; overflow: hidden;">
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">N&ordm; de Ticket</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span class="ticket-ref" style="color: #0c5a8a; font-size: 16px; font-weight: 700;">{$ticketNumber}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Asunto</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px; font-weight: 600;">{$subject}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Solicitante</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$userName}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Categor&iacute;a</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$categoryLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Prioridad</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$priorityLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Fecha creaci&oacute;n</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$createdAt}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- DESCRIPCIÓN -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <h2 class="section-head" style="margin: 0 0 10px; color: #1d2939; font-size: 14px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 6px; display: inline-block;">
                                Descripci&oacute;n del problema
                            </h2>
                            <div class="desc-box" style="background-color: #f9fafb; border-radius: 8px; padding: 15px; border-left: 4px solid #0c5a8a;">
                                <p style="margin: 0; color: #344054; font-size: 13px; line-height: 1.7; white-space: pre-wrap;">{$description}</p>
                            </div>
                        </td>
                    </tr>

                    <!-- SLA INFO -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <div class="sla-box" style="background-color: #f0f9ff; border-radius: 8px; padding: 15px; border: 1px solid #bae6fd;">
                                <p class="sla-text" style="margin: 0; color: #344054; font-size: 13px; line-height: 1.6;">
                                    <strong>Plazo de resoluci&oacute;n SLA:</strong> {$slaLabel}<br>
                                    Recuerda gestionar este ticket dentro del tiempo establecido seg&uacute;n su prioridad.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- BOTÓN -->
                    <tr>
                        <td style="padding: 20px 40px 30px;" align="center">
                            <a href="{$appUrl}/soporte_admin" style="display: inline-block; background-color: #0c5a8a; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                Ir al Panel de Soporte
                            </a>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="footer-box" style="background-color: #f2f4f7; padding: 20px 40px; text-align: center; border-top: 1px solid #d0d5dd;">
                            <p class="footer-main" style="margin: 0 0 4px; color: #344054; font-size: 13px; font-weight: 600;">
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
     * Enviar notificación al usuario cuando su ticket es cerrado/resuelto
     * Incluye la resolución del ticket
     */
    public function sendTicketClosedNotification($ticket, $resolvedByName = 'Equipo de Soporte') {
        $userEmail = $ticket['user_email'] ?? '';
        if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("[EPCO Mail] No se puede enviar notificación de cierre: email del usuario inválido o vacío");
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(trim($userEmail));

            $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
            $status = $ticket['status'] ?? 'cerrado';
            $statusLabel = $status === 'resuelto' ? 'Resuelto' : 'Cerrado';
            $this->mailer->Subject = "Ticket #{$ticketNumber} {$statusLabel} - Soporte TI EPCO";
            $this->mailer->Body = $this->getTicketClosedTemplate($ticket, $resolvedByName);
            $this->mailer->AltBody = $this->getTicketClosedPlainText($ticket, $resolvedByName);

            $this->mailer->send();
            error_log("[EPCO Mail] Notificación de cierre enviada a: $userEmail para ticket #{$ticketNumber}");
            return true;
        } catch (Exception $e) {
            error_log("[EPCO Mail] Error enviando notificación de cierre a $userEmail: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    /**
     * Template HTML de notificación de ticket cerrado/resuelto
     */
    private function getTicketClosedTemplate($ticket, $resolvedByName) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $status = $ticket['status'] ?? 'cerrado';

        $priorityLabels = [
            'urgente' => 'URGENTE', 'alta' => 'ALTA', 'media' => 'MEDIA', 'baja' => 'BAJA'
        ];
        $categoryLabels = [
            'hardware' => 'Hardware', 'software' => 'Software', 'red' => 'Red / Conectividad',
            'acceso' => 'Accesos / Permisos', 'otro' => 'Otro'
        ];
        $statusLabels = [
            'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'
        ];

        $priorityLabel = $priorityLabels[$priority] ?? ucfirst($priority);
        $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
        $statusLabel = $statusLabels[$status] ?? ucfirst($status);
        $statusColor = $status === 'resuelto' ? '#16a34a' : '#6b7280';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $closedAt = date('d/m/Y H:i');
        $ticketNumber = htmlspecialchars($ticket['ticket_number'] ?? "TK-{$ticket['id']}", ENT_QUOTES, 'UTF-8');
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';

        $safe = array_map(function($v) {
            return is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
        }, $ticket);

        $subject = $safe['subject'] ?? $safe['title'] ?? 'Sin asunto';
        $userName = $safe['user_name'] ?? 'Usuario';
        $description = $safe['description'] ?? 'Sin descripci&oacute;n';
        $resolution = $safe['resolution'] ?? 'Sin detalle de resoluci&oacute;n';
        $safeResolvedBy = htmlspecialchars($resolvedByName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        :root { color-scheme: light dark; }
        @media (prefers-color-scheme: dark) {
            body, .body-wrap { background-color: #1a1a2e !important; }
            .email-box { background-color: #16213e !important; }
            .header-bg { background-color: #065f46 !important; }
            .label-cell { background-color: #1a1a2e !important; color: #c4cdd5 !important; }
            .value-cell { background-color: #16213e !important; color: #e4e7ec !important; }
            .section-head { color: #e4e7ec !important; border-bottom-color: #10b981 !important; }
            .desc-box { background-color: #1a1a2e !important; border-left-color: #10b981 !important; }
            .desc-box p { color: #c4cdd5 !important; }
            .resolution-box { background-color: #1a1a2e !important; border-left-color: #16a34a !important; }
            .resolution-box p { color: #c4cdd5 !important; }
            .footer-box { background-color: #0f1a30 !important; }
            .footer-main { color: #c4cdd5 !important; }
            .footer-sub { color: #667085 !important; }
            .greeting { color: #e4e7ec !important; }
            .info-text { color: #c4cdd5 !important; }
            .ticket-ref { color: #7cb9e8 !important; }
            .survey-box { background-color: #1a1a2e !important; border-color: #2c3e6b !important; }
            .survey-text { color: #c4cdd5 !important; }
        }
    </style>
</head>
<body class="body-wrap" style="margin: 0; padding: 0; font-family: 'Arial Nova', Arial, Helvetica, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="body-wrap" style="background-color: #f0f2f5; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" class="email-box" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">

                    <!-- HEADER -->
                    <tr>
                        <td class="header-bg" style="background-color: #065f46; padding: 28px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700;">
                                Ticket {$statusLabel}
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">
                                Empresa Portuaria Coquimbo &mdash; Mesa de Ayuda TI
                            </p>
                        </td>
                    </tr>

                    <!-- SALUDO -->
                    <tr>
                        <td style="padding: 30px 40px 10px;">
                            <p class="greeting" style="margin: 0 0 10px; color: #1d2939; font-size: 16px; font-weight: 600;">
                                Estimado/a {$userName},
                            </p>
                            <p class="info-text" style="margin: 0; color: #475467; font-size: 14px; line-height: 1.6;">
                                Le informamos que su ticket de soporte ha sido <strong style="color: {$statusColor};">{$statusLabel}</strong>. A continuaci&oacute;n encontrar&aacute; los detalles y la resoluci&oacute;n aplicada.
                            </p>
                        </td>
                    </tr>

                    <!-- DATOS DEL TICKET -->
                    <tr>
                        <td style="padding: 20px 40px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; border-radius: 8px; overflow: hidden;">
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">N&ordm; de Ticket</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span class="ticket-ref" style="color: #0c5a8a; font-size: 16px; font-weight: 700;">{$ticketNumber}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Asunto</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px; font-weight: 600;">{$subject}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Categor&iacute;a</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$categoryLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Estado</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="display: inline-block; background-color: {$statusColor}; color: #ffffff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">{$statusLabel}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Atendido por</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$safeResolvedBy}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Fecha creaci&oacute;n</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; border-bottom: 1px solid #d0d5dd; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$createdAt}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell" style="padding: 10px 15px; width: 160px; background-color: #f9fafb;">
                                        <strong style="color: #344054; font-size: 13px;">Fecha cierre</strong>
                                    </td>
                                    <td class="value-cell" style="padding: 10px 15px; background-color: #ffffff;">
                                        <span style="color: #1d2939; font-size: 14px;">{$closedAt}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- RESOLUCIÓN -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <h2 class="section-head" style="margin: 0 0 10px; color: #1d2939; font-size: 14px; font-weight: 600; border-bottom: 2px solid #16a34a; padding-bottom: 6px; display: inline-block;">
                                Resoluci&oacute;n
                            </h2>
                            <div class="resolution-box" style="background-color: #f0fdf4; border-radius: 8px; padding: 15px; border-left: 4px solid #16a34a;">
                                <p style="margin: 0; color: #344054; font-size: 13px; line-height: 1.7; white-space: pre-wrap;">{$resolution}</p>
                            </div>
                        </td>
                    </tr>

                    <!-- DESCRIPCIÓN ORIGINAL -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <h2 class="section-head" style="margin: 0 0 10px; color: #1d2939; font-size: 14px; font-weight: 600; border-bottom: 2px solid #0c5a8a; padding-bottom: 6px; display: inline-block;">
                                Descripci&oacute;n original
                            </h2>
                            <div class="desc-box" style="background-color: #f9fafb; border-radius: 8px; padding: 15px; border-left: 4px solid #0c5a8a;">
                                <p style="margin: 0; color: #344054; font-size: 13px; line-height: 1.7; white-space: pre-wrap;">{$description}</p>
                            </div>
                        </td>
                    </tr>

                    <!-- ENCUESTA -->
                    <tr>
                        <td style="padding: 15px 40px 10px;">
                            <div class="survey-box" style="background-color: #fffbeb; border-radius: 8px; padding: 15px; border: 1px solid #fde68a;">
                                <p class="survey-text" style="margin: 0; color: #344054; font-size: 13px; line-height: 1.6;">
                                    <strong>&#191;C&oacute;mo fue tu experiencia?</strong><br>
                                    Tu opini&oacute;n nos ayuda a mejorar. Puedes calificar la atenci&oacute;n recibida ingresando a la plataforma con tu n&uacute;mero de ticket <strong>{$ticketNumber}</strong>.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- BOTÓN -->
                    <tr>
                        <td style="padding: 20px 40px 30px;" align="center">
                            <a href="{$appUrl}/ticket_seguimiento.php" style="display: inline-block; background-color: #065f46; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                Ver Detalles del Ticket
                            </a>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="footer-box" style="background-color: #f2f4f7; padding: 20px 40px; text-align: center; border-top: 1px solid #d0d5dd;">
                            <p class="footer-main" style="margin: 0 0 4px; color: #344054; font-size: 13px; font-weight: 600;">
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
     * Versión texto plano de la notificación de ticket cerrado
     */
    private function getTicketClosedPlainText($ticket, $resolvedByName) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $status = $ticket['status'] ?? 'cerrado';
        $statusLabel = $status === 'resuelto' ? 'Resuelto' : 'Cerrado';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $closedAt = date('d/m/Y H:i');
        $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $resolution = $ticket['resolution'] ?? 'Sin detalle de resolución';
        $userName = $ticket['user_name'] ?? 'Usuario';
        $subject = $ticket['subject'] ?? $ticket['title'] ?? 'Sin asunto';

        return <<<TEXT
═══════════════════════════════════════════════
  TICKET {$statusLabel}
  Empresa Portuaria Coquimbo - Mesa de Ayuda TI
═══════════════════════════════════════════════

Estimado/a {$userName},

Le informamos que su ticket de soporte ha sido {$statusLabel}.

───────────────────────────────────────────────
DATOS DEL TICKET
───────────────────────────────────────────────
- N° de Ticket: {$ticketNumber}
- Asunto: {$subject}
- Categoría: {$category}
- Prioridad: {$priority}
- Atendido por: {$resolvedByName}
- Fecha creación: {$createdAt}
- Fecha cierre: {$closedAt}

───────────────────────────────────────────────
RESOLUCIÓN
───────────────────────────────────────────────
{$resolution}

───────────────────────────────────────────────
DESCRIPCIÓN ORIGINAL
───────────────────────────────────────────────
{$ticket['description']}

───────────────────────────────────────────────
¿Cómo fue tu experiencia?
Tu opinión nos ayuda a mejorar. Ingresa a la plataforma
con tu número de ticket {$ticketNumber}.

Ver detalles del ticket:
{$appUrl}/ticket_seguimiento.php

Correo automático · No responder a este mensaje
TEXT;
    }

    /**
     * Versión texto plano de la notificación de asignación
     */
    private function getTicketAssignedPlainText($ticket, $assigneeName) {
        $priority = $ticket['priority'] ?? 'media';
        $category = $ticket['category'] ?? 'otro';
        $createdAt = date('d/m/Y H:i', strtotime($ticket['created_at']));
        $ticketNumber = $ticket['ticket_number'] ?? "TK-{$ticket['id']}";
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        $slaLabels = [
            'urgente' => '4 horas', 'alta' => '8 horas', 'media' => '24 horas', 'baja' => '48 horas'
        ];
        $slaLabel = $slaLabels[$priority] ?? '24 horas';
        $userName = $ticket['user_name'] ?? 'Usuario';
        $subject = $ticket['subject'] ?? $ticket['title'] ?? 'Sin asunto';

        return <<<TEXT
═══════════════════════════════════════════════
  TICKET ASIGNADO
  Empresa Portuaria Coquimbo - Mesa de Ayuda TI
═══════════════════════════════════════════════

Hola {$assigneeName},

Se te ha asignado el siguiente ticket de soporte:

───────────────────────────────────────────────
DATOS DEL TICKET
───────────────────────────────────────────────
- N° de Ticket: {$ticketNumber}
- Asunto: {$subject}
- Solicitante: {$userName}
- Categoría: {$category}
- Prioridad: {$priority}
- Fecha creación: {$createdAt}

───────────────────────────────────────────────
DESCRIPCIÓN
───────────────────────────────────────────────
{$ticket['description']}

───────────────────────────────────────────────
Plazo de resolución SLA: {$slaLabel}

Ir al Panel de Soporte:
{$appUrl}/soporte_admin

Correo automático · No responder a este mensaje
TEXT;
    }
}

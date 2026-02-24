-- =============================================
-- EPCO - Esquema de Base de Datos Completo v3.0
-- Sistema Portal Corporativo con SLA, Chat, Base de Conocimiento
-- 
-- Ejecutar en phpMyAdmin o MySQL CLI:
-- mysql -u root < schema.sql
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

DROP DATABASE IF EXISTS epco;
CREATE DATABASE epco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE epco;

-- =============================================
-- USUARIOS
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'soporte', 'social', 'denuncia', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    -- Datos adicionales
    birthday DATE DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =============================================
-- TICKETS DE SOPORTE (con SLA)
-- =============================================
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    -- Usuario que reporta
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) DEFAULT NULL,
    user_email VARCHAR(150) DEFAULT NULL,
    user_department VARCHAR(100) DEFAULT NULL,
    user_phone VARCHAR(20) DEFAULT NULL,
    -- Clasificación
    category ENUM('hardware', 'software', 'red', 'acceso', 'otro') NOT NULL DEFAULT 'otro',
    priority ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    -- Contenido
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    -- Estado
    status ENUM('abierto', 'asignado', 'en_proceso', 'pendiente', 'resuelto', 'cerrado') DEFAULT 'abierto',
    -- Asignación
    assigned_to INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    -- Resolución
    resolution TEXT DEFAULT NULL,
    resolution_type ENUM('solucionado', 'sin_solucion', 'duplicado', 'cancelado') DEFAULT NULL,
    -- SLA Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_response_at TIMESTAMP NULL,
    assigned_at TIMESTAMP NULL,
    work_started_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- SLA Targets (minutos)
    sla_response_target INT DEFAULT NULL,
    sla_resolution_target INT DEFAULT NULL,
    -- SLA Flags
    sla_response_met TINYINT(1) DEFAULT NULL,
    sla_resolution_met TINYINT(1) DEFAULT NULL,
    sla_paused_at TIMESTAMP NULL,
    sla_paused_minutes INT DEFAULT 0,
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned (assigned_to),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =============================================
-- HISTORIAL DE TICKETS
-- =============================================
CREATE TABLE ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    action ENUM('created', 'status_change', 'assigned', 'unassigned', 'priority_change', 'comment', 'resolved', 'closed', 'reopened', 'sla_paused', 'sla_resumed') NOT NULL,
    old_value VARCHAR(100) DEFAULT NULL,
    new_value VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB;

-- =============================================
-- COMENTARIOS DE TICKETS
-- =============================================
CREATE TABLE ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) DEFAULT NULL,
    comment TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    is_first_response TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB;

-- =============================================
-- ARCHIVOS ADJUNTOS DE TICKETS
-- =============================================
CREATE TABLE ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT 0,
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB;

-- =============================================
-- ENCUESTAS DE SATISFACCIÓN
-- =============================================
CREATE TABLE ticket_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    feedback TEXT DEFAULT NULL,
    response_time_rating TINYINT DEFAULT NULL,
    solution_rating TINYINT DEFAULT NULL,
    technician_rating TINYINT DEFAULT NULL,
    would_recommend TINYINT(1) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_rating (rating)
) ENGINE=InnoDB;

-- =============================================
-- CONFIGURACIÓN SLA
-- =============================================
CREATE TABLE sla_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    priority ENUM('baja', 'media', 'alta', 'urgente') NOT NULL UNIQUE,
    first_response_minutes INT NOT NULL,
    assignment_minutes INT NOT NULL,
    resolution_minutes INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- DENUNCIAS LEY KARIN
-- =============================================
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(20) NOT NULL UNIQUE,
    complaint_type ENUM('acoso_laboral', 'acoso_sexual', 'violencia_laboral', 'discriminacion', 'otro') NOT NULL,
    description TEXT NOT NULL,
    involved_persons TEXT DEFAULT NULL,
    evidence_description TEXT DEFAULT NULL,
    -- Denunciante
    is_anonymous TINYINT(1) DEFAULT 1,
    reporter_name VARCHAR(100) DEFAULT NULL,
    reporter_email VARCHAR(150) DEFAULT NULL,
    reporter_phone VARCHAR(20) DEFAULT NULL,
    reporter_department VARCHAR(100) DEFAULT NULL,
    -- Denunciado
    accused_name VARCHAR(100) DEFAULT NULL,
    accused_department VARCHAR(100) DEFAULT NULL,
    accused_position VARCHAR(100) DEFAULT NULL,
    -- Incidente
    incident_date DATE DEFAULT NULL,
    incident_location VARCHAR(200) DEFAULT NULL,
    witnesses TEXT DEFAULT NULL,
    -- Estado
    status ENUM('recibida', 'en_investigacion', 'resuelta', 'archivada') DEFAULT 'recibida',
    resolution TEXT DEFAULT NULL,
    investigator_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint_number (complaint_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =============================================
-- HISTORIAL DE DENUNCIAS
-- =============================================
CREATE TABLE complaint_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    is_confidential TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

-- =============================================
-- NOTICIAS
-- =============================================
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    excerpt VARCHAR(300) DEFAULT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    news_url VARCHAR(500) DEFAULT NULL,
    author_id INT DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_published (is_published),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB;

-- =============================================
-- EVENTOS/CALENDARIO
-- =============================================
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    event_type ENUM('meeting', 'birthday', 'holiday', 'training', 'corporate', 'other') DEFAULT 'other',
    start_date DATETIME NOT NULL,
    end_date DATETIME DEFAULT NULL,
    all_day TINYINT(1) DEFAULT 0,
    location VARCHAR(200) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#0a2540',
    is_public TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    recurrence ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none',
    reminder_minutes INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_start_date (start_date),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB;

-- =============================================
-- BASE DE CONOCIMIENTO
-- =============================================
CREATE TABLE knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    excerpt VARCHAR(500) DEFAULT NULL,
    category ENUM('hardware', 'software', 'red', 'acceso', 'general', 'procedimientos') DEFAULT 'general',
    tags VARCHAR(500) DEFAULT NULL,
    author_id INT DEFAULT NULL,
    views INT DEFAULT 0,
    helpful_yes INT DEFAULT 0,
    helpful_no INT DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    related_articles VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_published (is_published),
    FULLTEXT INDEX idx_search (title, content, tags)
) ENGINE=InnoDB;

-- =============================================
-- DOCUMENTOS
-- =============================================
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    file_size INT DEFAULT 0,
    category VARCHAR(100) DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    downloads INT DEFAULT 0,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- =============================================
-- LOGS DE ACTIVIDAD
-- =============================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- =============================================
-- PASSWORD RESETS
-- =============================================
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =============================================
-- CONFIGURACIÓN DE NOTIFICACIONES
-- =============================================
CREATE TABLE notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_ticket_created TINYINT(1) DEFAULT 1,
    email_ticket_updated TINYINT(1) DEFAULT 1,
    email_ticket_assigned TINYINT(1) DEFAULT 1,
    email_ticket_resolved TINYINT(1) DEFAULT 1,
    email_complaint_updated TINYINT(1) DEFAULT 1,
    email_news TINYINT(1) DEFAULT 1,
    email_events TINYINT(1) DEFAULT 1,
    browser_notifications TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- PREFERENCIAS DE USUARIO
-- =============================================
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    language VARCHAR(5) DEFAULT 'es',
    sidebar_collapsed TINYINT(1) DEFAULT 0,
    dashboard_layout VARCHAR(50) DEFAULT 'default',
    items_per_page INT DEFAULT 20,
    timezone VARCHAR(50) DEFAULT 'America/Santiago',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- COLA DE EMAILS
-- =============================================
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(150) NOT NULL,
    to_name VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    template VARCHAR(50) DEFAULT NULL,
    priority TINYINT DEFAULT 5,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

-- =============================================
-- API TOKENS
-- =============================================
CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    abilities TEXT DEFAULT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =============================================
-- DATOS INICIALES
-- =============================================

-- Usuarios (password: password)
INSERT INTO users (name, username, email, password, role, department, position) VALUES 
('Administrador EPCO', 'admin.epco', 'admin@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'TI', 'Administrador de Sistemas'),
('Soporte TI', 'soporte.ti', 'soporte@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Técnico Soporte', 'tecnico.soporte', 'tecnico@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Comunicaciones', 'comunicaciones.epco', 'social@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'social', 'Comunicaciones', 'Encargado de Comunicaciones'),
('Usuario Demo', 'usuario.demo', 'usuario@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Operaciones', 'Colaborador');

-- Configuración SLA
INSERT INTO sla_settings (priority, first_response_minutes, assignment_minutes, resolution_minutes) VALUES
('urgente', 60, 30, 240),
('alta', 240, 120, 1440),
('media', 480, 240, 2880),
('baja', 1440, 480, 4320);

-- Noticias
INSERT INTO news (title, excerpt, content, author_id, is_published, is_featured) VALUES 
('Bienvenidos al nuevo Portal EPCO', 
 'Conoce todas las nuevas funcionalidades del portal corporativo',
 '<p>Nos complace presentar el nuevo portal corporativo de EPCO, diseñado para mejorar la comunicación interna y facilitar el acceso a recursos importantes.</p><p>Este portal incluye nuevas funcionalidades como gestión de documentos, calendario de eventos y un sistema de soporte técnico integrado.</p>', 
 1, 1, 1),
('Actualización de políticas de seguridad', 
 'Nuevas políticas de seguridad informática vigentes',
 '<p>Se han actualizado las políticas de seguridad informática de la empresa. Por favor, revisa los nuevos lineamientos en la sección de documentos.</p><p>Recuerda cambiar tu contraseña cada 90 días y no compartir tus credenciales con nadie.</p>', 
 1, 1, 0),
('Capacitación Ley Karin', 
 'Capacitación obligatoria sobre la Ley 21.643',
 '<p>Se realizará una capacitación obligatoria sobre la Ley 21.643 (Ley Karin) para todos los colaboradores.</p><p>La capacitación abordará los procedimientos de denuncia, derechos y deberes de los trabajadores.</p>', 
 1, 1, 1),
('EPCO desarrolló con éxito su programa de visitas educativas 2025',
 'Más de 500 estudiantes visitaron nuestras instalaciones',
 '<p>Empresa Portuaria Coquimbo desarrolló exitosamente su programa de visitas educativas durante el primer trimestre de 2025.</p><p>Más de 500 estudiantes de la región participaron en recorridos guiados por las instalaciones portuarias.</p>',
 1, 1, 1);

-- Base de Conocimiento
INSERT INTO knowledge_base (title, slug, content, excerpt, category, tags, author_id, is_published, is_featured) VALUES
('Cómo reiniciar tu contraseña de Windows', 'reiniciar-contrasena-windows', 
'<h2>Pasos para reiniciar contraseña</h2><ol><li>Presiona Ctrl+Alt+Delete</li><li>Selecciona "Cambiar contraseña"</li><li>Ingresa tu contraseña actual</li><li>Escribe la nueva contraseña</li><li>Confirma y guarda</li></ol>',
'Guía paso a paso para cambiar tu contraseña de Windows', 'acceso', 
'contraseña,password,windows,login', 1, 1, 1),
('Conectar a la impresora de red', 'conectar-impresora-red',
'<h2>Instrucciones de conexión</h2><ol><li>Abre Configuración de Windows</li><li>Ve a Dispositivos → Impresoras</li><li>Haz clic en "Agregar impresora"</li><li>Selecciona la impresora de red</li><li>Sigue el asistente de instalación</li></ol>',
'Instrucciones para conectar tu equipo a las impresoras de red', 'hardware',
'impresora,printer,red,network', 1, 1, 0),
('Solucionar problemas de conexión VPN', 'problemas-conexion-vpn',
'<h2>Solución de problemas</h2><ul><li>Verifica tu conexión a internet</li><li>Reinicia el cliente VPN</li><li>Comprueba tus credenciales</li><li>Contacta a soporte si el problema persiste</li></ul>',
'Guía para resolver problemas comunes de conexión VPN', 'red',
'vpn,conexión,remoto,trabajo', 1, 1, 1),
('Configurar firma de correo en Outlook', 'configurar-firma-outlook',
'<h2>Pasos para configurar</h2><ol><li>Abre Outlook</li><li>Ve a Archivo → Opciones → Correo</li><li>Haz clic en "Firmas"</li><li>Crea una nueva firma</li><li>Añade tu información y guarda</li></ol>',
'Cómo añadir y personalizar tu firma de correo electrónico', 'software',
'outlook,correo,firma,email', 1, 1, 0);

-- Eventos
INSERT INTO events (title, description, event_type, start_date, end_date, all_day, color, is_public, created_by) VALUES
('Reunión mensual de equipo', 'Reunión mensual para revisar avances y planificar el próximo mes.', 'meeting', 
DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 10 HOUR, 
DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 11 HOUR + INTERVAL 30 MINUTE, 
0, '#0a2540', 1, 1),
('Día de la empresa', 'Celebración del aniversario de EPCO con actividades para todos los colaboradores.', 'corporate', 
DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
1, '#22c55e', 1, 1),
('Capacitación Ciberseguridad', 'Capacitación sobre buenas prácticas de ciberseguridad y protección de datos.', 'training',
DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 15 HOUR,
DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 17 HOUR,
0, '#f59e0b', 1, 1);

-- Tickets de ejemplo
INSERT INTO tickets (
    ticket_number, user_id, category, priority, title, description, status,
    sla_response_target, sla_resolution_target, created_at
) VALUES 
('TK-2026-0001', 5, 'software', 'media', 'Problema con Microsoft Office', 
 'Excel se cierra inesperadamente al abrir archivos grandes.', 'abierto',
 480, 2880, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('TK-2026-0002', 5, 'hardware', 'alta', 'Monitor no enciende', 
 'El monitor de mi estación de trabajo no enciende desde esta mañana.', 'asignado',
 240, 1440, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('TK-2026-0003', 5, 'red', 'urgente', 'Sin acceso a internet', 
 'Todo el departamento quedó sin acceso a internet.', 'en_proceso',
 60, 240, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Actualizar tickets con asignaciones
UPDATE tickets SET 
    assigned_to = 2, 
    assigned_at = DATE_SUB(NOW(), INTERVAL 4 HOUR),
    first_response_at = DATE_SUB(NOW(), INTERVAL 4 HOUR),
    sla_response_met = 1
WHERE ticket_number = 'TK-2026-0002';

UPDATE tickets SET 
    assigned_to = 3, 
    assigned_at = DATE_SUB(NOW(), INTERVAL 45 MINUTE),
    first_response_at = DATE_SUB(NOW(), INTERVAL 50 MINUTE),
    work_started_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE),
    sla_response_met = 1
WHERE ticket_number = 'TK-2026-0003';

-- Historial de tickets
INSERT INTO ticket_history (ticket_id, user_id, action, new_value, description) VALUES
(1, NULL, 'created', 'abierto', 'Ticket creado'),
(2, NULL, 'created', 'abierto', 'Ticket creado'),
(2, 2, 'assigned', 'Soporte TI', 'Ticket asignado'),
(2, 2, 'status_change', 'asignado', 'Estado cambiado a asignado'),
(3, NULL, 'created', 'abierto', 'Ticket creado'),
(3, 3, 'assigned', 'Técnico Soporte', 'Ticket asignado'),
(3, 3, 'status_change', 'en_proceso', 'Estado cambiado a en proceso');

SELECT '✓ Base de datos EPCO v3.0 creada exitosamente!' AS mensaje;

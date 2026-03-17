-- =============================================
-- EPCO - Script Completo de Base de Datos v3.2 (Docker)
-- Sistema Portal Corporativo Empresa Portuaria Coquimbo
-- 
-- Script unificado para inicialización en Docker
-- Se ejecuta automáticamente al crear el contenedor MySQL
--
-- Incluye:
-- - Estructura completa de todas las tablas
-- - Roles: admin, soporte, social, denuncia, user
-- - Sistema de tickets con SLA
-- - Denuncias Ley Karin
-- - Noticias e Intranet
-- - Base de Conocimiento
-- - Boletines internos
-- - Eventos y calendario
-- - Sistema de documentos
-- - Logs de actividad
-- - Cola de emails
-- - API tokens
-- - Datos iniciales de ejemplo
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Usar la base de datos creada por Docker
USE epco;

-- =============================================
-- TABLA: USUARIOS
-- =============================================
CREATE TABLE IF NOT EXISTS users (
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
    birthday DATE DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: TICKETS DE SOPORTE (con SLA)
-- =============================================
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) DEFAULT NULL,
    user_email VARCHAR(150) DEFAULT NULL,
    user_department VARCHAR(100) DEFAULT NULL,
    user_phone VARCHAR(20) DEFAULT NULL,
    category ENUM('hardware', 'software', 'red', 'acceso', 'otro') NOT NULL DEFAULT 'otro',
    priority ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('abierto', 'asignado', 'en_proceso', 'pendiente', 'en_pausa', 'resuelto', 'cerrado') DEFAULT 'abierto',
    assigned_to INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    resolution TEXT DEFAULT NULL,
    resolution_type ENUM('solucionado', 'sin_solucion', 'duplicado', 'cancelado') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_response_at TIMESTAMP NULL,
    assigned_at TIMESTAMP NULL,
    work_started_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sla_response_target INT DEFAULT NULL,
    sla_resolution_target INT DEFAULT NULL,
    sla_response_met TINYINT(1) DEFAULT NULL,
    sla_resolution_met TINYINT(1) DEFAULT NULL,
    sla_paused_at TIMESTAMP NULL,
    sla_paused_minutes INT DEFAULT 0,
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
-- TABLA: HISTORIAL DE TICKETS
-- =============================================
CREATE TABLE IF NOT EXISTS ticket_history (
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
-- TABLA: COMENTARIOS DE TICKETS
-- =============================================
CREATE TABLE IF NOT EXISTS ticket_comments (
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
-- TABLA: ARCHIVOS ADJUNTOS DE TICKETS
-- =============================================
CREATE TABLE IF NOT EXISTS ticket_attachments (
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
-- TABLA: ENCUESTAS DE SATISFACCIÓN
-- =============================================
CREATE TABLE IF NOT EXISTS ticket_surveys (
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
-- TABLA: CONFIGURACIÓN SLA
-- =============================================
CREATE TABLE IF NOT EXISTS sla_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    priority ENUM('baja', 'media', 'alta', 'urgente') NOT NULL UNIQUE,
    first_response_minutes INT NOT NULL,
    assignment_minutes INT NOT NULL,
    resolution_minutes INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- TABLA: DENUNCIAS LEY KARIN
-- =============================================
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(20) NOT NULL UNIQUE,
    complaint_type ENUM('acoso_laboral', 'acoso_sexual', 'violencia_laboral', 'discriminacion', 'otro') NOT NULL,
    description TEXT NOT NULL,
    involved_persons TEXT DEFAULT NULL,
    evidence_description TEXT DEFAULT NULL,
    is_anonymous TINYINT(1) DEFAULT 1,
    reporter_name VARCHAR(100) DEFAULT NULL,
    reporter_email VARCHAR(150) DEFAULT NULL,
    reporter_phone VARCHAR(20) DEFAULT NULL,
    reporter_department VARCHAR(100) DEFAULT NULL,
    accused_name VARCHAR(100) DEFAULT NULL,
    accused_department VARCHAR(100) DEFAULT NULL,
    accused_position VARCHAR(100) DEFAULT NULL,
    incident_date DATE DEFAULT NULL,
    incident_location VARCHAR(200) DEFAULT NULL,
    witnesses TEXT DEFAULT NULL,
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
-- TABLA: HISTORIAL DE DENUNCIAS
-- =============================================
CREATE TABLE IF NOT EXISTS complaint_logs (
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
-- TABLA: NOTICIAS
-- =============================================
CREATE TABLE IF NOT EXISTS news (
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
-- TABLA: EVENTOS/CALENDARIO
-- =============================================
CREATE TABLE IF NOT EXISTS events (
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
-- TABLA: BASE DE CONOCIMIENTO
-- =============================================
CREATE TABLE IF NOT EXISTS knowledge_base (
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
-- TABLA: DOCUMENTOS
-- =============================================
CREATE TABLE IF NOT EXISTS documents (
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
-- TABLA: BOLETINES INTERNOS
-- =============================================
CREATE TABLE IF NOT EXISTS bulletins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('urgent', 'event', 'info', 'maintenance', 'celebration') NOT NULL DEFAULT 'info',
    priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    icon VARCHAR(50) DEFAULT 'bi-megaphone',
    event_date DATE DEFAULT NULL,
    deadline_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_pinned TINYINT(1) DEFAULT 0,
    expanded_content TEXT DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    action_label VARCHAR(100) DEFAULT NULL,
    author_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATE DEFAULT NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_pinned (is_pinned),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: LECTURA DE BOLETINES
-- =============================================
CREATE TABLE IF NOT EXISTS bulletin_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulletin_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bulletin_id) REFERENCES bulletins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (bulletin_id, user_id)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: LOGS DE ACTIVIDAD
-- =============================================
CREATE TABLE IF NOT EXISTS activity_logs (
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
-- TABLA: PASSWORD RESETS
-- =============================================
CREATE TABLE IF NOT EXISTS password_resets (
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
-- TABLA: CONFIGURACIÓN DE NOTIFICACIONES
-- =============================================
CREATE TABLE IF NOT EXISTS notification_settings (
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
-- TABLA: PREFERENCIAS DE USUARIO
-- =============================================
CREATE TABLE IF NOT EXISTS user_preferences (
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
-- TABLA: DESTINATARIOS DE NOTIFICACIONES
-- Correos que reciben alertas cuando se crean/actualizan tickets
-- =============================================
CREATE TABLE IF NOT EXISTS notification_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    event_type ENUM('ticket_created', 'ticket_updated', 'all') DEFAULT 'all',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_event (email, event_type),
    INDEX idx_event_active (event_type, is_active)
) ENGINE=InnoDB;

-- Insertar destinatario por defecto
INSERT IGNORE INTO notification_recipients (email, name, event_type) VALUES
('soporteepco@gmail.com', 'Soporte Empresa Portuaria Coquimbo', 'all');

-- =============================================
-- TABLA: COLA DE EMAILS
-- =============================================
CREATE TABLE IF NOT EXISTS email_queue (
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
-- TABLA: CONFIGURACIÓN SMTP (editable desde UI)
-- =============================================
CREATE TABLE IF NOT EXISTS smtp_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Valores por defecto SMTP
INSERT IGNORE INTO smtp_config (config_key, config_value) VALUES
('smtp_enabled', 'true'),
('smtp_mode', 'direct'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', 'Soporte TI - Empresa Portuaria Coquimbo');

-- =============================================
-- TABLA: REMITENTES DE CORREO (CRUD desde UI)
-- =============================================
CREATE TABLE IF NOT EXISTS smtp_senders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sender_email (email),
    INDEX idx_active_default (is_active, is_default)
) ENGINE=InnoDB;

-- Remitente por defecto
INSERT IGNORE INTO smtp_senders (email, name, is_active, is_default) VALUES
('soporteepco@gmail.com', 'Soporte TI - Empresa Portuaria Coquimbo', 1, 1);

-- =============================================
-- TABLA: API TOKENS
-- =============================================
CREATE TABLE IF NOT EXISTS api_tokens (
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


-- =============================================================================
-- DATOS INICIALES
-- =============================================================================

-- =============================================
-- USUARIOS (Contraseña para todos: password)
-- =============================================
INSERT INTO users (name, username, email, password, role, department, position) VALUES 
('Administrador Empresa Portuaria Coquimbo', 'admin.epco', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'TI', 'Administrador de Sistemas'),
('Soporte TI', 'soporte.ti', 'soporte@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Técnico Soporte', 'tecnico.soporte', 'tecnico@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Comunicaciones', 'comunicaciones.epco', 'social@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'social', 'Comunicaciones', 'Encargado de Comunicaciones'),
('Comité de Ética', 'comite.etica', 'etica@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'denuncia', 'Recursos Humanos', 'Encargado Ley Karin'),
('Usuario Demo', 'usuario.demo', 'usuario@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Operaciones', 'Colaborador'),
('María González', 'maria.gonzalez', 'maria.gonzalez@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Administración', 'Asistente Administrativo'),
('Carlos Pérez', 'carlos.perez', 'carlos.perez@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Operaciones', 'Operador Portuario'),
('Ana López', 'ana.lopez', 'ana.lopez@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Finanzas', 'Analista Financiero');

-- =============================================
-- CONFIGURACIÓN SLA (tiempos en minutos)
-- =============================================
INSERT INTO sla_settings (priority, first_response_minutes, assignment_minutes, resolution_minutes) VALUES
('urgente', 30, 15, 240),
('alta', 60, 30, 480),
('media', 120, 60, 1440),
('baja', 240, 120, 2880);

-- =============================================
-- NOTICIAS
-- =============================================
INSERT INTO news (title, excerpt, content, author_id, category, is_published, is_featured) VALUES 
('Bienvenidos al nuevo Portal Empresa Portuaria Coquimbo', 
 'Conoce todas las nuevas funcionalidades del portal corporativo',
 '<p>Nos complace presentar el nuevo portal corporativo de EPCO, diseñado para mejorar la comunicación interna y facilitar el acceso a recursos importantes.</p><p>Este portal incluye nuevas funcionalidades como gestión de documentos, calendario de eventos y un sistema de soporte técnico integrado.</p><h3>Nuevas funcionalidades:</h3><ul><li>Sistema de tickets para soporte TI</li><li>Base de conocimiento</li><li>Calendario de eventos</li><li>Noticias corporativas</li><li>Canal de denuncias Ley Karin</li></ul>', 
 1, 'general', 1, 1),
 
('Actualización de políticas de seguridad', 
 'Nuevas políticas de seguridad informática vigentes',
 '<p>Se han actualizado las políticas de seguridad informática de la empresa. Por favor, revisa los nuevos lineamientos en la sección de documentos.</p><p>Recuerda cambiar tu contraseña cada 90 días y no compartir tus credenciales con nadie.</p><h3>Puntos clave:</h3><ul><li>Uso de contraseñas seguras</li><li>No compartir credenciales</li><li>Reportar correos sospechosos</li><li>Bloquear equipo al alejarse</li></ul>', 
 1, 'seguridad', 1, 0),
 
('Capacitación Ley Karin', 
 'Capacitación obligatoria sobre la Ley 21.643',
 '<p>Se realizará una capacitación obligatoria sobre la Ley 21.643 (Ley Karin) para todos los colaboradores.</p><p>La capacitación abordará los procedimientos de denuncia, derechos y deberes de los trabajadores.</p><h3>Contenido:</h3><ul><li>Marco legal y alcances</li><li>Tipos de acoso y violencia laboral</li><li>Procedimientos de denuncia</li><li>Protección al denunciante</li><li>Sanciones aplicables</li></ul>', 
 1, 'capacitacion', 1, 1),
 
('Empresa Portuaria Coquimbo desarrolló con éxito su programa de visitas educativas 2025',
 'Más de 500 estudiantes visitaron nuestras instalaciones',
 '<p>Empresa Portuaria Coquimbo desarrolló exitosamente su programa de visitas educativas durante el primer trimestre de 2025.</p><p>Más de 500 estudiantes de la región participaron en recorridos guiados por las instalaciones portuarias, conociendo la operación y la importancia del puerto para la economía regional.</p>',
 1, 'general', 1, 1),

('Nuevo sistema de tickets de soporte TI',
 'Ahora puedes reportar problemas técnicos de forma más eficiente',
 '<p>Hemos implementado un nuevo sistema de tickets para el área de soporte TI que permite hacer seguimiento en tiempo real de tus solicitudes.</p><h3>Características:</h3><ul><li>Seguimiento en línea de tickets</li><li>Notificaciones por correo</li><li>Adjuntar archivos</li><li>Historial completo</li><li>Encuesta de satisfacción</li></ul>',
 1, 'tecnologia', 1, 0);

-- =============================================
-- BASE DE CONOCIMIENTO
-- =============================================
INSERT INTO knowledge_base (title, slug, content, excerpt, category, tags, author_id, is_published, is_featured) VALUES
('Cómo reiniciar tu contraseña de Windows', 'reiniciar-contrasena-windows', 
'<h2>Pasos para reiniciar contraseña</h2>
<ol>
<li>Presiona <strong>Ctrl+Alt+Delete</strong> simultáneamente</li>
<li>Selecciona <strong>"Cambiar contraseña"</strong></li>
<li>Ingresa tu contraseña actual</li>
<li>Escribe la nueva contraseña (mínimo 8 caracteres, incluir mayúsculas, minúsculas y números)</li>
<li>Confirma la nueva contraseña</li>
<li>Presiona Enter o haz clic en la flecha</li>
</ol>
<h3>Requisitos de contraseña:</h3>
<ul>
<li>Mínimo 8 caracteres</li>
<li>Al menos una letra mayúscula</li>
<li>Al menos una letra minúscula</li>
<li>Al menos un número</li>
<li>No puede contener tu nombre de usuario</li>
</ul>',
'Guía paso a paso para cambiar tu contraseña de Windows', 'acceso', 
'contraseña,password,windows,login,seguridad', 1, 1, 1),

('Conectar a la impresora de red', 'conectar-impresora-red',
'<h2>Instrucciones de conexión a impresoras</h2>
<ol>
<li>Abre <strong>Configuración de Windows</strong> (Win + I)</li>
<li>Ve a <strong>Dispositivos → Impresoras y escáneres</strong></li>
<li>Haz clic en <strong>"Agregar impresora o escáner"</strong></li>
<li>Espera mientras Windows busca impresoras disponibles</li>
<li>Selecciona la impresora de red deseada</li>
<li>Sigue el asistente de instalación</li>
</ol>
<h3>Impresoras disponibles:</h3>
<ul>
<li><strong>EPC-PISO1-HP</strong> - Impresora HP LaserJet (Piso 1)</li>
<li><strong>EPC-PISO2-XEROX</strong> - Xerox WorkCentre (Piso 2)</li>
<li><strong>EPC-RECEPCION</strong> - HP OfficeJet (Recepción)</li>
</ul>',
'Instrucciones para conectar tu equipo a las impresoras de red', 'hardware',
'impresora,printer,red,network,HP,Xerox', 1, 1, 0),

('Solucionar problemas de conexión VPN', 'problemas-conexion-vpn',
'<h2>Solución de problemas VPN</h2>
<h3>Verificaciones básicas:</h3>
<ul>
<li>Verifica tu conexión a internet (abre un navegador)</li>
<li>Comprueba que el cliente VPN esté actualizado</li>
<li>Verifica que tus credenciales sean correctas</li>
</ul>
<h3>Pasos para solucionar:</h3>
<ol>
<li>Cierra completamente el cliente VPN</li>
<li>Reinicia tu adaptador de red (desactivar/activar)</li>
<li>Vuelve a abrir el cliente VPN</li>
<li>Intenta conectarte nuevamente</li>
</ol>
<h3>Si el problema persiste:</h3>
<ul>
<li>Reinicia tu computador</li>
<li>Verifica que el firewall no esté bloqueando la conexión</li>
<li>Contacta a soporte TI creando un ticket</li>
</ul>',
'Guía para resolver problemas comunes de conexión VPN', 'red',
'vpn,conexión,remoto,trabajo,teletrabajo', 1, 1, 1),

('Configurar firma de correo en Outlook', 'configurar-firma-outlook',
'<h2>Pasos para configurar firma en Outlook</h2>
<ol>
<li>Abre <strong>Microsoft Outlook</strong></li>
<li>Ve a <strong>Archivo → Opciones</strong></li>
<li>Selecciona <strong>Correo</strong> en el menú lateral</li>
<li>Haz clic en el botón <strong>"Firmas..."</strong></li>
<li>Crea una nueva firma con el botón "Nuevo"</li>
<li>Añade tu información y formato deseado</li>
<li>Selecciona la firma para nuevos mensajes y respuestas</li>
<li>Haz clic en "Aceptar" para guardar</li>
</ol>
<h3>Formato recomendado:</h3>
<pre>
Nombre Apellido
Cargo
Empresa Portuaria Coquimbo
Teléfono: +56 XX XXX XXXX
Email: nombre@example.com
</pre>',
'Cómo añadir y personalizar tu firma de correo electrónico', 'software',
'outlook,correo,firma,email,microsoft', 1, 1, 0),

('Solicitar acceso a sistemas', 'solicitar-acceso-sistemas',
'<h2>Procedimiento para solicitar accesos</h2>
<p>Para solicitar acceso a los sistemas corporativos, sigue estos pasos:</p>
<ol>
<li>Completa el formulario de solicitud de acceso</li>
<li>Obtén la aprobación de tu jefatura directa</li>
<li>Envía la solicitud al área de TI mediante ticket</li>
<li>Espera la confirmación de habilitación</li>
</ol>
<h3>Sistemas disponibles:</h3>
<ul>
<li><strong>ERP SAP</strong> - Sistema de gestión empresarial</li>
<li><strong>Portal RRHH</strong> - Autoservicio de recursos humanos</li>
<li><strong>Sistema Documental</strong> - Gestión de documentos</li>
<li><strong>BI Dashboard</strong> - Reportes y análisis</li>
</ul>',
'Procedimiento para solicitar acceso a los sistemas corporativos', 'acceso',
'acceso,permisos,sistemas,solicitud', 1, 1, 0),

-- Artículos adicionales de resolución de problemas y emergencias

('Qué hacer si tu computador no enciende', 'computador-no-enciende',
'<h2>Guía de emergencia: PC no enciende</h2>
<p>Si tu computador no enciende al presionar el botón de encendido, sigue estos pasos en orden antes de contactar a soporte.</p>

<h3>1. Verificaciones rápidas</h3>
<ul>
<li>Comprueba que el cable de poder esté firmemente conectado al equipo y a la corriente</li>
<li>Verifica que el enchufe tenga electricidad (prueba otro dispositivo en el mismo enchufe)</li>
<li>Si es notebook, conecta el cargador y espera 5 minutos antes de intentar encender</li>
<li>Revisa que el interruptor trasero de la fuente de poder (si existe) esté en posición ON (I)</li>
</ul>

<h3>2. Reinicio eléctrico</h3>
<ol>
<li>Desconecta el cable de poder del computador</li>
<li>Si es notebook, retira la batería (si es removible)</li>
<li>Mantén presionado el botón de encendido por <strong>15 segundos</strong></li>
<li>Vuelve a conectar el cable de poder / batería</li>
<li>Intenta encender normalmente</li>
</ol>

<h3>3. Si el equipo enciende pero no muestra imagen</h3>
<ul>
<li>Verifica que el cable del monitor esté bien conectado (HDMI, DisplayPort o VGA)</li>
<li>Prueba con otro cable o puerto de video si están disponibles</li>
<li>Si usas docking station, prueba conectando el monitor directo al equipo</li>
</ul>

<h3>4. Si nada funciona</h3>
<div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; border-radius: 8px; margin: 10px 0;">
<strong>Crea un ticket de soporte con prioridad URGENTE</strong> si necesitas el equipo para funciones críticas, o con prioridad ALTA para situaciones normales. Incluye:
<ul>
<li>Modelo del equipo y número de inventario (etiqueta en la parte inferior)</li>
<li>¿Hace algún sonido al intentar encender? (pitidos, ventilador girando)</li>
<li>¿Se enciende alguna luz LED en el equipo?</li>
</ul>
</div>',
'Pasos de emergencia cuando tu computador de escritorio o notebook no enciende', 'hardware',
'computador,no enciende,emergencia,encendido,pantalla negra,notebook', 1, 1, 1),

('Mi computador está muy lento — Soluciones rápidas', 'computador-lento-soluciones',
'<h2>Soluciones para un computador lento</h2>
<p>Un equipo lento afecta tu productividad. Aquí tienes pasos que puedes hacer por tu cuenta antes de crear un ticket.</p>

<h3>1. Reinicia el computador</h3>
<p>Puede parecer obvio, pero un reinicio completo soluciona la mayoría de los problemas de rendimiento. Usa <strong>Inicio → Reiniciar</strong> (no solo cerrar tapa en notebooks).</p>

<h3>2. Cierra programas que no estés usando</h3>
<ol>
<li>Presiona <strong>Ctrl + Shift + Esc</strong> para abrir el Administrador de Tareas</li>
<li>En la pestaña <strong>Procesos</strong>, ordena por "Memoria" o "CPU"</li>
<li>Cierra los programas que consuman muchos recursos y que no necesites</li>
<li><strong>No cierres</strong> procesos del sistema o que no reconozcas</li>
</ol>

<h3>3. Libera espacio en disco</h3>
<ol>
<li>Presiona <strong>Win + E</strong> para abrir el explorador de archivos</li>
<li>Revisa si el disco C: tiene menos del 10% de espacio libre (barra roja)</li>
<li>Vacía la Papelera de reciclaje</li>
<li>Elimina archivos en la carpeta Descargas que ya no necesites</li>
</ol>

<h3>4. Verifica las actualizaciones</h3>
<ol>
<li>Ve a <strong>Configuración → Windows Update</strong></li>
<li>Si hay actualizaciones pendientes, instálalas y reinicia</li>
</ol>

<h3>5. Si el problema persiste</h3>
<p>Crea un ticket de soporte indicando:</p>
<ul>
<li>¿Desde cuándo está lento?</li>
<li>¿Ocurre con algún programa específico?</li>
<li>¿Instalaste algo recientemente?</li>
</ul>',
'Pasos que puedes seguir por tu cuenta cuando tu computador funciona lento', 'software',
'lento,rendimiento,memoria,cpu,lentitud,performance', 1, 1, 1),

('Problemas con el correo Outlook — Guía de solución', 'problemas-correo-outlook',
'<h2>Solución de problemas comunes en Outlook</h2>

<h3>Outlook no abre o se congela al iniciar</h3>
<ol>
<li>Cierra Outlook completamente desde el Administrador de Tareas (Ctrl+Shift+Esc)</li>
<li>Mantén presionada la tecla <strong>Ctrl</strong> mientras haces doble clic en el icono de Outlook</li>
<li>Aparecerá un diálogo preguntando si deseas iniciar en <strong>Modo Seguro</strong> → haz clic en Sí</li>
<li>Si funciona en modo seguro, el problema es un complemento. Ve a Archivo → Opciones → Complementos → deshabilita los complementos uno a uno</li>
</ol>

<h3>No llegan correos nuevos</h3>
<ol>
<li>Verifica tu conexión a internet</li>
<li>Presiona <strong>F9</strong> o haz clic en "Enviar y recibir todas las carpetas"</li>
<li>Revisa la barra inferior: si dice "Trabajando sin conexión", haz clic en la pestaña <strong>Enviar y Recibir</strong> → desactiva "Trabajar sin conexión"</li>
<li>Revisa que tu buzón no esté lleno (se muestra un aviso en la parte superior)</li>
</ol>

<h3>No puedo enviar correos</h3>
<ul>
<li>Verifica que la dirección del destinatario sea correcta</li>
<li>Revisa la carpeta <strong>Bandeja de salida</strong> (si hay correos atascados, elimínalos y reintenta)</li>
<li>Si el archivo adjunto supera los 25 MB, usa OneDrive para compartirlo</li>
</ul>

<h3>La firma de correo no aparece</h3>
<p>Ve a <strong>Archivo → Opciones → Correo → Firmas</strong> y verifica que tu firma esté seleccionada para "Nuevos mensajes" y "Respuestas/Reenvíos".</p>',
'Guía para resolver los problemas más comunes de Microsoft Outlook', 'software',
'outlook,correo,email,no abre,congela,firma,enviar,recibir', 1, 1, 0),

('No tengo internet — Diagnóstico paso a paso', 'sin-internet-diagnostico',
'<h2>Sin conexión a internet: Diagnóstico rápido</h2>
<p>Sigue estos pasos en orden para diagnosticar y posiblemente resolver la falta de internet.</p>

<h3>Paso 1: ¿Es solo tu equipo?</h3>
<p>Pregunta a un compañero cercano si tiene internet. Si nadie tiene, es un problema de red general → crea un ticket con prioridad <strong>URGENTE</strong> indicando el área afectada.</p>

<h3>Paso 2: Verifica la conexión física</h3>
<ul>
<li><strong>Cable de red:</strong> Revisa que el cable Ethernet esté firmemente conectado al equipo y a la roseta de pared. Debe hacer "clic" al insertarse.</li>
<li><strong>WiFi:</strong> Verifica que el WiFi esté habilitado. Haz clic en el icono de red en la barra de tareas y comprueba que estás conectado a la red corporativa.</li>
</ul>

<h3>Paso 3: Reinicia la conexión de red</h3>
<ol>
<li>Haz clic derecho en el icono de red (esquina inferior derecha)</li>
<li>Selecciona <strong>"Solucionar problemas de red"</strong> o <strong>"Diagnósticos de red"</strong></li>
<li>Sigue las instrucciones del asistente</li>
</ol>

<h3>Paso 4: Reinicio rápido de red</h3>
<ol>
<li>Presiona <strong>Win + R</strong>, escribe <code>cmd</code> y presiona Enter</li>
<li>Escribe: <code>ipconfig /release</code> y presiona Enter</li>
<li>Luego escribe: <code>ipconfig /renew</code> y presiona Enter</li>
<li>Cierra la ventana e intenta navegar nuevamente</li>
</ol>

<h3>Paso 5: Si nada funciona</h3>
<p>Reinicia el computador completamente y vuelve a intentar. Si persiste, crea un ticket indicando:</p>
<ul>
<li>Tu ubicación exacta (piso, oficina)</li>
<li>Si estás conectado por cable o WiFi</li>
<li>Si otros equipos cercanos tienen el mismo problema</li>
</ul>',
'Guía paso a paso para diagnosticar problemas de conexión a internet', 'red',
'internet,sin conexión,wifi,ethernet,red,cable,diagnóstico', 1, 1, 1),

('Cómo actuar ante un ciberataque o correo sospechoso', 'ciberataque-correo-sospechoso',
'<h2>Protocolo ante amenazas de seguridad informática</h2>

<div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; border-radius: 8px; margin: 10px 0;">
<strong>⚠ IMPORTANTE:</strong> Si sospechas que tu equipo fue comprometido, <strong>NO lo apagues</strong> ni borres nada. Desconéctalo de la red (desenchufa el cable o desactiva WiFi) e informa inmediatamente a TI.
</div>

<h3>Cómo reconocer un correo sospechoso (phishing)</h3>
<ul>
<li>El remitente tiene un dominio extraño o con errores de ortografía</li>
<li>Te piden urgentemente hacer clic en un enlace o descargar un archivo</li>
<li>Solicitan contraseñas, datos bancarios o información personal</li>
<li>El saludo es genérico ("Estimado usuario" en vez de tu nombre)</li>
<li>Contiene errores gramaticales o de formato inusuales</li>
</ul>

<h3>¿Qué hacer si recibes un correo sospechoso?</h3>
<ol>
<li><strong>NO hagas clic</strong> en ningún enlace ni descargues archivos adjuntos</li>
<li><strong>NO respondas</strong> al correo</li>
<li><strong>NO reenvíes</strong> el correo a otros compañeros</li>
<li>Reporta el correo: haz clic derecho → <strong>"Informar de phishing"</strong> o <strong>"Correo no deseado"</strong></li>
<li>Crea un ticket de soporte adjuntando una captura de pantalla del correo</li>
</ol>

<h3>¿Qué hacer si hiciste clic en un enlace sospechoso?</h3>
<ol>
<li><strong>Desconecta tu equipo de la red</strong> inmediatamente (desenchufa el cable o desactiva WiFi)</li>
<li>No cierres el navegador (el equipo de seguridad puede necesitar la información)</li>
<li>Llama al teléfono <strong>512406479</strong> (emergencias TI) e informa lo ocurrido</li>
<li>Cambia tu contraseña desde otro dispositivo si ingresaste credenciales</li>
</ol>

<h3>Señales de que tu equipo podría estar comprometido</h3>
<ul>
<li>Ventanas emergentes inusuales o publicidad que no aparecía antes</li>
<li>El equipo está extremadamente lento sin razón aparente</li>
<li>Programas se abren o cierran solos</li>
<li>Tu navegador fue cambiado sin tu autorización</li>
<li>Archivos encriptados o con extensiones extrañas (.locked, .encrypted)</li>
</ul>',
'Protocolo de acción ante correos sospechosos, phishing y posibles ciberataques', 'procedimientos',
'seguridad,phishing,virus,malware,ciberataque,correo sospechoso,ransomware', 1, 1, 1),

('Cómo usar Microsoft Teams correctamente', 'guia-microsoft-teams',
'<h2>Guía rápida de Microsoft Teams</h2>

<h3>Iniciar una reunión</h3>
<ol>
<li>Abre Microsoft Teams</li>
<li>Haz clic en <strong>Calendario</strong> en la barra lateral izquierda</li>
<li>Clic en <strong>"Nueva reunión"</strong> (esquina superior derecha)</li>
<li>Agrega título, participantes, fecha y hora</li>
<li>Haz clic en <strong>"Enviar"</strong> para programar o <strong>"Reunirse ahora"</strong> para iniciar inmediatamente</li>
</ol>

<h3>Compartir pantalla en una reunión</h3>
<ol>
<li>Durante la reunión, haz clic en el icono <strong>↑ (flecha arriba)</strong> o <strong>"Compartir contenido"</strong></li>
<li>Elige qué compartir: pantalla completa, ventana específica o PowerPoint</li>
<li>Si aparece un error de permisos, cierra Teams y ábrelo como <strong>Administrador</strong> (clic derecho → Ejecutar como administrador)</li>
</ol>

<h3>Problemas de audio</h3>
<ul>
<li><strong>No me escuchan:</strong> Verifica que el micrófono correcto esté seleccionado (clic en ⋯ → Configuración del dispositivo)</li>
<li><strong>No escucho:</strong> Comprueba que el altavoz/auricular correcto esté seleccionado y el volumen no esté en silencio</li>
<li><strong>Eco o retroalimentación:</strong> Usa auriculares para evitar que el micrófono capte el sonido del altavoz</li>
</ul>

<h3>Chat y archivos</h3>
<ul>
<li>Puedes arrastrar archivos directamente a la ventana de chat para compartirlos</li>
<li>Los archivos compartidos en un canal se guardan en SharePoint y puedes acceder desde la pestaña <strong>"Archivos"</strong></li>
<li>Usa <strong>@nombre</strong> para mencionar a alguien específico en un mensaje</li>
</ul>',
'Guía práctica para usar Microsoft Teams: reuniones, audio y pantalla compartida', 'software',
'teams,reunión,videollamada,pantalla,audio,micrófono,compartir', 1, 1, 0),

('Procedimiento de emergencia: Pérdida de archivos', 'emergencia-perdida-archivos',
'<h2>Qué hacer si perdiste archivos importantes</h2>

<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 10px 0;">
<strong>⏰ El tiempo es clave:</strong> Mientras antes informes, mayores son las probabilidades de recuperar los archivos. No intentes instalar programas de recuperación por tu cuenta.
</div>

<h3>Paso 1: Revisa la Papelera de reciclaje</h3>
<p>Abre la Papelera de reciclaje en el escritorio. Si encuentras el archivo, haz clic derecho → <strong>"Restaurar"</strong>. El archivo volverá a su ubicación original.</p>

<h3>Paso 2: Busca versiones anteriores</h3>
<ol>
<li>Navega a la carpeta donde estaba el archivo</li>
<li>Haz clic derecho en la carpeta → <strong>"Propiedades"</strong></li>
<li>Ve a la pestaña <strong>"Versiones anteriores"</strong></li>
<li>Si aparecen versiones, selecciona una fecha anterior y haz clic en <strong>"Restaurar"</strong></li>
</ol>

<h3>Paso 3: Si era un archivo de OneDrive o SharePoint</h3>
<ol>
<li>Ingresa a <strong>onedrive.com</strong> o <strong>sharepoint.com</strong></li>
<li>Ve a la <strong>Papelera de reciclaje</strong> del sitio (menú lateral izquierdo)</li>
<li>Los archivos eliminados se mantienen por 93 días</li>
</ol>

<h3>Paso 4: Crea un ticket de soporte</h3>
<p>Si no pudiste recuperar el archivo, crea un ticket inmediatamente indicando:</p>
<ul>
<li>Nombre exacto del archivo y su ubicación</li>
<li>Fecha aproximada de la última vez que lo viste</li>
<li>¿Lo eliminaste tú o desapareció solo?</li>
<li>¿Estaba en una carpeta compartida o local?</li>
</ul>

<h3>Prevención: Cómo evitar perder archivos</h3>
<ul>
<li>Guarda tus archivos importantes en <strong>OneDrive</strong> (se respaldan automáticamente)</li>
<li>No guardes archivos críticos solo en el escritorio o disco local</li>
<li>Usa las carpetas compartidas del departamento para documentos de trabajo</li>
</ul>',
'Procedimiento de emergencia paso a paso para recuperar archivos eliminados o perdidos', 'procedimientos',
'archivos,recuperar,perdidos,eliminados,papelera,backup,respaldo', 1, 1, 0),

('Cómo conectarte a la red WiFi corporativa', 'conectar-wifi-corporativa',
'<h2>Conexión a la red WiFi de Empresa Portuaria Coquimbo</h2>

<h3>Red disponible</h3>
<table style="width:100%; border-collapse: collapse; margin: 15px 0;">
<tr style="background: #f0f9ff;"><td style="padding: 10px; border: 1px solid #e2e8f0;"><strong>Nombre de red</strong></td><td style="padding: 10px; border: 1px solid #e2e8f0;">EPC-Corporativa</td></tr>
<tr><td style="padding: 10px; border: 1px solid #e2e8f0;"><strong>Tipo de seguridad</strong></td><td style="padding: 10px; border: 1px solid #e2e8f0;">WPA2-Enterprise</td></tr>
<tr style="background: #f0f9ff;"><td style="padding: 10px; border: 1px solid #e2e8f0;"><strong>Autenticación</strong></td><td style="padding: 10px; border: 1px solid #e2e8f0;">Credenciales de dominio (mismo usuario y contraseña de Windows)</td></tr>
</table>

<h3>Pasos para conectarse</h3>
<ol>
<li>Haz clic en el <strong>icono de WiFi</strong> en la barra de tareas (esquina inferior derecha)</li>
<li>Busca la red <strong>"EPC-Corporativa"</strong> y haz clic en <strong>"Conectar"</strong></li>
<li>Ingresa tu <strong>usuario de dominio</strong> (el mismo que usas para iniciar sesión en Windows)</li>
<li>Ingresa tu <strong>contraseña de Windows</strong></li>
<li>Si aparece un certificado de seguridad, haz clic en <strong>"Conectar"</strong></li>
<li>Marca la casilla <strong>"Conectar automáticamente"</strong> para no tener que repetir el proceso</li>
</ol>

<h3>Red para visitantes</h3>
<p>Para visitantes o dispositivos personales, usar la red <strong>"EPC-Visitantes"</strong>. Solicita la contraseña del día en recepción.</p>

<h3>Problemas frecuentes</h3>
<ul>
<li><strong>"No se puede conectar a esta red":</strong> Olvida la red (clic derecho → Olvidar), reinicia WiFi e intenta nuevamente</li>
<li><strong>Conexión intermitente:</strong> Verifica que no estés al límite del área de cobertura. Las zonas de bodega y muelle pueden tener señal limitada</li>
<li><strong>Contraseña rechazada:</strong> Si cambiaste tu contraseña de Windows recientemente, debes ingresar la nueva contraseña</li>
</ul>',
'Instrucciones para conectarte a la red WiFi corporativa y solución de problemas', 'red',
'wifi,inalámbrico,wireless,conexión,corporativa,visitantes', 1, 1, 0),

('Guía de emergencia: Pantalla azul (BSOD)', 'pantalla-azul-bsod',
'<h2>Pantalla Azul de Windows (BSOD): Qué hacer</h2>
<p>La "pantalla azul de la muerte" (BSOD) indica un error crítico del sistema. No te preocupes, generalmente tiene solución.</p>

<div style="background: #f0f9ff; border-left: 4px solid #0369a1; padding: 15px; border-radius: 8px; margin: 10px 0;">
<strong>Lo más importante:</strong> Anota o toma foto del <strong>código de error</strong> que aparece en pantalla (ej: DRIVER_IRQL_NOT_LESS_OR_EQUAL, KERNEL_DATA_INPAGE_ERROR).
</div>

<h3>Si ocurre una sola vez</h3>
<ol>
<li>Espera a que el equipo se reinicie automáticamente (puede tardar 1-2 minutos)</li>
<li>Si no se reinicia solo, mantén presionado el botón de encendido por 10 segundos</li>
<li>Enciende nuevamente y trabaja normalmente</li>
<li>Si no vuelve a ocurrir en las próximas horas, fue un error aislado</li>
</ol>

<h3>Si ocurre repetidamente</h3>
<ol>
<li>Anota la hora exacta y qué estabas haciendo cuando ocurrió</li>
<li>Toma foto de la pantalla azul (el código de error es muy útil para diagnóstico)</li>
<li>Crea un ticket de soporte con prioridad <strong>ALTA</strong> adjuntando la foto</li>
<li>Indica si el error ocurre al usar un programa específico o de forma aleatoria</li>
</ol>

<h3>Causas comunes</h3>
<ul>
<li><strong>Actualización reciente de Windows</strong> que causó conflicto</li>
<li><strong>Driver de hardware</strong> incompatible o desactualizado</li>
<li><strong>Disco duro dañado</strong> o con sectores defectuosos</li>
<li><strong>Memoria RAM</strong> defectuosa</li>
<li><strong>Sobrecalentamiento</strong> del equipo</li>
</ul>

<h3>Mientras esperas al técnico</h3>
<p>Si necesitas seguir trabajando urgentemente, intenta usar <strong>Office Online</strong> (portal.office.com) desde un equipo prestado o tu celular para acceder a tus archivos de OneDrive.</p>',
'Qué hacer cuando aparece una pantalla azul de error en tu computador Windows', 'hardware',
'pantalla azul,BSOD,error,crash,reinicio,windows,blue screen', 1, 1, 0);

-- =============================================
-- EVENTOS
-- =============================================
INSERT INTO events (title, description, event_type, start_date, end_date, all_day, location, color, is_public, created_by) VALUES
('Reunión mensual de equipo', 
 'Reunión mensual para revisar avances y planificar el próximo mes.', 
 'meeting', 
 DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 10 HOUR, 
 DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 11 HOUR + INTERVAL 30 MINUTE, 
 0, 'Sala de Reuniones Principal', '#0a2540', 1, 1),

('Día de la empresa', 
 'Celebración del aniversario de Empresa Portuaria Coquimbo con actividades para todos los colaboradores.', 
 'corporate', 
 DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
 DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
 1, 'Instalaciones Empresa Portuaria Coquimbo', '#22c55e', 1, 1),

('Capacitación Ciberseguridad', 
 'Capacitación sobre buenas prácticas de ciberseguridad y protección de datos.', 
 'training',
 DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 15 HOUR,
 DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 17 HOUR,
 0, 'Auditorio', '#f59e0b', 1, 1),

('Feriado - Año Nuevo', 
 'Feriado legal.', 
 'holiday',
 '2026-01-01',
 '2026-01-01',
 1, NULL, '#ef4444', 1, 1),

('Cumpleaños María González',
 'Cumpleaños de nuestra compañera María.',
 'birthday',
 DATE_ADD(CURDATE(), INTERVAL 5 DAY),
 DATE_ADD(CURDATE(), INTERVAL 5 DAY),
 1, NULL, '#ec4899', 1, 1);

-- =============================================
-- BOLETINES INTERNOS
-- =============================================
INSERT INTO bulletins (title, content, category, priority, icon, deadline_date, expanded_content, action_label, is_pinned, author_id) VALUES
('Capacitación Ley Karin', 
 'Todos los colaboradores deben completar la capacitación obligatoria sobre prevención de acoso laboral.', 
 'urgent', 'high', 'bi-exclamation-triangle-fill', '2026-01-31', 
 'La capacitación está disponible en el portal de RRHH. Duración aproximada: 45 minutos. Al finalizar, recibirás un certificado que debes guardar.', 
 'Iniciar Capacitación', 1, 1),

('Reunión General', 
 'Próxima reunión de actualización corporativa con resultados del Q4.', 
 'event', 'normal', 'bi-people-fill', '2026-01-25', 
 'Lugar: Sala de Conferencias Principal. Puntos a tratar: resultados financieros, nuevos proyectos 2026 y reconocimientos del equipo.', 
 'Agendar', 0, 1),

('Cumpleaños del Mes', 
 'Felicitamos a todos los colaboradores que cumplen años en enero!', 
 'celebration', 'low', 'bi-gift-fill', NULL, 
 'María González (5 Ene) - Carlos Pérez (12 Ene) - Ana López (18 Ene) - Juan Martínez (22 Ene) - Patricia Sánchez (28 Ene)', 
 NULL, 0, 4),

('Mantenimiento Sistemas', 
 'Mantenimiento programado de servidores. Algunos servicios podrían no estar disponibles.', 
 'maintenance', 'normal', 'bi-tools', '2026-01-19', 
 'Servicios afectados: Correo corporativo (sábado 22:00-02:00), VPN (domingo 06:00-10:00). El portal web permanecerá operativo.', 
 'Ver Detalles', 0, 1),

('Nuevo beneficio dental',
 'A partir de febrero, todos los colaboradores tendrán acceso a seguro dental complementario.',
 'info', 'normal', 'bi-heart-pulse-fill', '2026-02-01',
 'El nuevo convenio incluye: limpieza dental semestral, descuentos en tratamientos y cobertura para cargas familiares. Inscripciones abiertas hasta el 31 de enero.',
 'Más información', 0, 4);

-- =============================================
-- TICKETS DE EJEMPLO
-- =============================================
INSERT INTO tickets (
    ticket_number, user_id, user_name, user_email, user_department,
    category, priority, title, description, status,
    sla_response_target, sla_resolution_target, created_at
) VALUES 
('TK-2026-0001', 6, 'Usuario Demo', 'usuario@example.com', 'Operaciones',
 'software', 'media', 'Problema con Microsoft Office', 
 'Excel se cierra inesperadamente al abrir archivos grandes. El problema comenzó después de la última actualización de Windows.', 
 'abierto', 480, 2880, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

('TK-2026-0002', 7, 'María González', 'maria.gonzalez@example.com', 'Administración',
 'hardware', 'alta', 'Monitor no enciende', 
 'El monitor de mi estación de trabajo no enciende desde esta mañana. Ya verifiqué los cables y están bien conectados.', 
 'asignado', 240, 1440, DATE_SUB(NOW(), INTERVAL 5 HOUR)),

('TK-2026-0003', 8, 'Carlos Pérez', 'carlos.perez@example.com', 'Operaciones',
 'red', 'urgente', 'Sin acceso a internet', 
 'Todo el departamento de Operaciones quedó sin acceso a internet desde las 9:00 AM. Necesitamos conexión para las operaciones del puerto.', 
 'en_proceso', 60, 240, DATE_SUB(NOW(), INTERVAL 1 HOUR)),

('TK-2026-0004', 9, 'Ana López', 'ana.lopez@example.com', 'Finanzas',
 'acceso', 'media', 'Solicitud de acceso a SAP', 
 'Necesito acceso al módulo de reportes financieros de SAP para generar los informes mensuales.', 
 'abierto', 480, 2880, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Actualizar tickets con asignaciones
UPDATE tickets SET 
    assigned_to = 2, 
    assigned_by = 1,
    assigned_at = DATE_SUB(NOW(), INTERVAL 4 HOUR),
    first_response_at = DATE_SUB(NOW(), INTERVAL 4 HOUR),
    sla_response_met = 1
WHERE ticket_number = 'TK-2026-0002';

UPDATE tickets SET 
    assigned_to = 3, 
    assigned_by = 1,
    assigned_at = DATE_SUB(NOW(), INTERVAL 45 MINUTE),
    first_response_at = DATE_SUB(NOW(), INTERVAL 50 MINUTE),
    work_started_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE),
    sla_response_met = 1
WHERE ticket_number = 'TK-2026-0003';

-- =============================================
-- HISTORIAL DE TICKETS
-- =============================================
INSERT INTO ticket_history (ticket_id, user_id, action, new_value, description) VALUES
(1, NULL, 'created', 'abierto', 'Ticket creado por el usuario'),
(2, NULL, 'created', 'abierto', 'Ticket creado por el usuario'),
(2, 1, 'assigned', 'Soporte TI', 'Ticket asignado a Soporte TI'),
(2, 2, 'status_change', 'asignado', 'Estado cambiado a asignado'),
(3, NULL, 'created', 'abierto', 'Ticket creado por el usuario'),
(3, 1, 'assigned', 'Técnico Soporte', 'Ticket asignado a Técnico Soporte'),
(3, 3, 'status_change', 'en_proceso', 'Estado cambiado a en proceso'),
(4, NULL, 'created', 'abierto', 'Ticket creado por el usuario');

-- =============================================
-- COMENTARIOS DE TICKETS
-- =============================================
INSERT INTO ticket_comments (ticket_id, user_id, user_name, comment, is_internal, is_first_response) VALUES
(2, 2, 'Soporte TI', 'Hemos recibido su solicitud. Pasaré a revisar el monitor en los próximos minutos.', 0, 1),
(3, 3, 'Técnico Soporte', 'Estamos verificando la conexión del switch principal del área de Operaciones.', 0, 1),
(3, 3, 'Técnico Soporte', 'Se detectó una falla en el puerto del switch. Procediendo a reconfigurar.', 1, 0);

-- =============================================
-- DOCUMENTOS
-- =============================================
INSERT INTO documents (title, description, file_path, file_type, category, uploaded_by, is_public) VALUES
('Manual de Usuario - Portal Empresa Portuaria Coquimbo', 'Guía completa de uso del portal corporativo', '/uploads/documents/manual_portal_epc.pdf', 'application/pdf', 'Manuales', 1, 1),
('Política de Seguridad Informática', 'Políticas y procedimientos de seguridad de la información', '/uploads/documents/politica_seguridad.pdf', 'application/pdf', 'Políticas', 1, 1),
('Reglamento Interno', 'Reglamento interno de orden, higiene y seguridad', '/uploads/documents/reglamento_interno.pdf', 'application/pdf', 'Reglamentos', 1, 1),
('Protocolo Ley Karin', 'Protocolo de prevención y denuncia según Ley 21.643', '/uploads/documents/protocolo_ley_karin.pdf', 'application/pdf', 'Protocolos', 1, 1);

-- =============================================
-- LIMPIEZA: Eliminar tablas de chat si existieran
-- =============================================
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS chatbot_learning;
DROP TABLE IF EXISTS chatbot_synonyms;
DROP TABLE IF EXISTS chatbot_dictionary;

-- =============================================
-- VERIFICACIÓN FINAL
-- =============================================
SELECT '✓ Base de datos EPCO v3.2 (Docker) inicializada exitosamente!' AS mensaje;
SELECT CONCAT('Usuarios: ', COUNT(*)) AS info FROM users;
SELECT CONCAT('Artículos KB: ', COUNT(*)) AS info FROM knowledge_base;
SELECT CONCAT('Noticias: ', COUNT(*)) AS info FROM news;
SELECT CONCAT('Eventos: ', COUNT(*)) AS info FROM events;
SELECT CONCAT('Boletines: ', COUNT(*)) AS info FROM bulletins;
SELECT CONCAT('Tickets: ', COUNT(*)) AS info FROM tickets;

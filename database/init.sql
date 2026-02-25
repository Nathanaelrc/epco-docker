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
    status ENUM('abierto', 'asignado', 'en_proceso', 'pendiente', 'resuelto', 'cerrado') DEFAULT 'abierto',
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
('soporteepco@gmail.com', 'Soporte EPCO', 'all');

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
('Administrador EPCO', 'admin.epco', 'admin@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'TI', 'Administrador de Sistemas'),
('Soporte TI', 'soporte.ti', 'soporte@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Técnico Soporte', 'tecnico.soporte', 'tecnico@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'soporte', 'TI', 'Técnico de Soporte'),
('Comunicaciones', 'comunicaciones.epco', 'social@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'social', 'Comunicaciones', 'Encargado de Comunicaciones'),
('Comité de Ética', 'comite.etica', 'etica@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'denuncia', 'Recursos Humanos', 'Encargado Ley Karin'),
('Usuario Demo', 'usuario.demo', 'usuario@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Operaciones', 'Colaborador'),
('María González', 'maria.gonzalez', 'maria.gonzalez@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Administración', 'Asistente Administrativo'),
('Carlos Pérez', 'carlos.perez', 'carlos.perez@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Operaciones', 'Operador Portuario'),
('Ana López', 'ana.lopez', 'ana.lopez@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Finanzas', 'Analista Financiero');

-- =============================================
-- CONFIGURACIÓN SLA (tiempos en minutos)
-- =============================================
INSERT INTO sla_settings (priority, first_response_minutes, assignment_minutes, resolution_minutes) VALUES
('urgente', 60, 30, 240),
('alta', 240, 120, 1440),
('media', 480, 240, 2880),
('baja', 1440, 480, 4320);

-- =============================================
-- NOTICIAS
-- =============================================
INSERT INTO news (title, excerpt, content, author_id, category, is_published, is_featured) VALUES 
('Bienvenidos al nuevo Portal EPCO', 
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
 
('EPCO desarrolló con éxito su programa de visitas educativas 2025',
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
<li><strong>EPCO-PISO1-HP</strong> - Impresora HP LaserJet (Piso 1)</li>
<li><strong>EPCO-PISO2-XEROX</strong> - Xerox WorkCentre (Piso 2)</li>
<li><strong>EPCO-RECEPCION</strong> - HP OfficeJet (Recepción)</li>
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
Email: nombre@epco.cl
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
'acceso,permisos,sistemas,solicitud', 1, 1, 0);

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
 'Celebración del aniversario de EPCO con actividades para todos los colaboradores.', 
 'corporate', 
 DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
 DATE_ADD(CURDATE(), INTERVAL 30 DAY), 
 1, 'Instalaciones EPCO', '#22c55e', 1, 1),

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
('TK-2026-0001', 6, 'Usuario Demo', 'usuario@epco.cl', 'Operaciones',
 'software', 'media', 'Problema con Microsoft Office', 
 'Excel se cierra inesperadamente al abrir archivos grandes. El problema comenzó después de la última actualización de Windows.', 
 'abierto', 480, 2880, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

('TK-2026-0002', 7, 'María González', 'maria.gonzalez@epco.cl', 'Administración',
 'hardware', 'alta', 'Monitor no enciende', 
 'El monitor de mi estación de trabajo no enciende desde esta mañana. Ya verifiqué los cables y están bien conectados.', 
 'asignado', 240, 1440, DATE_SUB(NOW(), INTERVAL 5 HOUR)),

('TK-2026-0003', 8, 'Carlos Pérez', 'carlos.perez@epco.cl', 'Operaciones',
 'red', 'urgente', 'Sin acceso a internet', 
 'Todo el departamento de Operaciones quedó sin acceso a internet desde las 9:00 AM. Necesitamos conexión para las operaciones del puerto.', 
 'en_proceso', 60, 240, DATE_SUB(NOW(), INTERVAL 1 HOUR)),

('TK-2026-0004', 9, 'Ana López', 'ana.lopez@epco.cl', 'Finanzas',
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
('Manual de Usuario - Portal EPCO', 'Guía completa de uso del portal corporativo', '/uploads/documents/manual_portal_epco.pdf', 'application/pdf', 'Manuales', 1, 1),
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

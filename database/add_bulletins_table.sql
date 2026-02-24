-- =============================================
-- EPCO - Tabla de Boletines Internos
-- Ejecutar para agregar funcionalidad de boletines
-- =============================================

USE epco;

-- Tabla de boletines
CREATE TABLE IF NOT EXISTS bulletins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('urgent', 'event', 'info', 'maintenance', 'celebration') NOT NULL DEFAULT 'info',
    priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    icon VARCHAR(50) DEFAULT 'bi-megaphone',
    -- Fechas
    event_date DATE DEFAULT NULL,
    deadline_date DATE DEFAULT NULL,
    -- Estado
    is_active TINYINT(1) DEFAULT 1,
    is_pinned TINYINT(1) DEFAULT 0,
    -- Detalles expandibles
    expanded_content TEXT DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    action_label VARCHAR(100) DEFAULT NULL,
    -- Autor
    author_id INT DEFAULT NULL,
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATE DEFAULT NULL,
    -- Indices
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_pinned (is_pinned),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Insertar boletines de ejemplo
INSERT INTO bulletins (title, content, category, priority, icon, deadline_date, expanded_content, action_label, is_pinned) VALUES
('Capacitacion Ley Karin', 'Todos los colaboradores deben completar la capacitacion obligatoria sobre prevencion de acoso laboral.', 'urgent', 'high', 'bi-exclamation-triangle-fill', '2026-01-31', 'La capacitacion esta disponible en el portal de RRHH. Duracion aproximada: 45 minutos. Al finalizar, recibiras un certificado que debes guardar.', 'Iniciar Capacitacion', 1),
('Reunion General', 'Proxima reunion de actualizacion corporativa con resultados del Q4.', 'event', 'normal', 'bi-people-fill', '2026-01-25', 'Lugar: Sala de Conferencias Principal. Puntos a tratar: resultados financieros, nuevos proyectos 2026 y reconocimientos del equipo.', 'Agendar', 0),
('Cumpleanos del Mes', 'Felicitamos a todos los colaboradores que cumplen anos en enero!', 'celebration', 'low', 'bi-gift-fill', NULL, 'Maria Gonzalez (5 Ene) - Carlos Perez (12 Ene) - Ana Lopez (18 Ene) - Juan Martinez (22 Ene) - Patricia Sanchez (28 Ene)', NULL, 0),
('Mantenimiento Sistemas', 'Mantenimiento programado de servidores. Algunos servicios podrian no estar disponibles.', 'maintenance', 'normal', 'bi-tools', '2026-01-19', 'Servicios afectados: Correo corporativo (sabado 22:00-02:00), VPN (domingo 06:00-10:00). El portal web permanecera operativo.', 'Ver Detalles', 0);

-- Tabla para tracking de lectura (opcional)
CREATE TABLE IF NOT EXISTS bulletin_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulletin_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bulletin_id) REFERENCES bulletins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (bulletin_id, user_id)
) ENGINE=InnoDB;

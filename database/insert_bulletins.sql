-- Datos de boletines con codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO bulletins (title, content, category, priority, icon, deadline_date, expanded_content, action_label, is_pinned) VALUES
('Capacitacion Ley Karin', 'Todos los colaboradores deben completar la capacitacion obligatoria sobre prevencion de acoso laboral.', 'urgent', 'high', 'bi-exclamation-triangle-fill', '2026-01-31', 'La capacitacion esta disponible en el portal de RRHH. Duracion aproximada: 45 minutos. Al finalizar, recibiras un certificado que debes guardar.', 'Iniciar Capacitacion', 1),
('Reunion General', 'Proxima reunion de actualizacion corporativa con resultados del Q4.', 'event', 'normal', 'bi-people-fill', '2026-01-25', 'Lugar: Sala de Conferencias Principal. Puntos a tratar: resultados financieros, nuevos proyectos 2026 y reconocimientos del equipo.', 'Agendar', 0),
('Cumpleanos del Mes', 'Felicitamos a todos los colaboradores que cumplen anos en enero!', 'celebration', 'low', 'bi-gift-fill', NULL, 'Maria Gonzalez (5 Ene) - Carlos Perez (12 Ene) - Ana Lopez (18 Ene) - Juan Martinez (22 Ene) - Patricia Sanchez (28 Ene)', NULL, 0),
('Mantenimiento Sistemas', 'Mantenimiento programado de servidores. Algunos servicios podrian no estar disponibles.', 'maintenance', 'normal', 'bi-tools', '2026-01-19', 'Servicios afectados: Correo corporativo (sabado 22:00-02:00), VPN (domingo 06:00-10:00). El portal web permanecera operativo.', 'Ver Detalles', 0);

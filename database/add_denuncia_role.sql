-- =============================================================
-- EPCO - Agregar rol 'denuncia' para acceso a Panel de Denuncias
-- Ejecutar en la base de datos MySQL
-- =============================================================

-- Modificar el ENUM de roles para incluir 'denuncia'
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'soporte', 'social', 'denuncia', 'user') NOT NULL DEFAULT 'user';

-- Verificar cambios
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'role';

-- Para asignar el rol denuncia a un usuario existente, usar:
-- UPDATE users SET role = 'denuncia' WHERE email = 'usuario@ejemplo.com';

-- Crear usuario de ejemplo con rol denuncia (opcional)
-- INSERT INTO users (name, email, password, role, department, is_active, created_at)
-- VALUES ('Comité Ética', 'etica@epco.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'denuncia', 'Recursos Humanos', 1, NOW());
-- (password: password)

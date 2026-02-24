-- =============================================
-- Script para eliminar tablas de Chat de EPCO
-- Ejecutar en phpMyAdmin o MySQL CLI si ya existe la BD:
-- mysql -u root epco < remove_chat_tables.sql
-- =============================================

USE epco;

-- Eliminar tablas de chat (si existen)
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;

-- Eliminar tablas de chatbot (si existen)
DROP TABLE IF EXISTS chatbot_learning;
DROP TABLE IF EXISTS chatbot_synonyms;
DROP TABLE IF EXISTS chatbot_dictionary;

SELECT '✓ Tablas de Chat y Chatbot eliminadas exitosamente!' AS mensaje;

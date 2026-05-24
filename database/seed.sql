-- database/seed.sql
-- Execute APÓS o schema.sql
-- Senha padrão: password (troque após o primeiro acesso)

INSERT INTO usuarios (nome, email, senha, idioma) VALUES (
    'Dra. Melody',
    'melo@medico.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'pt-BR'
);
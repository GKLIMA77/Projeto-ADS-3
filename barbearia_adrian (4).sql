-- ============================================================
-- barbearia_adrian.sql — Banco completo da Barbearia Adrian Souza
-- Atualizado com os caminhos locais para a pasta ""
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS barbearia_adrian_souza
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE barbearia_adrian_souza;

DROP TABLE IF EXISTS cliente_servico;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS horarios_bloqueados;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS servicos;
DROP VIEW  IF EXISTS vw_central_agendamentos;
DROP VIEW  IF EXISTS vw_relatorio_servicos;
DROP PROCEDURE IF EXISTS sp_obter_indicadores_dashboard;
DROP FUNCTION  IF EXISTS fn_calcular_faturamento_cliente;

SET FOREIGN_KEY_CHECKS = 1;

-- ── TABELAS ──────────────────────────────────────────────────

-- Tabela de Serviços
CREATE TABLE servicos (
id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
nome  VARCHAR(100) NOT NULL,
preco DECIMAL(8,2) NOT NULL,
ativo TINYINT(1)   NOT NULL DEFAULT 1,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Categorias da Loja
CREATE TABLE categorias (
id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
nome   VARCHAR(100) NOT NULL,
status ENUM('pendente','aprovada','cancelada') NOT NULL DEFAULT 'pendente',
PRIMARY KEY (id),
UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Produtos da Loja
CREATE TABLE produtos (
id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
categoria_id INT UNSIGNED NOT NULL,
nome         VARCHAR(120) NOT NULL,
descricao    VARCHAR(255) NULL,
preco        DECIMAL(8,2) NOT NULL,
imagem       VARCHAR(500) NULL,
status       ENUM('pendente','aprovado','cancelado') NOT NULL DEFAULT 'pendente',
criado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Clientes
CREATE TABLE clientes (
id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
nome     VARCHAR(100) NOT NULL,
telefone VARCHAR(20)  NULL,
email    VARCHAR(150) NULL,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Agendamentos (com suporte a observação do cliente e índice por status)
CREATE TABLE agendamentos (
id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
cliente_nome VARCHAR(100) NOT NULL,
servico_id   INT UNSIGNED NOT NULL,
data_hora    DATETIME     NOT NULL,
status       ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
observacao   VARCHAR(255) NULL,
criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
KEY idx_agendamentos_status (status),
FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Horários / Datas Bloqueadas para Folgas e Feriados
CREATE TABLE horarios_bloqueados (
id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
data   DATE         NOT NULL,
motivo VARCHAR(255) NULL,
PRIMARY KEY (id),
UNIQUE KEY uq_data_bloqueada (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela Relacional Cliente x Serviço
CREATE TABLE cliente_servico (
cliente_id INT UNSIGNED NOT NULL,
servico_id INT UNSIGNED NOT NULL,
PRIMARY KEY (cliente_id, servico_id),
FOREIGN KEY (cliente_id) REFERENCES clientes(id),
FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TRIGGER ──────────────────────────────────────────────────
DELIMITER //
CREATE TRIGGER trg_verificar_preco_positivo
BEFORE UPDATE ON servicos
FOR EACH ROW
BEGIN
IF NEW.preco <= 0 THEN
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'O preço do serviço deve ser um valor positivo!';
END IF;
END //
DELIMITER ;

-- ── FUNCTION ─────────────────────────────────────────────────
DELIMITER //
CREATE FUNCTION fn_calcular_faturamento_cliente(p_nome_cliente VARCHAR(100))
RETURNS DECIMAL(10,2)
DETERMINISTIC
READS SQL DATA
BEGIN
DECLARE v_total DECIMAL(10,2);
SELECT IFNULL(SUM(s.preco), 0.00) INTO v_total
FROM agendamentos a
JOIN servicos s ON s.id = a.servico_id
WHERE a.cliente_nome = p_nome_cliente AND a.status = 'confirmado';
RETURN v_total;
END //
DELIMITER ;

-- ── VIEWS ────────────────────────────────────────────────────
CREATE VIEW vw_central_agendamentos AS
SELECT
a.id AS agendamento_id,
a.cliente_nome,
s.id AS servico_id,
s.nome AS servico_nome,
s.preco AS servico_preco,
a.data_hora,
a.status,
a.observacao,
a.criado_em
FROM agendamentos a
JOIN servicos s ON s.id = a.servico_id;

CREATE VIEW vw_relatorio_servicos AS
WITH CTE_ServicoEstatistica AS (
SELECT s.id, s.nome, s.preco,
COUNT(a.id) AS total_agendamentos,
SUM(CASE WHEN a.status = 'confirmado' THEN s.preco ELSE 0 END) AS faturamento_gerado
FROM servicos s
LEFT JOIN agendamentos a ON a.servico_id = s.id
GROUP BY s.id, s.nome, s.preco
)
SELECT id, nome, preco, total_agendamentos, faturamento_gerado
FROM CTE_ServicoEstatistica;

-- ── PROCEDURE ────────────────────────────────────────────────
DELIMITER //
CREATE PROCEDURE sp_obter_indicadores_dashboard()
BEGIN
SELECT
(SELECT COUNT() FROM agendamentos) AS total_agendamentos,
(SELECT IFNULL(SUM(s.preco), 0) FROM agendamentos a JOIN servicos s ON s.id = a.servico_id WHERE a.status = 'confirmado') AS faturamento_total,
(SELECT COUNT() FROM agendamentos WHERE DATE(data_hora) = CURDATE()) AS agendamentos_hoje,
(SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente') AS pendentes;
END //
DELIMITER ;

-- ── DADOS INICIAIS ───────────────────────────────────────────

INSERT INTO servicos (nome, preco) VALUES
('Corte Premium',      45.00),
('Barba Premium',      35.00),
('Combo Completo',     70.00),
('Plano Profissional', 120.00);

INSERT INTO categorias (nome, status) VALUES
('Finalização',          'aprovada'),
('Cuidados com a barba', 'aprovada'),
('Acessórios',           'aprovada');

-- ── PRODUTOS COM CAMINHOS PARA A PASTA IMG ────────────────────

INSERT INTO produtos (categoria_id, nome, descricao, preco, imagem, status) VALUES

-- FINALIZAÇÃO (3 produtos)
(1, 'Pomada Modeladora Matte',
'Fixação forte com efeito seco e acabamento natural para o dia todo.',
39.90,
'Pomada.png',
'aprovado'),

(1, 'Shampoo Masculino 3 em 1',
'Limpa cabelo, barba e corpo com praticidade em uma única fórmula.',
32.90,
'shampo3.png',
'aprovado'),

(1, 'Cera de Cabelo Extra Forte',
'Controle máximo com brilho intenso. Segura o estilo durante horas.',
35.90,
'ceraCabelo.png',
'aprovado'),

-- CUIDADOS COM A BARBA (3 produtos)
(2, 'Óleo para Barba Premium',
'Blend de óleos naturais que hidrata, suaviza e dá brilho à barba.',
29.90,
'oleoBarba.png',
'aprovado'),

(2, 'Balm Hidratante para Barba',
'Amaciante e hidratante. Deixa a barba macia e com aroma suave.',
27.90,
'hidratante.png',
'aprovado'),

(2, 'Shampoo Específico para Barba',
'Limpa sem ressecar. Mantém a barba saudável, brilhosa e sem coceira.',
24.90,
'shampoBarba.png',
'aprovado'),

-- ACESSÓRIOS (3 produtos)
(3, 'Pente de Madeira Artesanal',
'Pente largo de madeira de qualidade. Não gera estática e respeita os fios.',
18.90,
'PenteMadeira.png',
'aprovado'),

(3, 'Escova de Barba com Cabo',
'Cerdas naturais firmes para distribuir o óleo e dar forma à barba.',
24.90,
'escovabarba.png',
'aprovado'),

(3, 'Tesoura Profissional de Acabamento',
'Aço inox alemão. Precisão para aparo de pontas, bigode e sobrancelha.',
44.90,
'TesouraAcabamento.png',
'aprovado');

-- Clientes de exemplo
INSERT INTO clientes (nome, telefone, email) VALUES
('Felipe Martins',   '44999990001', 'felipe@email.com'),
('Gustavo Henrique', '44999990002', 'gustavo@email.com'),
('Lucas Ferreira',   '44999990003', 'lucas@email.com');

INSERT INTO cliente_servico (cliente_id, servico_id) VALUES
(1, 1), (1, 2), (2, 3), (3, 1), (3, 4);

-- Agendamentos de exemplo (com observação)
INSERT INTO agendamentos (cliente_nome, servico_id, data_hora, status, observacao) VALUES
('Felipe Martins',   1, CURRENT_TIMESTAMP,     'confirmado', 'Corte disfarçado na tesoura'),
('Gustavo Henrique', 3, CURRENT_TIMESTAMP,     'pendente',   'Apenas alinhar a barba'),
('Lucas Ferreira',   4, '2025-07-11 14:00:00', 'confirmado', 'Sem navalha');

-- Exemplo de datas bloqueadas
INSERT INTO horarios_bloqueados (data, motivo) VALUES
('2025-12-25', 'Feriado de Natal'),
('2026-01-01', 'Ano Novo');

UPDATE produtos SET imagem = 'img/Pomada.png'            WHERE nome = 'Pomada Modeladora Matte';
UPDATE produtos SET imagem = 'img/shampo3.png'           WHERE nome = 'Shampoo Masculino 3 em 1';
UPDATE produtos SET imagem = 'img/ceraCabelo.png'        WHERE nome = 'Cera de Cabelo Extra Forte';
UPDATE produtos SET imagem = 'img/oleoBarba.png'         WHERE nome = 'Óleo para Barba Premium';
UPDATE produtos SET imagem = 'img/hidratante.png'        WHERE nome = 'Balm Hidratante para Barba';
UPDATE produtos SET imagem = 'img/shampoBarba.png'       WHERE nome = 'Shampoo Específico para Barba';
UPDATE produtos SET imagem = 'img/PenteMadeira.png'      WHERE nome = 'Pente de Madeira Artesanal';
UPDATE produtos SET imagem = 'img/escovabarba.png'       WHERE nome = 'Escova de Barba com Cabo';
UPDATE produtos SET imagem = 'img/TesouraAcabamento.png' WHERE nome = 'Tesoura Profissional de Acabamento';
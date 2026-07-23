-- ============================================================
-- barbearia_adrian.sql — Banco completo da Barbearia Adrian Souza
-- ============================================================
-- Como importar:
--   1. Abra o phpMyAdmin no XAMPP
--   2. Clique em "Importar" e selecione este arquivo
--   3. Clique em Executar
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS barbearia_adrian_souza
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE barbearia_adrian_souza;

-- Remove tudo antes de recriar (facilita reimportações)
DROP TABLE IF EXISTS cliente_servico;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS servicos;
DROP VIEW  IF EXISTS vw_central_agendamentos;
DROP VIEW  IF EXISTS vw_relatorio_servicos;
DROP PROCEDURE IF EXISTS sp_obter_indicadores_dashboard;
DROP FUNCTION  IF EXISTS fn_calcular_faturamento_cliente;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- TABELAS
-- ============================================================

-- Serviços disponíveis na barbearia
CREATE TABLE servicos (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(100) NOT NULL,
  preco DECIMAL(8,2) NOT NULL,
  ativo TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias dos produtos da loja
CREATE TABLE categorias (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome   VARCHAR(100) NOT NULL,
  status ENUM('pendente','aprovada','cancelada') NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos da loja (ligados a uma categoria)
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

-- Clientes cadastrados
CREATE TABLE clientes (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome     VARCHAR(100) NOT NULL,
  telefone VARCHAR(20)  NULL,
  email    VARCHAR(150) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agendamentos dos clientes
CREATE TABLE agendamentos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_nome VARCHAR(100) NOT NULL,
  servico_id   INT UNSIGNED NOT NULL,
  data_hora    DATETIME     NOT NULL,
  status       ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relação many-to-many entre clientes e serviços
CREATE TABLE cliente_servico (
  cliente_id INT UNSIGNED NOT NULL,
  servico_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (cliente_id, servico_id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TRIGGER — Garante que o preço do serviço seja sempre positivo
-- ============================================================
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


-- ============================================================
-- FUNCTION — Retorna o faturamento total gerado por um cliente
-- ============================================================
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


-- ============================================================
-- VIEW 1 — Centraliza agendamentos com dados do serviço
-- ============================================================
CREATE VIEW vw_central_agendamentos AS
SELECT
    a.id          AS agendamento_id,
    a.cliente_nome,
    s.id          AS servico_id,
    s.nome        AS servico_nome,
    s.preco       AS servico_preco,
    a.data_hora,
    a.status,
    a.criado_em
FROM agendamentos a
JOIN servicos s ON s.id = a.servico_id;


-- ============================================================
-- VIEW 2 (com CTE) — Relatório de serviços com totais
-- ============================================================
CREATE VIEW vw_relatorio_servicos AS
WITH CTE_ServicoEstatistica AS (
    SELECT
        s.id,
        s.nome,
        s.preco,
        COUNT(a.id) AS total_agendamentos,
        SUM(CASE WHEN a.status = 'confirmado' THEN s.preco ELSE 0 END) AS faturamento_gerado
    FROM servicos s
    LEFT JOIN agendamentos a ON a.servico_id = s.id
    GROUP BY s.id, s.nome, s.preco
)
SELECT id, nome, preco, total_agendamentos, faturamento_gerado
FROM CTE_ServicoEstatistica;


-- ============================================================
-- PROCEDURE — Busca os 4 indicadores do dashboard de uma vez
-- ============================================================
DELIMITER //
CREATE PROCEDURE sp_obter_indicadores_dashboard()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM agendamentos) AS total_agendamentos,
        (SELECT IFNULL(SUM(s.preco), 0)
           FROM agendamentos a
           JOIN servicos s ON s.id = a.servico_id
          WHERE a.status = 'confirmado') AS faturamento_total,
        (SELECT COUNT(*) FROM agendamentos WHERE DATE(data_hora) = CURDATE()) AS agendamentos_hoje,
        (SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente') AS pendentes;
END //
DELIMITER ;


-- ============================================================
-- DADOS INICIAIS
-- ============================================================

INSERT INTO servicos (nome, preco) VALUES
('Corte Premium',      45.00),
('Barba Premium',      35.00),
('Combo Completo',     70.00),
('Plano Profissional', 120.00);

-- 3 categorias
INSERT INTO categorias (nome, status) VALUES
('Finalização',          'aprovada'),
('Cuidados com a barba', 'aprovada'),
('Acessórios',           'aprovada');

-- 3 produtos por categoria = 9 no total
-- Imagens escolhidas para representar bem cada produto
INSERT INTO produtos (categoria_id, nome, descricao, preco, imagem, status) VALUES

-- Finalização
(1, 'Pomada Modeladora Matte',
    'Fixação forte com efeito seco e acabamento natural para o dia todo.',
    39.90,
    'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?q=80&w=900',
    'aprovado'),

(1, 'Shampoo 3 em 1',
    'Limpa cabelo e barba com praticidade e fragrância refrescante.',
    32.90,
    'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?q=80&w=900',
    'aprovado'),

(1, 'Cera de Cabelo Strong',
    'Controle total com acabamento brilhante que dura o dia inteiro.',
    35.90,
    'https://images.unsplash.com/photo-1522338242992-e1a54906a8da?q=80&w=900',
    'aprovado'),

-- Cuidados com a barba
(2, 'Óleo para Barba',
    'Blend leve com fragrância amadeirada e brilho discreto.',
    29.90,
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?q=80&w=900',
    'aprovado'),

(2, 'Balm Hidratante Pós-Barba',
    'Hidratação leve para acalmar a pele depois do barbear.',
    27.90,
    'https://images.unsplash.com/photo-1600428863017-ed7b4a7fbd91?q=80&w=900',
    'aprovado'),

(2, 'Shampoo para Barba',
    'Limpeza profunda que mantém a barba macia e com brilho saudável.',
    24.90,
    'https://images.unsplash.com/photo-1599351431613-18ef1fdd27e3?q=80&w=900',
    'aprovado'),

-- Acessórios
(3, 'Pente de Madeira',
    'Pente resistente para alinhar o cabelo sem puxar os fios.',
    18.90,
    'https://images.unsplash.com/photo-1622289970257-0a4c5f6f4e91?q=80&w=900',
    'aprovado'),

(3, 'Escova para Barba',
    'Cerdas firmes para alinhar e distribuir o óleo por toda a barba.',
    24.90,
    'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?q=80&w=900',
    'aprovado'),

(3, 'Tesoura de Acabamento',
    'Ideal para aparo de bigode e ajuste de barba em casa com precisão.',
    44.90,
    'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?q=80&w=900',
    'aprovado');

-- Clientes de exemplo
INSERT INTO clientes (nome, telefone, email) VALUES
('Felipe Martins',   '44999990001', 'felipe@email.com'),
('Gustavo Henrique', '44999990002', 'gustavo@email.com'),
('Lucas Ferreira',   '44999990003', 'lucas@email.com');

-- Relação clientes ↔ serviços
INSERT INTO cliente_servico (cliente_id, servico_id) VALUES
(1, 1), (1, 2), (2, 3), (3, 1), (3, 4);

-- Agendamentos de exemplo
INSERT INTO agendamentos (cliente_nome, servico_id, data_hora, status) VALUES
('Felipe Martins',   1, CURRENT_TIMESTAMP,     'confirmado'),
('Gustavo Henrique', 3, CURRENT_TIMESTAMP,     'pendente'),
('Lucas Ferreira',   4, '2025-07-11 14:00:00', 'confirmado');

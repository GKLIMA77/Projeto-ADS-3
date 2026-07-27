-- ============================================================
-- corrigir_imagens.sql
-- Execute este arquivo no seu banco para corrigir as imagens
-- ============================================================

USE barbearia_adrian_souza;

UPDATE produtos SET imagem = 'img/Pomada.png'            WHERE nome = 'Pomada Modeladora Matte';
UPDATE produtos SET imagem = 'img/shampo3.png'           WHERE nome = 'Shampoo Masculino 3 em 1';
UPDATE produtos SET imagem = 'img/ceraCabelo.png'        WHERE nome = 'Cera de Cabelo Extra Forte';
UPDATE produtos SET imagem = 'img/oleoBarba.png'         WHERE nome = 'Óleo para Barba Premium';
UPDATE produtos SET imagem = 'img/hidratante.png'        WHERE nome = 'Balm Hidratante para Barba';
UPDATE produtos SET imagem = 'img/shampoBarba.png'       WHERE nome = 'Shampoo Específico para Barba';
UPDATE produtos SET imagem = 'img/PenteMadeira.png'      WHERE nome = 'Pente de Madeira Artesanal';
UPDATE produtos SET imagem = 'img/escovabarba.png'       WHERE nome = 'Escova de Barba com Cabo';
UPDATE produtos SET imagem = 'img/TesouraAcabamento.png' WHERE nome = 'Tesoura Profissional de Acabamento';

-- Confirma o resultado
SELECT nome, imagem FROM produtos ORDER BY id;

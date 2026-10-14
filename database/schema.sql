-- Radar CNPJ | índice cadastral dos dados abertos da Receita Federal
-- MySQL 8 / MariaDB; execução única no banco reservado ao Radar.
CREATE TABLE IF NOT EXISTS radar_empresas (
 cnpj_basico CHAR(8) PRIMARY KEY,
 razao_social VARCHAR(255), natureza_juridica CHAR(4),
 qualificacao_responsavel CHAR(2), capital_social DECIMAL(18,2),
 porte CHAR(2), ente_federativo VARCHAR(100),
 INDEX idx_empresa_nome (razao_social(100)), INDEX idx_empresa_porte (porte),
 INDEX idx_empresa_natureza (natureza_juridica)
) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS radar_estabelecimentos (
 cnpj CHAR(14) PRIMARY KEY, cnpj_basico CHAR(8) NOT NULL,
 matriz_filial CHAR(1), nome_fantasia VARCHAR(255), situacao CHAR(2),
 data_situacao DATE, motivo_situacao CHAR(2), nome_cidade_exterior VARCHAR(255),
 pais CHAR(3), data_inicio DATE, cnae_principal CHAR(7),
 cnae_secundarios TEXT, tipo_logradouro VARCHAR(60), logradouro VARCHAR(255),
 numero VARCHAR(30), complemento VARCHAR(255), bairro VARCHAR(120),
 cep CHAR(8), uf CHAR(2), municipio_codigo CHAR(4),
 telefone1 VARCHAR(40), telefone2 VARCHAR(40), fax VARCHAR(40),
 email VARCHAR(255), situacao_especial VARCHAR(255), data_situacao_especial DATE,
 INDEX idx_uf_municipio (uf,municipio_codigo),
 INDEX idx_cnae (cnae_principal),
 INDEX idx_abertura (data_inicio),
 INDEX idx_situacao (situacao),
 INDEX idx_raiz (cnpj_basico),
 INDEX idx_uf_cnae_situacao (uf,cnae_principal,situacao)
) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS radar_simples (
 cnpj_basico CHAR(8) PRIMARY KEY,
 opcao_simples CHAR(1), data_opcao_simples DATE,
 data_exclusao_simples DATE, opcao_mei CHAR(1),
 data_opcao_mei DATE, data_exclusao_mei DATE
) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS radar_municipios (
 codigo CHAR(4) PRIMARY KEY, descricao VARCHAR(150) NOT NULL,
 INDEX idx_descricao (descricao)
) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS radar_cnaes (
 codigo CHAR(7) PRIMARY KEY, descricao VARCHAR(255) NOT NULL
) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS radar_cargas (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 competencia CHAR(7) NOT NULL, origem VARCHAR(255),
 iniciado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 concluido_em TIMESTAMP NULL,
 status VARCHAR(30) NOT NULL,
 empresas BIGINT DEFAULT 0, estabelecimentos BIGINT DEFAULT 0,
 simples BIGINT DEFAULT 0
) CHARACTER SET utf8mb4;

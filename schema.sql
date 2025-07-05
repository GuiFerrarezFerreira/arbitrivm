-- Banco de Dados Arbitrivm - Plataforma de Arbitragem Imobiliária
-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS arbitrivm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arbitrivm_db;

-- Tabela de tipos de usuário
CREATE TABLE tipos_usuario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255)
);

INSERT INTO tipos_usuario (tipo, descricao) VALUES
('admin', 'Administrador da Arbitrivm'),
('empresa', 'Imobiliária ou Condomínio'),
('arbitro', 'Árbitro'),
('solicitante', 'Solicitante/Parte');

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_usuario_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nome_completo VARCHAR(255) NOT NULL,
    cpf_cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    empresa_id INT DEFAULT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    email_verificado BOOLEAN DEFAULT FALSE,
    token_verificacao VARCHAR(255),
    token_recuperacao VARCHAR(255),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL,
    dois_fatores_ativo BOOLEAN DEFAULT FALSE,
    secret_2fa VARCHAR(255),
    notificacoes_email BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (tipo_usuario_id) REFERENCES tipos_usuario(id)
);

-- Tabela de empresas (imobiliárias/condomínios)
CREATE TABLE empresas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL UNIQUE,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(20) NOT NULL UNIQUE,
    tipo_empresa ENUM('imobiliaria', 'condominio') NOT NULL,
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    website VARCHAR(255),
    logo_url VARCHAR(500),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de membros da equipe B2B
CREATE TABLE equipe_empresa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cargo VARCHAR(100),
    permissao_nivel ENUM('visualizar', 'criar', 'gerenciar') DEFAULT 'visualizar',
    ativo BOOLEAN DEFAULT TRUE,
    data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_empresa_usuario (empresa_id, usuario_id)
);

-- Tabela de árbitros
CREATE TABLE arbitros (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL UNIQUE,
    oab_numero VARCHAR(20),
    oab_estado VARCHAR(2),
    biografia TEXT,
    experiencia_anos INT,
    especializacao_imobiliaria BOOLEAN DEFAULT FALSE,
    pos_imobiliario BOOLEAN DEFAULT FALSE,
    perfil_premium BOOLEAN DEFAULT FALSE,
    taxa_sucesso DECIMAL(5,2) DEFAULT 0,
    taxa_hora DECIMAL(10,2) DEFAULT 0,
    total_casos INT DEFAULT 0,
    foto_perfil VARCHAR(500),
    certificados TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de especializações dos árbitros
CREATE TABLE arbitro_especializacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    arbitro_id INT NOT NULL,
    especializacao ENUM('locacoes', 'disputas_condominiais', 'imobiliario_geral', 'danos', 'infracoes') NOT NULL,
    FOREIGN KEY (arbitro_id) REFERENCES arbitros(id) ON DELETE CASCADE,
    UNIQUE KEY unique_arbitro_espec (arbitro_id, especializacao)
);

-- Tabela de tipos de disputa
CREATE TABLE tipos_disputa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    categoria ENUM('danos', 'infracao_condominial', 'locacao', 'outros') NOT NULL,
    descricao TEXT,
    campos_obrigatorios JSON,
    ativo BOOLEAN DEFAULT TRUE
);

INSERT INTO tipos_disputa (nome, slug, categoria, descricao, ativo) VALUES
('Danos ao Imóvel', 'danos-imovel', 'danos', 'Disputas relacionadas a danos causados ao imóvel', TRUE),
('Infração Condominial', 'infracao-condominial', 'infracao_condominial', 'Violações ao regimento interno do condomínio', TRUE),
('Inadimplência de Aluguel', 'inadimplencia-aluguel', 'locacao', 'Falta de pagamento de aluguel', TRUE),
('Descumprimento Contratual', 'descumprimento-contratual', 'locacao', 'Violação de cláusulas contratuais de locação', TRUE);

-- Tabela de disputas
CREATE TABLE disputas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo_caso VARCHAR(20) UNIQUE NOT NULL,
    tipo_disputa_id INT NOT NULL,
    empresa_id INT,
    reclamante_id INT NOT NULL,
    reclamado_id INT,
    arbitro_id INT,
    status ENUM('triagem', 'aguardando_aceite', 'em_andamento', 'aguardando_sentenca', 'finalizada', 'cancelada') DEFAULT 'triagem',
    valor_causa DECIMAL(12,2),
    descricao TEXT,
    endereco_imovel VARCHAR(255),
    numero_unidade VARCHAR(50),
    numero_contrato VARCHAR(50),
    valor_aluguel DECIMAL(10,2),
    data_vencimento DATE,
    sindico_nome VARCHAR(255),
    unidade_condominial VARCHAR(50),
    descricao_conflito TEXT,
    data_abertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_inicio TIMESTAMP NULL,
    data_aceite TIMESTAMP NULL,
    data_finalizacao TIMESTAMP NULL,
    prazo_defesa DATE,
    sentenca_arquivo VARCHAR(500),
    acordo_realizado BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (tipo_disputa_id) REFERENCES tipos_disputa(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (reclamante_id) REFERENCES usuarios(id),
    FOREIGN KEY (reclamado_id) REFERENCES usuarios(id),
    FOREIGN KEY (arbitro_id) REFERENCES arbitros(id)
);

-- Tabela de informações de infração condominial
CREATE TABLE disputa_infracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL UNIQUE,
    tipo_infracao VARCHAR(100),
    data_infracao DATE,
    testemunhas TEXT,
    medidas_tomadas TEXT,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id) ON DELETE CASCADE
);

-- Tabela de documentos das disputas
CREATE TABLE disputa_documentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_documento ENUM('contrato', 'laudo', 'foto_video', 'ata', 'notificacao', 'comprovante', 'outros') NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tamanho BIGINT,
    mime_type VARCHAR(100),
    descricao TEXT,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de mensagens/comunicações
CREATE TABLE disputa_mensagens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de histórico das disputas
CREATE TABLE disputa_historico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    evento VARCHAR(100) NOT NULL,
    descricao TEXT,
    data_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de sentenças
CREATE TABLE sentencas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL UNIQUE,
    arbitro_id INT NOT NULL,
    relatorio TEXT NOT NULL,
    fundamentacao TEXT NOT NULL,
    dispositivo TEXT NOT NULL,
    valor_condenacao DECIMAL(12,2),
    prazo_cumprimento INT,
    arquivo_pdf VARCHAR(500),
    data_sentenca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id),
    FOREIGN KEY (arbitro_id) REFERENCES arbitros(id)
);

-- Tabela de avaliações
CREATE TABLE avaliacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disputa_id INT NOT NULL,
    avaliador_id INT NOT NULL,
    arbitro_id INT NOT NULL,
    nota INT CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disputa_id) REFERENCES disputas(id),
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id),
    FOREIGN KEY (arbitro_id) REFERENCES arbitros(id),
    UNIQUE KEY unique_avaliacao (disputa_id, avaliador_id)
);

-- Tabela de logs de auditoria
CREATE TABLE logs_auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    disputa_id INT,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    dados_anteriores JSON,
    dados_novos JSON,
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (disputa_id) REFERENCES disputas(id)
);

-- Tabela de relatórios B2B
CREATE TABLE relatorios_b2b (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    total_casos INT DEFAULT 0,
    casos_resolvidos INT DEFAULT 0,
    taxa_resolucao DECIMAL(5,2) DEFAULT 0,
    tempo_medio_resolucao INT DEFAULT 0,
    valor_total_disputas DECIMAL(15,2) DEFAULT 0,
    tipos_disputa_json JSON,
    data_geracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

-- Tabela de notificações
CREATE TABLE notificacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo ENUM('disputa_criada', 'nova_disputa', 'disputa_aceita', 'disputa_finalizada', 'nova_mensagem', 'novo_documento', 'sentenca_proferida', 'prazo_proximo', 'nova_disputa_triagem', 'sistema') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT,
    link VARCHAR(500),
    lida BOOLEAN DEFAULT FALSE,
    data_leitura TIMESTAMP NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Índices para melhor performance
CREATE INDEX idx_disputas_status ON disputas(status);
CREATE INDEX idx_disputas_empresa ON disputas(empresa_id);
CREATE INDEX idx_disputas_datas ON disputas(data_abertura, data_finalizacao);
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_comunicacoes_disputa ON disputa_mensagens(disputa_id);
CREATE INDEX idx_documentos_disputa ON disputa_documentos(disputa_id);
CREATE INDEX idx_logs_usuario ON logs_auditoria(usuario_id);
CREATE INDEX idx_logs_disputa ON logs_auditoria(disputa_id);
CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id, lida);
CREATE INDEX idx_usuarios_empresa ON usuarios(empresa_id);
CREATE INDEX idx_tipos_disputa_ativo ON tipos_disputa(ativo);

-- Criar usuário admin padrão (senha: Admin@123)
INSERT INTO usuarios (tipo_usuario_id, email, senha, nome_completo, cpf_cnpj, email_verificado) 
VALUES (1, 'admin@arbitrivm.com.br', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Sistema', '00000000000', TRUE);
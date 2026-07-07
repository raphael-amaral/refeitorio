SET NAMES utf8mb4;

CREATE TABLE usuarios (
    matricula VARCHAR(12) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('comum', 'snd', 'dp', 'dev') NOT NULL DEFAULT 'comum',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cardapios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    descricao TEXT,
    tipo ENUM('almoco', 'jantar') NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_data_tipo (data, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_usuario VARCHAR(12) NOT NULL,
    id_cardapio INT NOT NULL,
    status ENUM('agendado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_usuario) REFERENCES usuarios(matricula),
    FOREIGN KEY (id_cardapio) REFERENCES cardapios(id),
    UNIQUE KEY uk_matricula_cardapio (matricula_usuario, id_cardapio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questor_exportacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    valor_refeicao DECIMAL(6,2) NOT NULL,
    exportado_por VARCHAR(12),
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exportado_por) REFERENCES usuarios(matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (matricula, nome, senha, tipo) VALUES
('snd',   'Equipe SND', '$2y$10$hdqv3YqpLoXwuR/m8Bqtg.OIgBzVOy59zQ5c5Y8M5qq7OIBfqmX0G', 'snd'),
('teste', 'Usuário Teste', '$2y$10$f3vRylZ68AI3HYXqlN7aeOtkDhxDxIV09Z6BvY3yDdI9bvBcsVQUu', 'comum'),
('dev',   'Desenvolvedor', '$2y$10$MsojWKh9yCR36nheklmvxuBtbx.BAnOMka9kpwaOjYIgNY4lgumuC', 'dev'),
('dp', 'Departamento Pessoal', '$2y$10$0k63E/0520b5w/UqLG/WR.XY9i1hz.yCuyThfXoSJ9dXU2cyD/MWu', 'dp');
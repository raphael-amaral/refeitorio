CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('comum', 'admin', 'dev') NOT NULL DEFAULT 'comum',
    ativo TINYINT(1) DEFAULT 1
);

CREATE TABLE registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    matricula VARCHAR(20) NOT NULL,
    data_agendada DATE NOT NULL,
    status ENUM('agendado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT uq_usuario_data UNIQUE (usuario_id, data_agendada),
    CONSTRAINT fk_registros_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);

CREATE INDEX idx_data_agendada ON registros(data_agendada);
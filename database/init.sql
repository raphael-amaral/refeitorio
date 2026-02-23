CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(255) NOT NULL UNIQUE,
    nome VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('comum', 'admin') NOT NULL DEFAULT 'comum'
);

CREATE TABLE registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    data DATE NOT NULL,
    hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('agendado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado',
    CONSTRAINT uq_registros_usuario_data UNIQUE (usuario_id, data),
    CONSTRAINT fk_registros_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);
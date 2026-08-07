ALTER TABLE questor_exportacoes
    ADD COLUMN arquivo_backup VARCHAR(255) NULL AFTER exportado_por;

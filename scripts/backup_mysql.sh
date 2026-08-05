#!/bin/bash
set -euo pipefail

# Carrega variáveis do .env se existir na raiz do projeto
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ENV_PATH="$SCRIPT_DIR/../.env"
if [[ -f "$ENV_PATH" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$ENV_PATH"
  set +a
fi

PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

CONTAINER_NAME="${CONTAINER_NAME:?CONTAINER_NAME não definido no .env}"
DB_USER="${DB_USERNAME:?DB_USERNAME não definido no .env}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD não definido no .env}"
DB_DATABASE="${DB_DATABASE:?DB_DATABASE não definido no .env}"
DIR_BACKUP="${BACKUP_DIR:?BACKUP_DIR não definido no .env}"

mkdir -p "$DIR_BACKUP"

DATE="$(date +%Y-%m-%d_%H-%M-%S)"
FILE_PATH="$DIR_BACKUP/backup_${DB_DATABASE}_${DATE}.sql"

# Backup diario
/usr/bin/docker exec -e MYSQL_PWD="$DB_PASSWORD" "$CONTAINER_NAME" \
  /usr/bin/mysqldump --no-tablespaces -u"$DB_USER" "$DB_DATABASE" > "$FILE_PATH"

# Se hoje for o ultimo dia do mes, cria backup especial de fim de mes
if [[ "$(date -d tomorrow +%d)" == "01" ]]; then
  MESES=(JANEIRO FEVEREIRO MARCO ABRIL MAIO JUNHO JULHO AGOSTO SETEMBRO OUTUBRO NOVEMBRO DEZEMBRO)
  MES_NOME="${MESES[$((10#$(date +%m) - 1))]}"
  FINAL_MES_FILE="$DIR_BACKUP/backup_${DB_DATABASE}_FIM_${MES_NOME}_$(date +%Y).sql"
  cp "$FILE_PATH" "$FINAL_MES_FILE"
fi

# Mantem exatamente os 7 backups diarios mais recentes (nao considera FIM_<MES>)
mapfile -t BACKUPS_DIARIOS < <(
  find "$DIR_BACKUP" -maxdepth 1 -type f -name "backup_${DB_DATABASE}_*.sql" \
    ! -name "*_FIM_*" -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-
)
if (( ${#BACKUPS_DIARIOS[@]} > 7 )); then
  for backup_antigo in "${BACKUPS_DIARIOS[@]:7}"; do
    rm -- "$backup_antigo"
  done
fi

# Copia backups questor gerados pelo sistema para o diretório externo
QUESTOR_SRC="$SCRIPT_DIR/../storage/backups/questor"
QUESTOR_DST="$DIR_BACKUP/questor"
if [[ -d "$QUESTOR_SRC" ]]; then
  mkdir -p "$QUESTOR_DST"
  find "$QUESTOR_SRC" -maxdepth 1 -type f -name "questor_*.sql" | while read -r f; do
    dest="$QUESTOR_DST/$(basename "$f")"
    [[ ! -f "$dest" ]] && cp "$f" "$dest"
  done
fi

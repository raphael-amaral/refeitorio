#!/bin/bash
set -euo pipefail
umask 077

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

DB_HOST="${BACKUP_DB_HOST:-127.0.0.1}"
DB_PORT="${BACKUP_DB_PORT:-3103}"
DB_USER="${DB_USERNAME:?DB_USERNAME não definido no .env}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD não definido no .env}"
DB_DATABASE="${DB_DATABASE:?DB_DATABASE não definido no .env}"
DIR_BACKUP="${BACKUP_DIR:?BACKUP_DIR não definido no .env}"
DIR_DIARIOS="$DIR_BACKUP/diarios"
DIR_FIM_MES="$DIR_BACKUP/fim_mes"

mkdir -p "$DIR_DIARIOS" "$DIR_FIM_MES"

DATE="$(date +%Y-%m-%d_%H-%M-%S)"
FILE_PATH="$DIR_DIARIOS/backup_${DB_DATABASE}_${DATE}.sql"
TEMP_FILE="${FILE_PATH}.tmp"

cleanup() {
  rm -f -- "$TEMP_FILE"
}
trap cleanup EXIT

# Publica o backup diario apenas depois que o mysqldump terminar com conteúdo.
MYSQL_PWD="$DB_PASSWORD" /usr/bin/mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USER" \
  --single-transaction \
  --quick \
  --no-tablespaces \
  "$DB_DATABASE" > "$TEMP_FILE"

if [[ ! -s "$TEMP_FILE" ]]; then
  echo "Erro: mysqldump gerou um arquivo vazio." >&2
  exit 1
fi

mv -- "$TEMP_FILE" "$FILE_PATH"
trap - EXIT

# Se hoje for o ultimo dia do mes, cria backup especial de fim de mes
if [[ "$(date -d tomorrow +%d)" == "01" ]]; then
  MESES=(JANEIRO FEVEREIRO MARCO ABRIL MAIO JUNHO JULHO AGOSTO SETEMBRO OUTUBRO NOVEMBRO DEZEMBRO)
  MES_NOME="${MESES[$((10#$(date +%m) - 1))]}"
  FINAL_MES_FILE="$DIR_FIM_MES/backup_${DB_DATABASE}_FIM_${MES_NOME}_$(date +%Y).sql"
  cp "$FILE_PATH" "$FINAL_MES_FILE"
fi

# Mantem exatamente os 7 backups diarios mais recentes.
mapfile -t BACKUPS_DIARIOS < <(
  find "$DIR_DIARIOS" -maxdepth 1 -type f -name "backup_${DB_DATABASE}_*.sql" \
    -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-
)
if (( ${#BACKUPS_DIARIOS[@]} > 7 )); then
  for backup_antigo in "${BACKUPS_DIARIOS[@]:7}"; do
    rm -- "$backup_antigo"
  done
fi

#!/usr/bin/env bash

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

# Script unificado de restauracao de backups MySQL.
# Por padrao, cria backup de seguranca antes de restaurar.
#
# Uso:
#   ./scripts/restaurar_backup.sh
#   ./scripts/restaurar_backup.sh latest
#   ./scripts/restaurar_backup.sh /caminho/arquivo.sql
#   ./scripts/restaurar_backup.sh --list
#   ./scripts/restaurar_backup.sh --dry-run latest
#   ./scripts/restaurar_backup.sh --no-pre-backup latest

CONTAINER_NAME="${CONTAINER_NAME:?CONTAINER_NAME não definido no .env}"
DB_USER="${DB_USERNAME:?DB_USERNAME não definido no .env}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD não definido no .env}"
DB_DATABASE="${DB_DATABASE:?DB_DATABASE não definido no .env}"
BACKUP_DIR="${BACKUP_DIR:?BACKUP_DIR não definido no .env}"

DRY_RUN=0
USE_PRE_BACKUP=1
LIST_ONLY=0
TARGET_ARG=""

for arg in "$@"; do
  case "$arg" in
    --dry-run)
      DRY_RUN=1
      ;;
    --no-pre-backup)
      USE_PRE_BACKUP=0
      ;;
    --list)
      LIST_ONLY=1
      ;;
    *)
      if [[ -z "$TARGET_ARG" ]]; then
        TARGET_ARG="$arg"
      else
        echo "Erro: argumento extra nao reconhecido: $arg"
        exit 1
      fi
      ;;
  esac
done

if ! command -v docker >/dev/null 2>&1; then
  echo "Erro: docker nao encontrado no PATH."
  exit 1
fi

if [[ ! -d "$BACKUP_DIR" ]]; then
  echo "Erro: diretorio de backups nao encontrado: $BACKUP_DIR"
  exit 1
fi

# Durante a transição, também reconhece os backups antigos que ainda estiverem na raiz.
mapfile -t BACKUP_FILES < <(
  find "$BACKUP_DIR" "$BACKUP_DIR/diarios" "$BACKUP_DIR/fim_mes" \
    -maxdepth 1 -type f -name "*${DB_DATABASE}*.sql" 2>/dev/null | sort -u
)

if [[ ${#BACKUP_FILES[@]} -eq 0 ]]; then
  echo "Nenhum arquivo .sql de '$DB_DATABASE' encontrado em: $BACKUP_DIR"
  exit 1
fi

list_backups() {
  echo "Iniciando restauracao do banco de dados do refeitorio"
  echo "Backups disponiveis:"
  local i=1
  for file in "${BACKUP_FILES[@]}"; do
    printf "  %2d) %s\n" "$i" "$(basename "$file")"
    ((i++))
  done
}

choose_file_interactive() {
  list_backups >&2
  echo >&2
  read -r -p "Digite o numero do backup para restaurar ou pressione Enter para cancelar: " choice

  if [[ ! "$choice" =~ ^[0-9]+$ ]]; then
    echo "Opcao invalida."
    exit 1
  fi

  if (( choice < 1 || choice > ${#BACKUP_FILES[@]} )); then
    echo "Opcao fora do intervalo."
    exit 1
  fi

  echo "${BACKUP_FILES[$((choice - 1))]}"
}

resolve_target_file() {
  local arg="${1:-}"

  case "$arg" in
    "")
      choose_file_interactive
      ;;
    latest)
      echo "${BACKUP_FILES[-1]}"
      ;;
    *)
      if [[ ! -f "$arg" ]]; then
        echo "Erro: arquivo informado nao existe: $arg"
        exit 1
      fi
      echo "$arg"
      ;;
  esac
}

if [[ $LIST_ONLY -eq 1 ]]; then
  list_backups
  exit 0
fi

TARGET_FILE="$(resolve_target_file "$TARGET_ARG")"
DATE_NOW="$(date +%Y-%m-%d_%H-%M-%S)"
mkdir -p "$BACKUP_DIR/pre_restore"
PRE_BACKUP_FILE="$BACKUP_DIR/pre_restore/pre_restore_${DB_DATABASE}_${DATE_NOW}.sql"

echo
echo "Arquivo selecionado para restaurar: $TARGET_FILE"
if [[ $USE_PRE_BACKUP -eq 1 ]]; then
  echo "Backup de seguranca sera salvo em: $PRE_BACKUP_FILE"
else
  echo "Backup de seguranca: desativado (--no-pre-backup)"
fi
echo "Container: $CONTAINER_NAME"
echo "Banco: $DB_DATABASE"

if [[ $DRY_RUN -eq 1 ]]; then
  echo "Modo dry-run: nenhuma alteracao sera executada."
  exit 0
fi

if [[ $USE_PRE_BACKUP -eq 1 ]]; then
  read -r -p "Confirmar backup + restauracao? [y/N]: " confirm
else
  read -r -p "Confirmar restauracao sem backup previo? [y/N]: " confirm
fi

if [[ "${confirm,,}" != "y" ]]; then
  echo "Operacao cancelada."
  exit 0
fi

if [[ $USE_PRE_BACKUP -eq 1 ]]; then
  echo "1/2 Criando backup de seguranca do estado atual..."
  docker exec -e MYSQL_PWD="$DB_PASSWORD" "$CONTAINER_NAME" \
    mysqldump --no-tablespaces -u"$DB_USER" "$DB_DATABASE" > "$PRE_BACKUP_FILE"

  echo "2/2 Restaurando backup selecionado..."
  docker exec -i -e MYSQL_PWD="$DB_PASSWORD" "$CONTAINER_NAME" \
    mysql -u"$DB_USER" "$DB_DATABASE" < "$TARGET_FILE"

  echo "Restauracao concluida com sucesso."
  echo "Se precisar desfazer, restaure: $PRE_BACKUP_FILE"
else
  echo "Restaurando backup selecionado..."
  docker exec -i -e MYSQL_PWD="$DB_PASSWORD" "$CONTAINER_NAME" \
    mysql -u"$DB_USER" "$DB_DATABASE" < "$TARGET_FILE"
  echo "Restauracao concluida com sucesso."
fi

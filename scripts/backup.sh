#!/usr/bin/env bash
set -euo pipefail

# 定期バックアップ用スクリプト。
# - database.sqlite -> database_YYYYMMDD.sqlite
# - assets/uploads/ -> uploads_YYYYMMDD.zip
# 保存先はデフォルトでプロジェクトルートの1階層上（公開ディレクトリ外を想定）。

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DB_PATH="${DB_PATH:-${PROJECT_ROOT}/database.sqlite}"
UPLOADS_DIR="${UPLOADS_DIR:-${PROJECT_ROOT}/assets/uploads}"
BACKUP_DIR="${BACKUP_DIR:-$(cd "${PROJECT_ROOT}/.." && pwd)/agrimore-backups}"
DATE_SUFFIX="${BACKUP_DATE:-$(date +%Y%m%d)}"
DB_ONLY=0
UPLOADS_ONLY=0

usage() {
  cat <<USAGE
Usage: $(basename "$0") [--db-only|--uploads-only]

Environment variables:
  DB_PATH       SQLite DB path (default: PROJECT_ROOT/database.sqlite)
  UPLOADS_DIR   uploads directory (default: PROJECT_ROOT/assets/uploads)
  BACKUP_DIR    backup destination (default: ../agrimore-backups)
  BACKUP_DATE   date suffix (default: current date as YYYYMMDD)
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --db-only)
      DB_ONLY=1
      ;;
    --uploads-only)
      UPLOADS_ONLY=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
  shift
done

if [[ "${DB_ONLY}" -eq 1 && "${UPLOADS_ONLY}" -eq 1 ]]; then
  echo "--db-only and --uploads-only cannot be used together." >&2
  exit 2
fi

mkdir -p "${BACKUP_DIR}"

backup_database() {
  if [[ ! -f "${DB_PATH}" ]]; then
    echo "Database not found: ${DB_PATH}" >&2
    exit 1
  fi

  local dest="${BACKUP_DIR}/database_${DATE_SUFFIX}.sqlite"
  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "${DB_PATH}" ".backup '${dest}'"
  else
    cp -p "${DB_PATH}" "${dest}"
  fi
  echo "Created DB backup: ${dest}"
}

backup_uploads() {
  if [[ ! -d "${UPLOADS_DIR}" ]]; then
    echo "Uploads directory not found: ${UPLOADS_DIR}" >&2
    exit 1
  fi

  local dest="${BACKUP_DIR}/uploads_${DATE_SUFFIX}.zip"
  if ! command -v zip >/dev/null 2>&1; then
    echo "zip command is required for uploads backup." >&2
    exit 1
  fi

  local uploads_real
  local default_uploads_real
  uploads_real="$(cd "${UPLOADS_DIR}" && pwd)"
  default_uploads_real="$(cd "${PROJECT_ROOT}/assets/uploads" 2>/dev/null && pwd || true)"

  if [[ "${uploads_real}" == "${default_uploads_real}" ]]; then
    (
      cd "${PROJECT_ROOT}"
      zip -qr "${dest}" "assets/uploads"
    )
  else
    local uploads_parent
    local uploads_basename
    uploads_parent="$(cd "$(dirname "${UPLOADS_DIR}")" && pwd)"
    uploads_basename="$(basename "${UPLOADS_DIR}")"
    (
      cd "${uploads_parent}"
      zip -qr "${dest}" "${uploads_basename}"
    )
  fi
  echo "Created uploads backup: ${dest}"
}

if [[ "${UPLOADS_ONLY}" -eq 0 ]]; then
  backup_database
fi

if [[ "${DB_ONLY}" -eq 0 ]]; then
  backup_uploads
fi

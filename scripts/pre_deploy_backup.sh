#!/usr/bin/env bash
set -euo pipefail

# 本番反映前に必ず実行するDBバックアップ。
# 保存先や日付は scripts/backup.sh と同じ環境変数で上書きできます。
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "${SCRIPT_DIR}/backup.sh" --db-only

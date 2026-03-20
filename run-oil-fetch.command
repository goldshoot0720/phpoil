#!/bin/zsh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage"
LOG_FILE="$LOG_DIR/scheduled-fetch.log"

mkdir -p "$LOG_DIR"

if command -v php >/dev/null 2>&1; then
  PHP_BIN="$(command -v php)"
elif [ -x "/opt/homebrew/bin/php" ]; then
  PHP_BIN="/opt/homebrew/bin/php"
elif [ -x "/usr/local/bin/php" ]; then
  PHP_BIN="/usr/local/bin/php"
else
  echo "[ERROR] PHP executable not found." | tee -a "$LOG_FILE"
  exit 1
fi

cd "$SCRIPT_DIR"
"$PHP_BIN" cron/fetch_daily.php >> "$LOG_FILE" 2>&1

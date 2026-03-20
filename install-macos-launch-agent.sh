#!/bin/zsh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RUNNER="$SCRIPT_DIR/run-oil-fetch.command"
LAUNCH_AGENTS_DIR="$HOME/Library/LaunchAgents"
PLIST_ID="com.goldshoot0720.phpoil.fetch"
PLIST_PATH="$LAUNCH_AGENTS_DIR/$PLIST_ID.plist"
START_HOUR="${1:-13}"
START_MINUTE="${2:-0}"

mkdir -p "$LAUNCH_AGENTS_DIR"

if [ ! -x "$RUNNER" ]; then
  chmod +x "$RUNNER"
fi

cat > "$PLIST_PATH" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$PLIST_ID</string>
    <key>ProgramArguments</key>
    <array>
        <string>/bin/zsh</string>
        <string>$RUNNER</string>
    </array>
    <key>WorkingDirectory</key>
    <string>$SCRIPT_DIR</string>
    <key>RunAtLoad</key>
    <true/>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>$START_HOUR</integer>
        <key>Minute</key>
        <integer>$START_MINUTE</integer>
    </dict>
    <key>StandardOutPath</key>
    <string>$SCRIPT_DIR/storage/launchd.stdout.log</string>
    <key>StandardErrorPath</key>
    <string>$SCRIPT_DIR/storage/launchd.stderr.log</string>
</dict>
</plist>
EOF

launchctl unload "$PLIST_PATH" >/dev/null 2>&1 || true
launchctl load "$PLIST_PATH"
launchctl start "$PLIST_ID" >/dev/null 2>&1 || true

echo "LaunchAgent installed: $PLIST_PATH"
echo "Schedule: daily at $(printf '%02d:%02d' "$START_HOUR" "$START_MINUTE")"
echo "Run on login: enabled"

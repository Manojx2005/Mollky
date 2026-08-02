#!/usr/bin/env bash
# Molkky app launcher for macOS / Linux.
# The bundled PHP in ./php is Windows-only, so this uses the system PHP.
# Requires PHP with pdo_sqlite (bundled in most PHP builds).
set -e

cd "$(dirname "$0")"

if ! command -v php >/dev/null 2>&1; then
    echo "[!] PHP was not found. Please install PHP (with pdo_sqlite) first."
    echo "    macOS:  brew install php"
    echo "    Ubuntu: sudo apt install php-cli php-sqlite3"
    exit 1
fi

PORT="${PORT:-8000}"
URL="http://localhost:${PORT}/home.php"

echo "Starting Molkky app on ${URL}"
echo "Press Ctrl+C to stop the server."

# Open the browser on the home page (best-effort), then start the server.
( sleep 1; (command -v open >/dev/null && open "$URL") || (command -v xdg-open >/dev/null && xdg-open "$URL") ) >/dev/null 2>&1 &

exec php -S "127.0.0.1:${PORT}" -t "$(pwd)"

#!/usr/bin/env bash
# QuizOnline Launcher for macOS and Linux

PORT=8000
HOST="localhost"
URL="http://${HOST}:${PORT}/frontend/"

echo ""
echo "========================================"
echo "         QuizOnline Starting...         "
echo "========================================"
echo ""

# Check for PHP
if ! command -v php >/dev/null 2>&1; then
    echo "[ERROR] PHP is not installed or not in your PATH."
    echo "On macOS, you can install it via Homebrew: brew install php"
    echo "Or start Apache and MySQL through XAMPP / MAMP."
    exit 1
fi

echo "[1/2] Starting PHP development server on http://${HOST}:${PORT}..."

# Start PHP built-in server in background
php -S "${HOST}:${PORT}" >/dev/null 2>&1 &
SERVER_PID=$!

# Ensure server stops on exit
trap 'kill $SERVER_PID 2>/dev/null; echo ""; echo "QuizOnline server stopped."; exit 0' INT TERM EXIT

# Wait a second for server to initialize
sleep 1

echo "[2/2] Opening QuizOnline in your default browser..."
if command -v open >/dev/null 2>&1; then
    open "$URL"
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL"
fi

echo ""
echo "QuizOnline is running at: $URL"
echo "Press Ctrl+C to stop the server."
echo ""

# Wait on background PHP server process
wait $SERVER_PID

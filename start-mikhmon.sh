#!/usr/bin/env bash
PORT="${1:-8082}"
echo "Starting Mikhmon Desktop (NODERA) on http://127.0.0.1:$PORT ..."
php -S 127.0.0.1:$PORT

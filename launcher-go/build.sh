#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT_DIR="$(dirname "$SCRIPT_DIR")"
GOBIN="/home/rimuru/.local/go/bin/go"
if ! command -v "$GOBIN" &>/dev/null; then
    GOBIN="go"
fi

WINRES_BIN="/home/rimuru/go/bin/go-winres"
if ! command -v "$WINRES_BIN" &>/dev/null; then
    WINRES_BIN="go-winres"
fi

echo "==> Generating Windows icon & manifest resources..."
cd "$SCRIPT_DIR"
"$WINRES_BIN" make

echo "==> Compiling Mikhmon.exe (PE32+ GUI x86-64)..."
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 "$GOBIN" build \
    -ldflags="-H=windowsgui -s -w" \
    -o "$PARENT_DIR/Mikhmon.exe" \
    .

cp -f "$PARENT_DIR/Mikhmon.exe" "$PARENT_DIR/MikhmonServer.exe"

echo "==> Build successful:"
ls -lh "$PARENT_DIR/Mikhmon.exe" "$PARENT_DIR/MikhmonServer.exe"

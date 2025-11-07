#!/usr/bin/env bash
set -euo pipefail

# safe_read.sh <path>
TARGET="${1:-}"
SAFE_ROOT="/home"

if [ -z "$TARGET" ]; then
  echo "ERR|missing"
  exit 2
fi

# canonicaliza
CANON=$(realpath -- "$TARGET" 2>/dev/null) || { echo "ERR|notfound"; exit 3; }

# garante que CANON está dentro de SAFE_ROOT
case "$CANON" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *)
    echo "ERR|forbidden"
    exit 4
    ;;
esac

# garante que é ficheiro regular
if [ ! -f "$CANON" ]; then
  echo "ERR|nofile"
  exit 5
fi

# mime, size, basename
MIME=$(file --brief --mime-type -- "$CANON" 2>/dev/null || echo "application/octet-stream")
SIZE=$(stat -c '%s' -- "$CANON" 2>/dev/null || echo "0")
BNAME=$(basename -- "$CANON")

# cabeçalho simples para o PHP interpretar
echo "OK|$MIME|$SIZE|$BNAME"

# conteúdo em base64 (sem quebras)
base64 --wrap=0 -- "$CANON"
exit 0

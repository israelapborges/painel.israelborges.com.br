#!/usr/bin/env bash
set -euo pipefail

# safe_delete.sh <target> [--recursive]
TARGET="${1:-}"
RECURSIVE_FLAG="${2:-}"
SAFE_ROOT="/home"

if [ -z "$TARGET" ]; then
  echo "ERR|missing"
  exit 2
fi

# canonicaliza (realpath) — se falhar, erro
CANON=$(realpath -- "$TARGET" 2>/dev/null) || { echo "ERR|notfound"; exit 3; }

# garante que CANON está dentro de SAFE_ROOT
case "$CANON" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *)
    echo "ERR|forbidden"
    exit 4
    ;;
esac

# Se for ficheiro regular -> rm -f
if [ -f "$CANON" ]; then
  rm -f -- "$CANON" || { echo "ERR|rm_failed"; exit 5; }
  echo "OK|$CANON"
  exit 0
fi

# Se for symlink -> unlink
if [ -L "$CANON" ]; then
  unlink -- "$CANON" || { echo "ERR|unlink_failed"; exit 6; }
  echo "OK|$CANON"
  exit 0
fi

# Se for diretório:
if [ -d "$CANON" ]; then
  if [ "$RECURSIVE_FLAG" = "--recursive" ] || [ "$RECURSIVE_FLAG" = "-r" ]; then
    rm -rf -- "$CANON" || { echo "ERR|rmrf_failed"; exit 7; }
    echo "OK|$CANON"
    exit 0
  else
    # tenta rmdir (falhará se não estiver vazio)
    rmdir -- "$CANON" 2>/dev/null && { echo "OK|$CANON"; exit 0; } \
      || { echo "ERR|dir_not_empty"; exit 8; }
  fi
fi

# Se chegou aqui, tipo desconhecido
echo "ERR|unknown_type"
exit 9

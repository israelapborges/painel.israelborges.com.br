#!/usr/bin/env bash
set -euo pipefail

# safe_list.sh <path>
TARGET="${1:-}"
SAFE_ROOT="/home"

# mínimo de validação
if [ -z "$TARGET" ]; then
  echo "ERR|notfound"
  exit 2
fi

# canonicaliza
CANON=$(realpath -- "$TARGET" 2>/dev/null) || { echo "ERR|notfound"; exit 2; }

# garante que CANON está dentro de SAFE_ROOT
case "$CANON" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *)
    echo "ERR|forbidden"
    exit 3
    ;;
esac

# imprime: tipo|nome_relativo|size|mtime_epoch|owner|perms_octal
# %y = type (d,f,l,...), %P = nome relativo (sem o caminho), %s = size, %T@ = mtime epoch, %u = owner, %m = perms (octal)
# usa find para linha por item (nível 1)
find "$CANON" -maxdepth 1 -mindepth 1 -printf '%y|%P|%s|%T@|%u|%m\n'
exit 0

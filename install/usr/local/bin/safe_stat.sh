#!/usr/bin/env bash
set -euo pipefail

# safe_stat.sh <path>
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

# recolhe propriedades via stat/getent
UID=$(stat -c '%u' -- "$CANON" 2>/dev/null || echo '')
GID=$(stat -c '%g' -- "$CANON" 2>/dev/null || echo '')
MODE=$(stat -c '%a' -- "$CANON" 2>/dev/null || echo '')
TYPE=""
if [ -d "$CANON" ]; then TYPE="d"; elif [ -f "$CANON" ]; then TYPE="f"; elif [ -L "$CANON" ]; then TYPE="l"; else TYPE="o"; fi

UNAME=""
if [ -n "$UID" ]; then
  UNAME=$(getent passwd "$UID" | cut -d: -f1) || true
fi
GNAME=""
if [ -n "$GID" ]; then
  GNAME=$(getent group "$GID" | cut -d: -f1) || true
fi

# permissões human-readable (ls -ld style) e octal já temos MODE
PERM_HUMAN=$(ls -ld -- "$CANON" 2>/dev/null | awk '{print $1}' || echo '')

# tenta obter ACL em base64 se getfacl existir
ACL_BASE64=""
if command -v getfacl >/dev/null 2>&1; then
  # limitar saída a 5000 linhas/bytes para evitar explosão
  ACL_RAW=$(getfacl -p -- "$CANON" 2>/dev/null | sed -n '1,200p' || true)
  if [ -n "$ACL_RAW" ]; then
    # encode em base64 sem quebras
    ACL_BASE64=$(printf '%s' "$ACL_RAW" | base64 --wrap=0 2>/dev/null || true)
  fi
fi

# Saída: primeira linha com metas, segunda linha opcional com ACL base64
# Formato primeira linha: OK|UID|GID|UNAME|GNAME|MODE_OCTAL|PERM_HUMAN|TYPE
echo "OK|${UID}|${GID}|${UNAME}|${GNAME}|${MODE}|${PERM_HUMAN}|${TYPE}"

if [ -n "$ACL_BASE64" ]; then
  echo "$ACL_BASE64"
fi

exit 0

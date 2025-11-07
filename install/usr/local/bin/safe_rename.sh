#!/bin/bash
set -euo pipefail

# safe_rename.sh <src_fullpath> <dst_fullpath>
SRC="${1:-}"
DST="${2:-}"
SAFE_ROOT="/home"

if [ -z "$SRC" ] || [ -z "$DST" ]; then
  echo "ERR|missing"
  exit 2
fi

# canonicalize source (deve existir)
CAN_SRC=$(realpath -- "$SRC" 2>/dev/null) || { echo "ERR|src_notfound"; exit 3; }

# canonicalize parent do destino (pode não existir)
DST_PARENT=$(dirname -- "$DST")
CAN_DST_PARENT=$(realpath -- "$DST_PARENT" 2>/dev/null) || { echo "ERR|dst_parent_notfound"; exit 4; }

# garantir jaula
case "$CAN_SRC" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden_src"; exit 5;;
esac
case "$CAN_DST_PARENT" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 6;;
esac

# montar destino final
FINAL_DST="$CAN_DST_PARENT/$(basename -- "$DST")"

# se destino existe, recusar (por segurança)
if [ -e "$FINAL_DST" ]; then
  echo "ERR|exists"
  exit 7
fi

# tentar mv
if mv -- "$CAN_SRC" "$FINAL_DST"; then
  # ajustar owner para combinar com parent
  OWNER_UID=$(stat -c '%u' -- "$CAN_DST_PARENT" 2>/dev/null || echo '')
  OWNER_GID=$(stat -c '%g' -- "$CAN_DST_PARENT" 2>/dev/null || echo '')
  chmod 0644 -- "$FINAL_DST" || true
  if [ -n "$OWNER_UID" ] && [ -n "$OWNER_GID" ]; then
    chown "$OWNER_UID:$OWNER_GID" -- "$FINAL_DST" || true
  fi
  echo "OK|$FINAL_DST"
  exit 0
else
  echo "ERR|mv_failed"
  exit 8
fi


#!/bin/bash
set -euo pipefail

# safe_upload.sh <tmp_src> <dst_fullpath> [--overwrite]
TMP_SRC="${1:-}"
DST="${2:-}"
FLAG="${3:-}"
SAFE_ROOT="/home"

if [ -z "$TMP_SRC" ] || [ -z "$DST" ]; then
  echo "ERR|missing"
  exit 2
fi

# verifica tmp existe e é regular
if [ ! -f "$TMP_SRC" ]; then
  echo "ERR|tmp_notfound"
  exit 3
fi

# permite apenas tmp em /tmp ou /var/tmp
case "$TMP_SRC" in
  /tmp/*|/var/tmp/*) ;;
  *)
    echo "ERR|tmp_forbidden"
    exit 4
    ;;
esac

# canonicaliza pai do destino (destino pode não existir)
DST_PARENT=$(dirname -- "$DST")
CANON_DST_PARENT=$(realpath -- "$DST_PARENT" 2>/dev/null) || { echo "ERR|dst_parent_notfound"; exit 5; }

# garante que o pai está dentro de SAFE_ROOT
case "$CANON_DST_PARENT" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *)
    echo "ERR|forbidden_dst"
    exit 6
    ;;
esac

# cria parent se não existir (deveria existir mas garantimos)
mkdir -p -- "$CANON_DST_PARENT" || { echo "ERR|mkdir_failed"; exit 7; }

# monta canonical DST final (usa basename do DST)
BNAME=$(basename -- "$DST")
CANON_DST="$CANON_DST_PARENT/$BNAME"

# se destino existe e não pedir overwrite -> erro
if [ -e "$CANON_DST" ] && [ "$FLAG" != "--overwrite" ] && [ "$FLAG" != "-f" ]; then
  echo "ERR|exists"
  exit 8
fi

# se destino existe e overwrite pedido -> remove
if [ -e "$CANON_DST" ] && { [ "$FLAG" = "--overwrite" ] || [ "$FLAG" = "-f" ]; }; then
  rm -rf -- "$CANON_DST" || { echo "ERR|rm_dst_failed"; exit 9; }
fi

# mover tmp para destino (preservando atomicidade se possível)
if mv -- "$TMP_SRC" "$CANON_DST"; then
  :
else
  # fallback: copy then unlink
  if cp -- "$TMP_SRC" "$CANON_DST"; then
    rm -f -- "$TMP_SRC"
  else
    echo "ERR|move_failed"
    exit 10
  fi
fi

# ajusta owner/perms para combinar com parent (se possível)
OWNER_UID=$(stat -c '%u' -- "$CANON_DST_PARENT" 2>/dev/null || echo '')
OWNER_GID=$(stat -c '%g' -- "$CANON_DST_PARENT" 2>/dev/null || echo '')

chmod 0644 -- "$CANON_DST" || true
if [ -n "$OWNER_UID" ] && [ -n "$OWNER_GID" ]; then
  chown "$OWNER_UID:$OWNER_GID" -- "$CANON_DST" || true
fi

echo "OK|$CANON_DST"
exit 0

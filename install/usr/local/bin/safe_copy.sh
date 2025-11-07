#!/bin/bash
set -euo pipefail

# safe_copy.sh - copia arquivos/dirs dentro de /home com checagens mínimas
# saída: OK|<dest_path>  ou  ERR|<code>

if [ -z "${1:-}" ] ; then echo "ERR|missing"; exit 2; fi
if [ -z "${2:-}" ] ; then echo "ERR|dst_missing"; exit 2; fi

SRC="$1"
DST="$2"
MODE_RECURSIVE="${3:-}"

# canonicalizar
CAN_SRC="$(/bin/realpath -- "$SRC" 2>/dev/null)" || { echo "ERR|notfound"; exit 3; }
CAN_DST="$(/bin/realpath -m -- "$DST" 2>/dev/null || true)"

SAFE_ROOT="/home"

case "$CAN_SRC" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac

# Se dst não existe, normalizamos o pathname (realpath -m aceita não-existentes)
if [ -z "$CAN_DST" ]; then
  CAN_DST="$DST"
  CAN_DST="$(/bin/realpath -m -- "$CAN_DST")" || CAN_DST="$DST"
fi

case "$CAN_DST" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac

# Se dst é diretório existente -> nome final = dst/basename(src)
if [ -d "$CAN_DST" ]; then
  FINAL_DST="${CAN_DST%/}/$(basename "$CAN_SRC")"
else
  # se final terminava com / -> criar diretório e usar basename
  if [[ "$DST" =~ /$ ]]; then
    mkdir -p -- "$CAN_DST" || { echo "ERR|mkdir_failed"; exit 5; }
    FINAL_DST="${CAN_DST%/}/$(basename "$CAN_SRC")"
  else
    # assume destino final é um path de arquivo; garante parent
    PARENT="$(dirname "$CAN_DST")"
    mkdir -p -- "$PARENT" || { echo "ERR|mkdir_failed"; exit 5; }
    FINAL_DST="$CAN_DST"
  fi
fi

# Se origem é diretório e não pediu recursivo => erro
if [ -d "$CAN_SRC" ] && [ "${MODE_RECURSIVE:-}" != "--recursive" ] ; then
  echo "ERR|src_is_dir"; exit 6
fi

# Fazer a cópia:
if [ -d "$CAN_SRC" ]; then
  # diretório -> usar tar/untar ou rsync -a; aqui rsync simples
  command -v rsync >/dev/null 2>&1 || { echo "ERR|rsync_missing"; exit 7; }
  rsync -a -- "$CAN_SRC"/ "$FINAL_DST"/ || { echo "ERR|copy_failed"; exit 8; }
  # quando usamos rsync para diretório, a semântica acima cria/atualiza FINAL_DST/
else
  # arquivo -> cp -a
  /bin/cp -a -- "$CAN_SRC" "$FINAL_DST" || { echo "ERR|copy_failed"; exit 8; }
fi

# preservar dono do parent (melhor esforço): ajustar dono para owner do parent
PARENT_OWNER_UID=$(stat -c "%u" "$(dirname "$FINAL_DST")" 2>/dev/null || true)
PARENT_OWNER_GID=$(stat -c "%g" "$(dirname "$FINAL_DST")" 2>/dev/null || true)
if [ -n "$PARENT_OWNER_UID" ]; then
  /usr/bin/chown "${PARENT_OWNER_UID}:${PARENT_OWNER_GID}" "$FINAL_DST" 2>/dev/null || true
fi

echo "OK|$FINAL_DST"
exit 0


#!/bin/bash
set -euo pipefail
# safe_extract.sh <archive_path> <dest_dir> <format>
# format: zip | tar.gz | tar.bz2 | tar.xz | tar
# Saída:
#   OK|/caminho/para/dest
# ou
#   ERR|codigo
#
# Comportamento específico:
# - Se dest existir e for diretório -> extrai para esse diretório.
# - Se dest NÃO existir e o basename terminar em "_extracted" -> extrai no diretório onde está o arquivo (dirname do archive).
# - Caso contrário, cria o dest e extrai nele (comportamento tradicional).
#
# Códigos de erro: missing, notfound, forbidden, no_tool, extract_failed, tmp_failed

if [ -z "${1:-}" ]; then echo "ERR|missing"; exit 2; fi
ARCH="${1}"
DEST="${2:-}"
FMT="${3:-auto}"

# canonicalize archive
CAN_ARCH=$(realpath -- "${ARCH}" 2>/dev/null) || { echo "ERR|notfound"; exit 3; }
SAFE_ROOT="/home"
case "$CAN_ARCH" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac

# destination provided?
if [ -z "$DEST" ]; then
  echo "ERR|missing"
  exit 2
fi

# normalize provided dest (may not exist yet)
CAN_DEST=$(realpath -m -- "${DEST}" 2>/dev/null || true)

# Quick safety: ensure CAN_DEST under SAFE_ROOT (use -m produced path)
case "$CAN_DEST" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac

# Decide TARGET where extraction will actually occur
TARGET=""
if [ -d "$CAN_DEST" ]; then
  # explicit existing directory — use it
  TARGET="$CAN_DEST"
else
  # does the basename suggest a temporary "extracted" folder? (heurística)
  base="$(basename -- "$CAN_DEST")"
  if [[ "$base" =~ _extracted$ ]]; then
    # user likely expects extraction in the same dir as archive (no extra folder)
    TARGET="$(dirname -- "$CAN_ARCH")"
  else
    # otherwise, we'll create the requested dest directory
    mkdir -p -- "$CAN_DEST" || { echo "ERR|tmp_failed"; exit 6; }
    TARGET="$CAN_DEST"
  fi
fi

# determine format if auto
if [ -z "${FMT}" ] || [ "$FMT" = "auto" ]; then
  case "$CAN_ARCH" in
    *.tar.gz|*.tgz) FMT="tar.gz" ;;
    *.tar.bz2|*.tbz2) FMT="tar.bz2" ;;
    *.tar.xz|*.txz) FMT="tar.xz" ;;
    *.tar) FMT="tar" ;;
    *.zip) FMT="zip" ;;
    *) FMT="tar" ;; # fallback tentative
  esac
fi

# ensure tools exist
case "$FMT" in
  zip)
    if ! command -v unzip >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    ;;
  tar.gz|tar.bz2|tar.xz|tar)
    if ! command -v /bin/tar >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    ;;
  *)
    echo "ERR|no_tool"; exit 7
    ;;
esac

# perform extraction — overwrite existing files (unzip -o, tar default overwrites)
case "$FMT" in
  zip)
    # -o overwrite existing files without prompting; -q quiet
    /usr/bin/unzip -oq -- "$CAN_ARCH" -d "$TARGET" || { echo "ERR|extract_failed"; exit 8; }
    ;;
  tar.gz)
    # extract into target: -C <target> -xzf <archive>
    /bin/tar -C "$TARGET" -xzf "$CAN_ARCH" || { echo "ERR|extract_failed"; exit 8; }
    ;;
  tar.bz2)
    /bin/tar -C "$TARGET" -xjf "$CAN_ARCH" || { echo "ERR|extract_failed"; exit 8; }
    ;;
  tar.xz)
    /bin/tar -C "$TARGET" -xJf "$CAN_ARCH" || { echo "ERR|extract_failed"; exit 8; }
    ;;
  tar)
    /bin/tar -C "$TARGET" -xf "$CAN_ARCH" || { echo "ERR|extract_failed"; exit 8; }
    ;;
esac

# best-effort: set ownership of extracted content to owner of archive (useful when wrapper runs as root)
if [ -e "$CAN_ARCH" ]; then
  owner=$(stat -c "%u:%g" -- "$CAN_ARCH" 2>/dev/null || true)
  if [ -n "$owner" ]; then
    chown -R -- "$owner" "$TARGET" 2>/dev/null || true
  fi
fi

# final success: echo the actual target used
echo "OK|$TARGET"
exit 0


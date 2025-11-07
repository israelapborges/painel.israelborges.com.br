#!/bin/bash
set -euo pipefail
# safe_compress.sh <source_path> <dest_archive> <format>
# format: zip | tar.gz | tar.bz2 | tar.xz | tar
# Saída: OK|/canonical/archive   ou ERR|code

if [ -z "${1:-}" ]; then echo "ERR|missing"; exit 2; fi
SRC="$1"
DEST="${2:-}"
FMT="${3:-zip}"

CAN_SRC=$(realpath -- "$SRC" 2>/dev/null) || { echo "ERR|notfound"; exit 3; }
SAFE_ROOT="/home"
case "$CAN_SRC" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac

if [ -z "$DEST" ]; then echo "ERR|missing"; exit 2; fi
CAN_DEST=$(realpath -m -- "$DEST")
case "$CAN_DEST" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;; 
  *) echo "ERR|forbidden"; exit 4;;
esac

mkdir -p -- "$(dirname "$CAN_DEST")" || { echo "ERR|tmp_failed"; exit 6; }

SRC_DIR=$(dirname -- "$CAN_SRC")
SRC_BASE=$(basename -- "$CAN_SRC")

case "$FMT" in
  zip)
    if ! command -v zip >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    # cd into the parent and run zip quietly (-q) to avoid noisy stdout
    ( cd -- "$SRC_DIR" && /usr/bin/zip -r -q "$CAN_DEST" -- "$SRC_BASE" ) || { echo "ERR|compress_failed"; exit 8; }
    ;;
  tar.gz)
    if ! command -v tar >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    # CORREÇÃO: -C <dir> deve vir antes do arquivo -czf <dest>
    /bin/tar -C "$SRC_DIR" -czf "$CAN_DEST" -- "$SRC_BASE" || { echo "ERR|compress_failed"; exit 8; }
    ;;
  tar.bz2)
    if ! command -v tar >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    /bin/tar -C "$SRC_DIR" -cjf "$CAN_DEST" -- "$SRC_BASE" || { echo "ERR|compress_failed"; exit 8; }
    ;;
  tar.xz)
    if ! command -v tar >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    /bin/tar -C "$SRC_DIR" -cJf "$CAN_DEST" -- "$SRC_BASE" || { echo "ERR|compress_failed"; exit 8; }
    ;;
  tar)
    if ! command -v tar >/dev/null 2>&1; then echo "ERR|no_tool"; exit 7; fi
    /bin/tar -C "$SRC_DIR" -cf "$CAN_DEST" -- "$SRC_BASE" || { echo "ERR|compress_failed"; exit 8; }
    ;;
  *)
    echo "ERR|no_tool"; exit 7
    ;;
esac

# set owner to source owner (best effort)
if [ -e "$CAN_DEST" ]; then
  owner=$(stat -c "%u:%g" -- "$CAN_SRC" 2>/dev/null || echo "")
  if [ -n "$owner" ]; then
    chown -- "$owner" "$CAN_DEST" 2>/dev/null || true
  fi
  echo "OK|$CAN_DEST"
  exit 0
else
  echo "ERR|compress_failed"
  exit 8
fi


#!/bin/bash
set -euo pipefail

SRC="$1"
DST="$2"

SAFE_ROOT="/home"

if [ -z "$SRC" ] || [ -z "$DST" ]; then
  echo "ERR|missing"
  exit 2
fi

# canonicalize
CAN_SRC=$(realpath -- "$SRC" 2>/dev/null) || { echo "ERR|src_notfound"; exit 3; }
CAN_DST_PARENT=$(realpath -- "$(dirname -- "$DST")" 2>/dev/null) || { echo "ERR|dst_parent_notfound"; exit 4; }

# ensure inside SAFE_ROOT
case "$CAN_SRC" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden_src"; exit 5;;
esac
case "$CAN_DST_PARENT" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden_dst"; exit 6;;
esac

mkdir -p -- "$CAN_DST_PARENT" || { echo "ERR|mkdir_failed"; exit 7; }
BASENAME=$(basename -- "$DST")
FINAL_DST="$CAN_DST_PARENT/$BASENAME"

# if exists, refuse (safe)
if [ -e "$FINAL_DST" ]; then
  echo "ERR|exists"
  exit 8
fi

# if source is file -> zip -j, if dir -> tar -czf
if [ -f "$CAN_SRC" ]; then
  if /usr/bin/zip -j -q "$FINAL_DST" "$CAN_SRC"; then
    echo "OK|$FINAL_DST"
    exit 0
  else
    echo "ERR|zip_failed"
    exit 9
  fi
else
  # directory
  # use tar to avoid including parent path
  if /bin/tar -C "$CAN_SRC" -czf "$FINAL_DST" .; then
    echo "OK|$FINAL_DST"
    exit 0
  else
    echo "ERR|tar_failed"
    exit 10
  fi
fi

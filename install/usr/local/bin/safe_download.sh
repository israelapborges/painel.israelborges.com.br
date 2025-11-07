#!/bin/bash
set -euo pipefail
if [ -z "${1:-}" ]; then echo "ERR|missing"; exit 2; fi
CAN=$(realpath -- "$1" 2>/dev/null) || { echo "ERR|notfound"; exit 3; }
SAFE_ROOT="/home"
case "$CAN" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *) echo "ERR|forbidden"; exit 4;;
esac
exec /bin/cat -- "$CAN"

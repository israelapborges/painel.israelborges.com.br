#!/usr/bin/env bash
set -euo pipefail

# safe_write.sh <target> <backup_flag>
# Recebe o conteúdo em base64 via stdin.
TARGET="${1:-}"
BACKUP_FLAG="${2:-}"

SAFE_ROOT="/home"
MAX_BYTES=${SAFE_WRITE_MAX_BYTES:-5242880} # 5 MiB por padrão (pode ser sobrescrito via env)

if [ -z "$TARGET" ]; then
  echo "ERR|missing_target"
  exit 2
fi

# canonicaliza o PAI do target (o target pode não existir ainda)
PARENT=$(dirname -- "$TARGET")
CANON_PARENT=$(realpath -- "$PARENT" 2>/dev/null) || { echo "ERR|parent_notfound"; exit 3; }

# garante que o pai está dentro de SAFE_ROOT
case "$CANON_PARENT" in
  "$SAFE_ROOT" | "$SAFE_ROOT"/*) ;;
  *)
    echo "ERR|forbidden_parent"
    exit 4
    ;;
esac

# prepara tempfiles de forma segura
TMP_B64="$(mktemp --tmpdir safe_write_b64.XXXXXX)" || { echo "ERR|tmpfail"; exit 5; }
TMP_BIN="$(mktemp --tmpdir safe_write_bin.XXXXXX)" || { rm -f "$TMP_B64"; echo "ERR|tmpfail"; exit 5; }

# lê stdin (base64) para tmp
cat > "$TMP_B64" || { rm -f "$TMP_B64" "$TMP_BIN"; echo "ERR|read_stdin"; exit 6; }

# decodifica base64 para bin
if ! base64 --decode "$TMP_B64" > "$TMP_BIN" 2>/dev/null; then
  rm -f "$TMP_B64" "$TMP_BIN"
  echo "ERR|decode_failed"
  exit 7
fi

# checa tamanho
BYTES=$(stat -c '%s' -- "$TMP_BIN" 2>/dev/null || echo 0)
if [ -n "$MAX_BYTES" ] && [ "$BYTES" -gt "$MAX_BYTES" ]; then
  rm -f "$TMP_B64" "$TMP_BIN"
  echo "ERR|toolarge"
  exit 8
fi

# se existe alvo e backup_flag pedido, cria backup com timestamp
if [ -e "$TARGET" ] && { [ "$BACKUP_FLAG" = "--backup" ] || [ "$BACKUP_FLAG" = "-b" ]; }; then
  TS=$(date +%Y%m%d%H%M%S)
  BACKPATH="${TARGET}.bak.${TS}"
  if ! cp -- "$TARGET" "$BACKPATH" 2>/dev/null; then
    # backup falhou, mas não abortamos automaticamente; devolvemos erro
    rm -f "$TMP_B64" "$TMP_BIN"
    echo "ERR|backup_failed"
    exit 9
  fi
fi

# assegura diretório pai existe (criamos com permissões padrão se necessário)
mkdir -p -- "$PARENT" || { rm -f "$TMP_B64" "$TMP_BIN"; echo "ERR|mkdir_parent"; exit 10; }

# escreve de forma atômica: cria arquivo temporário no mesmo filesystem e mv
TARGET_TMP="${TARGET}.tmp.$$"
if ! mv -- "$TMP_BIN" "$TARGET_TMP" 2>/dev/null; then
  # mv pode falhar por filesystem diferente; então copy then unlink original temp
  if ! cp -- "$TMP_BIN" "$TARGET_TMP" 2>/dev/null; then
    rm -f "$TMP_B64" "$TMP_BIN" "$TARGET_TMP"
    echo "ERR|mv_failed"
    exit 11
  fi
  rm -f "$TMP_BIN"
fi

# set perms and owner based on parent dir
UID=$(stat -c '%u' -- "$CANON_PARENT" 2>/dev/null || echo '')
GID=$(stat -c '%g' -- "$CANON_PARENT" 2>/dev/null || echo '')

chmod 0644 -- "$TARGET_TMP" || true
if [ -n "$UID" ] && [ -n "$GID" ]; then
  chown "$UID:$GID" -- "$TARGET_TMP" || true
fi

# finaliza: renomeia temp para target (atômico)
if mv -f -- "$TARGET_TMP" "$TARGET"; then
  # cleanup
  rm -f "$TMP_B64"
  echo "OK|$TARGET"
  exit 0
else
  # tentativa de fallback: copy
  if cp -- "$TARGET_TMP" "$TARGET" 2>/dev/null; then
    rm -f "$TMP_B64" "$TARGET_TMP"
    echo "OK|$TARGET"
    exit 0
  fi
  rm -f "$TMP_B64" "$TARGET_TMP"
  echo "ERR|finalize_failed"
  exit 12
fi

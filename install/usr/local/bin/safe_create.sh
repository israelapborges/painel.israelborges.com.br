#!/usr/bin/env bash
set -euo pipefail

# safe_create.sh <target> <mode> <content_or_dash>
# mode: "dir" ou "file"
TARGET="${1:-}"
MODE="${2:-}"
CONTENT_ARG="${3:-}"

SAFE_ROOT="/home"

if [ -z "$TARGET" ] || [ -z "$MODE" ]; then
  echo "ERR|args"
  exit 2
fi

# Canonicaliza o pai do target (permitir criação quando o target não existir)
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

# Se for dir: mkdir -p
if [ "$MODE" = "dir" ]; then
  mkdir -p -- "$TARGET" || { echo "ERR|mkdir"; exit 5; }
  # Permissões padrão e dono igual ao pai
  chmod 0755 -- "$TARGET" || true
  UID=$(stat -c '%u' -- "$CANON_PARENT" 2>/dev/null || echo '')
  GID=$(stat -c '%g' -- "$CANON_PARENT" 2>/dev/null || echo '')
  if [ -n "$UID" ] && [ -n "$GID" ]; then
    chown "$UID:$GID" -- "$TARGET" || true
  fi
  echo "OK|$TARGET"
  exit 0
fi

# Se for file: cria pai se necessário, grava conteúdo (base64) no arquivo
if [ "$MODE" = "file" ]; then
  # garante diretório pai criado
  mkdir -p -- "$PARENT" || { echo "ERR|mkdir_parent"; exit 6; }

  # decide como obter o base64: se CONTENT_ARG == '-' lê do stdin, senão trata como base64 inline
  if [ "$CONTENT_ARG" = "-" ]; then
    # lê stdin como base64 e desserializa
    if ! base64 --decode > "$TARGET" 2>/dev/null; then
      echo "ERR|decode_stdin"
      exit 7
    fi
  else
    # conteúdo inline em base64 (pode ser vazio string)
    if [ -n "$CONTENT_ARG" ]; then
      echo -n "$CONTENT_ARG" | base64 --decode > "$TARGET" 2>/dev/null || { echo "ERR|decode_arg"; exit 8; }
    else
      # cria arquivo vazio
      : > "$TARGET" || { echo "ERR|create_empty"; exit 9; }
    fi
  fi

  # perms e dono baseados no pai
  chmod 0644 -- "$TARGET" || true
  UID=$(stat -c '%u' -- "$CANON_PARENT" 2>/dev/null || echo '')
  GID=$(stat -c '%g' -- "$CANON_PARENT" 2>/dev/null || echo '')
  if [ -n "$UID" ] && [ -n "$GID" ]; then
    chown "$UID:$GID" -- "$TARGET" || true
  fi

  echo "OK|$TARGET"
  exit 0
fi

# se chegou aqui, modo inválido
echo "ERR|invalid_mode"
exit 10

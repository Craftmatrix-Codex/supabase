#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$APP_ROOT/../.." && pwd)"
STUDIO_ROOT="$REPO_ROOT/apps/studio"

SOURCE_DIR="${STUDIO_CLIENT_SOURCE:-$STUDIO_ROOT/dist/client}"
if [[ -z "${STUDIO_CLIENT_SOURCE:-}" ]]; then
  if [[ ! -d "$STUDIO_ROOT/node_modules" ]]; then
    echo "Studio dependencies are missing: $STUDIO_ROOT/node_modules" >&2
    exit 1
  fi

  (
    cd "$STUDIO_ROOT"
    SUPADATA_STUDIO_BUILDING=1 pnpm run build:tanstack
  )
fi

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Studio client artifact is missing: $SOURCE_DIR" >&2
  exit 1
fi

for path in assets archive deno favicon fonts img json monaco-editor .well-known; do
  rm -rf "$APP_ROOT/public/$path"
done
rm -f "$APP_ROOT/public/supabase-logo.svg"
rm -rf "$APP_ROOT/public/studio"

cp -a "$SOURCE_DIR/." "$APP_ROOT/public/"
mkdir -p "$APP_ROOT/public/studio"
cp "$SOURCE_DIR/_shell.html" "$APP_ROOT/public/studio/index.html"
rm -f "$APP_ROOT/public/_shell.html"

printf 'Synced Studio client to %s/public\n' "$APP_ROOT"

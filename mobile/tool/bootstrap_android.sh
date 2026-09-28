#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -d android && -f android/app/build.gradle.kts ]]; then
  echo "Android host already exists."
  exit 0
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

flutter create   --platforms=android   --org af.businessos   --project-name pharmacy   --no-pub   "$TMP_DIR/pharmacy"

rm -rf android
cp -R "$TMP_DIR/pharmacy/android" android
cp "$TMP_DIR/pharmacy/.metadata" .metadata

echo "Generated Android host for af.businessos.pharmacy."

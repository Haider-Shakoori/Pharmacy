#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -d android || ! -f android/app/build.gradle.kts ]]; then
  TMP_DIR="$(mktemp -d)"
  trap 'rm -rf "$TMP_DIR"' EXIT

  flutter create --platforms=android --org af.businessos --project-name pharmacy --no-pub "$TMP_DIR/pharmacy"

  rm -rf android
  cp -R "$TMP_DIR/pharmacy/android" android
  cp "$TMP_DIR/pharmacy/.metadata" .metadata
  echo "Generated Android host for af.businessos.pharmacy."
else
  echo "Android host already exists."
fi

MANIFEST="android/app/src/main/AndroidManifest.xml"
GRADLE="android/app/build.gradle.kts"

if ! grep -q "android.permission.POST_NOTIFICATIONS" "$MANIFEST"; then
  sed -i '/<manifest /a\    <uses-permission android:name="android.permission.POST_NOTIFICATIONS" />' "$MANIFEST"
fi

if ! grep -q "isCoreLibraryDesugaringEnabled" "$GRADLE"; then
  sed -i '/compileOptions {/a\        isCoreLibraryDesugaringEnabled = true' "$GRADLE"
fi

if ! grep -q 'coreLibraryDesugaring("com.android.tools:desugar_jdk_libs' "$GRADLE"; then
  cat >> "$GRADLE" <<'EOF'

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
EOF
fi

echo "Android notification permission and desugaring are configured."

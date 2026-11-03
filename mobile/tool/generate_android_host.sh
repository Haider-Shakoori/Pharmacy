#!/usr/bin/env bash
set -euo pipefail

# Generates Android project sources only; does not build or sign an app.
# Run from the repository root with the project's pinned Flutter SDK.
cd "$(dirname "$0")/.."

if [[ -d android ]]; then
  echo "Android host already exists; refusing to overwrite it." >&2
  exit 1
fi

flutter create --platforms=android --project-name businessos_pharmacy --org af.businessos --no-pub .
echo "Android host generated. Review applicationId, SDK versions, permissions,"
echo "Gradle and signing configuration before committing. Do not run flutter build."

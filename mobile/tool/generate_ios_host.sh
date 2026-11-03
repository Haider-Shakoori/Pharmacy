#!/usr/bin/env bash
set -euo pipefail

# Source generation only. Never builds, signs or exports an IPA.
cd "$(dirname "$0")/.."

if [[ -d ios ]]; then
  echo "iOS host already exists; refusing to overwrite it." >&2
  exit 1
fi

flutter create --platforms=ios --project-name businessos_pharmacy --org af.businessos --no-pub .
echo "Review ios/Runner/Info.plist and add these entries before using the picker:"
echo "NSCameraUsageDescription: Capture medicine labels for offline scanning"
echo "NSPhotoLibraryUsageDescription: Select medicine label photos for offline scanning"
echo "Review signing, deployment target and bundle identifier before committing."
echo "Do not run flutter build or produce an IPA."

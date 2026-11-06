#!/usr/bin/env bash
set -euo pipefail

# Source generation only. Never builds, signs or exports an IPA.
cd "$(dirname "$0")/.."

if [[ -d ios ]]; then
  echo "iOS host already exists; refusing to overwrite it." >&2
  exit 1
fi

flutter create --platforms=ios --project-name businessos_pharmacy --org af.businessos --no-pub .
# Configure native privacy strings without compiling or signing anything.
python3 - <<'PY'
from pathlib import Path
import plistlib

path = Path('ios/Runner/Info.plist')
with path.open('rb') as source:
    data = plistlib.load(source)
data['NSCameraUsageDescription'] = 'Capture medicine labels for offline scanning.'
data['NSPhotoLibraryUsageDescription'] = 'Select medicine label photos for offline scanning.'
with path.open('wb') as destination:
    plistlib.dump(data, destination, sort_keys=False)
print('iOS camera and photo-library privacy descriptions configured.')
PY
echo "Review signing, deployment target and bundle identifier before committing."
echo "Do not run flutter build or produce an IPA."

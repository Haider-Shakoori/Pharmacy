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

# Camera hardware is optional so gallery-only devices can still install.
# image_picker uses a system camera intent; do not add CAMERA permission here.
python3 - <<'PY'
from pathlib import Path
import xml.etree.ElementTree as ET

manifest = Path('android/app/src/main/AndroidManifest.xml')
ET.register_namespace('android', 'http://schemas.android.com/apk/res/android')
tree = ET.parse(manifest)
root = tree.getroot()
name = '{http://schemas.android.com/apk/res/android}name'
required = '{http://schemas.android.com/apk/res/android}required'
if not any(node.get(name) == 'android.hardware.camera' for node in root.findall('uses-feature')):
    feature = ET.Element('uses-feature')
    feature.set(name, 'android.hardware.camera')
    feature.set(required, 'false')
    root.insert(0, feature)
    tree.write(manifest, encoding='utf-8', xml_declaration=True)
print('Android optional camera feature configured.')
PY
echo "Android host generated. Review applicationId, SDK versions, permissions,"
echo "Gradle and signing configuration before committing. Do not run flutter build."

#!/usr/bin/env bash
set -euo pipefail

# Development-only scaffold generator. Does not build an APK/IPA.
cd "$(dirname "$0")/.."
flutter create --platforms=android,ios --project-name businessos_pharmacy --org com.darmaltoon --no-pub .

python3 - <<'PY'
from pathlib import Path
android = Path('android/app/src/main/AndroidManifest.xml')
s = android.read_text()
for permission in ('INTERNET', 'CAMERA', 'POST_NOTIFICATIONS'):
    entry = f'    <uses-permission android:name="android.permission.{permission}" />'
    if entry not in s:
        s = s.replace('    <application', entry + '\n    <application', 1)
s = s.replace('android:label="businessos_pharmacy"', 'android:label="Darmaltoon"')
android.write_text(s)

ios = Path('ios/Runner/Info.plist')
s = ios.read_text().replace('<string>Businessos Pharmacy</string>', '<string>Darmaltoon</string>')
entries = {
    'NSCameraUsageDescription': 'Darmaltoon uses the camera to scan medicine barcodes and QR codes.',
    'NSLocalNetworkUsageDescription': 'Darmaltoon connects to your pharmacy server on the local network.',
}
for key, description in entries.items():
    if f'<key>{key}</key>' not in s:
        s = s.replace('<dict>', f'<dict>\n\t<key>{key}</key>\n\t<string>{description}</string>', 1)
if '<key>NSAppTransportSecurity</key>' not in s:
    s = s.replace('<dict>', '<dict>\n\t<key>NSAppTransportSecurity</key>\n\t<dict>\n\t\t<key>NSAllowsLocalNetworking</key>\n\t\t<true/>\n\t</dict>', 1)
ios.write_text(s)
PY
echo 'Android and iOS projects generated. Replace default icons with official Darmaltoon assets before release.'

#!/usr/bin/env bash
# Run from the repository root, in one persistent shell. The emulator action
# executes each line of its YAML "script" in a separate sh -c invocation.
set -Eeuo pipefail

OUT="mobile/build/emulator-uat"
APK="mobile/build/app/outputs/flutter-apk/app-debug.apk"
PKG="com.darmaltoon.businessos_pharmacy"
mkdir -p "$OUT"

capture_diagnostics() {
  local result="$?"
  adb devices -l > "$OUT/adb-devices-after.txt" 2>&1 || true
  adb logcat -d -v time > "$OUT/logcat.txt" 2>&1 || true
  adb shell dumpsys activity activities > "$OUT/activity.txt" 2>&1 || true
  if [ "$result" -ne 0 ]; then
    adb shell screencap -p /sdcard/emulator-failure.png > /dev/null 2>&1 || true
    adb pull /sdcard/emulator-failure.png "$OUT/failure.png" > /dev/null 2>&1 || true
    echo "::error::Darmaltoon emulator smoke test failed with exit code $result. Examine the uploaded evidence."
  fi
}
trap capture_diagnostics EXIT

command -v adb
test -s "$APK"
adb start-server
adb devices -l | tee "$OUT/adb-devices.txt"
adb wait-for-device
adb shell getprop sys.boot_completed | tr -d '\r' | tee "$OUT/boot.txt"
grep -qx '1' "$OUT/boot.txt"

adb install -r "$APK" | tee "$OUT/install.txt"
adb shell pm path "$PKG" | tee "$OUT/installed-path.txt"
grep -q 'package:' "$OUT/installed-path.txt"
printf '%s\n' "$PKG" > "$OUT/package.txt"

# Test a genuinely fresh launch without credentials or real tenant data.
adb logcat -c
adb shell am force-stop "$PKG" || true
adb shell am start -W -n "$PKG/.MainActivity" | tee "$OUT/launch.txt"
sleep 15
PID="$(adb shell pidof "$PKG" | tr -d '\r' || true)"
if [ -z "$PID" ]; then
  echo "::error::App process exited during initial launch."
  exit 1
fi
printf '%s\n' "$PID" > "$OUT/first-pid.txt"
adb shell screencap -p /sdcard/darmaltoon-first-launch.png
adb pull /sdcard/darmaltoon-first-launch.png "$OUT/first-launch.png"

# The device must be able to return to the app after Android backgrounds it.
adb shell input keyevent KEYCODE_HOME
adb shell am start -W -n "$PKG/.MainActivity" | tee "$OUT/relaunch.txt"
sleep 8
PID="$(adb shell pidof "$PKG" | tr -d '\r' || true)"
if [ -z "$PID" ]; then
  echo "::error::App process exited on relaunch."
  exit 1
fi
printf '%s\n' "$PID" > "$OUT/relaunch-pid.txt"
adb shell screencap -p /sdcard/darmaltoon-relaunch.png
adb pull /sdcard/darmaltoon-relaunch.png "$OUT/relaunch.png"

adb logcat -d -v time > "$OUT/logcat.txt"
if grep -E 'FATAL EXCEPTION|AndroidRuntime.*FATAL' "$OUT/logcat.txt" > "$OUT/crash-lines.txt"; then
  echo "::error::Android fatal exception detected during Darmaltoon smoke test."
  cat "$OUT/crash-lines.txt"
  exit 1
fi
echo "PASS: APK installed, started, survived first launch, and relaunched on emulator."

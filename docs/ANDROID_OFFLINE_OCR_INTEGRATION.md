# Android offline OCR integration contract

The Flutter project currently contains Dart sources only: `mobile/android/` is
absent. This is a native-host implementation specification, **not** a claim
that Android OCR is installed or functional.

## Android implementation sequence

1. Generate the Android host project from the pinned Flutter SDK in a
   dedicated change, review the resulting manifest, Gradle configuration,
   application identifier and signing settings. Do not create release
   packages or invoke `flutter build`.
2. Register a MethodChannel named `darmaltoon/offline_medicine_ocr` in
   the Android Flutter engine, with methods:
   - `isAvailable` → Boolean: true only if the native engine and all
     required local model assets are installed and usable offline.
   - `recognizeLocalImage` → List<String>: receives a map with a
     `path` string to an existing local image and returns recognized lines.
3. Use an on-device OCR engine with models **packaged at installation**.
   Do not use an unbundled model that downloads on first use. Audit
   supported scripts using actual installed models; do not assume Persian,
   Pashto, Arabic or Urdu support from Latin OCR.
4. Reject network URLs, remote content URIs, missing local files, and
   paths outside approved application-local or explicitly granted image
   picker storage. Decode with memory bounds, honor image orientation,
   release native resources, and run recognition off the UI thread.
5. Map unsupported language, missing model, permission denial, decode
   failure, and empty recognition to explicit errors. Never silently
   substitute a cloud API. Never transmit the image or extracted text.
6. Pass recognized lines into the existing Dart coordinator and editable
   pharmacist review. Scanning must not register a catalog item, batch
   or stock receipt without separate confirmation.
7. Validate on a fresh Android install in airplane mode with real medicine
   labels. Run language-specific acceptance samples for every script
   actually advertised as supported.

## Scope of current repository

`PlatformMedicineOcr` is a fail-closed Flutter bridge; it does not install
native recognition. `MedicineImageAcquisition` defines the image-source
contract but does not implement camera/gallery permissions or capture.

No Android application build, signing, merge or deployment is authorized
by this document.

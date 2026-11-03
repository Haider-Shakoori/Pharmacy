# iOS offline scanner integration

The Flutter iOS host project (`mobile/ios/`) has not yet been generated.
The plugin under `mobile/plugins/offline_medicine_ocr/ios/` provides an
Apple Vision native implementation of the existing method channel.

## Integration requirements

1. Generate iOS platform source with the pinned Flutter SDK (no app build),
   review signing, deployment target, bundle identifier and permissions.
2. Add `NSCameraUsageDescription` and `NSPhotoLibraryUsageDescription`
   to the host Info.plist before enabling camera/gallery capture.
3. Confirm the plugin is registered by Flutter and the iOS deployment
   target is at least 13.0. Run static checks without producing IPA.
4. Test on a physical iPhone in airplane mode with a freshly installed app.
   Check photo rotation, cancellation, unreadable images, permissions,
   image memory usage and handwritten/printed labels separately.
5. Apple Vision recognition languages depend on OS version and recognition
   revision. Do not advertise Dari/Pashto/Arabic support without checking
   `supportedRecognitionLanguages` on the actual device and verifying
   real packaging samples. Unsupported scripts require manual entry.
6. OCR only returns suggested text. Pharmacist confirmation remains
   mandatory; scanning must not create medicine or stock records.

No IPA, APK, EXE, installer, merge, or deployment is authorized.

import 'package:businessos_pharmacy/features/medicines/domain/platform_medicine_ocr.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('darmaltoon/offline_medicine_ocr_test');
  const ocr = PlatformMedicineOcr(channel: channel);

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  test('missing native handler reports unavailable', () async {
    expect(await ocr.isAvailable, isFalse);
    await expectLater(
      ocr.recognizeLocalImage('/local/medicine.jpg'),
      throwsStateError,
    );
  });

  test('passes local path to native handler without remote calls', () async {
    String? capturedPath;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          if (call.method == 'isAvailable') return true;
          if (call.method == 'recognizeLocalImage') {
            capturedPath = (call.arguments as Map)['path'] as String;
            return <String>['ExampleMed', '250 mg'];
          }
          throw MissingPluginException();
        });
    expect(await ocr.isAvailable, isTrue);
    expect(await ocr.recognizeLocalImage('/local/medicine.jpg'), <String>[
      'ExampleMed',
      '250 mg',
    ]);
    expect(capturedPath, '/local/medicine.jpg');
  });

  test('trims local image path before invoking native OCR', () async {
    String? capturedPath;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          capturedPath = (call.arguments as Map)['path'] as String;
          return <String>['ExampleMed'];
        });
    expect(await ocr.recognizeLocalImage('  /local/medicine.jpg  '), <String>[
      'ExampleMed',
    ]);
    expect(capturedPath, '/local/medicine.jpg');
  });

  test('native failure does not silently fall back to cloud OCR', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          throw PlatformException(code: 'MODEL_UNAVAILABLE');
        });
    expect(await ocr.isAvailable, isFalse);
    await expectLater(
      ocr.recognizeLocalImage('/local/medicine.jpg'),
      throwsStateError,
    );
  });

  test('remote and relative paths rejected before platform call', () async {
    for (final path in [
      'https://example.com/image.jpg',
      'relative.jpg',
      'content://external/image',
    ]) {
      await expectLater(ocr.recognizeLocalImage(path), throwsArgumentError);
    }
  });

  test('empty path rejected before platform call', () async {
    await expectLater(ocr.recognizeLocalImage(' '), throwsArgumentError);
  });
}

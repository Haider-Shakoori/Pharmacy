import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_coordinator.dart';
import 'package:flutter_test/flutter_test.dart';

class FakeNativeOcr implements NativeMedicineOcr {
  FakeNativeOcr({this.available = true});
  final bool available;
  int calls = 0;

  @override
  Future<bool> get isAvailable async => available;

  @override
  Future<List<String>> recognizeLocalImage(String imagePath) async {
    calls++;
    return ['ExampleMed', '250 mg Tablets', 'LOT: B1234'];
  }
}

void main() {
  test('uses native OCR and returns an unconfirmed draft', () async {
    final ocr = FakeNativeOcr();
    final draft = await MedicineScanCoordinator(ocr: ocr)
        .scanLocalImage('/local/medicine.jpg');
    expect(ocr.calls, 1);
    expect(draft.brandName, 'ExampleMed');
    expect(draft.strength, '250 mg');
    expect(draft.genericName, isNull);
  });

  test('does not invoke OCR when unavailable', () async {
    final ocr = FakeNativeOcr(available: false);
    await expectLater(
      MedicineScanCoordinator(ocr: ocr).scanLocalImage('/local/medicine.jpg'),
      throwsStateError,
    );
    expect(ocr.calls, 0);
  });

  test('rejects empty image path without invoking OCR', () async {
    final ocr = FakeNativeOcr();
    await expectLater(
      MedicineScanCoordinator(ocr: ocr).scanLocalImage('  '),
      throwsArgumentError,
    );
    expect(ocr.calls, 0);
  });
}

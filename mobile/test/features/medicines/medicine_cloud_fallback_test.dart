import 'package:businessos_pharmacy/features/medicines/domain/medicine_cloud_fallback.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:flutter_test/flutter_test.dart';

class _FakeGateway implements MedicineCloudVisionGateway {
  int calls = 0;

  @override
  Future<MedicineScanDraft> suggestFromImage({
    required String localImagePath,
    required MedicineScanDraft offlineDraft,
  }) async {
    calls++;
    return offlineDraft;
  }
}

void main() {
  const draft = MedicineScanDraft(sourceText: 'label');

  test('never calls cloud without explicit consent', () async {
    final gateway = _FakeGateway();
    await expectLater(
      MedicineCloudFallback(gateway).suggest(
        localImagePath: '/tmp/medicine.jpg',
        offlineDraft: draft,
        userConsented: false,
      ),
      throwsStateError,
    );
    expect(gateway.calls, 0);
  });

  test('rejects remote paths even with consent', () async {
    final gateway = _FakeGateway();
    await expectLater(
      MedicineCloudFallback(gateway).suggest(
        localImagePath: 'https://example.com/medicine.jpg',
        offlineDraft: draft,
        userConsented: true,
      ),
      throwsArgumentError,
    );
    expect(gateway.calls, 0);
  });

  test('permits one consented local-image request', () async {
    final gateway = _FakeGateway();
    final result = await MedicineCloudFallback(gateway).suggest(
      localImagePath: '/tmp/medicine.jpg',
      offlineDraft: draft,
      userConsented: true,
    );
    expect(result.sourceText, 'label');
    expect(gateway.calls, 1);
  });
}

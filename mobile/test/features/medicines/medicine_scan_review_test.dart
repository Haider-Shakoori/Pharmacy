import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const review = MedicineScanReview();
  const draft = MedicineScanDraft(
    brandName: 'Candidate',
    batchNumber: 'LOT123',
    expiresAt: '12/2028',
    sourceText: 'Candidate LOT123 EXP 12/2028',
  );

  test('requires explicit pharmacist confirmation', () {
    expect(
      () => review.confirm(
        draft: draft,
        pharmacistConfirmed: false,
        brandName: 'Candidate',
      ),
      throwsStateError,
    );
  });

  test('rejects empty reviewed brand name', () {
    expect(
      () => review.confirm(
        draft: draft,
        pharmacistConfirmed: true,
        brandName: ' ',
      ),
      throwsArgumentError,
    );
  });

  test('keeps stock batch and expiry out of catalog registration', () {
    final result = review.confirm(
      draft: draft,
      pharmacistConfirmed: true,
      brandName: ' Corrected Medicine ',
      genericName: ' ',
      strength: ' 500 mg ',
    );
    expect(result.brandName, 'Corrected Medicine');
    expect(result.genericName, isNull);
    expect(result.strength, '500 mg');
    expect(result.toString(), isNot(contains('LOT123')));
    expect(result.toString(), isNot(contains('12/2028')));
  });
}

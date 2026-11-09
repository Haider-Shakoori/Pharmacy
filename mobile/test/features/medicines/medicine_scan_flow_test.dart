import 'package:businessos_pharmacy/features/medicines/domain/medicine_image_acquisition.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_coordinator.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_scan_flow.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _LocalImages implements LocalMedicineImageSource {
  @override
  Future<String?> captureImage() async => '/tmp/medicine.jpg';

  @override
  Future<String?> chooseLocalImage() async => '/tmp/medicine.jpg';
}

class _OfflineOcr implements NativeMedicineOcr {
  @override
  Future<bool> get isAvailable async => true;

  @override
  Future<List<String>> recognizeLocalImage(String imagePath) async => [
    'Paracetamol 500mg tablets',
  ];
}

class _CancelledImages implements LocalMedicineImageSource {
  @override
  Future<String?> captureImage() async => null;

  @override
  Future<String?> chooseLocalImage() async => null;
}

class _FailingImages implements LocalMedicineImageSource {
  @override
  Future<String?> captureImage() async =>
      throw StateError('camera unavailable');

  @override
  Future<String?> chooseLocalImage() async =>
      throw StateError('gallery unavailable');
}

void main() {
  testWidgets('cancelled camera capture resets busy state without draft', (
    WidgetTester tester,
  ) async {
    final confirmed = <ReviewedMedicineScan>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MedicineScanFlow(
            images: MedicineImageAcquisition(_CancelledImages()),
            scanner: MedicineScanCoordinator(ocr: _OfflineOcr()),
            onConfirmed: confirmed.add,
          ),
        ),
      ),
    );
    await tester.tap(find.text('Camera'));
    await tester.pumpAndSettle();
    expect(find.byType(LinearProgressIndicator), findsNothing);
    expect(find.text('Camera'), findsOneWidget);
    expect(find.text('Review scanned medicine details'), findsNothing);
    expect(confirmed, isEmpty);
  });

  testWidgets('camera failure shows manual-entry fallback without saving', (
    WidgetTester tester,
  ) async {
    final confirmed = <ReviewedMedicineScan>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MedicineScanFlow(
            images: MedicineImageAcquisition(_FailingImages()),
            scanner: MedicineScanCoordinator(ocr: _OfflineOcr()),
            onConfirmed: confirmed.add,
          ),
        ),
      ),
    );
    await tester.tap(find.text('Camera'));
    await tester.pumpAndSettle();
    expect(
      find.text('Unable to scan offline. Enter medicine details manually.'),
      findsOneWidget,
    );
    expect(find.byType(LinearProgressIndicator), findsNothing);
    expect(confirmed, isEmpty);
  });

  testWidgets('scan requires review and can be discarded without saving', (
    WidgetTester tester,
  ) async {
    final confirmed = <ReviewedMedicineScan>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanFlow(
              images: MedicineImageAcquisition(_LocalImages()),
              scanner: MedicineScanCoordinator(ocr: _OfflineOcr()),
              onConfirmed: confirmed.add,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Gallery'));
    await tester.pumpAndSettle();
    expect(find.text('Review scanned medicine details'), findsOneWidget);
    expect(confirmed, isEmpty);

    await tester.ensureVisible(find.text('Discard scan'));
    await tester.tap(find.text('Discard scan'));
    await tester.pumpAndSettle();
    expect(find.text('Review scanned medicine details'), findsNothing);
    expect(confirmed, isEmpty);
  });
  testWidgets('camera capture produces review without auto-registering', (
    WidgetTester tester,
  ) async {
    final confirmed = <ReviewedMedicineScan>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanFlow(
              images: MedicineImageAcquisition(_LocalImages()),
              scanner: MedicineScanCoordinator(ocr: _OfflineOcr()),
              onConfirmed: confirmed.add,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Camera'));
    await tester.pumpAndSettle();
    expect(find.text('Review scanned medicine details'), findsOneWidget);
    expect(confirmed, isEmpty);
    await tester.ensureVisible(find.text('Discard scan'));
    await tester.tap(find.text('Discard scan'));
    await tester.pumpAndSettle();
    expect(confirmed, isEmpty);
    expect(find.text('Review scanned medicine details'), findsNothing);
  });

  testWidgets('confirmed scan is emitted once and draft is cleared', (
    WidgetTester tester,
  ) async {
    final confirmed = <ReviewedMedicineScan>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: MedicineScanFlow(
              images: MedicineImageAcquisition(_LocalImages()),
              scanner: MedicineScanCoordinator(ocr: _OfflineOcr()),
              onConfirmed: confirmed.add,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Gallery'));
    await tester.pumpAndSettle();
    expect(confirmed, isEmpty);

    await tester.ensureVisible(
      find.text('I have checked the medicine label and details'),
    );
    await tester.tap(
      find.text('I have checked the medicine label and details'),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Use reviewed details'));
    await tester.tap(find.text('Use reviewed details'));
    await tester.pumpAndSettle();

    expect(confirmed, hasLength(1));
    expect(confirmed.single.brandName, isNotEmpty);
    expect(find.text('Review scanned medicine details'), findsNothing);
    expect(find.text('Use reviewed details'), findsNothing);
  });
}

import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_registration_handoff.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_registration_dialog.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final handoff = MedicineRegistrationHandoff.prepare(
    reviewed: const ReviewedMedicineScan(
      brandName: 'Test medicine',
      genericName: null,
      strength: '500mg',
      dosageForm: 'tablet',
      barcode: null,
    ),
    duplicateCheckCompleted: true,
    possibleDuplicates: const [],
  );
  final registration = MobileRegistration(
    tenantId: 'tenant',
    tenantName: 'Test',
    activationId: 'activation',
    accessToken: 'token',
    accessExpiresAt: DateTime.now().add(const Duration(hours: 1)),
    leaseToken: 'lease',
    leaseExpiresAt: DateTime.now().add(const Duration(hours: 1)),
    userId: 'user',
    userName: 'Pharmacist',
    userEmail: 'test@example.test',
    permissions: const ['medicines.manage'],
  );

  testWidgets('registration is disabled until pharmacist approves', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MedicineRegistrationDialog(
            handoff: handoff,
            registration: registration,
          ),
        ),
      ),
    );
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, 'Register'))
          .onPressed,
      isNull,
    );
    expect(find.text('Reviewed: Test medicine'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('registration quantity fields reject out-of-range values', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MedicineRegistrationDialog(
            handoff: handoff,
            registration: registration,
          ),
        ),
      ),
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Units per purchase unit'),
      '100000000',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Reorder level'),
      '1000000000',
    );
    await tester.ensureVisible(
      find.text('I approve this medicine registration'),
    );
    await tester.tap(find.text('I approve this medicine registration'));
    await tester.pump();
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Register'));
    await tester.tap(find.widgetWithText(FilledButton, 'Register'));
    await tester.pump();
    expect(find.text('Enter a valid number'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });
}

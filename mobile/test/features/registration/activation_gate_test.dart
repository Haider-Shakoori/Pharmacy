import 'package:businessos_pharmacy/core/auth/mobile_registration_providers.dart';
import 'package:businessos_pharmacy/features/registration/presentation/mobile_registration_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('activation key appears before pharmacy credentials', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          mobileRegistrationProvider.overrideWith((Ref ref) async => null),
        ],
        child: const MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(child: MobileRegistrationCard()),
          ),
        ),
      ),
    );
    await tester.pump();
    expect(find.byKey(const ValueKey('activation-key-field')), findsOneWidget);
    expect(find.byKey(const ValueKey('activation-email-field')), findsNothing);
    expect(find.byKey(const ValueKey('activation-password-field')), findsNothing);
    await tester.tap(find.byKey(const ValueKey('activation-key-next')));
    await tester.pump();
    expect(find.text('Enter your activation key.'), findsOneWidget);
    await tester.enterText(
      find.byKey(const ValueKey('activation-key-field')),
      'TEST-KEY',
    );
    await tester.tap(find.byKey(const ValueKey('activation-key-next')));
    await tester.pump();
    expect(find.byKey(const ValueKey('activation-email-field')), findsOneWidget);
    expect(find.byKey(const ValueKey('activation-password-field')), findsOneWidget);
    expect(find.byKey(const ValueKey('activation-key-field')), findsNothing);
  });
}

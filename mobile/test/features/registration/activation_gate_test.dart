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
    expect(
      find.byKey(const ValueKey('activation-register-link')),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('activation-email-field')), findsNothing);
    expect(
      find.byKey(const ValueKey('activation-password-field')),
      findsNothing,
    );
    await tester.tap(find.byKey(const ValueKey('activation-key-next')));
    await tester.pump();
    expect(find.text('Enter your activation key.'), findsOneWidget);
    await tester.enterText(
      find.byKey(const ValueKey('activation-key-field')),
      'TEST-KEY',
    );
    await tester.tap(find.byKey(const ValueKey('activation-key-next')));
    await tester.pump();
    expect(
      find.byKey(const ValueKey('activation-email-field')),
      findsOneWidget,
    );
    expect(
      find.byKey(const ValueKey('activation-password-field')),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('activation-key-field')), findsNothing);
  });

  testWidgets(
    'credential step masks password, blocks empty login, and allows key change',
    (WidgetTester tester) async {
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

      const ValueKey<String> keyField = ValueKey<String>(
        'activation-key-field',
      );
      const ValueKey<String> nextButton = ValueKey<String>(
        'activation-key-next',
      );
      const ValueKey<String> emailField = ValueKey<String>(
        'activation-email-field',
      );
      const ValueKey<String> passwordField = ValueKey<String>(
        'activation-password-field',
      );
      const ValueKey<String> signIn = ValueKey<String>('activation-sign-in');
      const ValueKey<String> changeKey = ValueKey<String>('activation-back');

      await tester.enterText(find.byKey(keyField), 'TEST-LICENSE');
      await tester.tap(find.byKey(nextButton));
      await tester.pump();
      expect(
        find.text('Step 2 of 2 — Sign in to your pharmacy'),
        findsOneWidget,
      );
      expect(find.byKey(keyField), findsNothing);
      expect(
        tester.widget<TextField>(find.byKey(passwordField)).obscureText,
        isTrue,
      );

      await tester.ensureVisible(find.byKey(signIn));
      await tester.tap(find.byKey(signIn));
      await tester.pump();
      expect(
        find.text('Enter your activation key, email and password.'),
        findsOneWidget,
      );
      expect(find.byKey(emailField), findsOneWidget);

      await tester.enterText(find.byKey(emailField), 'tester@example.test');
      await tester.enterText(find.byKey(passwordField), 'NOT-A-REAL-PASSWORD');
      await tester.ensureVisible(find.byKey(changeKey));
      await tester.tap(find.byKey(changeKey));
      await tester.pump();

      expect(
        find.text('Step 1 of 2 — Enter your activation key'),
        findsOneWidget,
      );
      expect(
        tester.widget<TextField>(find.byKey(keyField)).controller!.text,
        'TEST-LICENSE',
      );
      expect(find.byKey(passwordField), findsNothing);

      await tester.ensureVisible(find.byKey(nextButton));
      await tester.tap(find.byKey(nextButton));
      await tester.pump();
      expect(
        tester.widget<TextField>(find.byKey(passwordField)).controller!.text,
        isEmpty,
      );
      expect(tester.takeException(), isNull);
    },
  );
}

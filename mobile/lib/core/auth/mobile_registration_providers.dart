import 'package:businessos_pharmacy/core/auth/mobile_registration.dart';
import 'package:businessos_pharmacy/core/auth/mobile_registration_repository.dart';
import 'package:businessos_pharmacy/core/storage/secure_store.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<MobileRegistrationRepository>
mobileRegistrationRepositoryProvider = Provider<MobileRegistrationRepository>(
  (Ref ref) => MobileRegistrationRepository(ref.watch(secureStoreProvider)),
);

final FutureProvider<MobileRegistration?> mobileRegistrationProvider =
    FutureProvider<MobileRegistration?>((Ref ref) {
      return ref.watch(mobileRegistrationRepositoryProvider).load();
    });

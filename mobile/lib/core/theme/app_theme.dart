import 'package:flutter/material.dart';

/// Darmaltoon design tokens, aligned with the approved five-phone reference.
abstract final class AppColors {
  static const Color primary = Color(0xFF1557C0);
  static const Color primaryContainer = Color(0xFFDDEAFF);
  static const Color background = Color(0xFFF4F7FC);
  static const Color surface = Colors.white;
  static const Color success = Color(0xFF1E9E5A);
  static const Color warning = Color(0xFFE38A1B);
  static const Color danger = Color(0xFFD93A3A);
  static const Color purple = Color(0xFF7A4FD6);
  static const Color teal = Color(0xFF13A8A8);
}

abstract final class AppTheme {
  static ThemeData light() {
    final ColorScheme colors = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.light,
    ).copyWith(primary: AppColors.primary, surface: AppColors.surface);

    return ThemeData(
      colorScheme: colors,
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.background,
      dividerTheme: const DividerThemeData(
        color: Color(0xFFE5EAF3),
        thickness: 1,
        space: 24,
      ),
      textTheme: const TextTheme(
        headlineSmall: TextStyle(
          color: Color(0xFF172B4D),
          fontWeight: FontWeight.w800,
          letterSpacing: -0.4,
        ),
        titleLarge: TextStyle(
          color: Color(0xFF172B4D),
          fontWeight: FontWeight.w700,
        ),
        titleMedium: TextStyle(
          color: Color(0xFF253858),
          fontWeight: FontWeight.w700,
        ),
        bodyMedium: TextStyle(color: Color(0xFF344563)),
        bodySmall: TextStyle(color: Color(0xFF63738C)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: AppColors.primaryContainer,
        height: 76,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      ),
      appBarTheme: const AppBarTheme(
        centerTitle: false,
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF1B2A41),
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      cardTheme: const CardThemeData(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(18)),
        ),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(12)),
        ),
      ),
    );
  }
}

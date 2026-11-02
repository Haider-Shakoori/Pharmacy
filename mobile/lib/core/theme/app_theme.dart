import 'package:flutter/material.dart';

abstract final class AppTheme {
  static ThemeData light() {
    final ColorScheme colors = ColorScheme.fromSeed(
      seedColor: const Color(0xFF1557C0),
      brightness: Brightness.light,
    );

    return ThemeData(
      colorScheme: colors,
      useMaterial3: true,
      scaffoldBackgroundColor: const Color(0xFFF4F7FC),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: Color(0xFFDDEAFF),
        height: 72,
      ),
      appBarTheme: const AppBarTheme(centerTitle: false),
      cardTheme: const CardThemeData(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        color: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(18))),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(),
      ),
    );
  }
}

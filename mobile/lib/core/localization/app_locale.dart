import 'package:flutter/material.dart';

enum AppLocale {
  english('en', 'English'),
  dari('fa', 'دری'),
  pashto('ps', 'پښتو');

  const AppLocale(this.code, this.label);

  final String code;
  final String label;

  Locale get locale => Locale(code);

  bool get isRtl => this != AppLocale.english;

  static AppLocale fromCode(String? code) {
    return AppLocale.values.firstWhere(
      (AppLocale locale) => locale.code == code,
      orElse: () => AppLocale.english,
    );
  }
}

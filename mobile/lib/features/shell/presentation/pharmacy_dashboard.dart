import 'package:businessos_pharmacy/core/localization/app_locale.dart';
import 'package:businessos_pharmacy/features/status/presentation/connectivity_banner.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// A navigation-first overview. Never invent sales totals or stock statistics.
class PharmacyDashboard extends StatelessWidget {
  const PharmacyDashboard({required this.onOpenSection, super.key});

  final ValueChanged<int> onOpenSection;

  @override
  Widget build(BuildContext context) {
    final locale = AppLocale.fromCode(
      Localizations.localeOf(context).languageCode,
    );
    final rtl = locale != AppLocale.english;
    final pashto = locale == AppLocale.pashto;
    String label(String en, String fa, String ps) =>
        rtl ? (pashto ? ps : fa) : en;
    final scheme = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: <Color>[Color(0xFF075AA7), Color(0xFF0A91BC)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 52,
                height: 52,
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: SvgPicture.asset(
                  'assets/brand/darmaltoon-mark.svg',
                  placeholderBuilder: (context) => const Icon(
                    Icons.local_pharmacy_rounded,
                    color: Color(0xFF075AA7),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Darmaltoon  •  درملتون',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 23,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                label(
                  'Your pharmacy workspace',
                  'مدیریت دواخانه شما',
                  'ستاسو د درملتون مدیریت',
                ),
                style: const TextStyle(color: Colors.white, fontSize: 14),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const ConnectivityBanner(),
        const SizedBox(height: 22),
        Text(
          label('Quick actions', 'دسترسی سریع', 'چټک کارونه'),
          style: Theme.of(context).textTheme.titleLarge
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.55,
          children: <Widget>[
            _ActionTile(
              icon: Icons.point_of_sale_rounded,
              title: label('New sale', 'فروش جدید', 'نوی پلور'),
              color: const Color(0xFF0877CA),
              onTap: () => onOpenSection(1),
            ),
            _ActionTile(
              icon: Icons.inventory_2_rounded,
              title: label('Inventory', 'موجودی', 'زېرمه'),
              color: const Color(0xFF119D84),
              onTap: () => onOpenSection(2),
            ),
            _ActionTile(
              icon: Icons.people_alt_rounded,
              title: label('Customers', 'مشتریان', 'پېرودونکي'),
              color: const Color(0xFF8065C5),
              onTap: () => onOpenSection(3),
            ),
            _ActionTile(
              icon: Icons.sync_rounded,
              title: label(
                'Sync & settings',
                'همگام‌سازی و تنظیمات',
                'همغږي او تنظیمات',
              ),
              color: const Color(0xFFE38C35),
              onTap: () => onOpenSection(4),
            ),
          ],
        ),
        const SizedBox(height: 20),
        Card(
          color: scheme.surface,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: <Widget>[
                Icon(Icons.verified_user_outlined, color: scheme.primary),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label(
                      'Sales, stock and customer records use your registered pharmacy data. Open Sync to refresh offline records.',
                      'فروشات، موجودی و مشتریان از اطلاعات دواخانه ثبت‌شده شما استفاده می‌کنند. برای تازه‌سازی آفلاین، همگام‌سازی را باز کنید.',
                      'پلور، زېرمه او پېرودونکي ستاسو د ثبت شوي درملتون معلومات کاروي. د تازه کولو لپاره همغږي پرانیزئ.',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.color,
    required this.onTap,
  });
  final IconData icon;
  final String title;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    color: color.withValues(alpha: 0.08),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon, size: 29, color: color),
            const SizedBox(height: 8),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(color: color, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    ),
  );
}

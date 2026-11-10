import 'package:businessos_pharmacy/features/medicines/domain/medicine_image_acquisition.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_coordinator.dart';
import 'package:businessos_pharmacy/features/medicines/domain/medicine_scan_review.dart';
import 'package:businessos_pharmacy/features/medicines/domain/offline_medicine_text_parser.dart';
import 'package:businessos_pharmacy/features/medicines/presentation/medicine_scan_review_panel.dart';
import 'package:flutter/material.dart';

/// Local-only scan flow. Returns reviewed catalog details to the caller.
/// It never saves a medicine or changes inventory.
class MedicineScanFlow extends StatefulWidget {
  const MedicineScanFlow({
    required this.images,
    required this.scanner,
    required this.onConfirmed,
    super.key,
  });

  final MedicineImageAcquisition images;
  final MedicineScanCoordinator scanner;
  final ValueChanged<ReviewedMedicineScan> onConfirmed;

  @override
  State<MedicineScanFlow> createState() => _MedicineScanFlowState();
}

class _MedicineScanFlowState extends State<MedicineScanFlow> {
  bool _busy = false;
  String? _error;
  MedicineScanDraft? _draft;
  final List<String> _recognizedPages = <String>[];

  Future<void> _scan(MedicineImageOrigin origin) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final image = await widget.images.acquire(origin);
      if (image == null) return;
      final draft = await widget.scanner.scanLocalImage(image.localPath);
      if (mounted) {
        setState(() {
          _recognizedPages.add(draft.sourceText);
          // Reparse all captured sides together so a second image can supply
          // missing fields without discarding recognized text from the first.
          _draft = widget.scanner.parser.parse(
            _recognizedPages.expand((page) => page.split('\n')).toList(),
          );
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Unable to scan offline. Enter medicine details manually.';
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _confirm(ReviewedMedicineScan reviewed) {
    widget.onConfirmed(reviewed);
    if (mounted) {
      setState(() {
        _draft = null;
        _recognizedPages.clear();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Scan medicine label (offline)'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          children: [
            OutlinedButton.icon(
              onPressed: _busy ? null : () => _scan(MedicineImageOrigin.camera),
              icon: const Icon(Icons.camera_alt_outlined),
              label: const Text('Camera'),
            ),
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () => _scan(MedicineImageOrigin.gallery),
              icon: const Icon(Icons.photo_library_outlined),
              label: const Text('Gallery'),
            ),
          ],
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        if (_draft != null) ...[
          Text('Images scanned: ${_recognizedPages.length}'),
          const Text('Scan another side to add missing label details.'),
          MedicineScanReviewPanel(
            key: ValueKey(_draft),
            draft: _draft!,
            onConfirmed: _confirm,
          ),
          TextButton.icon(
            onPressed: () => setState(() {
              _draft = null;
              _recognizedPages.clear();
            }),
            icon: const Icon(Icons.delete_outline),
            label: const Text('Discard scan'),
          ),
        ],
      ],
    );
  }
}

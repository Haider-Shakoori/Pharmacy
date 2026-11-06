# Darmaltoon — Native Offline Medicine Scanner (Approved Plan)

Status: approved requirement; implementation pending. This document does not claim the feature is implemented.

## Non-negotiable requirements

- Medicine registration must support camera capture and image upload on supported clients.
- OCR executes entirely on the user's device. No internet connection, hosted OCR API, paid account, or external server is required for text recognition.
- OCR must use a native on-device engine or an engine bundled with the app, with required language assets available offline. Download-on-first-use is not sufficient for an offline installation.
- Extract a **draft** only: brand, generic ingredient, strength, dosage form, manufacturer, pack size, barcode, and visible batch/expiry/manufacturing dates.
- Never fabricate unreadable or absent fields. Show source text and uncertain fields for manual review; pharmacist confirmation is required before saving.
- Product catalog fields remain separate from batch/expiry/stock receipt data. Never post stock or update batches merely by scanning packaging.
- Support offline draft persistence, duplicate detection against locally available catalog records, and synchronization through existing offline outbox rules.
- No scanned medicine images or recognized text leave the device unless the user explicitly enables and authorizes a future AI enhancement.

## Platform implementation

1. Audit the current Laravel medicine form, Flutter mobile/Windows workflows, and separate C# WPF desktop app. Do not conflate Flutter Windows with WPF.
2. Implement an OCR adapter interface with platform-specific on-device engines and packaged assets; verify actual availability on Android, iOS, and Windows.
3. Provide local image preprocessing, text extraction, barcode scanning, and deterministic medicine field parsing. Keep source evidence and per-field confidence/verification status.
4. Build a Scan Medicine action and editable confirmation screen in existing medicine registration workflows; handle permissions, image rotation, no-text images, and duplicate records.
5. Persist drafts locally with safe retry/sync, ensuring no duplicate catalog records or unintended inventory changes.
6. Test offline installation and operation with airplane mode; verify EN, Dari/Persian, Pashto, and imported packaging scripts against actual supported OCR models. Unsupported scripts require manual entry, not guessed output.
7. Add regression tests and platform build checks; do not deploy to production until approved.

## Future optional hybrid AI

- Define an optional, disabled-by-default AI enrichment provider interface.
- Prefer free, local, or self-hosted models when feasible; hosted free tiers may have quotas and are never core dependencies.
- Require explicit opt-in before transmitting an image or OCR text to any hosted provider.
- AI output is only a suggestion and always requires human verification; native OCR remains independently usable when AI is disabled or unavailable.

## Acceptance criteria

- Fresh offline installation can scan, extract supported text, edit and save a medicine draft without internet.
- No OCR network requests or mandatory AI services.
- Uncertain strength, ingredient, expiry, and batch fields remain unconfirmed until reviewed.
- Scanning alone cannot modify inventory.
- CI and platform-specific offline tests pass.

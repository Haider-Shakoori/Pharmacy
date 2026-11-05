# Darmaltoon Pharmacy — Flutter mobile client

This directory contains the offline-first Flutter client for Darmaltoon Pharmacy (Android and iOS). Earlier batch notes below are historical implementation notes, not the current feature inventory.

## Current implementation and verification

- Material 3 application with English, Dari and Pashto localization and RTL support.
- Encrypted device configuration and Local / Cloud / Automatic endpoint selection.
- Drift/SQLite offline storage, device registration, offline POS and sync components.
- Purchasing screens include a local receiving worksheet, persisted drafts and an offline goods-receipt outbox.
- Goods receipts are **not inventory postings**: stock must remain unchanged until an authenticated, idempotent server operation validates and posts the receipt.
- The goods-receipt validator requires at least one **positive received quantity**; a bonus-only receipt is currently rejected. Do not treat bonus-only receipt support as implemented.
- The branch includes a `goods_receipt.create` sync-push handler and `MobileGoodsReceiptService`: authorized, idempotent receipts are captured server-side with `pending_inventory` status and an accepted acknowledgment. Inventory posting is a separate authenticated workflow. End-to-end receipt capture, acknowledgment, and subsequent posting still require integration testing.
- GitHub Actions passed Flutter Mobile CI, Flutter Mobile Validation, Windows workflow, application CI and Release Candidate UAT at commit `5c397840`. Passing CI is not proof of physical-device or production readiness.

## Development and release policy

Keep development on the feature branch. Do not merge, deploy, produce release APK/IPA artifacts or distribute builds without explicit approval. Physical-device scanner/OCR, printer and permissions checks, end-to-end tenant/LAN/auth/sync tests, and signing verification are deferred to the final release gate. See `RELEASE_READINESS.md`.

## Historical implementation notes

## Batch 17 local database

The Android client now uses Drift/SQLite as its offline persistence boundary.
One pharmacy tenant gets one local database regardless of whether the selected
server mode is **Local**, **Cloud** or **Automatic**. Server failover changes
transport only; it does not change local business data.

The first schema includes medicine/batch/customer caches, local sale headers,
sale lines, payments, an idempotent sync outbox and stream checkpoints.
Financial and quantity decimals are retained as exact strings rather than
floating point values. The temporary `unbound` database is used only before
Batch 18 establishes the real tenant/device identity.

Drift code is generated during CI before formatting, analysis, tests and the
Android APK build. SQLite operation does not require either Local or Cloud to be
reachable.
